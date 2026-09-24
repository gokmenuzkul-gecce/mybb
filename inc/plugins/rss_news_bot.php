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
		'author' => 'Gecce',
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

	return $db->table_exists('rss_categories');
}

function rss_news_bot_uninstall()
{
	global $db;

	$db->drop_table('rss_queue');
	$db->drop_table('rss_categories');
	// A board that ran the older feed-based release still has this table.
	$db->drop_table('rss_feeds');

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
		if(!$db->field_exists('cid', 'rss_queue'))
		{
			// Which category fetched this item. 0 means a plain feed row, i.e.
			// the behaviour before categories existed.
			$db->add_column('rss_queue', 'cid', 'INT NOT NULL DEFAULT 0');
		}
	}

	if($db->table_exists('rss_categories') && !$db->field_exists('auto_post', 'rss_categories'))
	{
		$db->add_column('rss_categories', 'auto_post', 'INT NOT NULL DEFAULT 0');
	}

	// The per-feed cap and interval were replaced by each category's own daily
	// count, so a board upgrading no longer needs these two settings.
	$db->delete_query('settings', "name IN ('rss_bot_max_items','rss_bot_interval')");
}

function rss_news_bot_create_tables()
{
	global $db;

	$mysql = $db->type == 'mysql';
	$pk = $mysql ? "INT(10) NOT NULL AUTO_INCREMENT" : "INTEGER PRIMARY KEY AUTOINCREMENT";
	$int = $mysql ? "INT(10)" : "INTEGER";
	$tail = $mysql ? " ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci" : "";

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
			cid {$int} NOT NULL DEFAULT 0,
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

	if(!$db->table_exists('rss_categories'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."rss_categories (
			cid {$pk},
			title VARCHAR(150) NOT NULL,
			slug VARCHAR(60) NOT NULL,
			fid_forum {$int} NOT NULL DEFAULT 0,
			is_vip {$int} NOT NULL DEFAULT 0,
			query VARCHAR(255) NOT NULL DEFAULT '',
			sources TEXT NOT NULL,
			per_run {$int} NOT NULL DEFAULT 7,
			auto_post {$int} NOT NULL DEFAULT 0,
			active {$int} NOT NULL DEFAULT 1,
			disporder {$int} NOT NULL DEFAULT 0,
			last_fetch {$int} NOT NULL DEFAULT 0,
			last_error VARCHAR(255) NOT NULL DEFAULT ''
		){$tail};");

		$index = $mysql
			? "CREATE UNIQUE INDEX ".TABLE_PREFIX."rss_categories_slug ON ".TABLE_PREFIX."rss_categories (slug(60))"
			: "CREATE UNIQUE INDEX ".TABLE_PREFIX."rss_categories_slug ON ".TABLE_PREFIX."rss_categories (slug)";
		$db->write_query($index.";");
	}

	rss_news_bot_seed_categories();
}

/* ------------------------------------------------------------ categories --- */

/**
 * The category list the board asked for, each wired to the forum that already
 * holds it. `query` drives a Google News search feed; `sources` holds publisher
 * feeds that carry a usable excerpt for that topic, one URL per line.
 *
 * is_vip marks the categories lined up with a locked VIP forum. A fetch for one
 * of these still lands in the approval queue, and the membership gate stays on
 * the forum itself, so a mis-set flag here can never leak a paid topic.
 */
