<?php
/**
 * ACP: incoming sponsor requests.
 *
 * These arrive from the public form, so everything here is untrusted text. It is
 * escaped on output and never rendered as HTML.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Duyuru & Reklam', 'index.php?module=board_promos');
$page->add_breadcrumb_item('Sponsor Başvuruları', 'index.php?module=board_promos-requests');

$action = $mybb->get_input('action');
$rid = $mybb->get_input('rid', MyBB::INPUT_INT);

$statuses = array(
        'new' => 'Yeni',
        'contacted' => 'Görüşülüyor',
        'won' => 'Anlaşıldı',
        'lost' => 'Vazgeçildi',
);

if($action == 'status' && $rid && $mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $status = $mybb->get_input('status');
        if(!isset($statuses[$status]))
        {
                flash_message('Geçersiz durum.', 'error');
                admin_redirect('index.php?module=board_promos-requests');
        }

        $db->update_query('promo_sponsor_requests', array(
                'status' => $db->escape_string($status),
                'handled_by' => (int)$mybb->user['uid'],
                'handled_at' => TIME_NOW,
        ), "rid='{$rid}'");

        flash_message('Başvuru durumu güncellendi.', 'success');
        admin_redirect('index.php?module=board_promos-requests');
}

if($action == 'delete' && $rid)
{
        verify_post_check($mybb->get_input('my_post_key'));
        $db->delete_query('promo_sponsor_requests', "rid='{$rid}'");
        flash_message('Başvuru silindi.', 'success');
        admin_redirect('index.php?module=board_promos-requests');
}

$page->output_header('Sponsor Başvuruları');

// A request that has been answered still deserves to be visible, but the ones
// waiting on a reply are what the screen leads with.
$filter = $mybb->get_input('filter');
if(!isset($statuses[$filter]))
{
        $filter = '';
}

$notice = '<p>Yeni başvurular burada listelenir; aynı zamanda yönetim ana sayfasında ve özel mesajla bildirilir. '
        . 'Durum değiştirdiğinizde başvuru kaydı kimin ne zaman ilgilendiğini saklar.</p>';
$page->output_inline_message($notice);

$table = new Table;
$table->construct_header('Başvuru');
$table->construct_header('İletişim', array('class' => 'align_center'));
$table->construct_header('Bütçe', array('class' => 'align_center'));
$table->construct_header('Tarih', array('class' => 'align_center'));
$table->construct_header('Durum', array('class' => 'align_center'));
$table->construct_header('İşlem', array('class' => 'align_center'));

$key = $mybb->post_code;
$where = $filter !== '' ? "status='".$db->escape_string($filter)."'" : '';
$query = $db->simple_select('promo_sponsor_requests', '*', $where, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 200));

while($r = $db->fetch_array($query))
{
        $r_id = (int)$r['rid'];

        $message = nl2br(htmlspecialchars_uni($r['message']));
        $body = '<strong>'.htmlspecialchars_uni($r['name']).'</strong>';
        if(trim((string)$r['company']) !== '')
        {
                $body .= ' <span class="smalltext">'.htmlspecialchars_uni($r['company']).'</span>';
        }
        $body .= '<div class="smalltext" style="margin-top:6px;max-width:520px;">'.$message.'</div>';

        $table->construct_cell($body);
        $table->construct_cell('<a href="mailto:'.htmlspecialchars_uni($r['email']).'">'.htmlspecialchars_uni($r['email']).'</a>', array('class' => 'align_center'));
        $table->construct_cell($r['budget'] !== '' ? htmlspecialchars_uni($r['budget']) : '&ndash;', array('class' => 'align_center'));
        $table->construct_cell(my_date($mybb->settings['dateformat'], (int)$r['dateline'], '', false), array('class' => 'align_center'));

        $status_label = isset($statuses[$r['status']]) ? $statuses[$r['status']] : $r['status'];
        $status_html = '<strong>'.htmlspecialchars_uni($status_label).'</strong>';
        if((int)$r['handled_by'] > 0 && (int)$r['handled_at'] > 0)
        {
                $handled = $db->fetch_field($db->simple_select('users', 'username', "uid='".(int)$r['handled_by']."'"), 'username');
                $status_html .= '<div class="smalltext">'.htmlspecialchars_uni((string)$handled).' &middot; '
                        . htmlspecialchars_uni(my_date($mybb->settings['dateformat'], (int)$r['handled_at'], '', false)).'</div>';
        }
        $table->construct_cell($status_html, array('class' => 'align_center'));

        $form = new Form('index.php?module=board_promos-requests&amp;action=status&amp;rid='.$r_id, 'post');
        $select = '<select name="status">';
        foreach($statuses as $value => $label)
        {
                $sel = ((string)$value === (string)$r['status']) ? ' selected="selected"' : '';
                $select .= '<option value="'.htmlspecialchars_uni($value).'"'.$sel.'>'.htmlspecialchars_uni($label).'</option>';
        }
        $select .= '</select>';

        $actions = $form->generate_hidden_field('my_post_key', $key)
                . $select.' '.$form->generate_submit_button('Kaydet')
                . ' &middot; <a href="index.php?module=board_promos-requests&amp;action=delete&amp;rid='.$r_id.'&amp;my_post_key='.$key.'" onclick="return confirm(\'Bu başvuru silinsin mi?\');">Sil</a>';

        $table->construct_cell($actions, array('class' => 'align_center'));
        $table->construct_row();
}

if($table->num_rows() == 0)
{
        $table->construct_cell('Başvuru yok.', array('colspan' => 6));
        $table->construct_row();
}

$table->output($filter !== '' ? 'Başvurular &mdash; '.$statuses[$filter] : 'Tüm Başvurular');

$page->output_footer();
