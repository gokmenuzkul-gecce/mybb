<?php
/**
 * ACP: RSS news approval queue.
 *
 * Queued headlines are listed here and nothing is published until an admin
 * approves it. Approval is a link that opens a confirmation form; the POST
 * that follows does the insert, so a stray click can't create a topic.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('RSS Haber Botu', 'index.php?module=rss_news_bot');
$page->add_breadcrumb_item('Onay Kuyruğu', 'index.php?module=rss_news_bot-queue');

$action = $mybb->get_input('action');
$qid = $mybb->get_input('qid', MyBB::INPUT_INT);
$cid = $mybb->get_input('cid', MyBB::INPUT_INT);

$where = '';
$filter_suffix = '';
$filter_cat = null;
if($cid > 0)
{
	$filter_cat = $db->fetch_array($db->simple_select('rss_categories', 'title', "cid='{$cid}'"));
	if(!$filter_cat)
	{
		$cid = 0;
	}
	else
	{
		$where = "cid='{$cid}'";
		$filter_suffix = '&amp;cid='.$cid;
	}
}

/* ------------------------------------------------------------- actions --- */

if($action == 'approve' || $action == 'reject')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$item = $db->fetch_array($db->simple_select('rss_queue', '*', "qid='{$qid}'"));

	if(!$item)
	{
		flash_message('Kuyruk kaydı bulunamadı.', 'error');
		admin_redirect('index.php?module=rss_news_bot-queue'.$filter_suffix);
	}

	if($item['status'] != 'queued')
	{
		flash_message('Bu haber zaten işleme alınmış.', 'error');
		admin_redirect('index.php?module=rss_news_bot-queue'.$filter_suffix);
	}

	if($mybb->request_method != 'post')
	{
		$page->output_header('Haberi Onayla');
		$page->add_breadcrumb_item('Haberi Onayla');
		$form = new Form('index.php?module=rss_news_bot-queue&amp;action='.$action.'&amp;qid='.$qid.$filter_suffix, 'post');

		if($action == 'approve')
		{
			// Forum choices: the feed's own target, the global target, then the
			// rest of the board, so the admin can reroute a mis-categorised
			// headline without leaving this screen.
			$options = array();
			$global_fid = (int)$mybb->settings['rss_bot_forum'];
			$selected = (int)$item['fid_forum'] > 0 ? (int)$item['fid_forum'] : $global_fid;

			$fq = $db->simple_select('forums', 'fid, name, type', "type='f'", array('order_by' => 'name'));
			while($f = $db->fetch_array($fq))
			{
				$options[(int)$f['fid']] = htmlspecialchars_uni($f['name']);
			}

			// The body the admin will edit: whatever was saved before, else the
			// generated quote so the textarea is never unexpectedly empty.
			$existing_body = isset($item['body']) && trim((string)$item['body']) !== ''
				? $item['body']
				: rss_news_bot_build_message($item);

			$form_container = new FormContainer('Haberi Onayla');
			$form_container->output_row('Başlık', 'Gerekirse düzenleyip yayınlayın.', $form->generate_text_box('subject', htmlspecialchars_uni($item['title']), array('style' => 'width:100%')));
			$form_container->output_row('Kaynak', '', htmlspecialchars_uni($item['source']));
			$form_container->output_row('Açılacak forum', 'Haberin hangi foruma açılacağını seçin.', $form->generate_select_box('fid', $options, $selected));
			$form_container->output_row('Konu içeriği', 'Yayınlanmadan önce metni düzenleyebilirsiniz. Kaynak bağlantısı korunur.', $form->generate_text_area('body', $existing_body, array('style' => 'width:100%;height:260px;')));
			$form_container->output_row('Onay', 'Onayladığınızda konu açılır ve haber yayınlanır.', $form->generate_hidden_field('qid', $qid).$form->generate_hidden_field('action', $action));
			$form_container->end();
			$buttons[] = $form->generate_submit_button('Onayla ve Konu Aç');
		}
		else
		{
			$form_container = new FormContainer('Haberi Reddet');
			$form_container->output_row('Başlık', '', htmlspecialchars_uni($item['title']));
			$form_container->output_row('Red', 'Haber yayınlanmaz, kuyrukta reddedildi olarak işaretlenir.', $form->generate_hidden_field('qid', $qid).$form->generate_hidden_field('action', $action));
			$form_container->end();
			$buttons[] = $form->generate_submit_button('Reddet');
		}

		$form->output_submit_wrapper($buttons);
		$form->end();
		$page->output_footer();
		exit;
	}

	if($action == 'approve')
	{
		// The form lets the admin edit the headline, body and target forum; pass
		// those through so what gets published is what was reviewed.
		$override = array(
			'subject' => $mybb->get_input('subject'),
			'body' => $mybb->get_input('body'),
			'fid' => $mybb->get_input('fid', MyBB::INPUT_INT),
		);
		list($ok, $message) = rss_news_bot_approve($qid, (int)$mybb->user['uid'], $override);
		flash_message($message, $ok ? 'success' : 'error');
	}
	else
	{
		list($ok, $message) = rss_news_bot_reject($qid, (int)$mybb->user['uid']);
		flash_message($message, 'success');
	}

	admin_redirect('index.php?module=rss_news_bot-queue'.$filter_suffix);
}

if($action == 'fetch')
{
	verify_post_check($mybb->get_input('my_post_key'));

	// The queue is fed by categories now; fetching a plain feed list here would
	// bypass them and post straight to the global forum.
	$report = rss_news_bot_fetch_categories(true, $cid);

	$message = 'Kategoriler: '.(int)$report['categories'].', yeni haber: '.(int)$report['new'];
	if((int)$report['published'])
	{
		$message .= ', otomatik yayınlanan: '.(int)$report['published'];
	}
	$message .= '.';
	if(!empty($report['errors']))
	{
		$message .= ' Hatalar: '.implode(' | ', array_map('htmlspecialchars_uni', $report['errors']));
	}
	flash_message($message, $report['errors'] ? 'error' : 'success');

	admin_redirect('index.php?module=rss_news_bot-queue'.$filter_suffix);
}

