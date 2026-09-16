<?php
/**
 * Forum Cards for the crypto-web3 theme.
 *
 * Enriches each forum row with an icon chosen from the forum's own title and
 * with the last poster's avatar. MyBB's stock forumbit templates have no
 * placeholder for either, and functions_forumlist.php only exposes
 * lastposteruid, so the plugin attaches what the templates need to $forum.
 *
 * The markup lives in the theme's own copies of forumbit_depth1_cat,
 * forumbit_depth2_forum and forumbit_depth2_forum_lastpost. On a board whose
 * templates are still stock this plugin renders the icons and avatars for
 * nobody, so customise those templates before relying on it.
 */

if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

if(defined('THIS_SCRIPT') && THIS_SCRIPT == 'index.php')
{
	global $templatelist;

	if(isset($templatelist) && $templatelist != '')
	{
		$templatelist .= ',';
	}

	$templatelist .= 'forumbit_depth1_cat,forumbit_depth2_forum,forumbit_depth2_forum_lastpost';
}

$plugins->add_hook('build_forumbits_forum', 'crypto_forumcards_enrich');
$plugins->add_hook('pre_output_page', 'crypto_forumcards_slider');

function crypto_forumcards_info()
{
	return array(
		'name'			=> 'Crypto Forum Cards',
		'description'	=> 'Forum satirlarina basliga gore ikon ve son mesaj atanin avatarini ekler.',
		'website'		=> '',
		'author'		=> 'crypto-web3 theme',
		'authorsite'	=> '',
		'version'		=> '1.0',
		'compatibility'	=> '18*',
		'codename'		=> 'crypto_forumcards'
	);
}

/**
 * Pick an icon and accent colour from a forum's name.
 *
 * Rules are checked in order against the lowercased name, so narrower matches
 * are listed before the broad fallbacks at the end.
 */
function crypto_forumcards_lookup($name)
{
	$n = my_strtolower($name);

	$rules = array(
		'airdrop' => array('fa-solid fa-parachute-box', '#22c55e'),
		'testnet' => array('fa-solid fa-flask-vial', '#a855f7'),
		'mining' => array('fa-solid fa-microchip', '#f59e0b'),
		'telegram' => array('fa-brands fa-telegram', '#38bdf8'),
		'otomasyon' => array('fa-solid fa-code', '#818cf8'),
		'script' => array('fa-solid fa-code', '#818cf8'),
		'bot' => array('fa-solid fa-robot', '#818cf8'),
		'nft' => array('fa-solid fa-image', '#ec4899'),
		'metaverse' => array('fa-solid fa-vr-cardboard', '#ec4899'),
		'bitcoin' => array('fa-brands fa-bitcoin', '#f7931a'),
		'indikat' => array('fa-solid fa-wave-square', '#22d3ee'),
		'piyasa' => array('fa-solid fa-chart-line', '#22d3ee'),
		'analiz' => array('fa-solid fa-chart-line', '#22d3ee'),
		'al-sat' => array('fa-solid fa-arrow-right-arrow-left', '#34d399'),
		'strateji' => array('fa-solid fa-chess', '#34d399'),
		'takas' => array('fa-solid fa-arrow-right-arrow-left', '#34d399'),
		'balina' => array('fa-solid fa-whale', '#60a5fa'),
		'sinyal' => array('fa-solid fa-tower-broadcast', '#60a5fa'),
		'pazar' => array('fa-solid fa-store', '#fbbf24'),
		'yazilim' => array('fa-solid fa-laptop-code', '#fbbf24'),
		'grafik' => array('fa-solid fa-palette', '#fbbf24'),
		'hesap' => array('fa-solid fa-user-shield', '#fbbf24'),
		'hizmet' => array('fa-solid fa-handshake', '#fbbf24'),
		'haber' => array('fa-solid fa-newspaper', '#38bdf8'),
		'altcoin' => array('fa-solid fa-coins', '#facc15'),
		'proje' => array('fa-solid fa-cubes', '#a78bfa'),
		'inceleme' => array('fa-solid fa-magnifying-glass-chart', '#a78bfa'),
		'blokzinciri' => array('fa-solid fa-link', '#a78bfa'),
		'web3' => array('fa-solid fa-cube', '#a78bfa'),
		'finans' => array('fa-solid fa-sack-dollar', '#4ade80'),
		'vip' => array('fa-solid fa-crown', '#fbbf24'),
		'club' => array('fa-solid fa-crown', '#fbbf24'),
		'ticaret' => array('fa-solid fa-arrow-right-arrow-left', '#34d399'),
		'duyuru' => array('fa-solid fa-bullhorn', '#f97316'),
		'yenilik' => array('fa-solid fa-bullhorn', '#f97316'),
		'etkinlik' => array('fa-solid fa-trophy', '#facc15'),
		'yarisma' => array('fa-solid fa-trophy', '#facc15'),
		'tanisma' => array('fa-solid fa-hand-sparkles', '#22d3ee'),
		'tanit' => array('fa-solid fa-hand-sparkles', '#22d3ee'),
		'topluluk' => array('fa-solid fa-users', '#60a5fa'),
		'hos geldiniz' => array('fa-solid fa-door-open', '#f97316'),
		'kripto' => array('fa-solid fa-coins', '#facc15'),
	);

	foreach($rules as $needle => $style)
	{
		if(my_strpos($n, $needle) !== false)
		{
			return $style;
		}
	}

	return array('fa-solid fa-comments', '#64748b');
}

