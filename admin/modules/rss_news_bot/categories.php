<?php
/**
 * ACP: the left-menu category list for the RSS bot.
 *
 * Each row is one category the board asked for, wired to the forum that holds
 * it. From here an admin can fetch a single category on demand, fetch every
 * category, fix the target forum, adjust the search query or the publisher
 * feeds, change the per-run cap, and switch a category off without losing it.
 *
 * Everything a fetch produces still goes to the approval queue, so this screen
 * never publishes on its own.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('RSS Haber Botu', 'index.php?module=rss_news_bot');
$page->add_breadcrumb_item('Kategoriler', 'index.php?module=rss_news_bot-categories');

$action = $mybb->get_input('action');
$cid = $mybb->get_input('cid', MyBB::INPUT_INT);

/**
 * Forum choices for the mapping dropdown, keyed by fid.
 */
function rss_news_bot_category_forum_options()
{
        global $db;

        $options = array(0 => 'Genel ayarı kullan');
        $query = $db->simple_select('forums', 'fid, name, type', "type='f'", array('order_by' => 'name'));
        while($f = $db->fetch_array($query))
        {
                $options[(int)$f['fid']] = htmlspecialchars_uni($f['name']);
        }

        return $options;
}

/**
 * The one place a category row is written from the ACP, so the field list and
 * escaping stay in a single spot.
 */
function rss_news_bot_category_save($cid, $input)
{
        global $db;

        $data = array(
                'title' => $db->escape_string(trim($input['title'])),
                'fid_forum' => (int)$input['fid_forum'],
                'query' => $db->escape_string(trim($input['query'])),
                'sources' => $db->escape_string(trim($input['sources'])),
                'per_run' => (int)$input['per_run'],
                'auto_post' => (int)$input['auto_post'] ? 1 : 0,
                'active' => (int)$input['active'] ? 1 : 0,
        );

        if($data['title'] === '')
        {
                return false;
        }

        if($data['per_run'] < 1)
        {
                $data['per_run'] = 7;
        }

        if($cid > 0)
        {
                $db->update_query('rss_categories', $data, 'cid='.(int)$cid);
        }

        return true;
}

/* ------------------------------------------------------------- actions --- */

if($action == 'save' && $mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        if(rss_news_bot_category_save($cid, array(
                'title' => $mybb->get_input('title'),
                'fid_forum' => $mybb->get_input('fid_forum', MyBB::INPUT_INT),
                'query' => $mybb->get_input('query'),
                'sources' => $mybb->get_input('sources'),
                'per_run' => $mybb->get_input('per_run', MyBB::INPUT_INT),
                'auto_post' => $mybb->get_input('auto_post', MyBB::INPUT_INT),
                'active' => $mybb->get_input('active', MyBB::INPUT_INT),
        )))
        {
                flash_message('Kategori güncellendi.', 'success');
        }
        else
        {
                flash_message('Kategori adı boş olamaz.', 'error');
        }

        admin_redirect('index.php?module=rss_news_bot-categories');
}

if($action == 'toggle' && $mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $cat = $db->fetch_array($db->simple_select('rss_categories', '*', "cid='{$cid}'"));
        if($cat)
        {
                $db->update_query('rss_categories', array('active' => (int)$cat['active'] ? 0 : 1), 'cid='.$cid);
                flash_message('Kategori durumu değiştirildi.', 'success');
        }

        admin_redirect('index.php?module=rss_news_bot-categories');
}

if($action == 'fetch' && $mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        $report = rss_news_bot_fetch_categories(true, $cid);

        $message = 'Kategori: '.(int)$report['categories'].', yeni haber: '.(int)$report['new'].'.';
        if(!empty($report['errors']))
        {
                $message .= ' Hatalar: '.implode(' | ', array_map('htmlspecialchars_uni', $report['errors']));
        }
        flash_message($message, $report['errors'] ? 'error' : 'success');

        admin_redirect('index.php?module=rss_news_bot-categories');
}

/* ---------------------------------------------------------------- edit --- */

if($action == 'edit' && $cid > 0)
{
        $cat = $db->fetch_array($db->simple_select('rss_categories', '*', "cid='{$cid}'"));
        if(!$cat)
        {
                flash_message('Kategori bulunamadı.', 'error');
                admin_redirect('index.php?module=rss_news_bot-categories');
        }

        $page->output_header('Kategori Düzenle: '.htmlspecialchars_uni($cat['title']));

        $form = new Form('index.php?module=rss_news_bot-categories&amp;action=save&amp;cid='.$cid, 'post');
        $container = new FormContainer('Kategori Ayarları');

        $container->output_row('Kategori adı', 'Menüde ve konu başlığında görünür.', $form->generate_text_box('title', htmlspecialchars_uni($cat['title']), array('style' => 'width:100%')));

        $container->output_row(
                'Açılacak forum',
                'Bu kategoriden gelen haberlerin açılacağı forum. VIP kategorileri kilitli VIP forumuna bağlıdır.',
                $form->generate_select_box('fid_forum', rss_news_bot_category_forum_options(), (int)$cat['fid_forum'])
        );

        $container->output_row('Google News arama sorgusu', 'Kategoriye özel haber araması. Boş bırakılırsa kategori adı kullanılır.', $form->generate_text_box('query', htmlspecialchars_uni($cat['query']), array('style' => 'width:100%')));

        $container->output_row('Ek kaynak beslemeler', 'Her satıra bir RSS adresi. Tam makale metni bu beslemelerden alınır.', $form->generate_text_area('sources', htmlspecialchars_uni($cat['sources']), array('style' => 'width:100%;height:90px;')));

        $container->output_row('Günlük haber sayısı', 'Bu kategoriden günde en fazla kaç haber çekilsin (varsayılan 7).', $form->generate_text_box('per_run', (int)$cat['per_run'], array('style' => 'width:80px')));

        $container->output_row('Otomatik yayınlama', 'Evet seçilirse çekilen haberler admin onayı beklenmeden doğrudan açılır. Kapalıysa onay kuyruğuna düşer.', $form->generate_select_box('auto_post', array(0 => 'Hayır (onay kuyruğu)', 1 => 'Evet (otomatik yayınla)'), (int)$cat['auto_post']));

        $container->output_row('Durum', 'Kapalı kategoriler otomatik çekime dahil edilmez.', $form->generate_select_box('active', array(1 => 'Aktif', 0 => 'Kapalı'), (int)$cat['active']));

        $container->end();

        $buttons = array($form->generate_submit_button('Kaydet'), $form->generate_reset_button('Sıfırla'));
        $form->output_submit_wrapper($buttons);
        $form->end();

        $page->output_footer();
        exit;
}

