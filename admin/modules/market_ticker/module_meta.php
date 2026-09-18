<?php
/**
 * ACP module meta for the live market ticker.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function market_ticker_meta()
{
	global $page, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'ticker', 'title' => 'Fiyat Şeridi', 'link' => 'index.php?module=market_ticker-ticker');
	// Raw '&': add_menu_items() runs the link through htmlspecialchars_uni(),
	// so a pre-escaped &amp; would render as &amp;amp;.
	$sub_menu['20'] = array('id' => 'settings', 'title' => 'Ayarlar', 'link' => 'index.php?module=config-settings&action=change&gid='.market_ticker_gid());

	$sub_menu = $plugins->run_hooks('admin_market_ticker_menu', $sub_menu);

	$page->add_menu_item('Canlı Borsa Tablosu', 'market_ticker', 'index.php?module=market_ticker', 63, $sub_menu);
	return true;
}

function market_ticker_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = 'market_ticker';

	$actions = array(
		'ticker' => array('active' => 'ticker', 'file' => 'ticker.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'ticker';
	return 'ticker.php';
}

function market_ticker_admin_permissions()
{
	return array(
		'name' => 'Canlı Borsa Tablosu',
		'permissions' => array(
			'ticker' => 'Canlı fiyat şeridini görüntüle ve yenile',
		),
		'disporder' => 68,
	);
}

/**
 * The settings group id, needed to deep-link the settings sub menu.
 *
 * Returns 0 when the group is missing so the link still resolves to the
 * settings list instead of a broken gid.
 */
function market_ticker_gid()
{
	global $db;

	static $gid = null;
	if($gid === null)
	{
		$gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='market_ticker'"), 'gid');
	}
	return $gid;
}