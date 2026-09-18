<?php
/**
 * ACP module meta for social login.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function social_login_meta()
{
	global $page, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'providers', 'title' => 'Sağlayıcılar', 'link' => 'index.php?module=social_login-providers');
	$sub_menu['20'] = array('id' => 'accounts', 'title' => 'Bağlı Hesaplar', 'link' => 'index.php?module=social_login-accounts');
	// Raw '&': add_menu_items() runs the link through htmlspecialchars_uni(),
	// so a pre-escaped &amp; would render as &amp;amp;.
	$sub_menu['30'] = array('id' => 'settings', 'title' => 'Ayarlar', 'link' => 'index.php?module=config-settings&action=change&gid='.social_login_gid());

	$sub_menu = $plugins->run_hooks('admin_social_login_menu', $sub_menu);

	$page->add_menu_item('Sosyal Giriş', 'social_login', 'index.php?module=social_login', 64, $sub_menu);
	return true;
}

function social_login_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = 'social_login';

	$actions = array(
		'providers' => array('active' => 'providers', 'file' => 'providers.php'),
		'accounts' => array('active' => 'accounts', 'file' => 'accounts.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'providers';
	return 'providers.php';
}

function social_login_admin_permissions()
{
	return array(
		'name' => 'Sosyal Giriş',
		'permissions' => array(
			'providers' => 'Sosyal giriş sağlayıcılarını görüntüle',
			'accounts' => 'Sosyal girişle bağlanmış hesapları yönet',
		),
		'disporder' => 69,
	);
}

/**
 * Settings group id, used to deep-link the settings sub menu.
 */
function social_login_gid()
{
	global $db;

	static $gid = null;
	if($gid === null)
	{
		$gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='social_login'"), 'gid');
	}
	return $gid;
}