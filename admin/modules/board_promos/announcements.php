<?php
/**
 * ACP: manage the index announcement strip.
 *
 * An announcement can carry a start and an end date, which is what lets a
 * campaign be scheduled in advance and retire itself without anyone remembering
 * to switch it off.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Duyuru & Reklam', 'index.php?module=board_promos');
$page->add_breadcrumb_item('Duyurular', 'index.php?module=board_promos-announcements');

$action = $mybb->get_input('action');
$aid = $mybb->get_input('aid', MyBB::INPUT_INT);

if($mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $title = trim($mybb->get_input('title'));
        $body = trim($mybb->get_input('body'));
        $icon = trim($mybb->get_input('icon'));
        $accent = trim($mybb->get_input('accent'));
        $link_url = trim($mybb->get_input('link_url'));
        $link_text = trim($mybb->get_input('link_text'));
        $sticky = $mybb->get_input('sticky', MyBB::INPUT_INT) ? 1 : 0;
        $active = $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0;
        $disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);

        $errors = array();

        if($title === '')
        {
                $errors[] = 'Başlık boş olamaz.';
        }

        if($icon !== '' && !preg_match('/^fa-[a-z0-9 -]+$/i', $icon))
        {
                $errors[] = 'İkon bir FontAwesome sınıfı olmalı. Örn: fa-solid fa-bullhorn';
        }

        if($accent !== '' && !preg_match('/^#[0-9a-f]{3,6}$/i', $accent))
        {
                $errors[] = 'Vurgu rengi #rrggbb biçiminde olmalı.';
        }

        // A link with no label, or a label with no link, renders as a dead end.
        if($link_url !== '' && board_promos_safe_url($link_url) === '')
        {
                $errors[] = 'Bağlantı adresi geçersiz. http(s):// ile başlamalı ya da site içi bir yol olmalı.';
        }
        if(($link_url === '') !== ($link_text === ''))
        {
                $errors[] = 'Bağlantı adresi ve bağlantı metni birlikte doldurulmalı.';
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
                        'body' => $db->escape_string($body),
                        'icon' => $db->escape_string($icon),
                        'accent' => $db->escape_string($accent),
                        'link_url' => $db->escape_string($link_url),
                        'link_text' => $db->escape_string(my_substr($link_text, 0, 60)),
                        'sticky' => $sticky,
                        'active' => $active,
                        'start_date' => (int)$start,
                        'end_date' => (int)$end,
                        'disporder' => $disporder,
                );

                if($action == 'edit' && $aid)
                {
                        $db->update_query('promo_announcements', $fields, "aid='{$aid}'");
                        flash_message('Duyuru güncellendi.', 'success');
                }
                else
                {
                        $fields['dateline'] = TIME_NOW;
                        $db->insert_query('promo_announcements', $fields);
                        flash_message('Duyuru eklendi.', 'success');
                }
                admin_redirect('index.php?module=board_promos-announcements');
        }
}

if($action == 'delete' && $aid)
{
        verify_post_check($mybb->get_input('my_post_key'));
        $db->delete_query('promo_announcements', "aid='{$aid}'");
        flash_message('Duyuru silindi.', 'success');
        admin_redirect('index.php?module=board_promos-announcements');
}

$page->output_header('Duyurular');

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
                'title' => '', 'body' => '', 'icon' => 'fa-solid fa-bullhorn', 'accent' => '#f97316',
                'link_url' => '', 'link_text' => '', 'sticky' => 0, 'active' => 1,
                'start_date' => 0, 'end_date' => 0, 'disporder' => 0,
        );
        if($action == 'edit' && $aid)
        {
                $q = $db->simple_select('promo_announcements', '*', "aid='{$aid}'");
                $a = $db->fetch_array($q);
        }

        $fmt = function($ts) {
                return $ts > 0 ? date('Y-m-d', (int)$ts) : '';
        };

        $form = new Form('index.php?module=board_promos-announcements&amp;action='.$action.($aid ? '&amp;aid='.$aid : ''), 'post');
        $fc = new FormContainer($action == 'edit' ? 'Duyuruyu Düzenle' : 'Yeni Duyuru');

        $fc->output_row('Başlık', 'Kısa ve dikkat çekici olsun.', $form->generate_text_box('title', htmlspecialchars_uni($a['title']), array('maxlength' => 160)), 'title');
        $fc->output_row('Metin', 'Bir iki cümle yeterli; şerit tek satırda okunur.', $form->generate_text_area('body', htmlspecialchars_uni($a['body']), array('rows' => 4)), 'body');
        $fc->output_row('İkon', 'FontAwesome sınıfı. Örn: fa-solid fa-bullhorn', $form->generate_text_box('icon', htmlspecialchars_uni($a['icon'])), 'icon');
        $fc->output_row('Vurgu rengi', '#rrggbb biçiminde.', $form->generate_text_box('accent', htmlspecialchars_uni($a['accent'])), 'accent');
        $fc->output_row('Bağlantı adresi', 'İsteğe bağlı. Site içi için vip.php gibi bir yol da yazabilirsiniz.', $form->generate_text_box('link_url', htmlspecialchars_uni($a['link_url'])), 'link_url');
        $fc->output_row('Bağlantı metni', 'İsteğe bağlı. Bağlantı adresiyle birlikte doldurulmalı.', $form->generate_text_box('link_text', htmlspecialchars_uni($a['link_text']), array('maxlength' => 60)), 'link_text');
        $fc->output_row('Başlangıç', 'Boş bırakılırsa hemen yayına girer. Örn: 2026-09-20', $form->generate_text_box('start_date', $fmt($a['start_date'])), 'start_date');
        $fc->output_row('Bitiş', 'Boş bırakılırsa süresiz kalır.', $form->generate_text_box('end_date', $fmt($a['end_date'])), 'end_date');
        $fc->output_row('Sabit', 'Sabit duyurular şeritte ilk sırada ve daha görünür gösterilir.', $form->generate_yes_no_radio('sticky', (int)$a['sticky']));
        $fc->output_row('Sıra', '', $form->generate_text_box('disporder', (int)$a['disporder']), 'disporder');
        $fc->output_row('Aktif', 'Kapalı duyurular ana sayfada hiç görünmez.', $form->generate_yes_no_radio('active', (int)$a['active']));
        $fc->end();

        $buttons[] = $form->generate_submit_button('Kaydet');
        $form->output_submit_wrapper($buttons);
        $form->end();
        $page->output_footer();
}

$table = new Table;
$table->construct_header('Başlık');
$table->construct_header('Yayın aralığı', array('class' => 'align_center'));
$table->construct_header('Sabit', array('class' => 'align_center'));
$table->construct_header('Durum', array('class' => 'align_center'));
$table->construct_header('Sıra', array('class' => 'align_center'));
$table->construct_header('İşlem', array('class' => 'align_center'));

$key = $mybb->post_code;
$now = TIME_NOW;
$query = $db->simple_select('promo_announcements', '*', '', array('order_by' => 'sticky DESC, disporder', 'order_dir' => 'ASC'));

while($a = $db->fetch_array($query))
{
        $a_aid = (int)$a['aid'];

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

        $from = (int)$a['start_date'] > 0 ? date('Y-m-d', (int)$a['start_date']) : 'hemen';
        $to = (int)$a['end_date'] > 0 ? date('Y-m-d', (int)$a['end_date']) : 'süresiz';

        $title = '<strong>'.htmlspecialchars_uni($a['title']).'</strong>';
        if(trim((string)$a['body']) !== '')
        {
                $title .= '<div class="smalltext">'.htmlspecialchars_uni(my_substr($a['body'], 0, 90)).'</div>';
        }

        $table->construct_cell($title);
        $table->construct_cell(htmlspecialchars_uni($from).' &rarr; '.htmlspecialchars_uni($to), array('class' => 'align_center'));
        $table->construct_cell((int)$a['sticky'] ? 'Evet' : '&ndash;', array('class' => 'align_center'));
        $table->construct_cell($status, array('class' => 'align_center'));
        $table->construct_cell((int)$a['disporder'], array('class' => 'align_center'));
        $table->construct_cell('<a href="index.php?module=board_promos-announcements&amp;action=edit&amp;aid='.$a_aid.'">Düzenle</a> &middot; <a href="index.php?module=board_promos-announcements&amp;action=delete&amp;aid='.$a_aid.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu duyuru silinsin mi?\');">Sil</a>', array('class' => 'align_center'));
        $table->construct_row();
}

if($table->num_rows() == 0)
{
        $table->construct_cell('Henüz duyuru yok. Ana sayfa şeridi boş kalır.', array('colspan' => 6));
        $table->construct_row();
}

$table->output('Duyurular');

$form = new Form('index.php?module=board_promos-announcements&amp;action=add', 'post');
$buttons[] = $form->generate_submit_button('Yeni Duyuru Ekle');
$form->output_submit_wrapper($buttons);
$form->end();

$page->output_footer();