if($action == 'purge')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$days = (int)$mybb->get_input('days', MyBB::INPUT_INT);
	if($days < 1)
	{
		$days = 30;
	}

	$cutoff = TIME_NOW - ($days * 86400);
	$purge_where = "status='queued' AND dateline < '{$cutoff}'".($where !== '' ? " AND {$where}" : '');
	$removed = (int)$db->fetch_field($db->simple_select('rss_queue', 'COUNT(*) AS c', $purge_where), 'c');
	$db->delete_query('rss_queue', $purge_where);

	flash_message($removed.' adet eski onay kaydı silindi ('.$days.' günden eski, yalnızca bekleyenler).', 'success');
	admin_redirect('index.php?module=rss_news_bot-queue'.$filter_suffix);
}

/* ---------------------------------------------------------------- list --- */

$page->output_header('RSS Onay Kuyruğu'.($filter_cat ? ' - '.htmlspecialchars_uni($filter_cat['title']) : ''));

if($filter_cat)
{
	flash_message('Yalnızca <strong>'.htmlspecialchars_uni($filter_cat['title']).'</strong> kategorisi gösteriliyor. <a href="index.php?module=rss_news_bot-queue">Tüm kuyruğu göster</a>', 'success');
}

$form = new Form('index.php?module=rss_news_bot-queue&amp;action=fetch'.$filter_suffix, 'post');
$buttons = array($form->generate_submit_button($filter_cat ? 'Bu Kategoriyi Şimdi Çek' : 'Tüm Kategorileri Şimdi Çek'));
$form->output_submit_wrapper($buttons);
$form->end();

// A long-lived queue accumulates stale headlines (some feeds carry years-old
// items). Sweeping them out keeps the pending list meaningful without touching
// anything an admin already approved.
$purge_form = new Form('index.php?module=rss_news_bot-queue&amp;action=purge'.$filter_suffix, 'post');
$purge_container = new FormContainer('Eski Onay Kuyruğunu Temizle');
$purge_container->output_row('Kaç günden eski', 'Yalnızca hâlâ bekleyen (onaylanmamış/reddedilmemiş) kayıtlar silinir. Yayınlanmış konular etkilenmez.', $purge_form->generate_numeric_field('days', 30, array('id' => 'rss_purge_days', 'min' => 1, 'style' => 'width: 80px;')));
$purge_container->end();
$purge_buttons = array($purge_form->generate_submit_button('Eski Kayıtları Sil'));
$purge_form->output_submit_wrapper($purge_buttons);
$purge_form->end();

$pending_where = "status='queued'".($where !== '' ? " AND {$where}" : '');
$pending = $db->fetch_field($db->simple_select('rss_queue', 'COUNT(*) AS c', $pending_where), 'c');
if($pending)
{
	flash_message($pending.' haber onay bekliyor.', 'success');
}

$table = new Table;
$table->construct_header('Başlık');
$table->construct_header('Kaynak', array('width' => '16%'));
$table->construct_header('Tarih', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '11%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '16%'));

$status_labels = array(
	'queued' => array('Onay Bekliyor', '#f59e0b'),
	'approved' => array('Yayınlandı', '#22c55e'),
	'rejected' => array('Reddedildi', '#ef4444'),
);

$query = $db->simple_select('rss_queue', '*', $where, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 200));

$count = 0;
while($item = $db->fetch_array($query))
{
	$count++;
	$row_qid = (int)$item['qid'];
	$label = isset($status_labels[$item['status']]) ? $status_labels[$item['status']] : array($item['status'], '#64748b');

	$title = '<a href="'.htmlspecialchars_uni($item['link']).'" target="_blank" rel="noopener">'.htmlspecialchars_uni($item['title']).'</a>';
	if($item['summary'])
	{
		$title .= '<br /><small>'.htmlspecialchars_uni($item['summary']).'</small>';
	}

	$table->construct_cell($title);
	$table->construct_cell(htmlspecialchars_uni($item['source']));
	$table->construct_cell(my_date('relative', (int)$item['dateline']), array('class' => 'align_center'));
	$table->construct_cell('<span style="color:'.$label[1].';font-weight:600;">'.htmlspecialchars_uni($label[0]).'</span>', array('class' => 'align_center'));

	if($item['status'] == 'queued')
	{
		$key = $mybb->post_code;
		$controls = '<a href="index.php?module=rss_news_bot-queue&amp;action=approve&amp;qid='.$row_qid.$filter_suffix.'&amp;my_post_key='.$key.'">Onayla</a>';
		$controls .= ' &middot; <a href="index.php?module=rss_news_bot-queue&amp;action=reject&amp;qid='.$row_qid.$filter_suffix.'&amp;my_post_key='.$key.'">Reddet</a>';
		$table->construct_cell($controls, array('class' => 'align_center'));
	}
	elseif($item['status'] == 'approved' && $item['tid'])
	{
		$table->construct_cell('<a href="../showthread.php?tid='.(int)$item['tid'].'">Konuya git</a>', array('class' => 'align_center'));
	}
	else
	{
		$table->construct_cell('-', array('class' => 'align_center'));
	}

	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Kuyrukta haber yok. Ayarlardan botu açıp beslemeleri çekebilirsiniz.', array('colspan' => 5, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('Onay Kuyruğu');
$page->output_footer();