/**
 * Effective last poster per category.
 *
 * A category row stores lastpost = 0 and renders a child forum's last post
 * instead, so its own lastposteruid is useless for the avatar. Resolve the
 * newest descendant poster once, in a single query.
 */
function crypto_forumcards_category_posters()
{
	static $map = null;

	if($map !== null)
	{
		return $map;
	}

	global $db;

	$map = array();
	$rows = array();

	$query = $db->simple_select('forums', 'fid, parentlist, type, lastpost, lastposteruid');
	while($row = $db->fetch_array($query))
	{
		$rows[] = $row;
	}

	foreach($rows as $cat)
	{
		if($cat['type'] != 'c')
		{
			continue;
		}

		$best = array('lastpost' => 0, 'lastposteruid' => 0);
		$needle = ',' . $cat['fid'] . ',';

		foreach($rows as $child)
		{
			if($child['type'] == 'c' || $child['lastpost'] <= $best['lastpost'])
			{
				continue;
			}

			if(my_strpos(',' . trim($child['parentlist'], ',') . ',', $needle) !== false)
			{
				$best = $child;
			}
		}

		$map[$cat['fid']] = $best['lastposteruid'];
	}

	return $map;
}

function crypto_forumcards_enrich($forum)
{
	global $db;

	list($icon, $color) = crypto_forumcards_lookup($forum['name']);
	$forum['icon_class'] = $icon;
	$forum['icon_color'] = $color;

	$uid = (int)$forum['lastposteruid'];

	if(!$uid && $forum['type'] == 'c')
	{
		$posters = crypto_forumcards_category_posters();
		$uid = isset($posters[$forum['fid']]) ? (int)$posters[$forum['fid']] : 0;
	}

	$forum['lastpost_avatar'] = crypto_forumcards_avatar_for($uid);

	return $forum;
}

/**
 * Avatar URL for a user, falling back to the board's default avatar.
 *
 * The result is memoised per uid because a category and its children routinely
 * resolve to the same poster.
 */
function crypto_forumcards_avatar_for($uid)
{
	static $cache = array();

	if(isset($cache[$uid]))
	{
		return $cache[$uid];
	}

	global $db, $mybb;

	$cache[$uid] = '';

	if($uid)
	{
		$query = $db->simple_select('users', 'avatar, avatardimensions, avatartype, showavatars', 'uid=' . (int)$uid, array('limit' => 1));
		$poster = $db->fetch_array($query);

		if($poster && $poster['showavatars'] != 0)
		{
			// format_avatar() expects the raw column value: an uploaded avatar is
			// already stored as a full path, so prefixing it here would double it.
			if($poster['avatar'])
			{
				$formatted = format_avatar($poster['avatar'], $poster['avatardimensions'], '34x34');
				$cache[$uid] = $formatted['image'];
			}
		}
	}

	if($cache[$uid] == '')
	{
		$cache[$uid] = htmlspecialchars_uni($mybb->get_asset_url($mybb->settings['useravatar']));
	}

	return $cache[$uid];
}

