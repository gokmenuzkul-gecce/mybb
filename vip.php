<?php
/**
 * VIP membership purchase page.
 *
 * Public landing page (perks + plans) for guests, order form for members, and
 * a TXID submission form once an order exists. Confirmation is manual: the
 * order goes to the ACP queue and an admin checks the TXID on-chain.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'vip.php');

$templatelist = 'vip_page,vip_button,vip_pending';

require_once './global.php';
require_once MYBB_ROOT.'inc/functions_user.php';

// global.php has already loaded the active plugins, so the helper functions are
// defined by now. Bail out cleanly rather than defining them a second time if
// the plugin is switched off — the tables would be missing too.
if(!function_exists('vip_membership_grant'))
{
	error('VIP üyelik eklentisi devre dışı. Lütfen yönetime bildirin.');
}

$lang->load('member');

$vip_gid = (int)$mybb->settings['vip_group'];

// Page is readable by guests: the point is to sell. Purchasing needs an account.
add_breadcrumb('VIP Üyelik', 'vip.php');

$plugins->run_hooks('vip_start');

$uid = (int)$mybb->user['uid'];
$is_member = ($uid > 0);
$is_vip = ($is_member && ((int)$mybb->user['usergroup'] == $vip_gid));

$errors = array();
$notice = '';
$vip_body = '';
$pending = false;

/* ---------------------------------------------------------- order list --- */

// "Open" means the member still has a claim on the queue: either they have not
// sent a TXID yet (pending) or an admin has not ruled on it (review). Both must
// block a second order, otherwise a member in review can mint duplicate claims.
$open_statuses = array('pending', 'review');

if($is_member)
{
	$q = $db->simple_select('vip_orders', '*', "uid='{$uid}' ORDER BY dateline DESC", array('limit' => 20));
	while($o = $db->fetch_array($q))
	{
		if(in_array($o['status'], $open_statuses))
		{
			$pending = true;
		}
	}
}

/* ------------------------------------------------------------- actions --- */

$action = $mybb->get_input('action');

if($action == 'order' && $is_member)
{
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->settings['vip_enabled'] != 1)
	{
		$errors[] = 'VIP üyelik satışı şu anda kapalı.';
	}

	if(!$mybb->settings['vip_wallet'])
	{
		$errors[] = 'Ödeme adresi tanımlı değil. Lütfen yönetime bildirin.';
	}

	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$plan = $db->simple_select('vip_plans', '*', "pid='{$pid}' AND enabled='1'");
	$plan = $db->fetch_array($plan);

	if(!$plan)
	{
		$errors[] = 'Geçersiz üyelik planı.';
	}

	// One open order per member keeps the queue and the wallet reference
	// amounts meaningful.
	if($pending)
	{
		$errors[] = 'Zaten onay bekleyen bir ödemeniz var.';
	}

	if(!$errors)
	{
		$amount = (float)$plan['price'] * (float)$mybb->settings['vip_rate'];
		$order = array(
			'uid' => $uid,
			'username' => $db->escape_string($mybb->user['username']),
			'pid' => (int)$plan['pid'],
			'title' => $db->escape_string($plan['title']),
			'days' => (int)$plan['days'],
			'amount' => $amount,
			'amount_exact' => 0,
			'status' => 'pending',
			'txid' => '',
			'dateline' => TIME_NOW,
		);
		$oid = $db->insert_query('vip_orders', $order);

		$exact = vip_membership_exact_amount($amount, $oid);
		$db->update_query('vip_orders', array('amount_exact' => $exact), "oid='{$oid}'");

		redirect('vip.php?action=pay&oid='.$oid, 'Siparişiniz oluşturuldu.');
	}
}

