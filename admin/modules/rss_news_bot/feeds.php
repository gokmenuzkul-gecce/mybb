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
		'fid_forum' => $mybb->get_input('fid_forum', MyBB::INPUT_INT),
	));

	flash_message('Besleme eklendi.', 'success');
	admin_redirect('index.php?module=rss_news_bot-feeds');
}

// Bulk add: one "Başlık | adres" per line. Setting up a dozen sources one form
// at a time is the main reason the list ends up half-populated.
if($action == 'bulk' && $mybb->request_method == 'post')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$lines = preg_split('/\r\n|\r|\n/', (string)$mybb->get_input('bulk'));
	$added = 0;
	$skipped = 0;

	foreach($lines as $line)
	{
		$line = trim($line);
		if($line === '')
		{
			continue;
		}

		$parts = array_map('trim', explode('|', $line, 2));
		$url = $parts[0];
		$title = isset($parts[1]) ? $parts[1] : '';

		if(!preg_match('#^https?://#i', $url))
		{
			$skipped++;
			continue;
		}

		if($title === '')
		{
			// Fall back to the host so the row is still identifiable.
			$host = parse_url($url, PHP_URL_HOST);
			$title = $host ? $host : $url;
		}

		$dupe = $db->simple_select('rss_feeds', 'fid', "url='".$db->escape_string($url)."'");
		if($db->num_rows($dupe))
		{
			$skipped++;
			continue;
		}

		$db->insert_query('rss_feeds', array(
			'title' => $db->escape_string($title),
			'url' => $db->escape_string($url),
			'active' => 1,
			'dateline' => TIME_NOW,
			'fid_forum' => $mybb->get_input('fid_forum', MyBB::INPUT_INT),
		));
		$added++;
	}

	flash_message($added.' besleme eklendi.'.($skipped ? ' '.$skipped.' satır atlandı (geçersiz veya zaten kayıtlı).' : ''), 'success');
	admin_redirect('index.php?module=rss_news_bot-feeds');
}

