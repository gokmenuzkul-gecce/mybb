<?php
/**
 * ACP module meta for the RSS news bot.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function rss_news_bot_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'queue', 'title' => 'Onay Kuyruğu', 'link' => 'index.php?module=rss_news_bot-queue');
	$sub_menu['15'] = array('id' => 'categories', 'title' => 'Kategoriler', 'link' => 'index.php?module=rss_news_bot-categories');

	$sub_menu = $plugins->run_hooks('admin_rss_news_bot_menu', $sub_menu);

	$page->add_menu_item('RSS Haber Botu', 'rss_news_bot', 'index.php?module=rss_news_bot', 61, $sub_menu);
	return true;
}

function rss_news_bot_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = 'rss_news_bot';

	$actions = array(
		'queue' => array('active' => 'queue', 'file' => 'queue.php'),
		'categories' => array('active' => 'categories', 'file' => 'categories.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'queue';
	return 'queue.php';
}

function rss_news_bot_admin_permissions()
{
	return array(
		'name' => 'RSS Haber Botu',
		'permissions' => array(
			'queue' => 'Haber onay kuyruğunu görüntüle ve onayla',
                        'categories' => 'Kategorileri, kaynak beslemeleri ve çekimi yönet',
		),
		'disporder' => 66,
	);
}