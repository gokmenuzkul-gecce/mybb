<?php
/**
 * ACP: manage VIP plans (title, duration, price).
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('VIP Üyelik', 'index.php?module=vip_membership');
$page->add_breadcrumb_item('Planlar', 'index.php?module=vip_membership-plans');

$action = $mybb->get_input('action');
$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

if($mybb->request_method == 'post')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$title = trim($mybb->get_input('title'));
	$days = $mybb->get_input('days', MyBB::INPUT_INT);
	$price = (float)str_replace(',', '.', $mybb->get_input('price'));
	$disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);
	$enabled = $mybb->get_input('enabled', MyBB::INPUT_INT) ? 1 : 0;

	$errors = array();
	if($title === '')
	{
		$errors[] = 'Plan adı boş olamaz.';
	}
	if($days < 1)
	{
		$errors[] = 'Süre en az 1 gün olmalı.';
	}
	if($price < 0)
	{
		$errors[] = 'Fiyat negatif olamaz.';
	}

	if(!$errors)
	{
		$fields = array(
			'title' => $db->escape_string($title),
			'days' => $days,
			'price' => $price,
			'disporder' => $disporder,
			'enabled' => $enabled,
		);

		if($action == 'edit' && $pid)
		{
			$db->update_query('vip_plans', $fields, "pid='{$pid}'");
			flash_message('Plan güncellendi.', 'success');
		}
		else
		{
			$db->insert_query('vip_plans', $fields);
			flash_message('Plan eklendi.', 'success');
		}
		admin_redirect('index.php?module=vip_membership-plans');
	}
}

if($action == 'delete' && $pid)
{
	verify_post_check($mybb->get_input('my_post_key'));
	$db->delete_query('vip_plans', "pid='{$pid}'");
	flash_message('Plan silindi.', 'success');
	admin_redirect('index.php?module=vip_membership-plans');
}

$page->output_header('VIP Planları');

if($action == 'add' || $action == 'edit')
{
	$plan = array('title' => '', 'days' => 30, 'price' => '0.00', 'disporder' => 0, 'enabled' => 1);
	if($action == 'edit' && $pid)
	{
		$q = $db->simple_select('vip_plans', '*', "pid='{$pid}'");
		$plan = $db->fetch_array($q);
	}

	$form = new Form('index.php?module=vip_membership-plans&amp;action='.$action.($pid ? '&amp;pid='.$pid : ''), 'post');
	$fc = new FormContainer($action == 'edit' ? 'Planı Düzenle' : 'Yeni Plan');

	$fc->output_row('Plan adı', 'Örn. "1 Aylık VIP".', $form->generate_text_box('title', htmlspecialchars_uni($plan['title'])), 'title');
	$fc->output_row('Süre (gün)', '', $form->generate_text_box('days', (int)$plan['days']), 'days');
	$fc->output_row('Fiyat', 'USDT cinsinden. Kuruşlu fiyatlar ödeme eşleştirmesini kolaylaştırır.', $form->generate_text_box('price', $plan['price']), 'price');
	$fc->output_row('Sıra', '', $form->generate_text_box('disporder', (int)$plan['disporder']), 'disporder');
	$fc->output_row('Aktif', '', $form->generate_yes_no_radio('enabled', (int)$plan['enabled']));
	$fc->end();

	$buttons[] = $form->generate_submit_button('Kaydet');
	$form->output_submit_wrapper($buttons);
	$form->end();
	$page->output_footer();
	exit;
}

$table = new Table;
$table->construct_header('Plan');
$table->construct_header('Süre', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Fiyat', array('class' => 'align_center', 'width' => '15%'));
$table->construct_header('Sıra', array('class' => 'align_center', 'width' => '10%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '18%'));

$q = $db->simple_select('vip_plans', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
while($p = $db->fetch_array($q))
{
	$p_pid = (int)$p['pid'];
	$key = $mybb->post_code;
	$table->construct_cell('<strong>'.htmlspecialchars_uni($p['title']).'</strong>');
	$table->construct_cell((int)$p['days'].' gün', array('class' => 'align_center'));
	$table->construct_cell(number_format((float)$p['price'], 2).' USDT', array('class' => 'align_center'));
	$table->construct_cell((int)$p['disporder'], array('class' => 'align_center'));
	$table->construct_cell($p['enabled'] ? '<span style="color:#22c55e;">Aktif</span>' : '<span style="color:#ef4444;">Kapalı</span>', array('class' => 'align_center'));
	$table->construct_cell('<a href="index.php?module=vip_membership-plans&amp;action=edit&amp;pid='.$p_pid.'">Düzenle</a> &middot; <a href="index.php?module=vip_membership-plans&amp;action=delete&amp;pid='.$p_pid.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu plan silinsin mi?\');">Sil</a>', array('class' => 'align_center'));
	$table->construct_row();
}

$table->output('VIP Planları');

$form = new Form('index.php?module=vip_membership-plans&amp;action=add', 'post');
$buttons[] = $form->generate_submit_button('Yeni Plan Ekle');
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();