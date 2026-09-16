<?php
/**
 * ACP: manage ad placements.
 *
 * Each ad is bound to a named slot rather than a raw HTML position, so the theme
 * can be restructured without re-entering every campaign. The slot list here is
 * the one board_promos_ads() knows how to fill.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Duyuru & Reklam', 'index.php?module=board_promos');
$page->add_breadcrumb_item('Reklamlar', 'index.php?module=board_promos-ads');

$action = $mybb->get_input('action');
$adid = $mybb->get_input('adid', MyBB::INPUT_INT);

$placements = array(
        'index_top' => 'Ana sayfa &mdash; hero altı (geniş)',
        'index_mid' => 'Ana sayfa &mdash; forum listesi altı',
        'index_bottom' => 'Ana sayfa &mdash; sayfa sonu',
        'global_footer' => 'Tüm sayfalar &mdash; footer üstü',
);

$sizes = array(
        'leaderboard' => 'Leaderboard (geniş yatay)',
        'rectangle' => 'Dikdörtgen (kutu)',
        'skyscraper' => 'Dikey (uzun)',
        'inline' => 'Satır içi (akışkan)',
        'fluid' => 'Serbest genişlik',
        'button' => 'Buton',
);

$types = array(
        'text' => 'Yazılı',
        'image' => 'Görsel',
        'button' => 'Butonlu',
);

/**
 * Render a <select> with the row's current value preselected.
 */
function board_promos_select($name, $map, $current)
{
        $out = '<select name="'.$name.'" id="'.$name.'">';
        foreach($map as $value => $label)
        {
                $sel = ((string)$value === (string)$current) ? ' selected="selected"' : '';
                $out .= '<option value="'.htmlspecialchars_uni($value).'"'.$sel.'>'.htmlspecialchars_uni(strip_tags($label)).'</option>';
        }
        return $out.'</select>';
}

if($mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $title = trim($mybb->get_input('title'));
        $type = $mybb->get_input('type');
        $placement = $mybb->get_input('placement');
        $size = $mybb->get_input('size');
        $body = trim($mybb->get_input('body'));
        $image_url = trim($mybb->get_input('image_url'));
        $link_url = trim($mybb->get_input('link_url'));
        $button_text = trim($mybb->get_input('button_text'));
        $active = $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0;
        $disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);

        $errors = array();

        if($title === '')
        {
                $errors[] = 'Reklam adı boş olamaz (bu ad yalnızca panelde görünür).';
        }
        if(!isset($types[$type]))
        {
                $errors[] = 'Reklam türü geçersiz.';
        }
        if(!isset($placements[$placement]))
        {
                $errors[] = 'Yerleşim geçersiz.';
        }
        if(!isset($sizes[$size]))
        {
                $errors[] = 'Boyut geçersiz.';
        }

        // Each type has one field it cannot do without; catch it here rather than
        // shipping an empty box to the homepage.
        if($type === 'image' && $image_url === '')
        {
                $errors[] = 'Görsel reklam için görsel adresi gerekli.';
        }
        if($type === 'text' && $body === '')
        {
                $errors[] = 'Yazılı reklam için metin gerekli.';
        }
        if($type === 'button' && $button_text === '')
        {
                $errors[] = 'Butonlu reklam için buton metni gerekli.';
        }
        if($image_url !== '' && board_promos_safe_url($image_url) === '')
        {
                $errors[] = 'Görsel adresi geçersiz.';
        }
        if($link_url !== '' && board_promos_safe_url($link_url) === '')
        {
                $errors[] = 'Hedef bağlantı geçersiz.';
        }

        $start = board_promos_parse_date($mybb->get_input('start_date'));
        $end = board_promos_parse_date($mybb->get_input('end_date'));
        if($start === false)
        {
                $errors[] = 'Başlangıç tarihi anlaşılamadı. Örn: 2026-09-20';
        }
        if($end === false)
        {
                $errors[] = 'Bitiş tarihi anlaşılamadı. Örn: 2026-10-01';
        }
        if($start !== false && $end !== false && $start > 0 && $end > 0 && $end < $start)
        {
                $errors[] = 'Bitiş tarihi başlangıçtan önce olamaz.';
        }

        if(!$errors)
        {
                $fields = array(
                        'title' => $db->escape_string(my_substr($title, 0, 160)),
                        'type' => $db->escape_string($type),
                        'placement' => $db->escape_string($placement),
                        'size' => $db->escape_string($size),
                        'body' => $db->escape_string($body),
                        'image_url' => $db->escape_string($image_url),
                        'link_url' => $db->escape_string($link_url),
                        'button_text' => $db->escape_string(my_substr($button_text, 0, 60)),
                        'active' => $active,
                        'start_date' => (int)$start,
                        'end_date' => (int)$end,
                        'disporder' => $disporder,
                );

                if($action == 'edit' && $adid)
                {
                        $db->update_query('promo_ads', $fields, "adid='{$adid}'");
                        flash_message('Reklam güncellendi.', 'success');
                }
                else
                {
                        $fields['dateline'] = TIME_NOW;
                        $db->insert_query('promo_ads', $fields);
                        flash_message('Reklam eklendi.', 'success');
                }
                admin_redirect('index.php?module=board_promos-ads');
        }
}

