<?php
/**
 * ACP: live market ticker status and manual refresh.
 *
 * Prices are normally refreshed by the scheduled task and opportunistically on
 * the board index, so this screen exists to answer two questions an admin
 * actually has: "is the strip working?" and "can I force it now?".
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Canlı Borsa Tablosu', 'index.php?module=market_ticker');
$page->add_breadcrumb_item('Fiyat Şeridi', 'index.php?module=market_ticker-ticker');

$action = $mybb->get_input('action');

if($action == 'refresh')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$snapshot = market_ticker_refresh(true);

	if(is_array($snapshot) && !empty($snapshot['rows']))
	{
		flash_message('Fiyatlar yenilendi.'.(is_array($snapshot) ? ' '.count($snapshot['rows']).' coin güncellendi.' : ''), 'success');
	}
	else
	{
		flash_message('Fiyatlar yenilenemedi. CoinGecko yanıt vermedi veya kayıtlı coin ayarı geçersiz. Önceki kayıt korundu.', 'error');
	}

	admin_redirect('index.php?module=market_ticker-ticker');
}

$page->output_header('Canlı Borsa Tablosu');

$snapshot = $cache->read('market_ticker');
$has_snapshot = is_array($snapshot) && !empty($snapshot['rows']);

/* -------------------------------------------------------------- status --- */

$refresh_minutes = (int)$mybb->settings['market_ticker_refresh'];
if($refresh_minutes < 1)
{
	$refresh_minutes = 5;
}

$task = $db->fetch_array($db->simple_select('tasks', '*', "file='market_ticker'"));

$status = array(
	'Şerit durumu' => ($mybb->settings['market_ticker_on'] == 1)
		? '<span style="color:#22c55e;font-weight:600;">Açık</span>'
		: '<span style="color:#64748b;font-weight:600;">Kapalı</span>',
	'Para birimi' => htmlspecialchars_uni(strtoupper(market_ticker_currency())),
	'Kayıtlı coin sayısı' => $has_snapshot ? count($snapshot['rows']) : 0,
	'Son güncelleme' => $has_snapshot ? my_date('relative', (int)$snapshot['dateline']) : '<em>hiç</em>',
	'Yenileme aralığı' => $refresh_minutes.' dakika',
	'Zamanlanmış görev' => $task
		? (($task['enabled'] == 1) ? '<span style="color:#22c55e;font-weight:600;">Etkin</span>' : '<span style="color:#f59e0b;font-weight:600;">Kapalı</span>')
		: '<span style="color:#ef4444;font-weight:600;">Yok</span>',
);

$table = new Table;
$table->construct_header('Gösterge', array('width' => '60%'));
$table->construct_header('Değer', array('class' => 'align_center'));
foreach($status as $label => $value)
{
	$table->construct_cell($label);
	$table->construct_cell($value, array('class' => 'align_center'));
	$table->construct_row();
}
$table->output('Şerit Durumu');

/* --------------------------------------------------------------- prices --- */

if($has_snapshot)
{
	$currency = $snapshot['currency'];

	$table = new Table;
	$table->construct_header('Coin');
	$table->construct_header('Fiyat', array('class' => 'align_right'));
	$table->construct_header('24 saat', array('class' => 'align_right'));

	foreach($snapshot['rows'] as $row)
	{
		$change = $row['change'];
		if($change === null)
		{
			$change_html = '<span style="color:#64748b;">—</span>';
		}
		else
		{
			$color = ($change > 0.005) ? '#22c55e' : (($change < -0.005) ? '#ef4444' : '#64748b');
			$sign = ($change > 0) ? '+' : '';
			$change_html = '<span style="color:'.$color.';font-weight:600;">'.$sign.number_format($change, 2, '.', '').'%</span>';
		}

		$table->construct_cell(
			'<strong>'.htmlspecialchars_uni($row['symbol']).'</strong>'
			.'<br /><small>'.htmlspecialchars_uni($row['id']).'</small>'
		);
		$table->construct_cell(htmlspecialchars_uni(market_ticker_format_price($row['price'], $currency)), array('class' => 'align_right'));
		$table->construct_cell($change_html, array('class' => 'align_right'));
		$table->construct_row();
	}

	$table->output('Kayıtlı Fiyatlar');
}
else
{
	$table = new Table;
	$table->construct_cell('Henüz kayıtlı fiyat yok. "Fiyatları Yenile" düğmesini kullanın veya zamanlanmış görevin çalışmasını bekleyin.', array('colspan' => 1));
	$table->construct_row();
	$table->output('Kayıtlı Fiyatlar');
}

/* -------------------------------------------------------------- actions --- */

$form = new Form('index.php?module=market_ticker-ticker&amp;action=refresh', 'post');
$form_container = new FormContainer('Fiyatları Yenile');
$form_container->output_row(
	'CoinGecko isteği',
	'Fiyatlar normalde '.$refresh_minutes.' dakikada bir ve ana sayfa ziyaretlerinde otomatik yenilenir. Bu düğme beklemeden hemen çeker. İstek başarısız olursa son iyi kayıt korunur, şerit boşalmaz.',
	'<span class="smalltext">Para birimi ve coin listesi <a href="index.php?module=config-settings&amp;action=change&amp;gid='.market_ticker_gid().'">ayarlardan</a> değiştirilir.</span>'
);
$form_container->end();
$buttons = array($form->generate_submit_button('Fiyatları Yenile'));
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();