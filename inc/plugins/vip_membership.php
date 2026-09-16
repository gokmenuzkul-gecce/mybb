<?php
/**
 * VIP Membership — crypto/TRC-20 subscription system for the crypto-web3 theme.
 *
 * Flow: a member opens vip.php, requests a plan, is shown a TRC-20 USDT address
 * with a unique reference amount, pays from their wallet and submits the TXID.
 * The order lands in the ACP "Onay Bekleyen Ödemeler" queue. On approval the
 * member is moved into the VIP group; a scheduled task drops them back to
 * Registered once the paid period lapses.
 *
 * Deliberately does NOT talk to a payment gateway. There is no API key to
 * store, and no third party learns who our members are; the trade-off is that
 * each payment is confirmed by a human reading the TXID against the chain.
 */

if(!defined('IN_MYBB'))
{
	die('Bu dosyaya doğrudan erişilemez.');
}

$plugins->add_hook('global_start', 'vip_membership_global_start');
$plugins->add_hook('pre_output_page', 'vip_membership_inject_button');
$plugins->add_hook('member_profile_end', 'vip_membership_profile_badge');

function vip_membership_info()
{
	return array(
		'name'          => 'VIP Üyelik ve Kripto Ödeme',
		'description'   => 'TRC-20 USDT ile VIP üyelik satışı, admin onay kuyruğu ve otomatik süreli rütbe değişimi.',
		'website'       => '',
		'author'        => 'Crypton Web3 Community',
		'authorsite'    => '',
		'version'       => '1.0',
		'guid'          => 'b7c1e4a2f9d34c6e8a5b0d7f2c3e9a18',
		'compatibility' => '18*',
	);
}

function vip_membership_install()
{
	global $db, $cache;

	vip_membership_create_tables();
	vip_membership_create_settings();
	vip_membership_create_task();
	vip_membership_create_templates();

	$cache->update_usergroups();
	echo 'VIP Üyelik kuruldu.';
}

function vip_membership_is_installed()
{
	global $db;
	return $db->table_exists('vip_orders');
}

function vip_membership_uninstall()
{
	global $db, $cache;

	$db->drop_table('vip_plans');
	$db->drop_table('vip_orders');

	$db->delete_query('settings', "name IN ('vip_enabled','vip_wallet','vip_currency','vip_rate','vip_group','vip_days','vip_pending_note','vip_txid_note')");
	$db->delete_query('settinggroups', "name='vip_membership'");

	$db->delete_query('tasks', "file='vip_expire'");

	foreach(array('vip_page', 'vip_button', 'vip_pending') as $t)
	{
		$db->delete_query('templates', "title='{$t}'");
	}

	rebuild_settings();
	echo 'VIP Üyelik kaldırıldı.';
}

/* ------------------------------------------------------------- schema --- */