if($action == 'txid' && $is_member)
{
	verify_post_check($mybb->get_input('my_post_key'));

	$oid = $mybb->get_input('oid', MyBB::INPUT_INT);
	$txid = trim($mybb->get_input('txid'));

	$q = $db->simple_select('vip_orders', '*', "oid='{$oid}' AND uid='{$uid}'");
	$order = $db->fetch_array($q);

	if(!$order)
	{
		$errors[] = 'Sipariş bulunamadı.';
	}
	elseif($order['status'] != 'pending')
	{
		$errors[] = 'Bu sipariş zaten işleme alınmış.';
	}
	elseif($txid === '')
	{
		$errors[] = 'Lütfen işlem kimliğini (TXID) girin.';
	}
	// A TRON transaction hash is 64 hex characters. Rejecting anything else
	// catches pasted block-explorer URLs and truncated hashes early, before an
	// admin wastes time on them.
	elseif(!preg_match('/^[0-9a-fA-F]{64}$/', $txid))
	{
		$errors[] = 'TXID 64 karakterlik hexadecimal bir işlem kimliği olmalı (örn. 3f8a...).';
	}

	if(!$errors)
	{
		$db->update_query('vip_orders', array(
			'txid' => $db->escape_string($txid),
			'status' => 'review',
		), "oid='{$oid}'");

		redirect('vip.php', 'Ödemeniz incelemeye alındı. Onaylandığında VIP erişiminiz açılacak.');
	}
}

/* -------------------------------------------------------------- render --- */

