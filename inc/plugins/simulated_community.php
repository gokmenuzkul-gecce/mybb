<?php
/**
 * Simulated community seeding ("Suni Canlılık").
 *
 * A brand new forum has no discussions, and a forum with no discussions stays
 * empty. This plugin creates a small cast of demo profiles and a handful of
 * threads so the board does not read as abandoned.
 *
 * Every account this creates is labelled in its user title and signature, and
 * every post it writes carries a footer saying so. That is deliberate. The
 * point is to give visitors something to reply to, not to pass off synthetic
 * accounts as real members — a forum that is caught doing the latter loses the
 * trust it was trying to build.
 *
 * Seeding is idempotent: created accounts are recorded in `simulated_profiles`
 * and threads are matched on subject, so running any step twice adds nothing.
 */

if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('admin_load', 'simulated_community_admin_load');

function simulated_community_info()
{
	return array(
		'name' => 'Simüle Topluluk (Suni Canlılık)',
		'description' => 'Forumu boş göstermemek için etiketlenmiş demo hesaplar ve örnek tartışma konuları oluşturur. Tüm hesaplar "demo" olarak işaretlenir.',
		'website' => '',
		'author' => 'Gecce',
		'authorsite' => '',
		'version' => '1.0',
		'codename' => 'simulated_community',
	);
}

function simulated_community_install()
{
	simulated_community_create_settings();
	rebuild_settings();
}

function simulated_community_is_installed()
{
	global $db;

	return $db->table_exists('simulated_profiles');
}

function simulated_community_uninstall()
{
	global $db;

	$db->drop_table('simulated_profiles');
	$db->delete_query('settings', "name IN ('sim_community_enabled','sim_community_label')");
	$db->delete_query('settinggroups', "name='simulated_community'");

	rebuild_settings();
}

function simulated_community_create_settings()
{
	global $db;

	$group = $db->simple_select('settinggroups', 'gid', "name='simulated_community'");
	$gid = $db->fetch_field($group, 'gid');

	if(!$gid)
	{
		$gid = $db->insert_query('settinggroups', array(
			'name' => 'simulated_community',
			'title' => 'Simüle Topluluk',
			'description' => 'Demo hesaplar ve tohum içerik ayarları.',
			'disporder' => 71,
			'isdefault' => 0,
		));
	}

	$settings = array(
		array('sim_community_enabled', '1', 'Tohum içerik oluşturma açık mı?', 'Kapatıldığında yeni demo hesap veya konu oluşturulmaz.'),
		array('sim_community_label', 'Yapay zekâ destekli demo hesap', 'Hesaplarda görünecek etiket', 'Profil başlığı ve imzada görünen ifade.'),
	);

	foreach($settings as $i => $s)
	{
		$exists = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s[0])."'");
		if($db->num_rows($exists))
		{
			continue;
		}

		$db->insert_query('settings', array(
			'name' => $db->escape_string($s[0]),
			'title' => $db->escape_string($s[2]),
			'description' => $db->escape_string($s[3]),
			'optionscode' => 'text',
			'value' => $db->escape_string($s[1]),
			'disporder' => $i + 1,
			'gid' => (int)$gid,
			'isdefault' => 0,
		));
	}
}

/* ------------------------------------------------------------- personas --- */

/**
 * The cast. Each entry is one distinct voice: a hardcore chartist, a student
 * chasing airdrops, a cautious long-term holder, a bot developer and an NFT
 * collector. The variety is what makes the threads read like a conversation
 * rather than one person talking to themselves.
 */