function vip_membership_create_tables()
{
	global $db;

	// The board ships on SQLite but a real deployment is usually MySQL, and the
	// two disagree on the CREATE TABLE tail. Build it per engine rather than
	// forcing one dialect on both.
	$mysql = ($db->engine == 'mysql');
	$tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
	$pk = $mysql ? 'INT(11) NOT NULL AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
	$int = $mysql ? 'INT(11)' : 'INTEGER';
	$tiny = $mysql ? 'TINYINT(1)' : 'INTEGER';
	$dec = $mysql ? 'DECIMAL(16,2)' : 'REAL';
	$dec6 = $mysql ? 'DECIMAL(18,6)' : 'REAL';

	if(!$db->table_exists('vip_plans'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."vip_plans (
			pid {$pk},
			title VARCHAR(120) NOT NULL,
			days {$int} NOT NULL DEFAULT 30,
			price {$dec} NOT NULL DEFAULT 0,
			disporder {$int} NOT NULL DEFAULT 0,
			enabled {$tiny} NOT NULL DEFAULT 1".($mysql ? ",\n\t\t\tPRIMARY KEY (pid)" : "")."
		){$tail};");

		$db->insert_query('vip_plans', array(
			'title' => '1 Aylık VIP', 'days' => 30, 'price' => 25.00, 'disporder' => 1, 'enabled' => 1,
		));
		$db->insert_query('vip_plans', array(
			'title' => '3 Aylık VIP', 'days' => 90, 'price' => 60.00, 'disporder' => 2, 'enabled' => 1,
		));
		$db->insert_query('vip_plans', array(
			'title' => '1 Yıllık VIP', 'days' => 365, 'price' => 200.00, 'disporder' => 3, 'enabled' => 1,
		));
	}

	if(!$db->table_exists('vip_orders'))
	{
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."vip_orders (
			oid {$pk},
			uid {$int} NOT NULL DEFAULT 0,
			username VARCHAR(120) NOT NULL,
			pid {$int} NOT NULL DEFAULT 0,
			title VARCHAR(120) NOT NULL,
			days {$int} NOT NULL DEFAULT 0,
			amount {$dec} NOT NULL DEFAULT 0,
			amount_exact {$dec6} NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			txid VARCHAR(120) NOT NULL DEFAULT '',
			note TEXT,
			admin_note TEXT,
			dateline {$int} NOT NULL DEFAULT 0,
			paid_until {$int} NOT NULL DEFAULT 0,
			handled_by {$int} NOT NULL DEFAULT 0,
			handled_at {$int} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (oid),\n\t\t\tKEY uid (uid),\n\t\t\tKEY status (status),\n\t\t\tKEY txid (txid)" : "")."
		){$tail};");

		if(!$mysql)
		{
			// SQLite has no inline KEY clause; indexes are separate statements.
			$db->write_query("CREATE INDEX ".TABLE_PREFIX."vip_orders_uid ON ".TABLE_PREFIX."vip_orders (uid);");
			$db->write_query("CREATE INDEX ".TABLE_PREFIX."vip_orders_status ON ".TABLE_PREFIX."vip_orders (status);");
			$db->write_query("CREATE INDEX ".TABLE_PREFIX."vip_orders_txid ON ".TABLE_PREFIX."vip_orders (txid);");
		}
	}
}

function vip_membership_create_settings()
{
	global $db, $cache;

	// Idempotent: a half-finished install must be safe to re-run, otherwise a
	// retry doubles every setting and confuses the ACP.
	$existing_group = $db->simple_select('settinggroups', 'gid', "name='vip_membership'");
	if($db->num_rows($existing_group))
	{
		$gid = (int)$db->fetch_field($existing_group, 'gid');
	}
	else
	{
		$gid = $db->insert_query('settinggroups', array(
			'name' => 'vip_membership',
			'title' => 'VIP Üyelik',
			'description' => 'Kripto ödeme ile VIP üyelik satışı ayarları.',
			'disporder' => 60,
			'isdefault' => 0,
		));
	}

	$settings = array(
		array('vip_enabled', 'VIP satışı açık', '0', 'yesno', 'Kapalıysa vip.php satın alma formu yerine bilgilendirme gösterir.', 1),
		array('vip_wallet', 'USDT (TRC-20) cüzdan adresi', '', 'text', 'Ödemelerin gönderileceği TRON cüzdan adresi.', 2),
		array('vip_currency', 'Para birimi', 'USDT', 'text', 'Fiyatların gösterileceği birim.', 3),
		array('vip_rate', 'Kripto kuru (1 birim = ? USDT)', '1', 'text', 'Fiyatlar USD ise 1 bırakın.', 4),
		array('vip_group', 'VIP grup ID', '8', 'text', 'Ödeme onaylandığında atanacak kullanıcı grubu.', 5),
		array('vip_days', 'Varsayılan süre (gün)', '30', 'text', 'Plan seçilmediğinde kullanılacak süre.', 6),
		array('vip_pending_note', 'Ödeme bekleyenlere not', '', 'textarea', 'Onay bekleyen kullanıcıya gösterilecek metin.', 7),
	);
	foreach($settings as $s)
	{
		$exists = $db->simple_select('settings', 'sid', "name='{$s[0]}'");
		if($db->num_rows($exists))
		{
			continue;
		}
		$db->insert_query('settings', array(
			'name' => $s[0], 'title' => $s[1], 'description' => $s[4],
			'optionscode' => $s[3], 'value' => $s[2], 'disporder' => $s[5],
			'gid' => $gid, 'isdefault' => 0,
		));
	}
	rebuild_settings();
}