/**
 * Featured-thread slider for the board index.
 *
 * Threads are pulled live rather than hard-coded so the hero never goes stale,
 * and every candidate is filtered through the viewer's own forum permissions —
 * surfacing a thread from a forum they cannot read would leak both its title
 * and its existence.
 */
function crypto_forumcards_slider($contents)
{
        global $mybb, $db;

        if(THIS_SCRIPT != 'index.php')
        {
                return $contents;
        }

        // Guard on the markup, not the bare class prefix: headerinclude carries
        // the slider's JS, which contains "nextgen-slider-dot" and would
        // otherwise make this bail out on every page that loads the theme.
        if(strpos($contents, 'class="nextgen-slider"') !== false)
        {
                return $contents;
        }

        $perms = forum_permissions();
        $readable = array();
        foreach($perms as $fid => $perm)
        {
                if(!empty($perm['canview']) && !empty($perm['canviewthreads']))
                {
                        $readable[] = (int)$fid;
                }
        }

        if(!$readable)
        {
                return $contents;
        }

        // Prefer threads with discussion behind them; a slider of empty test
        // topics reads as a dead board. If the board is too young to have any,
        // fall back to whatever exists rather than showing nothing.
        $fids = implode(',', $readable);
        $sql = "
                SELECT t.tid, t.subject, t.replies, t.views, t.dateline, t.uid, t.username,
                       f.name AS forumname, f.fid
                FROM ".TABLE_PREFIX."threads t
                LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = t.fid)
                WHERE t.visible='1' AND t.fid IN ({$fids}) AND t.closed NOT LIKE 'moved|%'
                {FILTER}
                ORDER BY t.replies DESC, t.views DESC, t.dateline DESC
                LIMIT 5
        ";

        // str_replace rather than sprintf: the LIKE pattern above contains a
        // literal '%' that sprintf would read as a conversion specifier.
        $query = $db->query(str_replace('{FILTER}', 'AND (t.replies >= 2 OR t.views >= 50)', $sql));
        if($db->num_rows($query) < 2)
        {
                $query = $db->query(str_replace('{FILTER}', '', $sql));
        }

        $slides = '';
        $dots = '';
        $i = 0;
        while($t = $db->fetch_array($query))
        {
                $subject = htmlspecialchars_uni($t['subject']);
                $forum = htmlspecialchars_uni($t['forumname']);
                $url = htmlspecialchars_uni(get_thread_link((int)$t['tid']));
                $replies = (int)$t['replies'];
                $views = (int)$t['views'];
                $active = ($i === 0) ? ' is-active' : '';

                $slides .= '<a class="nextgen-slide'.$active.'" href="'.$url.'" data-slide="'.$i.'">'
                        . '<span class="nextgen-slide-forum">'.$forum.'</span>'
                        . '<strong class="nextgen-slide-title">'.$subject.'</strong>'
                        . '<span class="nextgen-slide-meta">'.$replies.' yanıt &middot; '.$views.' görüntüleme</span>'
                        . '</a>';

                $dots .= '<button type="button" class="nextgen-slider-dot'.$active.'" data-slide="'.$i.'" aria-label="'.$subject.'"></button>';
                $i++;
        }

        if($i < 2)
        {
                return $contents;
        }

        $slider = <<<HTML
<section class="nextgen-slider" aria-label="Öne çıkan konular" data-slider>
  <div class="nextgen-slider-track">{$slides}</div>
  <div class="nextgen-slider-nav">{$dots}</div>
</section>
HTML;

        // Sits directly above the forum directory so it reads as the board's
        // own highlight reel rather than as an advertisement.
        $needle = '<section class="nextgen-forum-directory"';
        $pos = strpos($contents, $needle);
        if($pos !== false)
        {
                $contents = substr_replace($contents, $slider.$needle, $pos, strlen($needle));
        }

        return $contents;
}