function simulated_community_personas()
{
	return array(
		'kaan_ta' => array(
			'username' => 'TeknikKaan',
			'persona' => 'Teknik analiz uzmanı',
			'signature' => 'Yapay zekâ destekli demo hesap. Yazdıklarım yatırım tavsiyesi değildir.',
		),
		'elif_airdrop' => array(
			'username' => 'AirdropElif',
			'persona' => 'Airdrop kovalayan öğrenci',
			'signature' => 'Yapay zekâ destekli demo hesap. Yazdıklarım yatırım tavsiyesi değildir.',
		),
		'muhittin_uzun' => array(
			'username' => 'UzunVadeciMuhittin',
			'persona' => 'Temkinli uzun vadeli yatırımcı',
			'signature' => 'Yapay zekâ destekli demo hesap. Yazdıklarım yatırım tavsiyesi değildir.',
		),
		'selin_bot' => array(
			'username' => 'BotcuSelin',
			'persona' => 'Bot geliştiren yazılımcı',
			'signature' => 'Yapay zekâ destekli demo hesap. Yazdıklarım yatırım tavsiyesi değildir.',
		),
		'mehmet_nft' => array(
			'username' => 'NFTciMehmet',
			'persona' => 'NFT ve metaverse meraklısı',
			'signature' => 'Yapay zekâ destekli demo hesap. Yazdıklarım yatırım tavsiyesi değildir.',
		),
	);
}

/**
 * Topic seeds: forum, title, and an ordered list of (persona, message).
 *
 * Timestamps are spread over the preceding days so the board looks lived-in
 * rather than everything appearing in the same minute.
 */
