<?php
/**
 * ACP: manage crypto payment networks (chain name, wallet address, explorer).
 *
 * The admin's own receiving addresses live here rather than in a single global
 * setting, so the board can accept the same coin on several chains and show the
 * member the right address for the one they actually paid from.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('VIP Üyelik', 'index.php?module=vip_membership');
$page->add_breadcrumb_item('Ödeme Ağları', 'index.php?module=vip_membership-networks');

$action = $mybb->get_input('action');
$nid = $mybb->get_input('nid', MyBB::INPUT_INT);

// A regex is stored as free text and later run against member input. Refuse
// anything that will not compile rather than saving a pattern that silently
// disables TXID validation for that network.
function vip_networks_regex_problem($regex)
{
	$regex = trim((string)$regex);
	if($regex === '')
	{
		return '';
	}
	if(@preg_match('/'.$regex.'/', '') === false)
	{
		return 'Geçersiz düzenli ifade. Örn: ^[0-9a-fA-F]{64}$';
	}
	return '';
}

if($mybb->request_method == 'post')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$name = trim($mybb->get_input('name'));
	$label = trim($mybb->get_input('label'));
	$wallet = trim($mybb->get_input('wallet'));
	$currency = trim($mybb->get_input('currency'));
	$explorer = trim($mybb->get_input('explorer'));
	$txid_hint = trim($mybb->get_input('txid_hint'));
	$txid_regex = trim($mybb->get_input('txid_regex'));
	$disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);
	$enabled = $mybb->get_input('enabled', MyBB::INPUT_INT) ? 1 : 0;

	$errors = array();
	if($name === '')
	{
		$errors[] = 'Ağ adı boş olamaz (örn. TRC-20).';
	}
	if($wallet === '')
	{
		$errors[] = 'Cüzdan adresi boş olamaz.';
	}
	if($currency === '')
	{
		$errors[] = 'Para birimi boş olamaz.';
	}

	$regex_problem = vip_networks_regex_problem($txid_regex);
	if($regex_problem !== '')
	{
		$errors[] = $regex_problem;
	}

	// Two rows sharing a name would make an order's stored network ambiguous,
	// so names are treated as the key members see.
	$dupe = $db->simple_select('vip_networks', 'nid', "name='".$db->escape_string($name)."' AND nid!='{$nid}'");
	if($db->num_rows($dupe))
	{
		$errors[] = 'Bu ağ adı zaten kullanılıyor.';
	}

	if(!$errors)
	{
		$fields = array(
			'name' => $db->escape_string($name),
			'label' => $db->escape_string($label),
			'wallet' => $db->escape_string($wallet),
			'currency' => $db->escape_string($currency),
			'explorer' => $db->escape_string($explorer),
			'txid_hint' => $db->escape_string($txid_hint),
			'txid_regex' => $db->escape_string($txid_regex),
			'disporder' => $disporder,
			'enabled' => $enabled,
		);

		if($action == 'edit' && $nid)
		{
			$db->update_query('vip_networks', $fields, "nid='{$nid}'");
			flash_message('Ödeme ağı güncellendi.', 'success');
		}
		else
		{
			$db->insert_query('vip_networks', $fields);
			flash_message('Ödeme ağı eklendi.', 'success');
		}
		admin_redirect('index.php?module=vip_membership-networks');
	}
}

if($action == 'delete' && $nid)
{
	verify_post_check($mybb->get_input('my_post_key'));

	// The last usable network is the difference between taking payments and
	// not, so refuse to remove it and say why.
	$remaining = 0;
	$chk = $db->simple_select('vip_networks', 'nid,wallet', "nid!='{$nid}'");
	while($row = $db->fetch_array($chk))
	{
		if(trim((string)$row['wallet']) !== '')
		{
			$remaining++;
		}
	}

	if($remaining === 0)
	{
		flash_message('Son geçerli ödeme ağı silinemez. Önce yeni bir ağ ekleyin.', 'error');
	}
	else
	{
		$db->delete_query('vip_networks', "nid='{$nid}'");
		flash_message('Ödeme ağı silindi.', 'success');
	}
	admin_redirect('index.php?module=vip_membership-networks');
}

$page->output_header('Ödeme Ağları');

if($errors)
{
	foreach($errors as $e)
	{
		$page->output_error('<p>'.htmlspecialchars_uni($e).'</p>');
	}
}

if($action == 'add' || $action == 'edit')
{
	$net = array(
		'name' => '', 'label' => '', 'wallet' => '', 'currency' => 'USDT',
		'explorer' => '', 'txid_hint' => '', 'txid_regex' => '',
		'disporder' => 0, 'enabled' => 1,
	);
	if($action == 'edit' && $nid)
	{
		$q = $db->simple_select('vip_networks', '*', "nid='{$nid}'");
		$net = $db->fetch_array($q);
	}

	$form = new Form('index.php?module=vip_membership-networks&amp;action='.$action.($nid ? '&amp;nid='.$nid : ''), 'post');
	$fc = new FormContainer($action == 'edit' ? 'Ödeme Ağını Düzenle' : 'Yeni Ödeme Ağı');

	$fc->output_row('Ağ adı', 'Kullanıcıya gösterilir. Örn. TRC-20, ERC-20, BEP-20.', $form->generate_text_box('name', htmlspecialchars_uni($net['name'])), 'name');
	$fc->output_row('Açıklama', 'Örn. "Tether (TRON ağı)".', $form->generate_text_box('label', htmlspecialchars_uni($net['label'])), 'label');
	$fc->output_row('Cüzdan adresi', 'Ödemelerin bu ağda gönderileceği adres.', $form->generate_text_box('wallet', htmlspecialchars_uni($net['wallet'])), 'wallet');
	$fc->output_row('Para birimi', 'Örn. USDT.', $form->generate_text_box('currency', htmlspecialchars_uni($net['currency'])), 'currency');
	$fc->output_row('Blok gezgini adresi', 'TXID\'nin sonuna eklenir. Örn. https://tronscan.org/#/transaction/', $form->generate_text_box('explorer', htmlspecialchars_uni($net['explorer'])), 'explorer');
	$fc->output_row('TXID ipucu', 'Ödeme formundaki örnek metin.', $form->generate_text_box('txid_hint', htmlspecialchars_uni($net['txid_hint'])), 'txid_hint');
	$fc->output_row('TXID deseni (regex)', 'Bu ağın işlem kimliği biçimi. Örn. TRON için ^[0-9a-fA-F]{64}$ , EVM için ^0x[0-9a-fA-F]{64}$ . Boş bırakılırsa biçim denetimi yapılmaz.', $form->generate_text_box('txid_regex', htmlspecialchars_uni($net['txid_regex'])), 'txid_regex');
	$fc->output_row('Sıra', '', $form->generate_text_box('disporder', (int)$net['disporder']), 'disporder');
	$fc->output_row('Aktif', 'Kapalı ağlar kullanıcıya gösterilmez.', $form->generate_yes_no_radio('enabled', (int)$net['enabled']));
	$fc->end();

	$buttons[] = $form->generate_submit_button('Kaydet');
	$form->output_submit_wrapper($buttons);
	$form->end();
	$page->output_footer();
	exit;
}

$table = new Table;
$table->construct_header('Ağ');
$table->construct_header('Cüzdan', array('width' => '28%'));
$table->construct_header('Para birimi', array('class' => 'align_center', 'width' => '10%'));
$table->construct_header('Sıra', array('class' => 'align_center', 'width' => '8%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '18%'));

$q = $db->simple_select('vip_networks', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
while($n = $db->fetch_array($q))
{
	$n_nid = (int)$n['nid'];
	$key = $mybb->post_code;
	$wallet = trim((string)$n['wallet']);
	$wallet_cell = $wallet === ''
		? '<span style="color:#ef4444;">Adres girilmemiş</span>'
		: '<code>'.htmlspecialchars_uni($wallet).'</code>';

	$title = '<strong>'.htmlspecialchars_uni($n['name']).'</strong>';
	if($n['label'] !== '')
	{
		$title .= '<br /><small>'.htmlspecialchars_uni($n['label']).'</small>';
	}

	// A row with no address cannot be used even though it is switched on, and
	// that combination is invisible otherwise.
	$usable = ($n['enabled'] && $wallet !== '');
	if($usable)
	{
		$status = '<span style="color:#22c55e;">Aktif</span>';
	}
	elseif(!$n['enabled'])
	{
		$status = '<span style="color:#ef4444;">Kapalı</span>';
	}
	else
	{
		$status = '<span style="color:#f59e0b;">Adres bekliyor</span>';
	}

	$table->construct_cell($title);
	$table->construct_cell($wallet_cell);
	$table->construct_cell(htmlspecialchars_uni($n['currency']), array('class' => 'align_center'));
	$table->construct_cell((int)$n['disporder'], array('class' => 'align_center'));
	$table->construct_cell($status, array('class' => 'align_center'));
	$table->construct_cell('<a href="index.php?module=vip_membership-networks&amp;action=edit&amp;nid='.$n_nid.'">Düzenle</a> &middot; <a href="index.php?module=vip_membership-networks&amp;action=delete&amp;nid='.$n_nid.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu ödeme ağı silinsin mi?\');">Sil</a>', array('class' => 'align_center'));
	$table->construct_row();
}

$table->output('Ödeme Ağları');

$form = new Form('index.php?module=vip_membership-networks&amp;action=add', 'post');
$buttons[] = $form->generate_submit_button('Yeni Ağ Ekle');
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();