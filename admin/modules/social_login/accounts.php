<?php
/**
 * ACP: accounts linked through a social provider.
 *
 * An admin needs this to help a locked-out member and to spot one identity
 * claimed by two local accounts. Unlinking only removes the social link; the
 * local account and its password are left alone, so the member keeps an
 * alternative way in.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Sosyal Giriş', 'index.php?module=social_login');
$page->add_breadcrumb_item('Bağlı Hesaplar', 'index.php?module=social_login-accounts');

$action = $mybb->get_input('action');
$iid = $mybb->get_input('iid', MyBB::INPUT_INT);

if($action == 'unlink')
{
	verify_post_check($mybb->get_input('my_post_key'));

	$identity = $db->fetch_array($db->simple_select('social_identities', '*', "iid='{$iid}'"));
	if($identity)
	{
		$db->delete_query('social_identities', "iid='{$iid}'");
		log_admin_action(array(
			'type' => 'social_login_unlink',
			'uid' => (int)$identity['uid'],
			'username' => $identity['identifier'],
		));
		flash_message('Bağlantı kaldırıldı. Yerel hesap ve şifresi etkilenmedi.', 'success');
	}
	else
	{
		flash_message('Bağlantı bulunamadı.', 'error');
	}

	admin_redirect('index.php?module=social_login-accounts');
}

$page->output_header('Bağlı Sosyal Hesaplar');

$providers = social_login_providers();
$titles = array();
foreach($providers as $key => $p)
{
	$titles[$key] = $p['title'];
}

$table = new Table;
$table->construct_header('Üye');
$table->construct_header('Sağlayıcı', array('class' => 'align_center', 'width' => '14%'));
$table->construct_header('Sağlayıcı e-postası');
$table->construct_header('Bağlanma', array('class' => 'align_center', 'width' => '16%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '12%'));

$query = $db->query("
	SELECT i.*, u.username
	FROM ".TABLE_PREFIX."social_identities i
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = i.uid)
	ORDER BY i.dateline DESC
");

$count = 0;
while($identity = $db->fetch_array($query))
{
	$count++;
	$row_iid = (int)$identity['iid'];
	$provider = isset($titles[$identity['provider']]) ? $titles[$identity['provider']] : $identity['provider'];

	$username = $identity['username'] ? htmlspecialchars_uni($identity['username']) : '<em>(silinmiş üye)</em>';

	$table->construct_cell($username.'<br /><small>uid: '.(int)$identity['uid'].'</small>');
	$table->construct_cell(htmlspecialchars_uni($provider), array('class' => 'align_center'));
	$table->construct_cell('<small>'.($identity['email'] ? htmlspecialchars_uni($identity['email']) : '<em>yok</em>').'</small>');
	$table->construct_cell(my_date('relative', (int)$identity['dateline']), array('class' => 'align_center'));
	$table->construct_cell(
		'<a href="index.php?module=social_login-accounts&amp;action=unlink&amp;iid='.$row_iid.'&amp;my_post_key='.$mybb->post_code.'">Bağlantıyı kaldır</a>',
		array('class' => 'align_center')
	);
	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Henüz hiçbir üye sosyal girişle hesabını bağlamamış.', array('colspan' => 5, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('Bağlı Hesaplar');

$page->output_footer();