function simulated_community_threads()
{
	return array(
		array(
			'fid' => 11,
			'subject' => 'Solana ekosistemi 2026\'da hâlâ alınır mı, yoksa geç mi kaldık?',
			'posts' => array(
				array('kaan_ta', "Haftalık grafikte SOL için 190-200 bandı uzun süredir çalışıyor. Ama bunu tek başına alım sebebi görmüyorum; ekosistemdeki geliştirici aktivitesine bakmak lazım. Son 3 ayda GitHub commit sayısı düşüşte, bunu gözden kaçırmayın.\n\nBenim takip ettiğim seviyeler: 168 desteği kırılırsa 140 bölgesi konuşulur. Yukarıda ise 235 üzeri günlük kapanış trendi çevirir."),
				array('elif_airdrop', "Ben öğrenci olarak biraz daha farklı bakıyorum, SOL ekosistemindeki airdrop'lar bana yazın ciddi getiri sağladı. Phantom'u kullanan herkes Jupiter'in dağıtımını hatırlıyordur.\n\nAma şunu fark ettim, artık herkes aynı taktiği biliyor. Sadece testnet yapıp beklemek yetmiyor, gerçekten ürünü kullanmak lazım. Kaan abi haklı, ben de artık sadece fiyata değil projenin traksiyonuna bakıyorum."),
				array('muhittin_uzun', "Ben bu işlere 2017'den beri bakıyorum ve şunu söyleyeyim: hiçbir zincir kalıcı değil, kalıcı olan altyapıyı kuranlar. Solana'nın kesintileri bir dönem can sıkıcıydı, şimdi daha stabil.\n\nBenim pozisyonum küçük, düzenli alım şeklinde. Ne 200 altında panik satışı yaparım ne 300 olunca koltuğa çıkarım. Gençler acele ediyor, oysa bu iş sabır işi."),
				array('selin_bot', "Konuya teknik taraftan bakayım: ekosistemde RPC maliyetleri ve blok süreleri hâlâ rakiplerine göre iyi. Ben arbitraj botumu Solana üzerinde çalıştırıyorum, işlem maliyeti Ethereum'a göre on kat düşük.\n\nAma şuna dikkat: başarısız transaction oranı yoğun saatlerde artıyor. Bot yazacaksanız priority fee ayarını dinamik yapmazsanız paranız boşa gider. Örnek ayarı isterseniz ayrı başlıkta paylaşabilirim."),
				array('mehmet_nft', "NFT tarafından ekleyeyim, Solana'da mint maliyeti çok düşük olduğu için sanatçılar oraya yöneldi. Ben hâlâ oradaki bazı koleksiyonları takip ediyorum.\n\nYalnız şu uyarıyı yapayım: 'sadece 10 SOL' diye satılan hiçbir koleksiyona körlemesine girmeyin. Sosyal medyada hype yaratıp taban fiyatı çökerten çok proje gördüm."),
			),
		),
		array(
			'fid' => 15,
			'subject' => 'Volatil piyasada grid bot mu, DCA mı? Hangisi gerçekten işe yarıyor',
			'posts' => array(
				array('selin_bot', "İkisini de altı aydır yan yana çalıştırıyorum, gözlemim şu: yatay ve dalgalı piyasada grid bot net kâr ediyor, tek yönlü trendde ise DCA daha iyi.\n\nGrid botun asıl riski 'grid dışı kalma'. Fiyat aralığı dışına çıktığında bot elinde coin veya nakit bırakıyor ve trendi kaçırıyorsunuz. Aralığı ATR'ye göre ayarlamak şart."),
				array('kaan_ta', "Grid botların çoğu backtest'te harika görünüp canlıda can yakıyor çünkü komisyon ve kayma hesaba katılmıyor. Aylık işlem sayınız 400'ü geçiyorsa komisyon getiriyinizi yiyip bitirir.\n\nDCA'nın sevmediğim yanı da şu: herkes 'al ve unut' diyor ama hangi varlıkta DCA yaptığınız önemli. Ölü bir projede DCA yapmak sadece kaybı yavaşlatır."),
				array('muhittin_uzun', "Ben ikisini de karmaşık buluyorum açıkçası. Aylık belirlediğim bir tutarı, üç parçaya bölüp sabit günlerde alıyorum. Ne bot kuruyorum ne gösterge takip ediyorum.\n\nSelin Hanım'ın dediği gibi trend tek yönlüyse bot anlamsız kalıyor. Benim gibi işiyle meşgul biri için basitlik en iyi strateji."),
				array('elif_airdrop', "Ben bu tartışmaya şunu ekleyeyim: küçük bütçeyle uğraşan biri olarak grid botun minimum tutar şartları can sıkıcı. Bir de botun kârı vergilendirme açısından takip etmesi zor, her işlem ayrı kayıt.\n\nAma şunu kabul ediyorum, yatay piyasada elimde tutup beklemekten iyi. Selin abla hangi borsanın botunu kullanıyorsun, komisyon oranı ne?"),
				array('selin_bot', "Elif, komisyon tarafı borsadan borsaya çok değişiyor. Ben API üzerinden çalıştığım için maker/taker oranlarına bakmadan karar vermeyin derim. Spesifik isim vermek istemiyorum ama 'sıfır komisyon' reklamı yapanlar genelde spread'i geniş tutuyor, kâr oradan gidiyor.\n\nBir de şunu ekleyeyim: botu kurduktan sonra bırakmayın, iki haftada bir performansı gözden geçirin. Piyasa rejimi değişince parametreler ölüyor."),
			),
		),
		array(
			'fid' => 11,
			'subject' => 'Yeni bir L2 projesine bakarken hangi metriklere güveniyorsunuz?',
			'posts' => array(
				array('muhittin_uzun', "Herkes TVL diyor ama bence TVL tek başına yanıltıcı. Kendi tokenını stake edip TVL şişiren projeler gördüm, sonra bir gecede yarısı çekildi.\n\nBen şuna bakıyorum: protokolün gelir üretip üretmediği. Ücret almıyorsa, sadece teşvik dağıtıyorsa, o para bittiğinde kullanıcı da biter."),
				array('kaan_ta', "Muhittin Bey'e katılıyorum, ben buna birkaç metrik ekliyorum:\n\n1. Aktif adres sayısı (tekil cüzdan, işlem sayısı değil)\n2. Geliştirici sayısı ve commit sürekliliği\n3. Köprü hacminin gerçek kullanıcıdan mı geliyor, arbitrajcıdan mı\n\nÖzellikle üçüncüsü kritik. Köprüden geçen hacmin %80'i arbitraj botuysa o rakam size hiçbir şey anlatmaz."),
				array('selin_bot', "Metrik okumayı otomatikleştirebilirsiniz, böylece projeyi her gün elle kontrol etmek zorunda kalmazsınız. Ben şu şekilde yapıyorum: günlük olarak benzersiz aktif adres, ortalama işlem ücreti ve yeni cüzdan açılışını çekip grafiğe döküyorum.\n\nDikkat: API'lerin çoğu 'işlem sayısı' verir, 'tekil cüzdan' vermez. Onu ayrı hesaplamanız gerekir, yoksa botların şişirdiği rakama bakıp yanlış karar verirsiniz."),
				array('elif_airdrop', "Gerçek kullanıcı olup olmadığını anlamanın en pratik yolu bence testnet aşamasında Discord'a bakmak. Soru soran insan sayısı, geliştiricilerin cevap hızı, bunlar fiyat grafiğinden çok daha dürüst sinyaller.\n\nBir de şuna dikkat edin: herkesin aynı anda aynı testneti yaptığı projelerden uzak durun, ödül havuzu kalabalığa bölünüyor. Kaan abi geçen hafta bir yerde söylemişti, aynı fikirdeyim."),
				array('mehmet_nft', "Ben biraz farklı bir açı ekleyeyim: L2'lerin NFT altyapısı çoğu zaman sonradan düşünülüyor. Mint maliyeti düşük olsun diye seçiyorsanız, köprü ücretlerini de hesaba katın.\n\nBen bir koleksiyonu L2'de aldım, satmak istediğimde köprü maliyeti kârın yarısını götürdü. Dersimi aldım, artık likidite derinliğine bakmadan almıyorum."),
			),
		),
	);
}

