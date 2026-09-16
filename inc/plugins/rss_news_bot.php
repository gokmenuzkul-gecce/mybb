<?php
/**
 * RSS news bot with a manual approval queue.
 *
 * Fetches configured Turkish crypto news feeds on a schedule, turns each new
 * item into a *queued* entry, and stops there. Nothing reaches the board until
 * an admin approves it in the ACP, which is the whole point: the feeds are
 * third-party content and an automated poster that publishes unreviewed
 * headlines is a spam and liability problem.
 */

if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('admin_load', 'rss_news_bot_admin_load');

function rss_news_bot_info()
{
	return array(
		'name' => 'RSS Haber Botu',
		'description' => 'Kripto haber sitelerinin RSS beslemelerini çeker, onay kuyruğuna ekler. Konular yalnızca yönetici onayından sonra açılır.',
		'website' => '',
		'author' => 'Crypton Web3',
		'authorsite' => '',
		'version' => '1.0',
		'codename' => 'rss_news_bot',
	);
}

/* ------------------------------------------------------------- install --- */

function rss_news_bot_install()
{
	global $db;

	rss_news_bot_create_tables();
	rss_news_bot_create_settings();
	rss_news_bot_create_task();

	rebuild_settings();
}

function rss_news_bot_is_installed()
{
	global $db;

	return $db->table_exists('rss_feeds');
}

function rss_news_bot_uninstall()
{
	global $db;

	$db->drop_table('rss_feeds');
	$db->drop_table('rss_queue');

	$db->delete_query('settings', "name IN ('rss_bot_enabled','rss_bot_forum','rss_bot_user','rss_bot_max_items','rss_bot_interval')");
	$db->delete_query('settinggroups', "name='rss_news_bot'");
	$db->delete_query('tasks', "file='rss_news_bot'");

	rebuild_settings();
}

/* -------------------------------------------------------------- schema --- */

/**
 * Adds columns introduced after the first release to boards that already have
 * the tables. CREATE TABLE only runs on a fresh install, so without this an
 * existing queue would never see the per-feed forum target or the edited body.
 */
function rss_news_bot_upgrade_tables()
{
	global $db;

	if($db->table_exists('rss_feeds'))
	{
		if(!$db->field_exists('fid_forum', 'rss_feeds'))
		{
			// 0 means "use the global target forum", so existing feeds keep
			// posting where they always did.
			$db->add_column('rss_feeds', 'fid_forum', 'INT NOT NULL DEFAULT 0');
		}
		if(!$db->field_exists('summary_length', 'rss_feeds'))
		{
			$db->add_column('rss_feeds', 'summary_length', 'INT NOT NULL DEFAULT 0');
		}
	}

	if($db->table_exists('rss_queue'))
	{
		if(!$db->field_exists('body', 'rss_queue'))
		{
			// An admin-edited body. Empty means "build it from the summary at
			// approval time", which is what the bot did before.
			$db->add_column('rss_queue', 'body', 'TEXT');
		}
		if(!$db->field_exists('fid_forum', 'rss_queue'))
		{
			$db->add_column('rss_queue', 'fid_forum', 'INT NOT NULL DEFAULT 0');
		}
	}
}

function rss_news_bot_create_tables()
{
	global $db;

	$mysql = $db->type == 'mysql';
	$pk = $mysql ? "INT(10) NOT NULL AUTO_INCREMENT" : "INTEGER PRIMARY KEY AUTOINCREMENT";
	$int = $mysql ? "INT(10)" : "INTEGER";
	$tail = $mysql ? " ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci" : "";

	if(!$db->table_exists('rss_feeds'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."rss_feeds (
			fid {$pk},
			title VARCHAR(150) NOT NULL,
			url VARCHAR(255) NOT NULL,
			active {$int} NOT NULL DEFAULT 1,
			dateline {$int} NOT NULL DEFAULT 0,
			last_fetch {$int} NOT NULL DEFAULT 0,
			last_error VARCHAR(255) NOT NULL DEFAULT '',
			fid_forum {$int} NOT NULL DEFAULT 0,
			summary_length {$int} NOT NULL DEFAULT 0
		){$tail};");
	}

	rss_news_bot_upgrade_tables();

	if(!$db->table_exists('rss_queue'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."rss_queue (
			qid {$pk},
			feed_id {$int} NOT NULL DEFAULT 0,
			source VARCHAR(150) NOT NULL,
			title VARCHAR(255) NOT NULL,
			link VARCHAR(255) NOT NULL,
			guid VARCHAR(255) NOT NULL,
			summary TEXT NOT NULL,
			body TEXT,
			fid_forum {$int} NOT NULL DEFAULT 0,
			dateline {$int} NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			tid {$int} NOT NULL DEFAULT 0,
			handled_by {$int} NOT NULL DEFAULT 0,
			handled_at {$int} NOT NULL DEFAULT 0
		){$tail};");

		// One queue row per feed item, so a re-run of the fetch never duplicates.
		// MySQL needs a prefix length on a TEXT/VARCHAR index; SQLite rejects it.
		$index = $mysql
			? "CREATE UNIQUE INDEX ".TABLE_PREFIX."rss_queue_guid ON ".TABLE_PREFIX."rss_queue (guid(191))"
			: "CREATE UNIQUE INDEX ".TABLE_PREFIX."rss_queue_guid ON ".TABLE_PREFIX."rss_queue (guid)";
		$db->write_query($index.";");
	}

	rss_news_bot_seed_feeds();
}