function vip_membership_create_task()
{
	global $db;

	$exists = $db->simple_select('tasks', 'tid', "file='vip_expire'");
	if(!$db->num_rows($exists))
	{
		$db->insert_query('tasks', array(
			'title' => 'VIP Üyelik Süresi Kontrolü',
			'description' => 'Süresi dolan VIP üyeleri Kayıtlı Üye grubuna geri döndürür.',
			'file' => 'vip_expire',
			'minute' => '15', 'hour' => '*', 'day' => '*', 'month' => '*', 'weekday' => '*',
			'nextrun' => TIME_NOW, 'lastrun' => 0, 'enabled' => 1, 'logging' => 1, 'locked' => 0,
		));
	}
}

function vip_membership_create_templates()
{
	global $db;

	$templates = array(
		'vip_page' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - VIP Üyelik</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="nextgen-vip-hero">
  <div class="nextgen-vip-hero-inner">
    <span class="nextgen-vip-kicker"><i class="fa-solid fa-crown"></i> VIP CLUB</span>
    <h1>Ayrıcalıklı Analiz ve Sinyaller</h1>
    <p>VIP Club; balina sinyalleri, erken aşama fırsatlar ve otomasyon scriptlerinin paylaşıldığı kapalı bir alandır. Üyeliğiniz onaylandığı anda tüm VIP forumları açılır.</p>
  </div>
</div>
<div class="wrapper nextgen-vip-wrap">
  <div class="nextgen-vip-perks">
    <div class="nextgen-vip-perk"><i class="fa-solid fa-whale"></i><strong>Balina Sinyalleri</strong><span>Spot sepeti ve giriş-çıkış seviyeleri.</span></div>
    <div class="nextgen-vip-perk"><i class="fa-solid fa-user-shield"></i><strong>Hesap Alım-Satım</strong><span>Doğrulanmış hesap ve hizmet pazarı.</span></div>
    <div class="nextgen-vip-perk"><i class="fa-solid fa-code"></i><strong>Otomasyon Scriptleri</strong><span>Çoklu hesap ve bot scriptleri.</span></div>
    <div class="nextgen-vip-perk"><i class="fa-solid fa-fire"></i><strong>Erken Airdrop</strong><span>Listelenmemiş erken aşama fırsatlar.</span></div>
  </div>
  {$vip_body}
</div>
{$footer}
</body>
</html>',
		'vip_button' => '<li class="nextgen-vip-navitem"><a href="{$mybb->settings[\'bburl\']}/vip.php" class="nextgen-vip-buy"><i class="fa-solid fa-crown" aria-hidden="true"></i> VIP Satın Al</a></li>',
		'vip_pending' => '<div class="nextgen-vip-notice nextgen-vip-notice-warn"><i class="fa-solid fa-clock"></i> Ödemeniz onay bekliyor. Onaylandığında VIP erişiminiz otomatik açılacaktır.</div>',
	);

	foreach($templates as $title => $tpl)
	{
		// The SQLite driver's quote_val() only wraps values in quotes; it does
		// not escape them, so the caller must escape first.
		$tpl = $db->escape_string($tpl);
		$title = $db->escape_string($title);

		$exists = $db->simple_select('templates', 'tid', "title='{$title}' AND sid='-2'");
		if($db->num_rows($exists))
		{
			$db->update_query('templates', array('template' => $tpl, 'dateline' => TIME_NOW), "title='{$title}' AND sid='-2'");
		}
		else
		{
			$db->insert_query('templates', array(
				'title' => $title, 'template' => $tpl, 'sid' => -2,
				'version' => 1820, 'status' => 0, 'dateline' => TIME_NOW,
			));
		}
	}
}

/* -------------------------------------------------------------- hooks --- */

/**
 * Loads the VIP helpers on every page. vip.php is not a normal MyBB script, so
 * it needs the class autoloaded before its own logic runs.
 */
function vip_membership_global_start()
{
	global $mybb;

	if(defined('THIS_SCRIPT') && THIS_SCRIPT == 'vip.php')
	{
		// Handled by vip.php itself.
	}
	// Keep the current order state available to any page that shows it.
	$mybb->vip_active = vip_membership_is_active($mybb->user['uid']);
}

/**
 * Is the member currently within a paid period?
 */
