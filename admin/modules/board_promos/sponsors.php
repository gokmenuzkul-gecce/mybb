<?php
/**
 * ACP: sponsor listings and their affiliate codes.
 *
 * A sponsor row is both a public listing and the attribution point for an
 * affiliate link, which is why the click total is shown next to it here.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Duyuru & Reklam', 'index.php?module=board_promos');
$page->add_breadcrumb_item('Sponsorlar', 'index.php?module=board_promos-sponsors');

$action = $mybb->get_input('action');
$sid = $mybb->get_input('sid', MyBB::INPUT_INT);

if($mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $name = trim($mybb->get_input('name'));
        $tier = trim($mybb->get_input('tier'));
        $description = trim($mybb->get_input('description'));
        $logo_url = trim($mybb->get_input('logo_url'));
        $url = trim($mybb->get_input('url'));
        $affiliate_code = trim($mybb->get_input('affiliate_code'));
        $active = $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0;
        $disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);

        $errors = array();

        if($name === '')
        {
                $errors[] = 'Sponsor adı boş olamaz.';
        }
        if($logo_url !== '' && board_promos_safe_url($logo_url) === '')
        {
                $errors[] = 'Logo adresi geçersiz.';
        }
        if($url !== '' && board_promos_safe_url($url) === '')
        {
                $errors[] = 'Hedef bağlantı geçersiz.';
        }
        if($affiliate_code !== '' && $url === '')
        {
                $errors[] = 'Affiliate kodu için önce hedef bağlantı girilmeli.';
        }

        if(!$errors)
        {
                $fields = array(
                        'name' => $db->escape_string(my_substr($name, 0, 160)),
                        'tier' => $db->escape_string(my_substr($tier, 0, 60)),
                        'description' => $db->escape_string($description),
                        'logo_url' => $db->escape_string($logo_url),
                        'url' => $db->escape_string($url),
                        'affiliate_code' => $db->escape_string(my_substr($affiliate_code, 0, 60)),
                        'active' => $active,
                        'disporder' => $disporder,
                );

                if($action == 'edit' && $sid)
                {
                        $db->update_query('promo_sponsors', $fields, "sid='{$sid}'");
                        flash_message('Sponsor güncellendi.', 'success');
                }
                else
                {
                        $fields['dateline'] = TIME_NOW;
                        $db->insert_query('promo_sponsors', $fields);
                        flash_message('Sponsor eklendi.', 'success');
                }
                admin_redirect('index.php?module=board_promos-sponsors');
        }
}

if($action == 'delete' && $sid)
{
        verify_post_check($mybb->get_input('my_post_key'));
        $db->delete_query('promo_sponsors', "sid='{$sid}'");
        flash_message('Sponsor silindi.', 'success');
        admin_redirect('index.php?module=board_promos-sponsors');
}

$page->output_header('Sponsorlar');

if($errors)
{
        foreach($errors as $e)
        {
                $page->output_error('<p>'.htmlspecialchars_uni($e).'</p>');
        }
}

if($action == 'add' || $action == 'edit')
{
        $s = array(
                'name' => '', 'tier' => '', 'description' => '', 'logo_url' => '',
                'url' => '', 'affiliate_code' => '', 'active' => 1, 'disporder' => 0,
        );
        if($action == 'edit' && $sid)
        {
                $q = $db->simple_select('promo_sponsors', '*', "sid='{$sid}'");
                $s = $db->fetch_array($q);
        }

        $form = new Form('index.php?module=board_promos-sponsors&amp;action='.$action.($sid ? '&amp;sid='.$sid : ''), 'post');
        $fc = new FormContainer($action == 'edit' ? 'Sponsoru Düzenle' : 'Yeni Sponsor');

        $fc->output_row('Sponsor adı', 'Herkesin göreceği ad.', $form->generate_text_box('name', htmlspecialchars_uni($s['name']), array('maxlength' => 160)), 'name');
        $fc->output_row('Seviye', 'İsteğe bağlı. Örn: Altın Sponsor.', $form->generate_text_box('tier', htmlspecialchars_uni($s['tier']), array('maxlength' => 60)), 'tier');
        $fc->output_row('Açıklama', 'Sponsor duvarında görünür.', $form->generate_text_area('description', htmlspecialchars_uni($s['description']), array('rows' => 3)), 'description');
        $fc->output_row('Logo adresi', 'Boş bırakılırsa adın baş harfi gösterilir.', $form->generate_text_box('logo_url', htmlspecialchars_uni($s['logo_url'])), 'logo_url');
        $fc->output_row('Hedef bağlantı', 'Ziyaretçinin yönlendirileceği adres.', $form->generate_text_box('url', htmlspecialchars_uni($s['url'])), 'url');
        $fc->output_row('Affiliate kodu', 'Bağlantıya ?ref=... olarak eklenir. Adreste {code} geçiyorsa oraya yazılır. Boş bırakılırsa eklenmez.', $form->generate_text_box('affiliate_code', htmlspecialchars_uni($s['affiliate_code']), array('maxlength' => 60)), 'affiliate_code');
        $fc->output_row('Sıra', '', $form->generate_text_box('disporder', (int)$s['disporder']), 'disporder');
        $fc->output_row('Aktif', 'Kapalı sponsorlar listede görünmez ve yönlendirme çalışmaz.', $form->generate_yes_no_radio('active', (int)$s['active']));
        $fc->end();

        $buttons[] = $form->generate_submit_button('Kaydet');
        $form->output_submit_wrapper($buttons);
        $form->end();
        $page->output_footer();
}

$table = new Table;
$table->construct_header('Sponsor');
$table->construct_header('Affiliate kodu', array('class' => 'align_center'));
$table->construct_header('Tıklama', array('class' => 'align_center'));
$table->construct_header('Durum', array('class' => 'align_center'));
$table->construct_header('İşlem', array('class' => 'align_center'));

$key = $mybb->post_code;
$query = $db->simple_select('promo_sponsors', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));

while($s = $db->fetch_array($query))
{
        $s_id = (int)$s['sid'];

        // Click totals come from the log table rather than a denormalised counter,
        // so they stay correct if the log is pruned or the code changes.
        $clicks = (int)$db->fetch_field(
                $db->simple_select('promo_clicks', 'COUNT(*) AS c', "kind='sponsor' AND refid='{$s_id}'"),
                'c'
        );

        $label = '<strong>'.htmlspecialchars_uni($s['name']).'</strong>';
        if(trim((string)$s['tier']) !== '')
        {
                $label .= '<div class="smalltext">'.htmlspecialchars_uni($s['tier']).'</div>';
        }

        $table->construct_cell($label);
        $table->construct_cell($s['affiliate_code'] !== '' ? htmlspecialchars_uni($s['affiliate_code']) : '&ndash;', array('class' => 'align_center'));
        $table->construct_cell($clicks, array('class' => 'align_center'));
        $table->construct_cell((int)$s['active'] ? '<span style="color:#22c55e;">Aktif</span>' : '<span style="color:#ef4444;">Kapalı</span>', array('class' => 'align_center'));
        $table->construct_cell('<a href="index.php?module=board_promos-sponsors&amp;action=edit&amp;sid='.$s_id.'">Düzenle</a> &middot; <a href="index.php?module=board_promos-sponsors&amp;action=delete&amp;sid='.$s_id.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu sponsor silinsin mi?\');">Sil</a>', array('class' => 'align_center'));
        $table->construct_row();
}

if($table->num_rows() == 0)
{
        $table->construct_cell('Henüz sponsor yok.', array('colspan' => 5));
        $table->construct_row();
}

$table->output('Sponsorlar');

$form = new Form('index.php?module=board_promos-sponsors&amp;action=add', 'post');
$buttons[] = $form->generate_submit_button('Yeni Sponsor Ekle');
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();
