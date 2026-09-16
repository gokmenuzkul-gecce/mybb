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
	$sub_menu['20'] = array('id' => 'feeds', 'title' => 'Beslemeler', 'link' => 'index.php?module=rss_news_bot-feeds');

	$sub_menu = $plugins->run_hooks('admin_rss_news_bot_menu', $sub_menu);

	$page->add_menu_item('RSS Haber Botu', 'rss_news_bot', 'index.php?module=rss_news_bot', 66, $sub_menu);
	return true;
}

function rss_news_bot_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = 'rss_news_bot';

	$actions = array(
		'queue' => array('active' => 'queue', 'file' => 'queue.php'),
		'feeds' => array('active' => 'feeds', 'file' => 'feeds.php'),
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
	global $lang;

	return array(
		'rss_news_bot' => array(
			'queue' => 'Haber onay kuyruğunu görüntüle ve onayla',
			'feeds' => 'RSS beslemelerini yönet',
		),
	);
}