/* ------------------------------------------------------------ accounts --- */

function simulated_community_table_create()
{
	global $db;

	$mysql = $db->type == 'mysql';
	$pk = $mysql ? "INT(10) NOT NULL AUTO_INCREMENT" : "INTEGER PRIMARY KEY AUTOINCREMENT";
	$int = $mysql ? "INT(10)" : "INTEGER";
	$tail = $mysql ? " ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci" : "";

	if(!$db->table_exists('simulated_profiles'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."simulated_profiles (
			spid {$pk},
			key_name VARCHAR(50) NOT NULL,
			uid {$int} NOT NULL DEFAULT 0,
			persona VARCHAR(100) NOT NULL DEFAULT '',
			dateline {$int} NOT NULL DEFAULT 0
		){$tail};");
	}
}

/**
 * Create the demo accounts.
 *
 * Passwords are random and never surfaced: these accounts are not meant to be
 * logged into by anyone.
 */
function simulated_community_create_accounts()
{
	global $db, $mybb;

	simulated_community_table_create();

	require_once MYBB_ROOT.'inc/functions_user.php';

	$label = $mybb->settings['sim_community_label'] ? $mybb->settings['sim_community_label'] : 'Yapay zekâ destekli demo hesap';
	$created = array();
	$now = TIME_NOW;
	$offset = 0;

	foreach(simulated_community_personas() as $key => $p)
	{
		$existing = $db->simple_select('simulated_profiles', 'uid', "key_name='".$db->escape_string($key)."'");
		if($db->num_rows($existing))
		{
			$existing_uid = (int)$db->fetch_field($existing, 'uid');

			// The profile row survives if the account was deleted by hand. Fall
			// through and recreate the account rather than handing back a uid
			// that no longer resolves.
			$still_there = $db->simple_select('users', 'uid', "uid='{$existing_uid}'");
			if($db->num_rows($still_there))
			{
				$created[$key] = $existing_uid;
				continue;
			}

			$db->delete_query('simulated_profiles', "key_name='".$db->escape_string($key)."'");
		}

		$username = $p['username'];

		// A demo account must never collide with a real member's name.
		$taken = $db->simple_select('users', 'uid', "username='".$db->escape_string($username)."'");
		if($db->num_rows($taken))
		{
			$username .= 'Demo';
		}

		// A random, never-surfaced password: these accounts are not meant to be
		// logged into. create_password() returns salt+hash as an array.
		$credentials = create_password(bin2hex(random_bytes(16)));
		$salt = $credentials['salt'];
		$password = $credentials['password'];

		// regdate is staggered backwards so the accounts do not all share a
		// registration timestamp.
		$offset += 86400 * 3;

		$uid = $db->insert_query('users', array(
			'username' => $db->escape_string($username),
			'password' => $db->escape_string($password),
			'salt' => $db->escape_string($salt),
			'loginkey' => $db->escape_string(random_str(50)),
			'email' => $db->escape_string($key.'@demo.invalid'),
			'postnum' => 0,
			'threadnum' => 0,
			'avatar' => '',
			'avatardimensions' => '',
			'avatartype' => '',
			'usergroup' => 2,
			'additionalgroups' => '',
			'displaygroup' => 0,
			'usertitle' => $db->escape_string($label.' · '.$p['persona']),
			'regdate' => $now - $offset,
			'lastactive' => $now - $offset + 3600,
			'lastvisit' => $now - $offset + 3600,
			'lastpost' => 0,
			'signature' => $db->escape_string($p['signature']),
			'allownotices' => 1,
			'hideemail' => 0,
			'showavatars' => 1,
			'showquickreply' => 1,
			'receivepms' => 1,
			'pmnotify' => 1,
			'threadmode' => 'linear',
			'daysprune' => 0,
			'dateformat' => 0,
			'timeformat' => 0,
			'timezone' => 0,
			'style' => 0,
			'language' => '',
			// These six are NOT NULL with no default in the SQLite schema; the
			// MySQL schema supplies defaults, so a MySQL-only insert passes
			// here and then fails outright on SQLite.
			'buddylist' => '',
			'ignorelist' => '',
			'pmfolders' => '',
			'notepad' => '',
			'usernotes' => '',
		));

		$db->insert_query('simulated_profiles', array(
			'key_name' => $db->escape_string($key),
			'uid' => (int)$uid,
			'persona' => $db->escape_string($p['persona']),
			'dateline' => $now,
		));

		$created[$key] = (int)$uid;
	}

	return $created;
}