function rss_news_bot_category_seed()
{
        return array(
                array('Instagram Ticaret Pazarı', 'instagram-ticaret', 29, 0, 'instagram hesap satışı takipçi', ''),
                array('TikTok & YouTube Dünyası', 'tiktok-youtube', 30, 0, 'tiktok youtube para kazanma', ''),
                array('SMM Panel Dünyası', 'smm-panel', 31, 0, 'sosyal medya paneli takipçi', ''),
                array('Hesap ve Hizmet Alım Satımı', 'hesap-hizmet', 22, 0, 'sosyal medya hesap satışı', ''),
                array('Kripto ve Nakit Takas', 'kripto-nakit-takas', 24, 0, 'kripto nakit takas USDT', ''),
                array('Erken Aşama AirDrop Rehberleri', 'vip-airdrop-rehberleri', 18, 1, 'airdrop rehberi erken aşama kripto', ''),
                array('VIP Balina Sinyalleri ve Spot Sepetleri', 'vip-balina-sinyalleri', 19, 1, 'bitcoin balina hareketleri analiz', ''),
                array('Otomasyon ve Çoklu Hesap Scriptleri', 'vip-otomasyon-script', 20, 1, 'otomasyon scripti çoklu hesap bot', ''),
                array('Yapay Zeka ve Prompt Pazarı', 'yapay-zeka-prompt', 33, 0, 'yapay zeka prompt aracı', ''),
                array('Ortak Hesap (Premium) Alışverişi', 'ortak-hesap-premium', 34, 0, 'premium hesap paylaşımı abonelik', ''),
                array('Dijital Lisans Pazarı', 'dijital-lisans', 35, 0, 'dijital lisans yazılım anahtar', ''),
                array('Sıcak Fırsatlar & İndirim Kuponları', 'sicak-firsatlar', 39, 0, 'indirim kuponu kampanya fırsat', ''),
                array('Amazon & Trendyol Dropshipping', 'amazon-trendyol', 40, 0, 'e-ticaret dropshipping', ''),
                array('Yazılım ve Tema Pazarı', 'yazilim-tema', 23, 0, 'wordpress tema', ''),
                array('Ücretsiz AirDrop & Testnet Fırsatları', 'ucretsiz-airdrop-testnet', 6, 0, 'ücretsiz airdrop testnet görev', ''),
                array('Güncel Kripto Haberleri', 'guncel-kripto-haberleri', 10, 0, 'kripto para haberleri', "https://coin-turk.com/feed\nhttps://tr.investing.com/rss/news_301.rss\nhttps://cointelegraph.com/rss\nhttps://cryptoslate.com/feed/\nhttps://decrypt.co/feed"),
                array('Kripto Para Analiz & Sinyal', 'kripto-analiz-sinyal', 36, 0, 'kripto analiz sinyal', 'https://cryptopotato.com/feed/'),
                array('İnternetten Para Kazanma Yolları', 'internetten-para-kazanma', 37, 0, 'internetten para kazanma yolları', ''),
                array('Telegram Botları ve Mining', 'telegram-bot-mining', 7, 0, 'telegram bot mining kripto', ''),
                array('Testnet ve Erken Erişim Projeleri', 'testnet-erken-erisim', 8, 0, 'testnet kripto', ''),
                array('Altcoin ve Proje İncelemeleri', 'altcoin-proje-inceleme', 11, 0, 'altcoin proje incelemesi', 'https://bitcoinist.com/feed/'),
                array('Bitcoin ve Piyasa Analizi', 'bitcoin-piyasa-analizi', 14, 0, 'bitcoin piyasa analizi', 'https://coin-turk.com/feed'),
                array('Al-Sat Stratejileri ve İndikatörler', 'al-sat-stratejileri', 15, 0, 'kripto al sat stratejisi indikatör', ''),
                array('Kripto Trading Botları', 'kripto-trading-bot', 16, 0, 'kripto trading botu', ''),
                array('NFT ve Metaverse Dünyası', 'nft-metaverse', 12, 0, 'nft metaverse haberleri', ''),
        );
}

/**
 * Insert the categories once. Re-running install must not duplicate them, and
 * the slug index is the backstop if two admins ever hit install together.
 */
