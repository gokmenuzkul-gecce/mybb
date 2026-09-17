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
$plugins->add_hook('global_start', 'crypto_usermenu_global_start');

function crypto_forumcards_info()
{
	return array(
		'name'			=> 'Crypto Forum Cards',
		'description'	=> 'Forum satirlarina basliga gore ikon ve son mesaj atanin avatarini ekler.',
		'website'		=> '',
		'author'		=> 'Gecce',
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


/* --------------------------------------------------------- user menu --- */

/**
 * Feed the header's user menu: avatar, primary group title and PM counters.
 */
function crypto_usermenu_global_start()
{
    global $mybb, $db, $templates, $lang, $config;
    global $nextgen_usermenu_avatar, $nextgen_usermenu_group, $nextgen_usermenu_pm, $nextgen_usermenu_staff;

    $nextgen_usermenu_staff = '';

    $uid = (int)$mybb->user['uid'];
    if($uid <= 0)
    {
        return;
    }

    $avatar = format_avatar($mybb->user['avatar'], $mybb->user['avatardimensions'], '44x44');
    $nextgen_usermenu_avatar = '<img class="nextgen-usermenu-img" src="'.htmlspecialchars_uni($avatar['image']).'" alt="" loading="lazy" />';

    $group = $mybb->usergroup['title'] ? $mybb->usergroup['title'] : $lang->guest;
    $nextgen_usermenu_group = htmlspecialchars_uni($group);

    $unread = (int)$mybb->user['unreadpms'];
    $total = (int)$mybb->user['totalpms'];
    $nextgen_usermenu_pm = '<span class="nextgen-usermenu-badge'.($unread > 0 ? ' is-unread' : '').'">'.$unread.'/'.$total.'</span>';

    // Staff shortcuts are built here rather than with a template conditional:
    // MyBB 1.8 templates have no <if> syntax, so an inline conditional would be
    // printed to every member verbatim and leak the links to them.
    $bburl = $mybb->settings['bburl'];
    $staff = '';

    if(!empty($mybb->usergroup['canmodcp']))
    {
        $staff .= '<a class="nextgen-usermenu-link is-staff" href="'.$bburl.'/modcp.php">'
            . '<i class="fa-solid fa-gavel" aria-hidden="true"></i><span>Mod CP</span></a>';
    }

    if(!empty($mybb->usergroup['cancp']))
    {
        $admin_dir = !empty($config['admin_dir']) ? $config['admin_dir'] : 'admin';
        $staff .= '<a class="nextgen-usermenu-link is-staff" href="'.$bburl.'/'.$admin_dir.'/index.php">'
            . '<i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Admin CP</span></a>';
    }

    $nextgen_usermenu_staff = $staff;
}