/* -------------------------------------------------------------- topics --- */

function simulated_community_seed_threads()
{
	global $db, $mybb;

	if($mybb->settings['sim_community_enabled'] != 1)
	{
		return array('threads' => 0, 'posts' => 0, 'skipped' => 0, 'disabled' => true);
	}

	require_once MYBB_ROOT.'inc/datahandlers/post.php';

	$accounts = simulated_community_create_accounts();
	$report = array('threads' => 0, 'posts' => 0, 'skipped' => 0);
	$day = 0;

	foreach(simulated_community_threads() as $seed)
	{
		$exists = $db->simple_select('threads', 'tid', "subject='".$db->escape_string($seed['subject'])."'");
		if($db->num_rows($exists))
		{
			$report['skipped']++;
			continue;
		}

		$forum = $db->fetch_array($db->simple_select('forums', 'fid', "fid='".(int)$seed['fid']."'"));
		if(!$forum)
		{
			$report['skipped']++;
			continue;
		}

		$day++;
		$base = TIME_NOW - (86400 * (6 - $day));
		$tid = 0;
		$step = 0;

		foreach($seed['posts'] as $entry)
		{
			list($key, $message) = $entry;

			if(!isset($accounts[$key]))
			{
				continue;
			}

			$uid = (int)$accounts[$key];
			$user = $db->fetch_array($db->simple_select('users', 'uid, username', "uid='{$uid}'"));
			if(!$user)
			{
				continue;
			}

			// Each reply lands a few hours after the previous one.
			$posttime = $base + ($step * 5400);
			$step++;

			$body = $message."\n\n[hr]\n[color=#94a3b8][size=1]Bu ileti, forumun tohum içerik amacıyla oluşturduğu bir demo hesap tarafından yazılmıştır.[/size][/color]";

			$handler = new PostDataHandler('insert');

			if($tid)
			{
				$handler->action = 'post';
				$data = array(
					'tid' => $tid,
					'fid' => (int)$seed['fid'],
					'subject' => $seed['subject'],
					'uid' => $uid,
					'username' => $user['username'],
					'message' => $body,
					'ipaddress' => '',
					'posthash' => '',
					'savedraft' => 0,
					'dateline' => $posttime,
				);
			}
			else
			{
				$handler->action = 'thread';
				$data = array(
					'fid' => (int)$seed['fid'],
					'subject' => $seed['subject'],
					'prefix' => 0,
					'icon' => 0,
					'uid' => $uid,
					'username' => $user['username'],
					'message' => $body,
					'ipaddress' => '',
					'posthash' => '',
					'savedraft' => 0,
					'dateline' => $posttime,
				);
			}

			$handler->set_data($data);

			// The first post of a thread goes through validate_thread() and
			// insert_thread(); later posts use the *_post() pair. Mixing them
			// up fails validation with an empty error list.
			if($tid)
			{
				$valid = $handler->validate_post();
				if(!$valid)
				{
					continue;
				}
				$result = $handler->insert_post();
			}
			else
			{
				$valid = $handler->validate_thread();
				if(!$valid)
				{
					continue;
				}
				$result = $handler->insert_thread();
			}

			$result = is_array($result) ? $result : array('tid' => $tid, 'pid' => 0);

			if(!$tid && !empty($result['tid']))
			{
				$tid = (int)$result['tid'];
				$report['threads']++;

				// Backdate the thread row itself; the data handler stamps its
				// own dateline and there is no hook to override it.
				$db->update_query('threads', array('dateline' => $posttime), "tid='{$tid}'");
			}

			if(!empty($result['pid']))
			{
				$db->update_query('posts', array('dateline' => $posttime), "pid='".(int)$result['pid']."'");
			}

			$report['posts']++;
		}

		if($tid)
		{
			simulated_community_sync_thread_counters($tid);
		}
	}

	simulated_community_sync_user_counters();

	$GLOBALS['cache']->update_forums();
	$GLOBALS['cache']->update_stats();

	return $report;
}

