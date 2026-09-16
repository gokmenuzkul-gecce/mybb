<?php
/**
 * ACP module meta for VIP membership.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

function vip_membership_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'orders', 'title' => 'Ödeme Kuyruğu', 'link' => 'index.php?module=vip_membership-orders');
	$sub_menu['20'] = array('id' => 'plans', 'title' => 'Planlar', 'link' => 'index.php?module=vip_membership-plans');

	$sub_menu = $plugins->run_hooks('admin_vip_membership_menu', $sub_menu);

	$page->add_menu_item('VIP Üyelik', 'vip_membership', 'index.php?module=vip_membership', 65, $sub_menu);
	return true;
}

function vip_membership_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = 'vip_membership';

	$actions = array(
		'orders' => array('active' => 'orders', 'file' => 'orders.php'),
		'plans' => array('active' => 'plans', 'file' => 'plans.php'),
	);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}

	$page->active_action = 'orders';
	return 'orders.php';
}

function vip_membership_admin_permissions()
{
	global $lang;

	return array(
		'vip_membership' => array(
			'orders' => 'Ödeme kuyruğunu görüntüle ve onayla',
			'plans' => 'VIP planlarını yönet',
		),
	);
}