if($action == 'delete' && $adid)
{
        verify_post_check($mybb->get_input('my_post_key'));
        $db->delete_query('promo_ads', "adid='{$adid}'");
        flash_message('Reklam silindi.', 'success');
        admin_redirect('index.php?module=board_promos-ads');
}

$page->output_header('Reklamlar');

if($errors)
{
        foreach($errors as $e)
        {
                $page->output_error('<p>'.htmlspecialchars_uni($e).'</p>');
        }
}

if($action == 'add' || $action == 'edit')
{
        $a = array(
                'title' => '', 'type' => 'text', 'placement' => 'index_mid', 'size' => 'fluid',
                'body' => '', 'image_url' => '', 'link_url' => '', 'button_text' => '',
                'active' => 1, 'start_date' => 0, 'end_date' => 0, 'disporder' => 0,
        );
        if($action == 'edit' && $adid)
        {
                $q = $db->simple_select('promo_ads', '*', "adid='{$adid}'");
                $a = $db->fetch_array($q);
        }

        $fmt = function($ts) {
                return $ts > 0 ? date('Y-m-d', (int)$ts) : '';
        };

        $form = new Form('index.php?module=board_promos-ads&amp;action='.$action.($adid ? '&amp;adid='.$adid : ''), 'post');
        $fc = new FormContainer($action == 'edit' ? 'Reklamı Düzenle' : 'Yeni Reklam');

        $fc->output_row('Reklam adı', 'Yalnızca panelde görünür. Örn: Eylül bülten sponsorluğu.', $form->generate_text_box('title', htmlspecialchars_uni($a['title']), array('maxlength' => 160)), 'title');
        $fc->output_row('Tür', 'Görsel, yazılı ya da butonlu.', board_promos_select('type', $types, $a['type']));
        $fc->output_row('Yerleşim', 'Reklamın sayfada çıkacağı alan.', board_promos_select('placement', $placements, $a['placement']));
        $fc->output_row('Boyut', 'Alanın görsel biçimi.', board_promos_select('size', $sizes, $a['size']));
        $fc->output_row('Metin', 'Yazılı ve butonlu reklamlarda gösterilir.', $form->generate_text_area('body', htmlspecialchars_uni($a['body']), array('rows' => 3)), 'body');
        $fc->output_row('Görsel adresi', 'Tam adres ya da site içi yol. Örn: images/sponsor.png', $form->generate_text_box('image_url', htmlspecialchars_uni($a['image_url'])), 'image_url');
        $fc->output_row('Buton metni', 'Butonlu reklamlarda zorunlu. Örn: Hemen İncele', $form->generate_text_box('button_text', htmlspecialchars_uni($a['button_text']), array('maxlength' => 60)), 'button_text');
        $fc->output_row('Hedef bağlantı', 'Tıklamalar sayaçtan geçer, böylece raporlanabilir.', $form->generate_text_box('link_url', htmlspecialchars_uni($a['link_url'])), 'link_url');
        $fc->output_row('Başlangıç', 'Boşsa hemen yayına girer.', $form->generate_text_box('start_date', $fmt($a['start_date'])), 'start_date');
        $fc->output_row('Bitiş', 'Boşsa süresiz kalır.', $form->generate_text_box('end_date', $fmt($a['end_date'])), 'end_date');
        $fc->output_row('Sıra', '', $form->generate_text_box('disporder', (int)$a['disporder']), 'disporder');
        $fc->output_row('Aktif', 'Kapalı reklamlar gösterilmez.', $form->generate_yes_no_radio('active', (int)$a['active']));
        $fc->end();

        $buttons[] = $form->generate_submit_button('Kaydet');
        $form->output_submit_wrapper($buttons);
        $form->end();
        $page->output_footer();
}