if($action == 'pay' && $is_member)
{
	$oid = $mybb->get_input('oid', MyBB::INPUT_INT);
	$q = $db->simple_select('vip_orders', '*', "oid='{$oid}' AND uid='{$uid}'");
	$order = $db->fetch_array($q);

	if(!$order)
	{
		$errors[] = 'Sipariş bulunamadı.';
	}
	elseif($order['status'] == 'approved')
	{
		$notice = 'Bu sipariş onaylanmış. VIP erişiminiz aktif.';
	}
	// Nothing left for the member to do while an admin reviews the TXID; showing
	// the payment form again would invite a second transfer.
	elseif($order['status'] == 'review')
	{
		$vip_body = $templates->get('vip_pending', 1, 0);
		$vip_body = eval('return "'.$vip_body.'";');
	}
	elseif($order['status'] == 'rejected')
	{
		$notice = 'Bu sipariş reddedildi. Yeni bir sipariş oluşturabilirsiniz.';
	}
	elseif($order['status'] == 'expired')
	{
		$notice = 'Bu siparişin süresi doldu. Yeni bir sipariş oluşturabilirsiniz.';
	}
	else
	{
		$wallet = htmlspecialchars_uni($mybb->settings['vip_wallet']);
		$currency = htmlspecialchars_uni($mybb->settings['vip_currency']);
		$exact = htmlspecialchars_uni($order['amount_exact']);
		$plain = htmlspecialchars_uni($order['amount']);
		$title = htmlspecialchars_uni($order['title']);

		$post_key = $mybb->post_code;
		$form_action = htmlspecialchars_uni($mybb->settings['bburl']).'/vip.php';

		// Wallet addresses are long; give the member a one-click copy instead
		// of asking them to select a 34-character string by hand.
		$vip_body = <<<HTML
<div class="nextgen-vip-pay">
  <h2>{$title}</h2>
  <p class="nextgen-vip-pay-lead">Aşağıdaki adrese <strong>tam olarak {$exact} {$currency}</strong> gönderin. Tutardaki kuruş farkı ödemenizin eşleştirilmesi içindir; lütfen yuvarlamayın.</p>

  <div class="nextgen-vip-field">
    <span class="nextgen-vip-label">Cüzdan adresi (TRC-20 / TRON)</span>
    <div class="nextgen-vip-copyrow">
      <code id="vip-wallet">{$wallet}</code>
      <button type="button" class="nextgen-vip-copy" data-copy="#vip-wallet"><i class="fa-regular fa-copy"></i> Kopyala</button>
    </div>
  </div>

  <div class="nextgen-vip-field">
    <span class="nextgen-vip-label">Gönderilecek tutar</span>
    <div class="nextgen-vip-copyrow">
      <code id="vip-amount">{$exact} {$currency}</code>
      <button type="button" class="nextgen-vip-copy" data-copy="#vip-amount"><i class="fa-regular fa-copy"></i> Kopyala</button>
    </div>
    <small>Liste fiyatı: {$plain} {$currency}</small>
  </div>

  <div class="nextgen-vip-warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>Yalnızca <strong>TRC-20 (TRON)</strong> ağı üzerinden USDT gönderin. Farklı bir ağdan yapılan transferler kaybolur ve iade edilemez.</div>
  </div>

  <form action="{$form_action}" method="post" class="nextgen-vip-txform">
    <input type="hidden" name="action" value="txid" />
    <input type="hidden" name="oid" value="{$oid}" />
    <input type="hidden" name="my_post_key" value="{$post_key}" />
    <label for="vip-txid">Ödeme sonrası işlem kimliği (TXID)</label>
    <input type="text" id="vip-txid" name="txid" class="textbox" placeholder="64 karakterlik işlem kimliği" autocomplete="off" />
    <button type="submit" class="nextgen-vip-submit"><i class="fa-solid fa-paper-plane"></i> Ödemeyi Bildir</button>
  </form>
</div>
HTML;
	}
}
elseif($is_member && $pending)
{
	$vip_body = $templates->get('vip_pending', 1, 0);
	$vip_body = eval('return "'.$vip_body.'";');
}
else
{
	// Plan grid. Guests see the same grid, with the button pointing at login.
	$q = $db->simple_select('vip_plans', '*', "enabled='1'", array('order_by' => 'disporder', 'order_dir' => 'ASC'));
	$plans = '';
	while($p = $db->fetch_array($q))
	{
		$ptitle = htmlspecialchars_uni($p['title']);
		$price = htmlspecialchars_uni(number_format((float)$p['price'], 2, '.', ''));
		$days = (int)$p['days'];
		$currency = htmlspecialchars_uni($mybb->settings['vip_currency']);
		$per = number_format($p['price'] / max(1, $days) * 30, 2);

		if($is_member)
		{
			$cta = '<form action="'.htmlspecialchars_uni($mybb->settings['bburl']).'/vip.php" method="post">
				<input type="hidden" name="action" value="order" />
				<input type="hidden" name="pid" value="'.(int)$p['pid'].'" />
				<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
				<button type="submit" class="nextgen-vip-submit">Şimdi Satın Al</button>
			</form>';
		}
		else
		{
			$cta = '<a class="nextgen-vip-submit" href="'.htmlspecialchars_uni($mybb->settings['bburl']).'/member.php?action=login">Giriş Yap ve Satın Al</a>';
		}

		$plans .= <<<HTML
<div class="nextgen-vip-plan">
  <span class="nextgen-vip-plan-days">{$days} gün</span>
  <strong class="nextgen-vip-plan-title">{$ptitle}</strong>
  <span class="nextgen-vip-plan-price">{$price} <small>{$currency}</small></span>
  <span class="nextgen-vip-plan-per">aylık ~{$per} {$currency}</span>
  {$cta}
</div>
HTML;
	}

	if($mybb->settings['vip_enabled'] != 1)
	{
		$plans = '<div class="nextgen-vip-notice nextgen-vip-notice-warn"><i class="fa-solid fa-circle-info"></i> VIP üyelik satışı şu anda kapalı.</div>';
	}

	$vip_body = '<div class="nextgen-vip-plans">'.$plans.'</div>';
}

if($errors)
{
	$err = '';
	foreach($errors as $e)
	{
		$err .= '<li>'.htmlspecialchars_uni($e).'</li>';
	}
	$vip_body = '<div class="nextgen-vip-notice nextgen-vip-notice-error"><ul>'.$err.'</ul></div>'.$vip_body;
}

$plugins->run_hooks('vip_end');

eval('$vip_page = "'.$templates->get('vip_page').'";');
output_page($vip_page);