/**
 * Seed the Turkish-language feeds the board was asked to use.
 *
 * Only the feed list is prefilled. Nothing is fetched or posted at install
 * time, so installing the plugin has no side effects on the board.
 */
function rss_news_bot_seed_feeds()
{
	global $db;

	$existing = $db->fetch_field($db->simple_select('rss_feeds', 'COUNT(*) AS c'), 'c');
	if($existing)
	{
		return;
	}

	$feeds = array(
		array('Coin-Turk', 'https://coin-turk.com/feed'),
		array('Investing.com Türkçe Kripto', 'https://tr.investing.com/rss/news_301.rss'),
		array('Cointelegraph', 'https://cointelegraph.com/rss'),
		array('CryptoSlate', 'https://cryptoslate.com/feed/'),
		array('Decrypt', 'https://decrypt.co/feed'),
	);

	foreach($feeds as $f)
	{
		$db->insert_query('rss_feeds', array(
			'title' => $db->escape_string($f[0]),
			'url' => $db->escape_string($f[1]),
			'active' => 1,
			'dateline' => TIME_NOW,
		));
	}
}

/* ------------------------------------------------------------ settings --- */

function rss_news_bot_create_settings()
{
	global $db;

	$group = $db->simple_select('settinggroups', 'gid', "name='rss_news_bot'");
	$gid = $db->fetch_field($group, 'gid');

	if(!$gid)
	{
		$gid = $db->insert_query('settinggroups', array(
			'name' => 'rss_news_bot',
			'title' => 'RSS Haber Botu',
			'description' => 'RSS beslemelerinden haber çekme ve onay kuyruğu ayarları.',
			'disporder' => 70,
			'isdefault' => 0,
		));
	}

	$settings = array(
		array('rss_bot_enabled', '0', 'select', 'Bot aktif mi?', 'Kapalıysa görev hiçbir şey çekmez.', array(0 => 'Hayır', 1 => 'Evet')),
		array('rss_bot_forum', '10', 'text', 'Hedef forum ID', 'Onaylanan haberlerin açılacağı forumun ID değeri.', ''),
		array('rss_bot_user', '1', 'text', 'Paylaşan kullanıcı ID', 'Konuların hangi hesap adına açılacağı.', ''),
		array('rss_bot_max_items', '5', 'text', 'Çalıştırma başına en fazla haber', 'Tek görev çalışmasında kuyruğa eklenecek üst sınır.', ''),
		array('rss_bot_interval', '3600', 'text', 'Beslemeleri çekme aralığı (saniye)', 'Bu süre dolmadan aynı besleme tekrar çekilmez.', ''),
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
			'title' => $db->escape_string($s[3]),
			'description' => $db->escape_string($s[4]),
			'optionscode' => $db->escape_string(rss_news_bot_optionscode($s[2], $s[5])),
			'value' => $db->escape_string($s[1]),
			'disporder' => $i + 1,
			'gid' => (int)$gid,
			'isdefault' => 0,
		));
	}
}

function rss_news_bot_optionscode($type, $options)
{
	if($type != 'select')
	{
		return 'text';
	}

	$out = "select\n";
	foreach($options as $value => $label)
	{
		$out .= $value.'='.$label."\n";
	}

	return trim($out);
}

/* ---------------------------------------------------------------- task --- */

function rss_news_bot_create_task()
{
	global $db;

	$exists = $db->simple_select('tasks', 'tid', "file='rss_news_bot'");
	if($db->num_rows($exists))
	{
		return;
	}

	$db->insert_query('tasks', array(
		'title' => $db->escape_string('RSS Haber Botu Besleme Çekimi'),
		'description' => $db->escape_string('Aktif RSS beslemelerini çeker ve yeni haberleri onay kuyruğuna ekler.'),
		'file' => 'rss_news_bot',
		'minute' => '0',
		'hour' => '*',
		'day' => '*',
		'weekday' => '*',
		'month' => '*',
		'enabled' => 1,
		'logging' => 1,
	));
}

/* ------------------------------------------------------------ fetching --- */

