<?php
/**
 * Scheduled task: expire lapsed VIP memberships.
 *
 * Runs hourly. Any member whose latest approved order has run out is moved back
 * to the Registered group. Members whose VIP group was assigned by hand and who
 * have no order at all are left alone — the task only touches people it sold to.
 */

function task_vip_expire($task)
{
	global $db, $cache, $mybb;

	$vip_gid = (int)$mybb->settings['vip_group'];
	$registered_gid = 2;

	// Candidates: members sitting in the VIP group.
	$query = $db->simple_select('users', 'uid, username', "usergroup='{$vip_gid}'");
	$expired = array();

	while($user = $db->fetch_array($query))
	{
		$uid = (int)$user['uid'];

		$order = $db->simple_select('vip_orders', 'oid, paid_until', "uid='{$uid}' AND status='approved' ORDER BY paid_until DESC", array('limit' => 1));

		if(!$db->num_rows($order))
		{
			// No order on record: a manual VIP. Not ours to revoke.
			continue;
		}

		$row = $db->fetch_array($order);
		if((int)$row['paid_until'] > TIME_NOW)
		{
			continue;
		}

		$expired[] = $uid;
	}

	if(!$expired)
	{
		return true;
	}

	$idlist = implode(',', array_map('intval', $expired));
	$db->update_query('users', array(
		'usergroup' => $registered_gid,
		'usertitle' => '',
	), "uid IN ({$idlist})");

	$db->update_query('vip_orders', array('status' => 'expired'), "status='approved' AND paid_until < '".TIME_NOW."' AND uid IN ({$idlist})");

	if(is_object($GLOBALS['plugins']))
	{
		// run_hooks() takes $arguments by reference, so an inline array literal
		// raises a fatal error on PHP 8.
		$hook_args = array('uids' => $expired);
		$GLOBALS['plugins']->run_hooks('task_vip_expire_end', $hook_args);
	}

	$cache->update_usergroups();
	add_task_log($task, 'Süresi dolan '.count($expired).' VIP üyelik kapatıldı ve üyeler Kayıtlı Üye grubuna döndürüldü.');

	return true;
}