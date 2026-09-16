<?php
/**
 * ACP: simulated community dashboard.
 *
 * Five separate actions, each one deliberate:
 *   1. hesaplar  - create the demo accounts
 *   2. konular   - seed the discussion threads
 *   3. yanitlar  - append replies to existing seeded threads
 *   4. aktivite  - give threads a plausible view count and recent activity
 *   5. temizle   - remove everything this plugin created
 *
 * Nothing here should be reachable by a half-configured board, so each action
 * re-checks its own preconditions instead of trusting the UI to have hidden
 * the button.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Simüle Topluluk', 'index.php?module=simulated_community');
$page->add_breadcrumb_item('Panel', 'index.php?module=simulated_community-dashboard');

$action = $mybb->get_input('action');

/* ------------------------------------------------------------- actions --- */

if($action)
{
	verify_post_check($mybb->get_input('my_post_key'));

	if(!simulated_community_is_installed())
	{
		flash_message('Eklenti kurulu değil.', 'error');
		admin_redirect('index.php?module=simulated_community-dashboard');
	}

	if($mybb->settings['sim_community_enabled'] != 1)
	{
		flash_message('Simüle topluluk kapalı. Önce ayarlardan açın.', 'error');
		admin_redirect('index.php?module=simulated_community-dashboard');
	}

	switch($action)
	{
		case 'hesaplar':
			$accounts = simulated_community_create_accounts();
			flash_message(count($accounts).' demo hesap hazır.', 'success');
			break;

		case 'konular':
			$report = simulated_community_seed_threads();
			flash_message('Oluşturulan konu: '.$report['threads'].', mesaj: '.$report['posts'].', atlanan: '.$report['skipped'].'.', 'success');
			break;

		case 'yanitlar':
			$added = simulated_community_append_replies();
			flash_message($added.' yeni yanıt eklendi.', 'success');
			break;

		case 'aktivite':
			$touched = simulated_community_refresh_activity();
			flash_message($touched.' konunun görüntüleme ve son aktivite bilgisi güncellendi.', 'success');
			break;

		case 'temizle':
			$removed = simulated_community_purge();
			flash_message($removed.' demo hesap, konu ve mesaj silindi.', 'success');
			break;
	}

	admin_redirect('index.php?module=simulated_community-dashboard');
}

/* ---------------------------------------------------------------- view --- */

$page->output_header('Simüle Topluluk Paneli');

$accounts = (int)$db->fetch_field($db->simple_select('simulated_profiles', 'COUNT(*) AS c'), 'c');
$seeded_subjects = array();
foreach(simulated_community_threads() as $seed)
{
	$seeded_subjects[] = $db->escape_string($seed['subject']);
}
$subject_list = "'".implode("','", $seeded_subjects)."'";

$seeded_threads = (int)$db->fetch_field($db->simple_select('threads', 'COUNT(*) AS c', "subject IN ({$subject_list})"), 'c');
$seeded_posts = (int)$db->fetch_field($db->simple_select('posts', 'COUNT(*) AS c', "tid IN (SELECT tid FROM ".TABLE_PREFIX."threads WHERE subject IN ({$subject_list}))"), 'c');
$queue_pending = (int)$db->fetch_field($db->simple_select('rss_queue', 'COUNT(*) AS c', "status='queued'"), 'c');

$stats = array(
	'Demo hesap' => $accounts,
	'Tohum konu' => $seeded_threads,
	'Tohum mesaj' => $seeded_posts,
	'Onay bekleyen haber' => $queue_pending,
);

$table = new Table;
$table->construct_header('Gösterge', array('width' => '60%'));
$table->construct_header('Değer', array('class' => 'align_center'));
foreach($stats as $label => $value)
{
	$table->construct_cell($label);
	$table->construct_cell('<strong>'.$value.'</strong>', array('class' => 'align_center'));
	$table->construct_row();
}
$table->output('Durum');

$tasks = array(
	array('hesaplar', '1. Demo Hesapları Oluştur', 'Beş farklı kişilikte, "demo hesap" olarak etiketlenmiş üye oluşturur. Var olanlar tekrar oluşturulmaz.'),
	array('konular', '2. Tohum Konuları Aç', 'Hazır tartışma konularını ve ilk mesajlarını açar. Daha önce açılmış konular atlanır.'),
	array('yanitlar', '3. Konuşma Akışı Ekle', 'Mevcut tohum konularına kişiler arası yanıtlar ekler; konular tek kişinin monoloğu gibi görünmez.'),
	array('aktivite', '4. Aktivite Tazele', 'Konulara gerçekçi görüntüleme sayısı verir ve son mesaj tarihlerini tazeler.'),
	array('temizle', '5. Demo İçeriği Temizle', 'Bu eklentinin oluşturduğu tüm hesapları, konuları ve mesajları siler. Gerçek üyelere dokunmaz.'),
);

foreach($tasks as $task)
{
	list($act, $title, $desc) = $task;

	$form = new Form('index.php?module=simulated_community-dashboard&amp;action='.$act, 'post');
	$form_container = new FormContainer($title);
	$form_container->output_row($title, $desc, $form->generate_submit_button('Çalıştır'));
	$form_container->end();
	$form->end();
}

$page->output_footer();