/**
 * Point each demo account's counters at the posts and threads it actually made.
 *
 * postnum drives the "posts per day" line on the profile; leaving it at zero
 * while the account visibly has posts is an obvious tell.
 */
function simulated_community_sync_user_counters()
{
	global $db;

	$db->write_query("UPDATE ".TABLE_PREFIX."users u SET
		postnum = (SELECT COUNT(*) FROM ".TABLE_PREFIX."posts p WHERE p.uid = u.uid AND p.visible = 1),
		threadnum = (SELECT COUNT(*) FROM ".TABLE_PREFIX."threads t WHERE t.uid = u.uid AND t.visible = 1)
		WHERE u.uid IN (SELECT uid FROM ".TABLE_PREFIX."simulated_profiles)");
}

/**
 * Recompute a seeded thread's reply count, last-post fields and views.
 *
 * The data handler maintains these incrementally, but it stamps "now" as the
 * last-post time, which would put every backdated thread at the top of the
 * index with an identical timestamp.
 */
function simulated_community_sync_thread_counters($tid, $views_override = null)
{
	global $db;

	$tid = (int)$tid;

	$count = (int)$db->fetch_field($db->simple_select('posts', 'COUNT(*) AS c', "tid='{$tid}' AND visible=1"), 'c');
	$replies = $count > 0 ? $count - 1 : 0;

	$last = $db->fetch_array($db->simple_select('posts', 'uid, username, dateline', "tid='{$tid}' AND visible=1", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1)));

	// A plausible view count: seeded threads should not all show zero, which
	// is itself a signal that nobody has read them. An explicit override lets
	// the activity refresher pick its own number.
	$views = $views_override !== null ? (int)$views_override : random_int(40, 320);

	$db->update_query('threads', array(
		'replies' => $replies,
		'views' => $views,
		'lastpost' => $last ? (int)$last['dateline'] : 0,
		'lastposter' => $last ? $db->escape_string($last['username']) : '',
		'lastposteruid' => $last ? (int)$last['uid'] : 0,
	), "tid='{$tid}'");

	$forum = $db->fetch_array($db->simple_select('threads', 'fid', "tid='{$tid}'"));
	if(!$forum)
	{
		return;
	}

	$fid = (int)$forum['fid'];

	$threads = (int)$db->fetch_field($db->simple_select('threads', 'COUNT(*) AS c', "fid='{$fid}' AND visible=1"), 'c');
	$posts = (int)$db->fetch_field($db->simple_select('posts', 'COUNT(*) AS c', "fid='{$fid}' AND visible=1"), 'c');
	$lastf = $db->fetch_array($db->simple_select('threads', 'lastpost, lastposter, lastposteruid, tid, subject', "fid='{$fid}' AND visible=1", array('order_by' => 'lastpost', 'order_dir' => 'DESC', 'limit' => 1)));

	$db->update_query('forums', array(
		'threads' => $threads,
		'posts' => $posts,
		'lastpost' => $lastf ? (int)$lastf['lastpost'] : 0,
		'lastposter' => $lastf ? $db->escape_string($lastf['lastposter']) : '',
		'lastposteruid' => $lastf ? (int)$lastf['lastposteruid'] : 0,
		'lastposttid' => $lastf ? (int)$lastf['tid'] : 0,
		'lastpostsubject' => $lastf ? $db->escape_string($lastf['subject']) : '',
	), "fid='{$fid}'");
}