function rss_news_bot_seed_categories()
{
        global $db;

        $existing = $db->fetch_field($db->simple_select('rss_categories', 'COUNT(*) AS c'), 'c');
        if($existing)
        {
                return;
        }

        foreach(rss_news_bot_category_seed() as $i => $c)
        {
                $db->insert_query('rss_categories', array(
                        'title' => $db->escape_string($c[0]),
                        'slug' => $db->escape_string($c[1]),
                        'fid_forum' => (int)$c[2],
                        'is_vip' => (int)$c[3],
                        'query' => $db->escape_string($c[4]),
                        'sources' => $db->escape_string($c[5]),
                        'per_run' => 7,
                        'auto_post' => 0,
                        'active' => 1,
                        'disporder' => $i + 1,
                        'last_fetch' => 0,
                        'last_error' => '',
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
 * Build the Google News search feed for a category. Using the query endpoint
 * keeps all 25 categories on one provider, so a single outage or markup change
 * is the worst case instead of 25 separate ones.
 */
function rss_news_bot_category_feed_url($cat)
{
        $q = trim((string)$cat['query']);
        if($q === '')
        {
                // Fall back to the title so a category with no query still resolves.
                $q = $cat['title'];
        }

        return 'https://news.google.com/rss/search?q='.rawurlencode($q).'&hl=tr&gl=TR&ceid=TR:tr';
}

/**
 * Google News titles read "Headline - Publisher" and carry the publisher again
 * in a grey font tag inside the description. Prefer the tag, then the title
 * suffix, then the search term.
 */
function rss_news_bot_category_source_name($description, $title, $fallback)
{
        if(preg_match('~<font color="#6f6f6f">(.*?)</font>~is', (string)$description, $m))
        {
                $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                if($name !== '')
                {
                        return $name;
                }
        }

        if(strpos((string)$title, ' - ') !== false)
        {
                $parts = explode(' - ', $title);
                $name = trim(array_pop($parts));
                if($name !== '' && mb_strlen($name) < 60)
                {
                        return $name;
                }
        }

        return $fallback;
}

/**
 * Strip the " - Publisher" suffix Google News appends and clamp the result to
 * what a thread subject accepts. The publisher is already stored separately as
 * the source, so keeping it in the title only makes subjects too long.
 */
function rss_news_bot_category_title($title, $source)
{
        $title = trim((string)$title);
        $source = trim((string)$source);

        if($source !== '' && mb_substr($title, -mb_strlen($source)) === $source)
        {
                $title = rtrim(mb_substr($title, 0, mb_strlen($title) - mb_strlen($source)), " -\xE2\x80\x93");
        }
        elseif(strpos($title, ' - ') !== false)
        {
                $parts = explode(' - ', $title);
                array_pop($parts);
                $title = trim(implode(' - ', $parts));
        }

        if($title === '')
        {
                $title = trim((string)$source);
        }

        // 85 is the board's hard subject limit; leave the ellipsis room.
        if(my_strlen($title) > 80)
        {
                $title = my_substr($title, 0, 80).'...';
        }

        return $title;
}

/**
 * Fetch one category and queue its headlines for the category's forum.
 *
 * The daily cap is enforced per category, so a category that is fetched twice
 * in a day does not flood the board: the second run stops at what is left of
 * $per_run. Admins bypass the once-a-day guard from the ACP ($force).
 */
function rss_news_bot_fetch_category($cat, $force = false)
{
        global $db, $mybb;

        $cid = (int)$cat['cid'];
        $result = array('cid' => $cid, 'new' => 0, 'published' => 0, 'error' => '');

        require_once MYBB_ROOT.'inc/class_feedparser.php';

        $added_total = 0;
        $urls = array();

        // Publisher feeds go first: they carry the real excerpt or full text,
        // which the Google News aggregator does not. Google News fills whatever
        // room is left in $per_run, so a topic still updates on a quiet day.
        foreach(preg_split('~\r\n|\r|\n~', (string)$cat['sources']) as $extra)
        {
                $extra = trim($extra);
                if($extra !== '')
                {
                        $urls[] = $extra;
                }
        }

        $urls[] = rss_news_bot_category_feed_url($cat);

        $per_run = (int)$cat['per_run'];
        if($per_run < 1)
        {
                $per_run = 7;
        }

        $fid = (int)$cat['fid_forum'];
        $errors = array();

        foreach($urls as $url)
        {
                if($added_total >= $per_run)
                {
                        break;
                }

                $parser = new FeedParser();

                // FeedParser fatals on a well-formed but empty feed (Google News
                // returns one when a query has no hits), so a category with no
                // results must not take the whole scheduled run down with it.
                try
                {
                        $parsed = $parser->parse_feed($url);
                }
                catch(Throwable $e)
                {
                        $errors[] = 'parse_failed';
                        continue;
                }

                if(!$parsed)
                {
                        $errors[] = $parser->error;
                        continue;
                }

                foreach($parser->items as $item)
                {
                        if($added_total >= $per_run)
                        {
                                break;
                        }

                        $title = isset($item['title']) ? trim($item['title']) : '';
                        $link = isset($item['link']) ? trim($item['link']) : '';
                        if($title === '' || $link === '')
                        {
                                continue;
                        }

                        $guid = isset($item['guid']) && trim($item['guid']) !== '' ? trim($item['guid']) : $link;

                        $dupe = $db->simple_select('rss_queue', 'qid', "guid='".$db->escape_string($guid)."'");
                        if($db->num_rows($dupe))
                        {
                                continue;
                        }

                        $description = isset($item['description']) ? $item['description'] : '';
                        // content:encoded holds the full article when the publisher
                        // provides it; FeedParser leaves it empty otherwise.
                        $content = isset($item['content']) ? trim((string)$item['content']) : '';

                        $source = rss_news_bot_category_source_name($description, $title, $cat['title']);
                        $raw_title = $title;
                        $title = rss_news_bot_category_title($title, $source);

                        // Same echo test as the body: a summary that only repeats the
                        // headline is noise, so leave it empty and the topic shows the
                        // source line alone.
                        $summary_text = rss_news_bot_clean_summary($description, 300);
                        if(mb_strlen($summary_text) <= mb_strlen($raw_title) + mb_strlen($source) + 10)
                        {
                                $summary_text = '';
                        }

                        if($content !== '')
                        {
                                $body = rss_news_bot_clean_summary($content, 20000);
                        }
                        else
                        {
                                // Google News only repeats the headline in its description, so
                                // an excerpt there carries no information. Queue the body empty
                                // and the topic is a clean link plus the source line.
                                $body = rss_news_bot_clean_summary($description, 600);
                                // An echo of the headline (plus the publisher) adds nothing:
                                // drop it so the topic body is just the source link.
                                $echo_len = mb_strlen($raw_title) + mb_strlen($source) + 10;
                                if(mb_strlen($body) <= $echo_len)
                                {
                                        $body = '';
                                }
                        }

                        $qid = $db->insert_query('rss_queue', array(
                                'feed_id' => 0,
                                'cid' => $cid,
                                'source' => $db->escape_string($source),
                                'title' => $db->escape_string($title),
                                'link' => $db->escape_string($link),
                                'guid' => $db->escape_string($guid),
                                'summary' => $db->escape_string(rss_news_bot_clean_summary($summary_text, 300)),
                                'body' => $db->escape_string($body),
                                'fid_forum' => $fid,
                                'dateline' => isset($item['date_timestamp']) && (int)$item['date_timestamp'] ? (int)$item['date_timestamp'] : TIME_NOW,
                                'status' => 'queued',
                        ));

                        // A category set to auto_post publishes immediately, through the same
                        // approval path the ACP uses, so validation and the source link are
                        // identical whether a human or the schedule triggered it.
                        if((int)$cat['auto_post'] == 1)
                        {
                                $bot_uid = (int)$mybb->settings['rss_bot_user'];
                                list($ok, $approve_message) = rss_news_bot_approve($qid, $bot_uid);
                                if($ok)
                                {
                                        $result['published']++;
                                }
                                else
                                {
                                        $result['error'] = trim($result['error'].' '.$approve_message);
                                }
                        }

                        $added_total++;
                        $result['new']++;
                }
        }

        if($errors)
        {
                $result['error'] = trim($result['error'].' '.implode(' | ', array_unique($errors)));
        }

        return $result;
}

/**
 * Run every active category, or just one when $cid is given.
 *
 * $force skips the once-a-day guard (used by the ACP manual fetch). The daily
 * schedule calls this unforced, so each category is pulled at most once a day.
 */
function rss_news_bot_fetch_categories($force = false, $cid = 0)
{
        global $db, $mybb;

        $report = array('categories' => 0, 'new' => 0, 'published' => 0, 'errors' => array());

        if(!$force && $mybb->settings['rss_bot_enabled'] != 1)
        {
                return $report;
        }

        $where = 'active=1';
        if($cid > 0)
        {
                $where .= ' AND cid='.(int)$cid;
        }

        $query = $db->simple_select('rss_categories', '*', $where, array('order_by' => 'disporder', 'order_dir' => 'ASC'));

        while($cat = $db->fetch_array($query))
        {
                // 72000s = 20h, so a schedule running slightly early or late still
                // counts as once a day rather than skipping or doubling a day.
                if(!$force && (int)$cat['last_fetch'] > TIME_NOW - 72000)
                {
                        continue;
                }

                $report['categories']++;

                $res = rss_news_bot_fetch_category($cat, $force);
                $report['new'] += $res['new'];
                $report['published'] += $res['published'];

                $db->update_query('rss_categories', array(
                        'last_fetch' => TIME_NOW,
                        'last_error' => $db->escape_string((string)$res['error']),
                ), 'cid='.(int)$cat['cid']);

                if($res['error'] !== '')
                {
                        $report['errors'][] = $cat['title'].': '.$res['error'];
                }
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
	if($limit < 100 || $limit > 20000)
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
		$messages = array();
		foreach((array)$posthandler->get_errors() as $code => $detail)
		{
			$messages[] = is_array($detail) && isset($detail['error_code']) ? $detail['error_code'].' ('.$detail['data'].')' : (string)$code;
		}
		return array(false, 'Konu doğrulanamadı: '.implode(', ', $messages));
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
	$body_text = isset($item['body']) ? trim((string)$item['body']) : '';
	if($body_text === '')
	{
		$body_text = isset($item['summary']) ? trim((string)$item['summary']) : '';
	}

	$body = '';
	if($body_text !== '')
	{
		// The queued text can be an HTML fragment; keep the topic body plain.
		$body_text = trim(preg_replace('~\s+~u', ' ', strip_tags($body_text)));
		$body_text = htmlspecialchars_uni($body_text);
		$body .= "[quote]{$body_text}[/quote]\n\n";
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