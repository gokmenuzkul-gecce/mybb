<?php
/**
 * ACP module meta for the forum cards plugin.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function crypto_forumcards_meta()
{
	global $page, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'icons', 'title' => 'İkon Eşleşmeleri', 'link' => 'index.php?module=crypto_forumcards-icons');

	$sub_menu = $plugins->run_hooks('admin_crypto_forumcards_menu', $sub_menu);

	$page->add_menu_item('Forum Kartları', 'crypto_forumcards', 'index.php?module=crypto_forumcards', 66, $sub_menu);
	return true;
}

function crypto_forumcards_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = 'crypto_forumcards';

	$actions = array(
		'icons' => array('active' => 'icons', 'file' => 'icons.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'icons';
	return 'icons.php';
}

function crypto_forumcards_admin_permissions()
{
	return array(
		'name' => 'Forum Kartları',
		'permissions' => array(
			'icons' => 'Forum ikon eşleşmelerini görüntüle',
		),
		'disporder' => 70,
	);
}