/* ---------------------------------------------------------------- list --- */

$page->output_header('RSS Kategorileri');

// The whole list is fetched from the queue too, so the counter and the table
// never disagree about what is waiting.
$form = new Form('index.php?module=rss_news_bot-categories&amp;action=fetch', 'post');
$buttons = array($form->generate_submit_button('Tüm Kategorileri Şimdi Çek'));
$form->output_submit_wrapper($buttons);
$form->end();

$fname = array();
$fquery = $db->simple_select('forums', 'fid, name', "type='f'");
while($f = $db->fetch_array($fquery))
{
        $fname[(int)$f['fid']] = $f['name'];
}

$table = new Table;
$table->construct_header('Kategori');
$table->construct_header('Forum', array('width' => '20%'));
$table->construct_header('Günlük', array('class' => 'align_center', 'width' => '6%'));
$table->construct_header('Kuyruk', array('class' => 'align_center', 'width' => '7%'));
$table->construct_header('Son çekim', array('class' => 'align_center', 'width' => '11%'));
$table->construct_header('Durum', array('class' => 'align_center', 'width' => '8%'));
$table->construct_header('İşlem', array('class' => 'align_center', 'width' => '20%'));

$query = $db->simple_select('rss_categories', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
$count = 0;

while($cat = $db->fetch_array($query))
{
        $count++;
        $row_cid = (int)$cat['cid'];
        $fid = (int)$cat['fid_forum'];

        $label = htmlspecialchars_uni($cat['title']);
        if((int)$cat['is_vip'])
        {
                $label .= ' <span style="color:#f59e0b;font-weight:600;">VIP</span>';
        }

        $pending = (int)$db->fetch_field($db->simple_select('rss_queue', 'COUNT(*) AS c', "cid='{$row_cid}' AND status='queued'"), 'c');

        $table->construct_cell($label);
        $table->construct_cell($fid > 0 && isset($fname[$fid]) ? htmlspecialchars_uni($fname[$fid]) : '<em>Genel ayar</em>');
        $table->construct_cell((int)$cat['per_run'], array('class' => 'align_center'));
        $table->construct_cell($pending > 0 ? '<a href="index.php?module=rss_news_bot-queue&amp;cid='.$row_cid.'">'.$pending.'</a>' : '0', array('class' => 'align_center'));
        $table->construct_cell((int)$cat['last_fetch'] > 0 ? my_date('relative', (int)$cat['last_fetch']) : '-', array('class' => 'align_center'));

        if((int)$cat['active'])
        {
                $status = '<span style="color:#22c55e;font-weight:600;">Aktif</span>';
        }
        else
        {
                $status = '<span style="color:#64748b;font-weight:600;">Kapalı</span>';
        }
        if($cat['last_error'] !== '')
        {
                $status .= '<br /><small style="color:#ef4444;">'.htmlspecialchars_uni(my_substr($cat['last_error'], 0, 60)).'</small>';
        }
        $table->construct_cell($status, array('class' => 'align_center'));

        $key = $mybb->post_code;
        $controls = '<a href="index.php?module=rss_news_bot-categories&amp;action=edit&amp;cid='.$row_cid.'">Düzenle</a>';

        // The fetch and toggle links are POST-only so a prefetching browser or a
        // stray click cannot queue a run or flip a category.
        $controls .= ' &middot; <form method="post" action="index.php?module=rss_news_bot-categories&amp;action=fetch&amp;cid='.$row_cid.'" style="display:inline">
                <input type="hidden" name="my_post_key" value="'.$key.'" />
                <input type="submit" class="submit_button" value="Çek" style="padding:0 6px;" />
        </form>';
        $controls .= ' <form method="post" action="index.php?module=rss_news_bot-categories&amp;action=toggle&amp;cid='.$row_cid.'" style="display:inline">
                <input type="hidden" name="my_post_key" value="'.$key.'" />
                <input type="submit" class="submit_button" value="'.((int)$cat['active'] ? 'Kapat' : 'Aç').'" style="padding:0 6px;" />
        </form>';

        $table->construct_cell($controls, array('class' => 'align_center'));

        $table->construct_row();
}

if($count == 0)
{
        $table->construct_cell('Kategori bulunamadı. Eklentiyi yeniden kurmak listeyi oluşturur.', array('colspan' => 7, 'class' => 'align_center'));
        $table->construct_row();
}

$table->output('RSS Kategorileri');

$page->output_footer();