/**
 * Pull every active feed and queue anything not seen before.
 *
 * Returns a small report so the task log and the ACP both have something
 * useful to show.
 */
function rss_news_bot_fetch_all($force = false)
{
	global $db, $mybb;

	$report = array('feeds' => 0, 'new' => 0, 'errors' => array());

	if($mybb->settings['rss_bot_enabled'] != 1 && !$force)
	{
		return $report;
	}

	require_once MYBB_ROOT.'inc/class_feedparser.php';

	$max = (int)$mybb->settings['rss_bot_max_items'];
	if($max < 1)
	{
		$max = 5;
	}

	$interval = (int)$mybb->settings['rss_bot_interval'];
	if($interval < 60)
	{
		$interval = 3600;
	}

	$query = $db->simple_select('rss_feeds', '*', 'active=1', array('order_by' => 'fid'));
	while($feed = $db->fetch_array($query))
	{
		if(!$force && (int)$feed['last_fetch'] > TIME_NOW - $interval)
		{
			continue;
		}

		$report['feeds']++;

		$parser = new FeedParser();
		$ok = $parser->parse_feed($feed['url']);

		if(!$ok)
		{
			$report['errors'][] = $feed['title'].': '.$parser->error;
			$db->update_query('rss_feeds', array(
				'last_fetch' => TIME_NOW,
				'last_error' => $db->escape_string((string)$parser->error),
			), 'fid='.(int)$feed['fid']);
			continue;
		}

		$added = 0;
		foreach($parser->items as $item)
		{
			if($added >= $max)
			{
				break;
			}

			$title = isset($item['title']) ? trim($item['title']) : '';
			$link = isset($item['link']) ? trim($item['link']) : '';
			$guid = isset($item['guid']) ? trim($item['guid']) : '';

			if($title === '' || $link === '')
			{
				continue;
			}

			// The parser derives a guid when the feed omits one, but never
			// trust it blindly: a duplicate guid would silently drop the item
			// from the queue entirely.
			if($guid === '')
			{
				$guid = $link;
			}

			$dupe = $db->simple_select('rss_queue', 'qid', "guid='".$db->escape_string($guid)."'");
			if($db->num_rows($dupe))
			{
				continue;
			}

			$description = isset($item['description']) ? $item['description'] : '';

			// A per-feed forum target lets the admin route categories to the
			// board sections they belong in; 0 means "use the global setting".
			$feed_forum = isset($feed['fid_forum']) ? (int)$feed['fid_forum'] : 0;
			$feed_len = isset($feed['summary_length']) ? (int)$feed['summary_length'] : 0;

			$db->insert_query('rss_queue', array(
				'feed_id' => (int)$feed['fid'],
				'source' => $db->escape_string($feed['title']),
				'title' => $db->escape_string($title),
				'link' => $db->escape_string($link),
				'guid' => $db->escape_string($guid),
				'summary' => $db->escape_string(rss_news_bot_clean_summary($description, $feed_len)),
				'fid_forum' => $feed_forum,
				'dateline' => (int)$item['date_timestamp'] ? (int)$item['date_timestamp'] : TIME_NOW,
				'status' => 'queued',
			));

			$added++;
			$report['new']++;
		}

		$db->update_query('rss_feeds', array('last_fetch' => TIME_NOW, 'last_error' => ''), 'fid='.(int)$feed['fid']);
	}

	return $report;
}

/**
 * Feed descriptions are HTML fragments with tracking pixels and relative URLs.
 * Keep a plain-text excerpt; the topic itself links to the source.
 *
 * $limit is per feed. The default is high enough that a topic reads like a real
 * post rather than a truncated teaser, but the full article is still never
 * copied — that is the publisher's content, not ours.
 */
function rss_news_bot_clean_summary($html, $limit = 0)
{
	$limit = (int)$limit;
	if($limit < 100 || $limit > 5000)
	{
		$limit = 900;
	}

	// strip_tags drops the tags but leaves the text between <script>/<style>,
	// which would surface as gibberish in the excerpt. Remove those blocks first.
	$html = (string)$html;
	$html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);

	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = preg_replace('/\s+/u', ' ', $text);
	$text = trim($text);

	if(function_exists('my_strlen') && my_strlen($text) > $limit)
	{
		$text = my_substr($text, 0, $limit).'...';
	}
	elseif(strlen($text) > $limit)
	{
		$text = substr($text, 0, $limit).'...';
	}

	return $text;
}

/* ------------------------------------------------------------- approve --- */

/**
 * Turn a queued item into a real thread.
 *
 * $override lets the ACP pass an edited body and a different target forum. The
 * body is what the admin reviewed on screen, so editing it here is the last
 * chance to fix a machine-translated headline before it goes public.
 */
