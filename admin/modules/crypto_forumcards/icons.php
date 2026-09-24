<?php
/**
 * ACP: per-forum icon editor.
 *
 * A forum row's icon comes from either the keyword heuristic in
 * inc/plugins/crypto_forumcards.php or an explicit row in crypto_forum_icons.
 * This page is the explicit path: pick a forum, choose a Font Awesome class and
 * a colour, and the override wins from then on. Newly created forums show up
 * here automatically, which is the point -- the keyword list cannot know about
 * a forum the admin has not created yet.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Forum Kartlari', 'index.php?module=crypto_forumcards');

$table_exists = $db->table_exists('crypto_forum_icons');

// Offered as one-click suggestions; the field stays free text so any icon the
// theme's Font Awesome build ships can be used.
$crypto_icon_suggestions = array(
        'fa-solid fa-comments' => 'Genel',
        'fa-solid fa-bullhorn' => 'Duyuru',
        'fa-solid fa-trophy' => 'Etkinlik',
        'fa-solid fa-hand-sparkles' => 'Tanisma',
        'fa-solid fa-coins' => 'Kripto',
        'fa-solid fa-parachute-box' => 'AirDrop',
        'fa-solid fa-flask-vial' => 'Testnet',
        'fa-solid fa-microchip' => 'Mining',
        'fa-brands fa-telegram' => 'Telegram',
        'fa-solid fa-chart-line' => 'Analiz',
        'fa-brands fa-bitcoin' => 'Bitcoin',
        'fa-solid fa-robot' => 'Bot',
        'fa-solid fa-crown' => 'VIP',
        'fa-brands fa-instagram' => 'Instagram',
        'fa-brands fa-tiktok' => 'TikTok',
        'fa-brands fa-youtube' => 'YouTube',
        'fa-solid fa-store' => 'Pazar',
        'fa-solid fa-handshake' => 'Hizmet',
        'fa-solid fa-handshake-angle' => 'Sponsor',
        'fa-solid fa-brain' => 'Yapay zeka',
        'fa-solid fa-truck-fast' => 'Dropshipping',
        'fa-solid fa-tags' => 'Firsat / kupon',
        'fa-solid fa-toolbox' => 'Araclar',
        'fa-solid fa-globe' => 'Dunya',
        'fa-solid fa-users' => 'Topluluk',
        'fa-solid fa-door-open' => 'Hos geldiniz',
);

$fid = isset($mybb->input['fid']) ? (int)$mybb->input['fid'] : 0;

if($mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        if(!$table_exists)
        {
                flash_message('Ikon tablosu yok. Crypto Forum Cards eklentisini yonetim panelinden yeniden etkinlestirin.', 'error');
                admin_redirect('index.php?module=crypto_forumcards-icons');
        }

        $post_fid = (int)$mybb->input['fid'];
        $icon = trim($mybb->get_input('icon'));
        $color = trim($mybb->get_input('color'));

        $exists = $db->fetch_field($db->simple_select('forums', 'fid', 'fid='.$post_fid), 'fid');

        if(!$exists)
        {
                flash_message('Gecersiz forum.', 'error');
                admin_redirect('index.php?module=crypto_forumcards-icons');
        }

        if($icon === '' && $color === '')
        {
                $db->delete_query('crypto_forum_icons', 'fid='.$post_fid);
                flash_message('Ikon kaldirildi; bu forum yine otomatik eslesmeyi kullanacak.', 'success');
        }
        else
        {
                if($icon !== '' && !preg_match('#^fa-[a-z0-9 -]+$#i', $icon))
                {
                        flash_message('Ikon sinifi gecersiz gorunuyor. Ornek: fa-solid fa-crown', 'error');
                        admin_redirect('index.php?module=crypto_forumcards-icons&fid='.$post_fid);
                }

                if($color !== '' && !preg_match('~^#[0-9a-f]{3,8}$~i', $color))
                {
                        flash_message('Renk gecersiz. Ornek: #fbbf24', 'error');
                        admin_redirect('index.php?module=crypto_forumcards-icons&fid='.$post_fid);
                }

                $db->replace_query('crypto_forum_icons', array(
                        'fid' => $post_fid,
                        'icon' => $db->escape_string($icon),
                        'color' => $db->escape_string($color),
                ));

                flash_message('Ikon kaydedildi.', 'success');
        }

        admin_redirect('index.php?module=crypto_forumcards-icons');
}

$page->add_breadcrumb_item('Ikon Ekle / Duzenle', 'index.php?module=crypto_forumcards-icons');
$page->output_header('Forum Ikonu Ekle / Duzenle');

$overrides = array();

if($table_exists)
{
        $query = $db->simple_select('crypto_forum_icons', '*');

        while($row = $db->fetch_array($query))
        {
                $overrides[(int)$row['fid']] = $row;
        }
}

// -------------------------------------------------------------- edit form ---

$form = new Form('index.php?module=crypto_forumcards-icons', 'post', 'crypto_icon_form');

$form_container = new FormContainer('Ikon Ata');
$form_container->output_row(
        'Forum',
        'Ikonu degistirilecek forum veya kategori.',
        $form->generate_forum_select('fid', $fid, array('id' => 'crypto_icon_fid', 'main_option' => 'Seciniz...')),
        'fid'
);
$form_container->output_row(
        'Ikon sinifi',
        'Font Awesome sinifi, ornek <code>fa-solid fa-crown</code>. Bos birakilirsa otomatik eslesme kullanilir.',
        $form->generate_text_box('icon', '', array('id' => 'crypto_icon_input', 'style' => 'width:320px;')),
        'icon'
);
$form_container->output_row(
        'Renk',
        'Ikonun vurgu rengi, hex olarak, ornek <code>#fbbf24</code>.',
        $form->generate_text_box('color', '#fbbf24', array('id' => 'crypto_icon_color', 'style' => 'width:120px;')),
        'color'
);
$form_container->end();

$buttons = array($form->generate_submit_button('Kaydet'));
$form->output_submit_wrapper($buttons);
$form->end();

// ------------------------------------------------------------- suggestions --

$table = new Table;
$table->construct_header('Onerilen ikonlar');
$table->construct_header('', array('width' => '1'));

$suggestions = '';

foreach($crypto_icon_suggestions as $class => $label)
{
        $suggestions .= '<button type="button" class="crypto-icon-pick" data-icon="'.htmlspecialchars_uni($class).'" '
                . 'style="display:inline-flex;align-items:center;gap:6px;margin:3px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;cursor:pointer;">'
                . '<i class="'.htmlspecialchars_uni($class).'" aria-hidden="true"></i>'
                . '<span style="font-size:11px;color:#475569;">'.htmlspecialchars_uni($label).'</span></button>';
}

$table->construct_cell('<div>'.$suggestions.'</div>');
$table->construct_cell('');
$table->construct_row();
$table->output('Oneriler');

// --------------------------------------------------------------- overview ---

$table = new Table;
$table->construct_header('Forum');
$table->construct_header('Kaynak', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Ikon', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Renk', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Islem', array('class' => 'align_center', 'width' => '12%'));

$query = $db->simple_select('forums', 'fid, name, type', '', array('order_by' => 'parentlist, disporder, name'));

$count = 0;
$fallback = 0;

while($forum = $db->fetch_array($query))
{
        $count++;
        list($auto_icon, $auto_color) = crypto_forumcards_lookup($forum['name']);

        $icon = $auto_icon;
        $color = $auto_color;
        $manual = false;

        if(isset($overrides[(int)$forum['fid']]))
        {
                $manual = true;

                if(!empty($overrides[(int)$forum['fid']]['icon']))
                {
                        $icon = $overrides[(int)$forum['fid']]['icon'];
                }

                if(!empty($overrides[(int)$forum['fid']]['color']))
                {
                        $color = $overrides[(int)$forum['fid']]['color'];
                }
        }

        if(!$manual && $auto_icon === 'fa-solid fa-comments')
        {
                $fallback++;
        }

        $type = ($forum['type'] == 'c') ? '<span style="color:#64748b;">kategori</span>' : 'forum';

        $table->construct_cell('<strong>'.htmlspecialchars_uni($forum['name']).'</strong><br /><small>fid: '.(int)$forum['fid'].' &middot; '.$type.'</small>');

        if($manual)
        {
                $source = '<span style="color:#2563eb;font-weight:600;">elle</span>';
        }
        elseif($auto_icon === 'fa-solid fa-comments')
        {
                $source = '<span style="color:#f59e0b;font-weight:600;">genel</span>';
        }
        else
        {
                $source = '<span style="color:#22c55e;font-weight:600;">otomatik</span>';
        }

        $table->construct_cell($source, array('class' => 'align_center'));
        $table->construct_cell('<i class="'.htmlspecialchars_uni($icon).'" aria-hidden="true"></i><br /><small>'.htmlspecialchars_uni($icon).'</small>', array('class' => 'align_center'));
        $table->construct_cell('<code>'.htmlspecialchars_uni($color).'</code><br /><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:'.htmlspecialchars_uni($color).';vertical-align:middle;"></span>', array('class' => 'align_center'));
        $table->construct_cell('<a href="index.php?module=crypto_forumcards-icons&amp;fid='.(int)$forum['fid'].'">duzenle</a>', array('class' => 'align_center'));
        $table->construct_row();
}

if($count == 0)
{
        $table->construct_cell('Kayitli forum yok.', array('colspan' => 5, 'class' => 'align_center'));
        $table->construct_row();
}

$table->output('Forum Ikonlari');

if(!$table_exists)
{
        $warn = new Table;
        $warn->construct_cell('<strong>crypto_forum_icons</strong> tablosu bulunamadi. Crypto Forum Cards eklentisini devre disi birakip yeniden etkinlestirin; tablo o zaman olusturulur.', array('colspan' => 1));
        $warn->construct_row();
        $warn->output('Uyari');
}

// Suggestion buttons are progressive enhancement only: the field is a plain
// text box and the form posts without this script.
$pick_js = <<<'JS'
<script type="text/javascript">
(function () {
    var input = document.getElementById('crypto_icon_input');
    if (!input) { return; }
    var buttons = document.getElementsByClassName('crypto-icon-pick');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].onclick = function () {
            input.value = this.getAttribute('data-icon');
            input.focus();
        };
    }
})();
</script>
JS;

echo $pick_js;

$page->output_footer();