/* ------------------------------------------------------------- activity --- */

/**
 * Append a few more replies to threads that already exist.
 *
 * Only threads this plugin seeded are touched, matched on subject, so an admin
 * running this on a live board cannot accidentally inject replies into real
 * discussions.
 */
function simulated_community_append_replies()
{
	global $db, $mybb;

	if($mybb->settings['sim_community_enabled'] != 1)
	{
		return 0;
	}

	require_once MYBB_ROOT.'inc/datahandlers/post.php';

	$accounts = simulated_community_create_accounts();
	$added = 0;
	$step = 0;

	foreach(simulated_community_threads() as $seed)
	{
		$thread = $db->fetch_array($db->simple_select('threads', '*', "subject='".$db->escape_string($seed['subject'])."'"));

		if(!$thread)
		{
			continue;
		}

		$tid = (int)$thread['tid'];

		// The last persona in the seed already speaks last; reply as the others
		// so the same name does not appear twice in a row.
		$order = array_keys($seed['posts']);
		foreach($order as $idx)
		{
			$key = $seed['posts'][$idx][0];

			if(!isset($accounts[$key]))
			{
				continue;
			}

			$uid = (int)$accounts[$key];
			$user = $db->fetch_array($db->simple_select('users', 'uid, username', "uid='{$uid}'"));

			if(!$user)
			{
				continue;
			}

			$step++;
			$posttime = TIME_NOW - (86400 * 2) + ($step * 1800);

			$handler = new PostDataHandler('insert');
			$handler->action = 'post';
			$handler->set_data(array(
				'tid' => $tid,
				'fid' => (int)$thread['fid'],
				'subject' => $thread['subject'],
				'uid' => $uid,
				'username' => $user['username'],
				'message' => simulated_community_reply_text($key, $thread['subject'])."\n\n[hr]\n[color=#94a3b8][size=1]Bu ileti, forumun tohum içerik amacıyla oluşturduğu bir demo hesap tarafından yazılmıştır.[/size][/color]",
				'ipaddress' => '',
				'posthash' => '',
				'savedraft' => 0,
				'dateline' => $posttime,
			));

			if(!$handler->validate_post())
			{
				continue;
			}

			$result = $handler->insert_post();

			if(is_array($result) && !empty($result['pid']))
			{
				$db->update_query('posts', array('dateline' => $posttime), "pid='".(int)$result['pid']."'");
				$added++;
			}
		}

		simulated_community_sync_thread_counters($tid);
	}

	simulated_community_sync_user_counters();
	$GLOBALS['cache']->update_forums();
	$GLOBALS['cache']->update_stats();

	return $added;
}

/**
 * Short follow-up lines, keyed by persona.
 *
 * Kept separate from the opening posts: these read as reactions ("katılıyorum",
 * "şu noktayı ekleyeyim") rather than new arguments, which is what a real reply
 * looks like.
 */
function simulated_community_reply_text($key, $subject)
{
	$lines = array(
		'kaan_ta' => "Yukarıdaki yorumlara katılıyorum ama bir noktayı düzeltmek isterim: seviyeler tek başına anlam taşımıyor, hacimle birlikte okunmalı. Hacimsiz kırılımlar çoğu zaman geri dönüyor.",
		'elif_airdrop' => "Ben hâlâ aynı fikirdeyim, küçük bütçeyle en mantıklısı erken aşamada ürünü gerçekten kullanmak. Sadece beklemek artık getiri sağlamıyor, kalabalık çok arttı.",
		'muhittin_uzun' => "Tartışmayı okudum, hepinize teşekkürler. Ben yine de acele etmeyen taraftayım; bu piyasada en pahalı hata sabırsızlık oluyor.",
		'selin_bot' => "Teknik bir ekleme yapayım: bu senaryoyu geriye dönük test ettim, komisyon ve kayma dahil edilince sonuçlar ciddi biçimde değişiyor. Test ederken bunları mutlaka hesaba katın.",
		'mehmet_nft' => "Konuya farklı bir açıdan bakayım: likidite derinliği olmayan hiçbir pazarda pozisyon açmam. Çıkış yapamadıktan sonra kâğıt üstündeki kârın anlamı kalmıyor.",
	);

	return isset($lines[$key]) ? $lines[$key] : 'Konuya katkı sağlayan bir yanıt.';
}

