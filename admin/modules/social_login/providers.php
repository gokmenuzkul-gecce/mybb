<?php
/**
 * ACP: social login provider status.
 *
 * Answers the questions an admin has after pasting credentials in: which
 * providers are switched on, whether both halves of the credential pair are
 * present, and what callback URL to register with the provider.
 *
 * Secret values are never printed. Only whether a value is stored.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Sosyal Giriş', 'index.php?module=social_login');
$page->add_breadcrumb_item('Sağlayıcılar', 'index.php?module=social_login-providers');

$page->output_header('Sosyal Giriş Sağlayıcıları');

$callback = $mybb->settings['bburl'].'/social_login.php?action=callback';
$providers = social_login_providers();

$linked_counts = array();
$q = $db->simple_select('social_identities', 'provider, COUNT(*) AS c', '', array('group_by' => 'provider'));
while($row = $db->fetch_array($q))
{
	$linked_counts[$row['provider']] = (int)$row['c'];
}

$table = new Table;
$table->construct_header('Sağlayıcı');
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Client ID', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Client Secret', array('class' => 'align_center', 'width' => '14%'));
$table->construct_header('Bağlı hesap', array('class' => 'align_center', 'width' => '12%'));

foreach($providers as $key => $p)
{
	$on = ($mybb->settings[$p['on_setting']] == 1);
	$has_id = trim((string)$mybb->settings[$p['id_setting']]) !== '';
	$has_secret = trim((string)$mybb->settings[$p['secret_setting']]) !== '';

	if($on && $has_id && $has_secret)
	{
		$status = '<span style="color:#22c55e;font-weight:600;">Hazır</span>';
	}
	else if($on)
	{
		$status = '<span style="color:#f59e0b;font-weight:600;">Eksik</span>';
	}
	else
	{
		$status = '<span style="color:#64748b;font-weight:600;">Kapalı</span>';
	}

	$yes = '<span style="color:#22c55e;">var</span>';
	$no = '<span style="color:#ef4444;">yok</span>';

	$table->construct_cell('<strong>'.htmlspecialchars_uni($p['title']).'</strong><br /><small>'.$key.'</small>');
	$table->construct_cell($status, array('class' => 'align_center'));
	$table->construct_cell($has_id ? $yes : $no, array('class' => 'align_center'));
	$table->construct_cell($has_secret ? $yes : $no, array('class' => 'align_center'));
	$table->construct_cell('<strong>'.(isset($linked_counts[$key]) ? $linked_counts[$key] : 0).'</strong>', array('class' => 'align_center'));
	$table->construct_row();
}

$table->output('Sağlayıcılar');

$table = new Table;
$table->construct_cell(
	'<p><strong>Yetkilendirme geri dönüş adresi</strong> (sağlayıcı panelinde aynen bu adres tanımlanmalıdır):</p>'
	.'<p><code>'.htmlspecialchars_uni($callback).'</code></p>'
	.'<p class="smalltext">Kimlik ve gizli anahtar değerleri <a href="index.php?module=config-settings&amp;action=change&amp;gid='.social_login_gid().'">ayarlardan</a> girilir. Gizli anahtarlar veritabanında saklanır; veritabanı okuma erişimini anahtar sızıntısı sayın.</p>',
	array('colspan' => 1)
);
$table->construct_row();
$table->output('Geri Dönüş Adresi');

$page->output_footer();