if($action == 'edit' && $mybb->request_method == 'post')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$feed = $db->fetch_array($db->simple_select('rss_feeds', '*', "fid='{$fid}'"));
	if(!$feed)
	{
		flash_message('Besleme bulunamadı.', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds');
	}

	$title = trim($mybb->get_input('title'));
	$url = trim($mybb->get_input('url'));

	if($title === '' || !preg_match('#^https?://#i', $url))
	{
		flash_message('Başlık zorunlu, adres http:// veya https:// ile başlamalıdır.', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds&amp;action=edit&amp;fid='.$fid);
	}

	$len = $mybb->get_input('summary_length', MyBB::INPUT_INT);
	if($len != 0 && ($len < 100 || $len > 5000))
	{
		flash_message('Özet uzunluğu 100 ile 5000 arasında olmalıdır (0 = varsayılan).', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds&amp;action=edit&amp;fid='.$fid);
	}

	$db->update_query('rss_feeds', array(
		'title' => $db->escape_string($title),
		'url' => $db->escape_string($url),
		'fid_forum' => $mybb->get_input('fid_forum', MyBB::INPUT_INT),
		'summary_length' => $len,
	), "fid='{$fid}'");

	flash_message('Besleme güncellendi.', 'success');
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

// Forum list is used by all three forms below.
$forum_options = array(0 => 'Varsayılan (genel ayar)');
$fq = $db->simple_select('forums', 'fid, name, type', "type='f'", array('order_by' => 'name'));
while($f = $db->fetch_array($fq))
{
	$forum_options[(int)$f['fid']] = htmlspecialchars_uni($f['name']);
}

if($action == 'edit')
{
	$feed = $db->fetch_array($db->simple_select('rss_feeds', '*', "fid='{$fid}'"));

	if(!$feed)
	{
		flash_message('Besleme bulunamadı.', 'error');
		admin_redirect('index.php?module=rss_news_bot-feeds');
	}

	$form = new Form('index.php?module=rss_news_bot-feeds&amp;action=edit&amp;fid='.$fid, 'post');
	$form_container = new FormContainer('Beslemeyi Düzenle');
	$form_container->output_row('Başlık', 'Kaynak adı; haber konularında kaynak olarak görünür.', $form->generate_text_box('title', htmlspecialchars_uni($feed['title']), array('id' => 'title')));
	$form_container->output_row('Besleme adresi', 'RSS veya Atom adresi.', $form->generate_text_box('url', htmlspecialchars_uni($feed['url']), array('id' => 'url')));
	$form_container->output_row('Hedef forum', 'Bu beslemeden gelen haberlerin açılacağı forum.', $form->generate_select_box('fid_forum', $forum_options, (int)$feed['fid_forum']));
	$form_container->output_row('Özet uzunluğu', 'Haber metninden alınacak karakter sayısı. 0 = varsayılan (900).', $form->generate_text_box('summary_length', (int)$feed['summary_length'], array('id' => 'summary_length', 'style' => 'width:110px')));
	$form_container->end();
	$buttons = array($form->generate_submit_button('Kaydet'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<p><a href="index.php?module=rss_news_bot-feeds">&larr; Besleme listesine dön</a></p>';

	$page->output_footer();
	exit;
}

$form = new Form('index.php?module=rss_news_bot-feeds&amp;action=add', 'post');
$form_container = new FormContainer('Yeni Besleme Ekle');
$form_container->output_row('Başlık', 'Kaynak adı; haber konularında kaynak olarak görünür.', $form->generate_text_box('title', '', array('id' => 'title')));
$form_container->output_row('Besleme adresi', 'RSS veya Atom adresi.', $form->generate_text_box('url', '', array('id' => 'url')));
$form_container->output_row('Hedef forum', 'Boş bırakılırsa genel ayardaki forum kullanılır.', $form->generate_select_box('fid_forum', $forum_options, 0));
$form_container->end();
$buttons = array($form->generate_submit_button('Besleme Ekle'));
$form->output_submit_wrapper($buttons);
$form->end();

$form = new Form('index.php?module=rss_news_bot-feeds&amp;action=bulk', 'post');
$form_container = new FormContainer('Toplu Besleme Ekle');
$form_container->output_row(
	'Beslemeler',
	'Her satıra bir besleme. Biçim: <code>adres | Başlık</code>. Başlık yazılmazsa site adresi kullanılır.',
	$form->generate_text_area('bulk', '', array('style' => 'width:100%;height:150px;'))
);
$form_container->output_row('Hedef forum', 'Bu toplu eklemedeki tüm beslemeler için geçerli olur.', $form->generate_select_box('fid_forum', $forum_options, 0));
$form_container->end();
$buttons = array($form->generate_submit_button('Toplu Ekle'));
$form->output_submit_wrapper($buttons);
$form->end();

$table = new Table;
$table->construct_header('Başlık');
$table->construct_header('Adres');
$table->construct_header('Hedef forum', array('class' => 'align_center', 'width' => '15%'));
$table->construct_header('Son çekim', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '9%'));
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

	$feed_fid = (int)$feed['fid_forum'];
	$forum_name = $feed_fid > 0 && isset($forum_options[$feed_fid])
		? $forum_options[$feed_fid]
		: '<em>varsayılan</em>';
	$table->construct_cell($forum_name, array('class' => 'align_center'));

	$table->construct_cell($feed['last_fetch'] ? my_date('relative', (int)$feed['last_fetch']) : '<em>hiç</em>', array('class' => 'align_center'));
	$table->construct_cell($feed['active'] ? '<span style="color:#22c55e;font-weight:600;">Aktif</span>' : '<span style="color:#64748b;font-weight:600;">Kapalı</span>', array('class' => 'align_center'));

	$controls = '<a href="index.php?module=rss_news_bot-feeds&amp;action=edit&amp;fid='.$row_fid.'">Düzenle</a>';
	$controls .= ' &middot; <a href="index.php?module=rss_news_bot-feeds&amp;action=toggle&amp;fid='.$row_fid.'&amp;my_post_key='.$key.'">'.($feed['active'] ? 'Kapat' : 'Aç').'</a>';
	$controls .= ' &middot; <a href="index.php?module=rss_news_bot-feeds&amp;action=delete&amp;fid='.$row_fid.'&amp;my_post_key='.$key.'">Sil</a>';
	$table->construct_cell($controls, array('class' => 'align_center'));

	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Kayıtlı besleme yok.', array('colspan' => 6, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('Beslemeler');
$page->output_footer();