/**
 * Give seeded threads a plausible view count and pull their newest reply
 * forward in time so the board looks recently active.
 *
 * Both fields are derived from the thread's actual posts: the view count is
 * randomised within a modest band, and the last-post time is moved by shifting
 * the newest post's dateline. Setting lastpost without moving a real post would
 * leave the thread pointing at a timestamp no post has, which is visible to
 * anyone sorting by last reply.
 */
function simulated_community_refresh_activity()
{
	global $db;

	$touched = 0;
	$offset = 2700;

	foreach(simulated_community_threads() as $seed)
	{
		$thread = $db->fetch_array($db->simple_select('threads', 'tid', "subject='".$db->escape_string($seed['subject'])."'"));

		if(!$thread)
		{
			continue;
		}

		$tid = (int)$thread['tid'];

		$newest = $db->fetch_array($db->simple_select('posts', 'pid', "tid='{$tid}' AND visible=1", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1)));

		if(!$newest)
		{
			continue;
		}

		$offset += 2700;
		$db->update_query('posts', array('dateline' => TIME_NOW - $offset), "pid='".(int)$newest['pid']."'");

		simulated_community_sync_thread_counters($tid, random_int(90, 450));
		$touched++;
	}

	simulated_community_sync_user_counters();
	$GLOBALS['cache']->update_forums();

	return $touched;
}

/* --------------------------------------------------------------- purge --- */

/**
 * Remove everything this plugin created.
 *
 * Deletion is driven by the uid list recorded at creation time, so a real
 * member's posts are never in scope even if they share a subject line.
 */
function simulated_community_purge()
{
	global $db;

	$uids = array();
	$query = $db->simple_select('simulated_profiles', 'uid');
	while($row = $db->fetch_array($query))
	{
		$uids[] = (int)$row['uid'];
	}

	if(!$uids)
	{
		return 0;
	}

	$list = implode(',', $uids);

	$db->delete_query('posts', "uid IN ({$list})");
	$db->delete_query('threads', "uid IN ({$list})");
	$db->delete_query('users', "uid IN ({$list})");
	$db->delete_query('simulated_profiles');

	// Rebuild forum counters from what is actually left.
	$forums = array();
	$query = $db->simple_select('forums', 'fid', "type='f'");
	while($row = $db->fetch_array($query))
	{
		$forums[] = (int)$row['fid'];
	}

	foreach($forums as $fid)
	{
		$threads = (int)$db->fetch_field($db->simple_select('threads', 'COUNT(*) AS c', "fid='{$fid}' AND visible=1"), 'c');
		$posts = (int)$db->fetch_field($db->simple_select('posts', 'COUNT(*) AS c', "fid='{$fid}' AND visible=1"), 'c');
		$last = $db->fetch_array($db->simple_select('threads', 'lastpost, lastposter, lastposteruid, tid, subject', "fid='{$fid}' AND visible=1", array('order_by' => 'lastpost', 'order_dir' => 'DESC', 'limit' => 1)));

		$db->update_query('forums', array(
			'threads' => $threads,
			'posts' => $posts,
			'lastpost' => $last ? (int)$last['lastpost'] : 0,
			'lastposter' => $last ? $db->escape_string($last['lastposter']) : '',
			'lastposteruid' => $last ? (int)$last['lastposteruid'] : 0,
			'lastposttid' => $last ? (int)$last['tid'] : 0,
			'lastpostsubject' => $last ? $db->escape_string($last['subject']) : '',
		), "fid='{$fid}'");
	}

	$GLOBALS['cache']->update_forums();
	$GLOBALS['cache']->update_stats();

	return count($uids);
}

/* ----------------------------------------------------------------- ACP --- */

function simulated_community_admin_load()
{
	global $mybb;

	if($mybb->get_input('module') != 'simulated_community')
	{
		return;
	}

	if(!simulated_community_is_installed())
	{
		flash_message('Simüle Topluluk eklentisi kurulu değil.', 'error');
		admin_redirect('index.php?module=config-plugins');
	}
}