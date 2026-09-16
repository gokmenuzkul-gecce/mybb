<?php
/**
 * ACP: manage RSS feed sources.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('RSS Haber Botu', 'index.php?module=rss_news_bot');
$page->add_breadcrumb_item('Beslemeler', 'index.php?module=rss_news_bot-feeds');

$action = $mybb->get_input('action');
$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

/* ------------------------------------------------------------- actions --- */

if($action == 'add' && $mybb->request_method == 'post')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$title = trim($mybb->get_input('title'));
	$url = trim($mybb->get_input('url'));

	if($title === '' || $url === '')
	{
		flash_message('Başlık ve adres zorunludur.', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds');
	}

	// Only http(s) feeds: the fetcher validates again, but rejecting junk here
	// gives the admin a clear message instead of a silent no-op later.
	if(!preg_match('#^https?://#i', $url))
	{
		flash_message('Adres http:// veya https:// ile başlamalıdır.', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds');
	}

	$db->insert_query('rss_feeds', array(
		'title' => $db->escape_string($title),
		'url' => $db->escape_string($url),
		'active' => 1,
		'dateline' => TIME_NOW,
	));

	flash_message('Besleme eklendi.', 'success');
	admin_redirect('index.php?module=rss_news_bot-feeds');
}

if($action == 'toggle')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$feed = $db->fetch_array($db->simple_select('rss_feeds', '*', "fid='{$fid}'"));

	if($feed)
	{
		$db->update_query('rss_feeds', array('active' => $feed['active'] ? 0 : 1), "fid='{$fid}'");
		flash_message('Besleme durumu güncellendi.', 'success');
	}

	admin_redirect('index.php?module=rss_news_bot-feeds');
}

if($action == 'delete')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$db->delete_query('rss_feeds', "fid='{$fid}'");
	flash_message('Besleme silindi.', 'success');
	admin_redirect('index.php?module=rss_news_bot-feeds');
}

/* ---------------------------------------------------------------- list --- */

$page->output_header('RSS Beslemeleri');

$form = new Form('index.php?module=rss_news_bot-feeds&amp;action=add', 'post');
$form_container = new FormContainer('Yeni Besleme Ekle');
$form_container->output_row('Başlık', 'Kaynak adı; haber konularında kaynak olarak görünür.', $form->generate_text_box('title', '', array('id' => 'title')));
$form_container->output_row('Besleme adresi', 'RSS veya Atom adresi.', $form->generate_text_box('url', '', array('id' => 'url')));
$form_container->end();
$buttons = array($form->generate_submit_button('Besleme Ekle'));
$form->output_submit_wrapper($buttons);
$form->end();

$table = new Table;
$table->construct_header('Başlık');
$table->construct_header('Adres');
$table->construct_header('Son çekim', array('class' => 'align_center', 'width' => '14%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '11%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '16%'));

$query = $db->simple_select('rss_feeds', '*', '', array('order_by' => 'fid'));

$count = 0;
while($feed = $db->fetch_array($query))
{
	$count++;
	$row_fid = (int)$feed['fid'];
	$key = $mybb->post_code;

	$cell = htmlspecialchars_uni($feed['title']);
	if($feed['last_error'])
	{
		$cell .= '<br /><small style="color:#ef4444;">Hata: '.htmlspecialchars_uni($feed['last_error']).'</small>';
	}

	$table->construct_cell($cell);
	$table->construct_cell('<small>'.htmlspecialchars_uni($feed['url']).'</small>');
	$table->construct_cell($feed['last_fetch'] ? my_date('relative', (int)$feed['last_fetch']) : '<em>hiç</em>', array('class' => 'align_center'));
	$table->construct_cell($feed['active'] ? '<span style="color:#22c55e;font-weight:600;">Aktif</span>' : '<span style="color:#64748b;font-weight:600;">Kapalı</span>', array('class' => 'align_center'));

	$controls = '<a href="index.php?module=rss_news_bot-feeds&amp;action=toggle&amp;fid='.$row_fid.'&amp;my_post_key='.$key.'">'.($feed['active'] ? 'Kapat' : 'Aç').'</a>';
	$controls .= ' &middot; <a href="index.php?module=rss_news_bot-feeds&amp;action=delete&amp;fid='.$row_fid.'&amp;my_post_key='.$key.'">Sil</a>';
	$table->construct_cell($controls, array('class' => 'align_center'));

	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Kayıtlı besleme yok.', array('colspan' => 5, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('Beslemeler');
$page->output_footer();