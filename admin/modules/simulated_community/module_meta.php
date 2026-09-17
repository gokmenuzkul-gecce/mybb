<?php
/**
 * ACP module meta for the simulated community seeder.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function simulated_community_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'dashboard', 'title' => 'Panel', 'link' => 'index.php?module=simulated_community-dashboard');

	$sub_menu = $plugins->run_hooks('admin_simulated_community_menu', $sub_menu);

	$page->add_menu_item('Simüle Topluluk', 'simulated_community', 'index.php?module=simulated_community', 62, $sub_menu);
	return true;
}

function simulated_community_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = 'simulated_community';

	$actions = array(
		'dashboard' => array('active' => 'dashboard', 'file' => 'dashboard.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'dashboard';
	return 'dashboard.php';
}

function simulated_community_admin_permissions()
{
	return array(
		'name' => 'Simüle Topluluk',
		'permissions' => array(
			'dashboard' => 'Simüle topluluk panelini görüntüle ve içerik üret',
		),
		'disporder' => 67,
	);
}