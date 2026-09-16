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

/* ------------------------------------------------------------- actions --- */

if($action == 'approve' || $action == 'reject')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$item = $db->fetch_array($db->simple_select('rss_queue', '*', "qid='{$qid}'"));

	if(!$item)
	{
		flash_message('Kuyruk kaydı bulunamadı.', 'error');
		admin_redirect('index.php?module=rss_news_bot-queue');
	}

	if($item['status'] != 'queued')
	{
		flash_message('Bu haber zaten işleme alınmış.', 'error');
		admin_redirect('index.php?module=rss_news_bot-queue');
	}

	if($mybb->request_method != 'post')
	{
		$page->output_header('Haberi Onayla');
		$page->add_breadcrumb_item('Haberi Onayla');
		$form = new Form('index.php?module=rss_news_bot-queue&amp;action='.$action.'&amp;qid='.$qid, 'post');

		if($action == 'approve')
		{
			$forum = $db->fetch_array($db->simple_select('forums', 'name', "fid='".(int)$mybb->settings['rss_bot_forum']."'"));
			$target = $forum ? $forum['name'] : '(geçersiz forum ayarı)';

			$form_container = new FormContainer('Haberi Onayla');
			$form_container->output_row('Başlık', '', htmlspecialchars_uni($item['title']));
			$form_container->output_row('Kaynak', '', htmlspecialchars_uni($item['source']));
			$form_container->output_row('Açılacak forum', 'Konu bu foruma taşınacak konu olarak eklenecek.', htmlspecialchars_uni($target));
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
		list($ok, $message) = rss_news_bot_approve($qid, (int)$mybb->user['uid']);
		flash_message($message, $ok ? 'success' : 'error');
	}
	else
	{
		list($ok, $message) = rss_news_bot_reject($qid, (int)$mybb->user['uid']);
		flash_message($message, 'success');
	}

	admin_redirect('index.php?module=rss_news_bot-queue');
}

if($action == 'fetch')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$report = rss_news_bot_fetch_all(true);

	$message = 'Beslemeler: '.(int)$report['feeds'].', yeni haber: '.(int)$report['new'].'.';
	if(!empty($report['errors']))
	{
		$message .= ' Hatalar: '.implode(' | ', array_map('htmlspecialchars_uni', $report['errors']));
	}
	flash_message($message, $report['errors'] ? 'error' : 'success');

	admin_redirect('index.php?module=rss_news_bot-queue');
}

/* ---------------------------------------------------------------- list --- */

$page->output_header('RSS Onay Kuyruğu');

$form = new Form('index.php?module=rss_news_bot-queue&amp;action=fetch', 'post');
$buttons = array($form->generate_submit_button('Beslemeleri Şimdi Çek'));
$form->output_submit_wrapper($buttons);
$form->end();

$pending = $db->fetch_field($db->simple_select('rss_queue', 'COUNT(*) AS c', "status='queued'"), 'c');
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

$query = $db->simple_select('rss_queue', '*', '', array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 200));

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
		$controls = '<a href="index.php?module=rss_news_bot-queue&amp;action=approve&amp;qid='.$row_qid.'&amp;my_post_key='.$key.'">Onayla</a>';
		$controls .= ' &middot; <a href="index.php?module=rss_news_bot-queue&amp;action=reject&amp;qid='.$row_qid.'&amp;my_post_key='.$key.'">Reddet</a>';
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