function vip_membership_is_active($uid)
{
	global $db, $mybb;

	if(!$uid)
	{
		return false;
	}

	// Trust the group only when the subscription row still has time left, so a
	// manually added member without an order keeps their group.
	$vip_gid = (int)$mybb->settings['vip_group'];
	$order = $db->simple_select('vip_orders', 'paid_until', "uid='{$uid}' AND status='approved' ORDER BY paid_until DESC", array('limit' => 1));
	if($db->num_rows($order))
	{
		$row = $db->fetch_array($order);
		return ((int)$row['paid_until'] > TIME_NOW);
	}

	$user = $db->simple_select('users', 'usergroup', "uid='{$uid}'");
	$urow = $db->fetch_array($user);
	return ($urow && (int)$urow['usergroup'] == $vip_gid);
}

/**
 * Adds the "VIP Satın Al" entry to the header navigation. Uses string
 * replacement rather than editing the template so the button disappears
 * cleanly when the plugin is deactivated.
 */
function vip_membership_inject_button($contents)
{
	global $mybb, $db, $templates, $lang;

	$vip_gid = (int)$mybb->settings['vip_group'];
	$is_vip = ((int)$mybb->user['usergroup'] == $vip_gid);

	// Admins and existing VIP members are already inside; only sell to others.
	if($is_vip || $mybb->usergroup['cancp'] == 1)
	{
		return $contents;
	}

	if(strpos($contents, 'nextgen-vip-navitem') !== false)
	{
		return $contents;
	}

	$button = eval($templates->render('vip_button'));

	// Splice the item into the top-links list, right after it opens.
	$needle = '<ul class="menu top_links" id="nextgen-top-links">';
	$pos = strpos($contents, $needle);
	if($pos !== false)
	{
		$contents = substr_replace($contents, $needle.$button, $pos, strlen($needle));
	}
	else
	{
		$contents = str_replace('</header>', $button.'</header>', $contents);
	}

	return $contents;
}

/**
 * Shows a gold VIP badge next to the member's name on their profile.
 */
function vip_membership_profile_badge()
{
	global $memprofile, $mybb, $vip_badge;

	$vip_gid = (int)$mybb->settings['vip_group'];
	if($memprofile && (int)$memprofile['usergroup'] == $vip_gid)
	{
		$vip_badge = '<span class="nextgen-vip-badge"><i class="fa-solid fa-crown"></i> VIP</span>';
	}
}

/* ------------------------------------------------------------ helpers --- */

/**
 * Assigns the VIP group and extends the paid period. Central so the ACP, the
 * scheduled task and any future gateway agree on the rules.
 */
function vip_membership_grant($order, $days, $admin_uid = 0)
{
	global $db, $cache;

	$uid = (int)$order['uid'];
	$days = max(1, (int)$days);

	$user = $db->simple_select('users', 'uid, usergroup, additionalgroups, displaygroup', "uid='{$uid}'");
	$urow = $db->fetch_array($user);
	if(!$urow)
	{
		return false;
	}

	$vip_gid = (int)$GLOBALS['mybb']->settings['vip_group'];

	// Extend from the later of now / current expiry so buying again stacks
	// instead of resetting the clock.
	$current = $db->simple_select('vip_orders', 'paid_until', "uid='{$uid}' AND status='approved' ORDER BY paid_until DESC", array('limit' => 1));
	$base = TIME_NOW;
	if($db->num_rows($current))
	{
		$row = $db->fetch_array($current);
		$base = max(TIME_NOW, (int)$row['paid_until']);
	}
	$paid_until = $base + ($days * 86400);

	$update = array(
		'usergroup' => $vip_gid,
		'displaygroup' => 0,
		'usertitle' => 'VIP Üye',
		'additionalgroups' => '',
	);
	$db->update_query('users', $update, "uid='{$uid}'");

	$db->update_query('vip_orders', array(
		'status' => 'approved',
		'paid_until' => $paid_until,
		'handled_by' => (int)$admin_uid,
		'handled_at' => TIME_NOW,
	), "oid='".(int)$order['oid']."'");

	$cache->update_usergroups();
	return $paid_until;
}

/**
 * Reference amount unique to the order. Two members paying "25 USDT" at the
 * same time become distinguishable on-chain by the fractional part, which is
 * what makes manual confirmation practical.
 */
function vip_membership_exact_amount($amount, $oid)
{
	$cents = (int)($oid % 99) + 1;
	return number_format((float)$amount + ($cents / 100), 6, '.', '');
}