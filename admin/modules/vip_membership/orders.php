<?php
/**
 * ACP: VIP payment queue.
 *
 * Lists orders, lets an admin approve (grants the group and starts the clock)
 * or reject them. Approval is the only place a membership begins from a
 * payment, so this is the security-critical screen.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('VIP Üyelik', 'index.php?module=vip_membership');
$page->add_breadcrumb_item('Ödeme Kuyruğu', 'index.php?module=vip_membership-orders');

$action = $mybb->get_input('action');
$oid = $mybb->get_input('oid', MyBB::INPUT_INT);

/* ------------------------------------------------------------- actions --- */

if($action == 'approve' || $action == 'reject')
{
	verify_post_check($mybb->get_input('my_post_key'));

	// GET renders a confirmation form; the POST that follows carries it out.
	// Using the HTTP verb (not a hidden flag) keeps the two paths from being
	// confused with each other.
	if($mybb->request_method != 'post')
	{
		$page->output_header('Ödeme İşlemi');
		$page->add_breadcrumb_item('Ödeme İşlemi');
		$form = new Form('index.php?module=vip_membership-orders&amp;action='.$action.'&amp;oid='.$oid, 'post');

		if($action == 'approve')
		{
			$form_container = new FormContainer('Ödemeyi Onayla');
			$form_container->output_row('Onay', 'Üye VIP grubuna alınacak ve üyelik süresi başlayacak. TXID\'yi zincir üzerinde doğruladığınızdan emin olun.', $form->generate_hidden_field('oid', $oid).$form->generate_hidden_field('action', $action));
			$form_container->end();
			$buttons[] = $form->generate_submit_button('Onayla');
		}
		else
		{
			$form_container = new FormContainer('Ödemeyi Reddet');
			$form_container->output_row('Red nedeni', 'Üyeye gösterilecek kısa bir açıklama.', $form->generate_hidden_field('oid', $oid).$form->generate_hidden_field('action', $action));
			$form_container->output_row('Not', '', $form->generate_text_area('admin_note', ''), 'not');
			$form_container->end();
			$buttons[] = $form->generate_submit_button('Reddet');
		}

		$form->output_submit_wrapper($buttons);
		$form->end();
		$page->output_footer();
		exit;
	}

	$order = $db->simple_select('vip_orders', '*', "oid='{$oid}'");
	$order = $db->fetch_array($order);

	if(!$order)
	{
		flash_message('Sipariş bulunamadı.', 'error');
		admin_redirect('index.php?module=vip_membership-orders');
	}

	if($order['status'] != 'pending' && $order['status'] != 'review')
	{
		flash_message('Bu sipariş zaten işleme alınmış.', 'error');
		admin_redirect('index.php?module=vip_membership-orders');
	}

	if($action == 'approve')
	{
		vip_membership_grant($order, (int)$order['days'], (int)$mybb->user['uid']);
		flash_message('Ödeme onaylandı, üye VIP grubuna alındı.', 'success');
	}
	else
	{
		$db->update_query('vip_orders', array(
			'status' => 'rejected',
			'admin_note' => $db->escape_string($mybb->get_input('admin_note')),
			'handled_by' => (int)$mybb->user['uid'],
			'handled_at' => TIME_NOW,
		), "oid='{$oid}'");
		flash_message('Ödeme reddedildi.', 'success');
	}

	admin_redirect('index.php?module=vip_membership-orders');
}

/* ---------------------------------------------------------------- list --- */

$page->output_header('VIP Ödeme Kuyruğu');

$table = new Table;
$table->construct_header('Üye', array('width' => '16%'));
$table->construct_header('Plan');
$table->construct_header('Tutar', array('class' => 'align_center', 'width' => '11%'));
$table->construct_header('TXID', array('width' => '20%'));
$table->construct_header('Tarih', array('class' => 'align_center', 'width' => '11%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '10%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '14%'));

$status_labels = array(
	'pending' => array('Ödeme Bekliyor', '#f59e0b'),
	'review' => array('İncelemede', '#38bdf8'),
	'approved' => array('Onaylandı', '#22c55e'),
	'rejected' => array('Reddedildi', '#ef4444'),
	'expired' => array('Süresi Doldu', '#64748b'),
);

$query = $db->simple_select('vip_orders', '*', '', array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 200));

$count = 0;
while($order = $db->fetch_array($query))
{
	$count++;
	$row_oid = (int)$order['oid'];
	$label = isset($status_labels[$order['status']]) ? $status_labels[$order['status']] : array($order['status'], '#64748b');

	$profile = build_profile_link(htmlspecialchars_uni($order['username']), (int)$order['uid']);
	$txid = $order['txid'] ? '<code style="font-size:11px;word-break:break-all;">'.htmlspecialchars_uni(substr($order['txid'], 0, 24)).'&hellip;</code>' : '<em>girilmedi</em>';

	$table->construct_cell($profile);
	$table->construct_cell(htmlspecialchars_uni($order['title']).' <small>('.(int)$order['days'].' gün)</small>');
	$table->construct_cell(htmlspecialchars_uni($order['amount_exact'] ? $order['amount_exact'] : $order['amount']), array('class' => 'align_center'));
	$table->construct_cell($txid);
	$table->construct_cell(my_date('relative', (int)$order['dateline']), array('class' => 'align_center'));
	$table->construct_cell('<span style="color:'.$label[1].';font-weight:600;">'.htmlspecialchars_uni($label[0]).'</span>', array('class' => 'align_center'));

	if($order['status'] == 'pending' || $order['status'] == 'review')
	{
		$key = $mybb->post_code;
		$controls = '<a href="index.php?module=vip_membership-orders&amp;action=approve&amp;oid='.$row_oid.'&amp;my_post_key='.$key.'">Onayla</a>';
		$controls .= ' &middot; <a href="index.php?module=vip_membership-orders&amp;action=reject&amp;oid='.$row_oid.'&amp;my_post_key='.$key.'">Reddet</a>';
		$table->construct_cell($controls, array('class' => 'align_center'));
	}
	elseif($order['status'] == 'approved')
	{
		$until = $order['paid_until'] ? my_date($mybb->settings['dateformat'], (int)$order['paid_until']) : '-';
		$table->construct_cell('Bitiş:<br />'.$until, array('class' => 'align_center'));
	}
	else
	{
		$table->construct_cell('-', array('class' => 'align_center'));
	}

	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Kayıtlı ödeme yok.', array('colspan' => 7, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('Ödeme Kuyruğu');
$page->output_footer();