$table = new Table;
$table->construct_header('Reklam');
$table->construct_header('Yerleşim', array('class' => 'align_center'));
$table->construct_header('Boyut', array('class' => 'align_center'));
$table->construct_header('Tıklama', array('class' => 'align_center'));
$table->construct_header('Durum', array('class' => 'align_center'));
$table->construct_header('İşlem', array('class' => 'align_center'));

$key = $mybb->post_code;
$now = TIME_NOW;
$query = $db->simple_select('promo_ads', '*', '', array('order_by' => 'placement, disporder', 'order_dir' => 'ASC'));

while($a = $db->fetch_array($query))
{
        $a_id = (int)$a['adid'];

        if((int)$a['active'] != 1)
        {
                $status = '<span style="color:#ef4444;">Kapalı</span>';
        }
        elseif((int)$a['start_date'] > 0 && (int)$a['start_date'] > $now)
        {
                $status = '<span style="color:#f59e0b;">Planlandı</span>';
        }
        elseif((int)$a['end_date'] > 0 && (int)$a['end_date'] < $now)
        {
                $status = '<span style="color:#f59e0b;">Süresi geçti</span>';
        }
        else
        {
                $status = '<span style="color:#22c55e;">Yayında</span>';
        }

        $label = '<strong>'.htmlspecialchars_uni($a['title']).'</strong>'
                . '<div class="smalltext">'.htmlspecialchars_uni(isset($types[$a['type']]) ? $types[$a['type']] : $a['type']).'</div>';

        $table->construct_cell($label);
        $table->construct_cell(htmlspecialchars_uni(isset($placements[$a['placement']]) ? strip_tags($placements[$a['placement']]) : $a['placement']), array('class' => 'align_center'));
        $table->construct_cell(htmlspecialchars_uni(isset($sizes[$a['size']]) ? $sizes[$a['size']] : $a['size']), array('class' => 'align_center'));
        $table->construct_cell((int)$a['clicks'], array('class' => 'align_center'));
        $table->construct_cell($status, array('class' => 'align_center'));
        $table->construct_cell('<a href="index.php?module=board_promos-ads&amp;action=edit&amp;adid='.$a_id.'">Düzenle</a> &middot; <a href="index.php?module=board_promos-ads&amp;action=delete&amp;adid='.$a_id.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu reklam silinsin mi?\');">Sil</a>', array('class' => 'align_center'));
        $table->construct_row();
}

if($table->num_rows() == 0)
{
        $table->construct_cell('Henüz reklam yok. Boş alanlarda sponsorluk daveti gösterilir.', array('colspan' => 6));
        $table->construct_row();
}

$table->output('Reklam Alanları');

$form = new Form('index.php?module=board_promos-ads&amp;action=add', 'post');
$buttons[] = $form->generate_submit_button('Yeni Reklam Ekle');
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();