function rss_news_bot_approve($qid, $admin_uid, $override = array())
{
	global $db, $mybb;

	$qid = (int)$qid;
	$item = $db->fetch_array($db->simple_select('rss_queue', '*', "qid='{$qid}'"));

	if(!$item)
	{
		return array(false, 'Kuyruk kaydı bulunamadı.');
	}

	if($item['status'] != 'queued')
	{
		return array(false, 'Bu haber zaten işleme alınmış.');
	}

	// Forum resolution order: what the admin picked for this post, then the
	// feed's own target, then the global setting.
	$fid = 0;
	if(isset($override['fid']) && (int)$override['fid'] > 0)
	{
		$fid = (int)$override['fid'];
	}
	elseif((int)$item['fid_forum'] > 0)
	{
		$fid = (int)$item['fid_forum'];
	}
	else
	{
		$fid = (int)$mybb->settings['rss_bot_forum'];
	}

	$forum = $db->fetch_array($db->simple_select('forums', 'fid, name', "fid='{$fid}'"));
	if(!$forum)
	{
		return array(false, 'Hedef forum bulunamadı. Ayarlardan geçerli bir forum ID girin.');
	}

	$uid = (int)$mybb->settings['rss_bot_user'];
	$user = $db->fetch_array($db->simple_select('users', 'uid, username', "uid='{$uid}'"));
	if(!$user)
	{
		return array(false, 'Paylaşan kullanıcı bulunamadı. Ayarlardan geçerli bir kullanıcı ID girin.');
	}

	$subject = $item['title'];
	if(isset($override['subject']) && trim($override['subject']) !== '')
	{
		$subject = trim($override['subject']);
	}

	$body_override = isset($override['body']) ? trim($override['body']) : '';
	if($body_override !== '')
	{
		$message = $body_override;
		// The source link is the one thing that must survive editing, so append
		// it when the admin's text dropped it.
		if(strpos($message, $item['link']) === false)
		{
			$message .= "\n\n[kaynak]".$item['link']."[/kaynak]";
		}
	}
	else
	{
		$message = rss_news_bot_build_message($item);
	}

	require_once MYBB_ROOT.'inc/datahandlers/post.php';
	$posthandler = new PostDataHandler('insert');
	$posthandler->action = 'thread';

	$new_thread = array(
		'fid' => $fid,
		'subject' => $subject,
		'prefix' => 0,
		'icon' => 0,
		'uid' => $uid,
		'username' => $user['username'],
		'message' => $message,
		'ipaddress' => '',
		'posthash' => '',
		'savedraft' => 0,
	);

	$posthandler->set_data($new_thread);

	if(!$posthandler->validate_thread())
	{
		return array(false, 'Konu doğrulanamadı: '.implode(' ', (array)$posthandler->get_errors()));
	}

	// insert_thread() returns array('pid','tid','visible'), not a bare id.
	$result = $posthandler->insert_thread();
	$tid = is_array($result) ? (int)$result['tid'] : (int)$result;

	$db->update_query('rss_queue', array(
		'status' => 'approved',
		'tid' => $tid,
		'body' => $db->escape_string($message),
		'fid_forum' => $fid,
		'handled_by' => (int)$admin_uid,
		'handled_at' => TIME_NOW,
	), "qid='{$qid}'");

	return array(true, 'Konu açıldı (tid '.$tid.').');
}

function rss_news_bot_build_message($item)
{
	$source = htmlspecialchars_uni($item['source']);
	$link = htmlspecialchars_uni($item['link']);
	$link = str_replace(array('"', '<', '>'), '', $link);
	$summary = htmlspecialchars_uni($item['summary']);

	$body = '';
	if($summary !== '')
	{
		$body .= "[quote]{$summary}[/quote]\n\n";
	}

	$body .= "Haberin tamamı ve doğrulaması için kaynak: [url={$link}]{$source}[/url]\n\n";
	$body .= "[i]Bu konu RSS beslemesinden alınmış ve yönetici onayıyla yayınlanmıştır.[/i]";

	return $body;
}

function rss_news_bot_reject($qid, $admin_uid)
{
	global $db;

	$qid = (int)$qid;

	$db->update_query('rss_queue', array(
		'status' => 'rejected',
		'handled_by' => (int)$admin_uid,
		'handled_at' => TIME_NOW,
	), "qid='{$qid}' AND status='queued'");

	return array(true, 'Haber reddedildi.');
}

/* ------------------------------------------------------------ ACP gate --- */

function rss_news_bot_admin_load()
{
	global $mybb, $db, $page, $lang, $plugins;

	if($mybb->get_input('module') != 'rss_news_bot')
	{
		return;
	}

	if(!rss_news_bot_is_installed())
	{
		flash_message('RSS Haber Botu eklentisi kurulu değil.', 'error');
		admin_redirect('index.php?module=config-plugins');
	}
}