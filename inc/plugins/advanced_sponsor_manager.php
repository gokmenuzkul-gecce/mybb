<?php
/**
 * Advanced Sponsor Manager — sponsor / advertisement management for MyBB 1.8.
 *
 * A sponsor is a row in mybb_asm_sponsors. It carries the copy, the logo, the
 * destination link, the slot it occupies and — the part that matters for paid
 * partnerships — an affiliate/tracking code and an owner uid.
 *
 * Two things are deliberately separated:
 *
 *   - The affiliate code is written only from the ACP. The sponsor-facing panel
 *     never reads it, never renders it and never accepts it from a form.
 *   - Every outbound link (the sponsor's own and each product item's) is
 *     rewritten through reklam.php, which appends that code server-side and
 *     bumps the click counters on the way out.
 *
 * So a sponsor can change their copy, their images, their product links and
 * their status, but the tracking code that rides along is never theirs to see
 * or edit.
 */

if(!defined('IN_MYBB'))
{
        die('Bu dosyaya doğrudan erişilemez.');
}

if(isset($plugins) && is_object($plugins))
{
        $plugins->add_hook('global_start', 'advanced_sponsor_manager_global_start');
        $plugins->add_hook('pre_output_page', 'advanced_sponsor_manager_pre_output');
        // forumdisplay_start runs before $fid is parsed, so the category slot has
        // to be filled at the *_end hooks, which still run before the template
        // is eval()'d.
        $plugins->add_hook('forumdisplay_end', 'advanced_sponsor_manager_forum_start');
        $plugins->add_hook('showthread_end', 'advanced_sponsor_manager_thread_start');
        $plugins->add_hook('newthread_start', 'advanced_sponsor_manager_newthread_start');
        $plugins->add_hook('newthread_do_newthread_end', 'advanced_sponsor_manager_thread_ad_save');
        $plugins->add_hook('modcp_nav', 'advanced_sponsor_manager_modcp_nav');
        $plugins->add_hook('modcp_start', 'advanced_sponsor_manager_modcp_page');

        // Adds the "Sponsor Özel Yönetim" flag to the usergroup editor, the same
        // way core moderate/admin flags are drawn.
        $plugins->add_hook('admin_formcontainer_output_row', 'advanced_sponsor_manager_groups_form_row');
        $plugins->add_hook('admin_user_groups_edit_commit', 'advanced_sponsor_manager_groups_edit_commit');
        $plugins->add_hook('admin_user_groups_add_commit', 'advanced_sponsor_manager_groups_add_commit');
}

function advanced_sponsor_manager_info()
{
        return array(
                'name'          => 'Gelişmiş Sponsor ve Reklam Yönetimi',
                'description'   => 'Sponsor/reklam kartları, kategori sponsorluğu, kenar bannerları, tıklama sayaçları ve affiliate takip kodu yönetimi.',
                'website'       => '',
                'author'        => 'Gecce',
                'authorsite'    => '',
                'version'       => '1.0',
                'guid'          => '9b1f4c7a2e6d48f3a5c90871bd2e64af',
                'compatibility' => '18*',
        );
}

/* ------------------------------------------------------------- install --- */

function advanced_sponsor_manager_install()
{
        advanced_sponsor_manager_create_tables();
        advanced_sponsor_manager_create_settings();
        advanced_sponsor_manager_activate();

        echo 'Gelişmiş Sponsor ve Reklam Yönetimi kuruldu. Admin panelinden <strong>Sponsor Yönetimi</strong> bölümüne göz atın.';
}

function advanced_sponsor_manager_is_installed()
{
        global $db;

        return $db->table_exists('asm_sponsors');
}

function advanced_sponsor_manager_uninstall()
{
        global $db;

        $db->drop_table('asm_sponsors');
        $db->drop_table('asm_items');
        $db->drop_table('asm_clicks');

        $db->delete_query('settings', "name IN ('asm_enabled','asm_affiliate_default','asm_click_dedupe','asm_new_tab','asm_modcp_enabled','asm_label','asm_show_clicks','asm_guest_visible','asm_thread_ad_enabled')");
        $db->delete_query('settinggroups', "name='advanced_sponsor_manager'");

        advanced_sponsor_manager_remove_placeholders();
        advanced_sponsor_manager_remove_templates();
        advanced_sponsor_manager_remove_acp_module();
        advanced_sponsor_manager_remove_redirect_script();

        rebuild_settings();
}

/* activate()/deactivate() only touch the theme and the ACP shim. Tables and
   settings belong to the installer, so a deactivate never destroys sponsor
   data and a reactivate does not duplicate it. */
function advanced_sponsor_manager_activate()
{
        advanced_sponsor_manager_create_templates();
        advanced_sponsor_manager_insert_placeholders();
        advanced_sponsor_manager_write_acp_module();
        advanced_sponsor_manager_write_redirect_script();
        rebuild_settings();
}

function advanced_sponsor_manager_deactivate()
{
        advanced_sponsor_manager_remove_placeholders();
}

/* ------------------------------------------------------------- schema --- */

function advanced_sponsor_manager_create_tables()
{
        global $db;

        $mysql = $db->type == 'mysql';
        $pk = $mysql ? "INT(10) NOT NULL AUTO_INCREMENT" : "INTEGER PRIMARY KEY AUTOINCREMENT";
        $int = $mysql ? "INT(10)" : "INTEGER";
        $tail = $mysql ? " ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci" : "";

        if(!$db->table_exists('asm_sponsors'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."asm_sponsors (
                        sid {$pk},
                        title VARCHAR(150) NOT NULL,
                        tagline VARCHAR(255) NOT NULL DEFAULT '',
                        logo VARCHAR(255) NOT NULL DEFAULT '',
                        url VARCHAR(255) NOT NULL DEFAULT '',
                        affiliate VARCHAR(255) NOT NULL DEFAULT '',
                        slot VARCHAR(20) NOT NULL DEFAULT 'single',
                        position VARCHAR(20) NOT NULL DEFAULT 'top',
                        edge VARCHAR(10) NOT NULL DEFAULT '',
                        fid {$int} NOT NULL DEFAULT 0,
                        uid {$int} NOT NULL DEFAULT 0,
                        status VARCHAR(20) NOT NULL DEFAULT 'active',
                        disporder {$int} NOT NULL DEFAULT 0,
                        clicks {$int} NOT NULL DEFAULT 0,
                        dateline {$int} NOT NULL DEFAULT 0,
                        updated {$int} NOT NULL DEFAULT 0
                ){$tail};");
        }

        if(!$db->table_exists('asm_items'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."asm_items (
                        iid {$pk},
                        sid {$int} NOT NULL DEFAULT 0,
                        title VARCHAR(150) NOT NULL DEFAULT '',
                        image VARCHAR(255) NOT NULL DEFAULT '',
                        url VARCHAR(255) NOT NULL DEFAULT '',
                        active {$int} NOT NULL DEFAULT 1,
                        disporder {$int} NOT NULL DEFAULT 0,
                        clicks {$int} NOT NULL DEFAULT 0,
                        dateline {$int} NOT NULL DEFAULT 0
                ){$tail};");
        }

        if(!$db->table_exists('asm_clicks'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."asm_clicks (
                        cid {$pk},
                        sid {$int} NOT NULL DEFAULT 0,
                        iid {$int} NOT NULL DEFAULT 0,
                        uid {$int} NOT NULL DEFAULT 0,
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        dateline {$int} NOT NULL DEFAULT 0
                ){$tail};");
        }

        // A moderator attaches one category sponsor to a thread they start. The
        // sponsor row itself is kept in asm_sponsors; this table only records the
        // thread -> sponsor link.
        if(!$db->table_exists('asm_thread_ads'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."asm_thread_ads (
                        taid {$pk},
                        tid {$int} NOT NULL DEFAULT 0,
                        sid {$int} NOT NULL DEFAULT 0,
                        uid {$int} NOT NULL DEFAULT 0,
                        dateline {$int} NOT NULL DEFAULT 0
                ){$tail};");
        }

        advanced_sponsor_manager_upgrade_tables();
}

/**
 * Additive migrations for boards that installed an earlier build.
 */
function advanced_sponsor_manager_upgrade_tables()
{
        global $db;

        if($db->table_exists('usergroups') && !$db->field_exists('asm_sponsor_panel', 'usergroups'))
        {
                // The "Sponsor Özel Yönetim" flag. Off by default: nobody gains
                // sponsor-panel access just by installing the plugin.
                $db->add_column('usergroups', 'asm_sponsor_panel', "TINYINT(1) NOT NULL DEFAULT '0'");
        }

        if(!$db->table_exists('asm_sponsors'))
        {
                return;
        }

        if(!$db->field_exists('affiliate', 'asm_sponsors'))
        {
                // An empty value means "fall back to the global code", so a board
                // upgrading keeps whatever it configured centrally.
                $db->add_column('asm_sponsors', 'affiliate', "VARCHAR(255) NOT NULL DEFAULT ''");
        }
        if(!$db->field_exists('uid', 'asm_sponsors'))
        {
                $db->add_column('asm_sponsors', 'uid', "INT NOT NULL DEFAULT 0");
        }
        if(!$db->field_exists('edge', 'asm_sponsors'))
        {
                $db->add_column('asm_sponsors', 'edge', "VARCHAR(10) NOT NULL DEFAULT ''");
        }
}

/* ----------------------------------------------------------- settings --- */

function advanced_sponsor_manager_create_settings()
{
        global $db;

        $existing = $db->simple_select('settinggroups', 'gid', "name='advanced_sponsor_manager'");
        if($db->num_rows($existing))
        {
                $gid = (int)$db->fetch_field($existing, 'gid');
        }
        else
        {
                $gid = (int)$db->insert_query('settinggroups', array(
                        'name' => 'advanced_sponsor_manager',
                        'title' => 'Sponsor ve Reklam Yönetimi',
                        'description' => 'Sponsor alanları, affiliate takip kodu ve tıklama sayacı ayarları.',
                        'disporder' => 70,
                        'isdefault' => 0,
                ));
        }

        $settings = array(
                array('asm_enabled', 1, 'select', 'Eklenti aktif mi?', 'Kapalıysa ön yüzde hiçbir sponsor alanı render edilmez.', array(0 => 'Hayır', 1 => 'Evet')),
                array('asm_affiliate_default', '', 'text', 'Genel affiliate / takip kodu', 'Sponsorun kendi kodu yoksa tüm linklerin sonuna bu kod eklenir. Örn: ref=sitemiz veya utm_source=forum. Sponsor bu alanı göremez.', ''),
                array('asm_click_dedupe', 3600, 'text', 'Tıklama tekrar aralığı (saniye)', 'Aynı IP aynı sponsoru bu süre içinde tekrar tıklarsa sayaç artmaz (0 = her tıklamayı say).', ''),
                array('asm_new_tab', 1, 'select', 'Linkler yeni sekmede', 'Reklam linkleri yeni sekmede mi açılsın?', array(0 => 'Hayır', 1 => 'Evet')),
                array('asm_modcp_enabled', 1, 'select', 'ModCP sponsor paneli', 'Sponsor yönetimi ModCP menüsüne de eklensin mi?', array(0 => 'Hayır', 1 => 'Evet')),
                array('asm_label', 'Sponsor', 'text', 'Ön yüz alan etiketi', 'Kategori/forum sponsor kartının üstünde görünen küçük etiket.', ''),
                array('asm_show_clicks', 0, 'select', 'Ön yüzde tıklama sayısı görünsün mü?', 'Tıklama sayısını yalnızca yönetim panelinden görmek için Hayır seçin. Sayaç her durumda kaydedilmeye devam eder.', array(0 => 'Hayır (önerilen)', 1 => 'Evet')),
                array('asm_guest_visible', 1, 'select', 'Ziyaretçiler reklamları görsün mü?', 'Evet ise reklam alanları çıkış yapmış ziyaretçilere de gösterilir. Hayır ise yalnızca giriş yapmış üyeler görür.', array(0 => 'Hayır (yalnızca üyeler)', 1 => 'Evet (herkes)')),
                array('asm_thread_ad_enabled', 1, 'select', 'Moderatör konu reklamı', 'Kategorisinde moderatör olan üyeler, açtıkları her konunun üstüne o kategoriye ait reklamı ekleyebilsin mi?', array(0 => 'Hayır', 1 => 'Evet')),
        );

        $disporder = 1;
        foreach($settings as $s)
        {
                $exists = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s[0])."'");
                if($db->num_rows($exists))
                {
                        ++$disporder;
                        continue;
                }

                $db->insert_query('settings', array(
                        'name' => $db->escape_string($s[0]),
                        'title' => $db->escape_string($s[3]),
                        'description' => $db->escape_string($s[4]),
                        'optionscode' => $db->escape_string(advanced_sponsor_manager_optionscode($s[2], $s[5])),
                        'value' => $db->escape_string((string)$s[1]),
                        'disporder' => $disporder,
                        'gid' => $gid,
                        'isdefault' => 0,
                ));

                ++$disporder;
        }
}

function advanced_sponsor_manager_optionscode($type, $options)
{
        if($type != 'select')
        {
                return $type;
        }

        $parts = array();
        foreach($options as $key => $label)
        {
                $parts[] = $key.'='.$label;
        }

        // Optionscode uses a literal backslash-n between entries.
        return 'select\n'.implode('\n', $parts);
}

/* ---------------------------------------------------------- templates --- */

function advanced_sponsor_manager_template_defs()
{
        return array(
'advanced_sponsor_single' => '<div class="asm-card asm-card--single asm-status-{$status}">
  <div class="asm-card-media">
    <a href="{$href}"{$target}><img src="{$image}" alt="{$title}" loading="lazy" /></a>
    {$soon}
  </div>
  <div class="asm-card-body">
    <span class="asm-card-kicker">{$slot_label}</span>
    <h3 class="asm-card-title"><a href="{$href}"{$target}>{$title}</a></h3>
    <p class="asm-card-tagline">{$tagline}</p>
    <div class="asm-card-foot">
      <a class="asm-btn" href="{$href}"{$target}>{$button}</a>
      {$clicks_badge}
    </div>
  </div>
</div>',
'advanced_sponsor_duo' => '<div class="asm-duo">{$items}</div>',
'advanced_sponsor_forum' => '<section class="asm-forum-slot">
  <div class="asm-forum-head">
    <span class="asm-forum-label"><i class="fa-solid fa-handshake"></i> {$label}</span>
    <span class="asm-forum-hint">Bu bölümün sponsorları</span>
  </div>
  <div class="asm-forum-card asm-status-{$status}">
    <div class="asm-forum-brand">
      <a href="{$href}"{$target}><img src="{$image}" alt="{$title}" loading="lazy" /></a>
      <div>
        <h3><a href="{$href}"{$target}>{$title}</a></h3>
        <p>{$tagline}</p>
      </div>
    </div>
    <div class="asm-forum-products">{$products}</div>
  </div>
  {$soon}
</section>',
'advanced_sponsor_product' => '<a class="asm-product asm-status-{$status}" href="{$href}"{$target}>
  <span class="asm-product-image"><img src="{$image}" alt="{$title}" loading="lazy" />{$soon}</span>
  <span class="asm-product-title">{$title}</span>
</a>',
'advanced_sponsor_edge' => '<aside class="asm-edge asm-edge--{$edge} asm-status-{$status}">
  <a href="{$href}"{$target}>
    <img src="{$image}" alt="{$title}" loading="lazy" />
    <span class="asm-edge-title">{$title}</span>
  </a>
  {$soon}
</aside>',
'advanced_sponsor_soon' => '<span class="asm-soon"><span class="asm-soon-badge">Çok Yakında</span></span>',
'advanced_sponsor_zone' => '<div class="asm-zone asm-zone--{$position}">{$cards}</div>',
        );
}

function advanced_sponsor_manager_create_templates()
{
        global $db;

        foreach(advanced_sponsor_manager_template_defs() as $title => $tpl)
        {
                $title = $db->escape_string($title);
                $tpl = $db->escape_string($tpl);

                $exists = $db->simple_select('templates', 'tid', "title='{$title}' AND sid='-2'");
                if($db->num_rows($exists))
                {
                        $db->update_query('templates', array('template' => $tpl, 'dateline' => TIME_NOW), "title='{$title}' AND sid='-2'");
                }
                else
                {
                        $db->insert_query('templates', array(
                                'title' => $title,
                                'template' => $tpl,
                                'sid' => -2,
                                'version' => 1820,
                                'status' => 0,
                                'dateline' => TIME_NOW,
                        ));
                }
        }
}

function advanced_sponsor_manager_remove_templates()
{
        global $db;

        $titles = array();
        foreach(array_keys(advanced_sponsor_manager_template_defs()) as $t)
        {
                $titles[] = "'".$db->escape_string($t)."'";
        }

        if($titles)
        {
                $db->delete_query('templates', "title IN (".implode(',', $titles).")");
        }
}

/**
 * A placeholder is inserted only once. find_replace_templatesets() has no
 * "already there?" guard, and running activate() twice used to stack copies of
 * the same variable into one template.
 */
function advanced_sponsor_manager_template_insert($title, $find, $insert)
{
        global $db;

        $escaped_title = $db->escape_string($title);
        $query = $db->simple_select('templates', 'tid, template', "title='{$escaped_title}'");

        $rows = array();
        while($row = $db->fetch_array($query))
        {
                // Buffer first: writing while a SELECT cursor is open over the same
                // table makes SQLite refuse the write.
                $rows[] = $row;
        }

        foreach($rows as $row)
        {
                if(strpos($row['template'], $insert) !== false)
                {
                        continue;
                }

                $pos = strpos($row['template'], $find);
                if($pos === false)
                {
                        continue;
                }

                $new = substr($row['template'], 0, $pos + strlen($find))
                        . $insert
                        . substr($row['template'], $pos + strlen($find));

                $db->update_query('templates', array(
                        'template' => $db->escape_string($new),
                        'dateline' => TIME_NOW,
                ), "tid='".(int)$row['tid']."'");
        }
}

function advanced_sponsor_manager_insert_placeholders()
{
        advanced_sponsor_manager_template_insert(
                'header',
                '<main id="content">'."\n".'    <div class="wrapper">',
                "\n      {\$advanced_sponsors_top}"
        );

        advanced_sponsor_manager_template_insert(
                'footer',
                '<div id="footer">',
                "{\$advanced_sponsors_bottom}\n"
        );

        foreach(array('forumdisplay', 'showthread') as $tpl)
        {
                advanced_sponsor_manager_template_insert($tpl, '{$header}', "\n{\$advanced_category_sponsor}");
        }

        advanced_sponsor_manager_template_insert('modcp_nav', '{$modcp_nav_users}', "\n        {\$nav_asm_sponsor}");

        // Moderator thread-ad picker on the new-thread form, and the rendered
        // banner on the thread page.
        advanced_sponsor_manager_template_insert('newthread', '{$loginbox}', "\n{\$asm_thread_ad_selector}");
        advanced_sponsor_manager_template_insert('showthread', '{$advanced_category_sponsor}', "\n{\$asm_thread_ad}");
}

function advanced_sponsor_manager_remove_placeholders()
{
        global $db;

        $map = array(
                'header' => array('{$advanced_sponsors_top}'),
                'footer' => array('{$advanced_sponsors_bottom}'),
                'forumdisplay' => array('{$advanced_category_sponsor}'),
                'showthread' => array('{$advanced_category_sponsor}', '{$asm_thread_ad}'),
                'newthread' => array('{$asm_thread_ad_selector}'),
                'modcp_nav' => array('{$nav_asm_sponsor}'),
        );

        foreach($map as $title => $tokens)
        {
                $escaped_title = $db->escape_string($title);
                $query = $db->simple_select('templates', 'tid, template', "title='{$escaped_title}'");
                $rows = array();
                while($row = $db->fetch_array($query))
                {
                        $rows[] = $row;
                }

                foreach($rows as $row)
                {
                        $new = $row['template'];
                        foreach($tokens as $token)
                        {
                                // Every occurrence, not just the first: an old double
                                // activate left two copies behind.
                                $new = str_replace($token, '', $new);
                        }

                        if($new !== $row['template'])
                        {
                                $db->update_query('templates', array(
                                        'template' => $db->escape_string($new),
                                        'dateline' => TIME_NOW,
                                ), "tid='".(int)$row['tid']."'");
                        }
                }
        }
}

/* ------------------------------------------------ ACP module bootstrap --- */

/**
 * MyBB only recognises an ACP module that lives in its own directory with a
 * module_meta.php, and this file has to stay self-contained. activate() writes
 * those two shims; they hold no logic, they only require this file and call the
 * entry points further down.
 */
function advanced_sponsor_manager_write_acp_module()
{
        $dir = MYBB_ROOT.'admin/modules/advanced_sponsor_manager';
        if(!is_dir($dir))
        {
                @mkdir($dir, 0755, true);
        }

        $meta = '<?php
/**
 * ACP module shim for the Advanced Sponsor Manager.
 *
 * Generated by advanced_sponsor_manager_write_acp_module(). The real code lives
 * in inc/plugins/advanced_sponsor_manager.php so the plugin stays in one file.
 */
if(!defined(\'IN_MYBB\'))
{
        die(\'Direct initialization of this file is not allowed.\');
}

require_once MYBB_ROOT.\'inc/plugins/advanced_sponsor_manager.php\';

function advanced_sponsor_manager_meta()
{
        return advanced_sponsor_manager_acp_meta();
}

function advanced_sponsor_manager_action_handler($action)
{
        return advanced_sponsor_manager_acp_action_handler($action);
}

function advanced_sponsor_manager_admin_permissions()
{
        return advanced_sponsor_manager_acp_permissions();
}
';

        $index = '<?php
/**
 * ACP entry point for the Advanced Sponsor Manager.
 *
 * Generated by advanced_sponsor_manager_write_acp_module().
 */
if(!defined(\'IN_MYBB\'))
{
        die(\'Direct initialization of this file is not allowed.\');
}

require_once MYBB_ROOT.\'inc/plugins/advanced_sponsor_manager.php\';

advanced_sponsor_manager_acp_page();
';

        @file_put_contents($dir.'/module_meta.php', $meta);
        @file_put_contents($dir.'/index.php', $index);
}

function advanced_sponsor_manager_remove_acp_module()
{
        $dir = MYBB_ROOT.'admin/modules/advanced_sponsor_manager';
        @unlink($dir.'/module_meta.php');
        @unlink($dir.'/index.php');
        @rmdir($dir);
}

/**
 * The outbound-click script. reklam.php cannot live in the plugin directory
 * (MyBB executes it at the board root), so activate() writes a two-line shim
 * that loads global.php and hands over to advanced_sponsor_manager_handle_click().
 */
function advanced_sponsor_manager_write_redirect_script()
{
        $file = MYBB_ROOT.'reklam.php';

        $shim = '<?php
/**
 * Sponsor click tracker for the Advanced Sponsor Manager.
 *
 * Generated by advanced_sponsor_manager_write_redirect_script(). Keep the real
 * logic in inc/plugins/advanced_sponsor_manager.php.
 */
define(\'IN_MYBB\', 1);
require_once dirname(__FILE__).\'/global.php\';

advanced_sponsor_manager_handle_click();
';

        @file_put_contents($file, $shim);
}

function advanced_sponsor_manager_remove_redirect_script()
{
        @unlink(MYBB_ROOT.'reklam.php');
}

/* ------------------------------------------------------------ helpers --- */

/**
 * The settings group id, for the ACP sub-menu deep link.
 *
 * Returns 0 when the group is missing so the link still resolves to the
 * settings list rather than a broken gid.
 */
function advanced_sponsor_manager_gid()
{
        global $db;

        static $gid = null;
        if($gid === null)
        {
                $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='advanced_sponsor_manager'"), 'gid');
        }

        return $gid;
}

function advanced_sponsor_manager_enabled()
{
        global $mybb;

        return !empty($mybb->settings['asm_enabled']) && is_object($GLOBALS['db']) && $GLOBALS['db']->table_exists('asm_sponsors');
}

function advanced_sponsor_manager_visible_to_current_user()
{
        global $mybb;

        // "Ziyaretçiler reklamları görsün mü?" - off hides every zone from
        // logged-out traffic. Admins keep their preview regardless.
        if(empty($mybb->settings['asm_guest_visible']) && (int)$mybb->user['uid'] === 0)
        {
                return false;
        }

        return true;
}

function advanced_sponsor_manager_slots()
{
        return array(
                'single' => 'Tekli Kart',
                'duo'    => 'İkili Grup (2-3 sponsor)',
                'forum'  => 'Kategori / Forum Sponsor Kartı',
                'edge'   => 'Kenar Banner (sol/sağ)',
        );
}

function advanced_sponsor_manager_positions()
{
        return array(
                'top'    => 'İçerik Üstü',
                'bottom' => 'Footer Üstü',
                'edge'   => 'Kenar (sabit)',
        );
}

function advanced_sponsor_manager_statuses()
{
        return array(
                'active'  => 'Aktif',
                'passive' => 'Pasif',
                'draft'   => 'Taslak',
                'soon'    => 'Çok Yakında',
        );
}

function advanced_sponsor_manager_edges()
{
        return array(
                ''     => 'Yok',
                'left' => 'Sol kenar',
                'right' => 'Sağ kenar',
        );
}

/**
 * Incoming URL for every tracked link. sid is enough on its own; iid is present
 * only for product tiles, so a product click does not inflate the sponsor's own
 * counter.
 */
function advanced_sponsor_manager_track_url($sid, $iid = 0)
{
        global $mybb;

        $url = htmlspecialchars_uni($mybb->settings['bburl']).'/reklam.php?sid='.(int)$sid;
        if($iid)
        {
                $url .= '&amp;iid='.(int)$iid;
        }

        return $url;
}

/**
 * Only http(s) survives. A javascript: or data: URL pasted into the sponsor
 * field would otherwise become a clickable XSS payload for every visitor.
 */
function advanced_sponsor_manager_safe_url($url)
{
        $url = trim((string)$url);
        if($url === '')
        {
                return '';
        }

        if(!preg_match('#^https?://#i', $url))
        {
                return '';
        }

        if(preg_match('#^https?://[^\s]*[\s"\']#i', $url))
        {
                return '';
        }

        return $url;
}

function advanced_sponsor_manager_image_or_placeholder($image)
{
        $image = trim((string)$image);

        if($image === '')
        {
                return htmlspecialchars_uni($GLOBALS['mybb']->settings['bburl']).'/themes/crypto-web3/media/bg-plexus.jpg';
        }

        if(!preg_match('#^https?://#i', $image) && strpos($image, '/') !== 0)
        {
                $image = $GLOBALS['mybb']->settings['bburl'].'/'.$image;
        }

        return htmlspecialchars_uni($image);
}

function advanced_sponsor_manager_target_attr()
{
        global $mybb;

        if(!empty($mybb->settings['asm_new_tab']))
        {
                return ' target="_blank" rel="noopener nofollow sponsored"';
        }

        return ' rel="nofollow sponsored"';
}

/**
 * Fetch sponsors for one placement.
 *
 * @param string $position top|bottom|edge
 * @param string $edge     left|right, only meaningful for position=edge
 * @param int    $fid      category restriction, 0 means board-wide
 * @param int    $limit
 */
function advanced_sponsor_manager_fetch($position, $edge = '', $fid = 0, $limit = 0)
{
        global $db;

        $where = "position='".$db->escape_string($position)."' AND status IN ('active','soon')";

        if($position === 'edge')
        {
                $where .= " AND slot='edge'";
        }
        else
        {
                // Category ('forum') slots are fetched per fid by
                // render_category(); edge banners belong to the edge zone. Without
                // this a category sponsor also popped up in the top zone.
                $where .= " AND slot IN ('single','duo')";
        }

        if($edge !== '')
        {
                $where .= " AND edge='".$db->escape_string($edge)."'";
        }

        if($fid > 0)
        {
                $where .= " AND (fid='".(int)$fid."' OR fid='0')";
        }

        $options = array('order_by' => 'disporder', 'order_dir' => 'asc');
        if($limit > 0)
        {
                $options['limit'] = (int)$limit;
        }

        $query = $db->simple_select('asm_sponsors', '*', $where, $options);

        $rows = array();
        while($row = $db->fetch_array($query))
        {
                $rows[] = $row;
        }

        return $rows;
}

/* ----------------------------------------------------------- renderer --- */

function advanced_sponsor_manager_template($template_name, $vars)
{
        global $templates;

        $template = $templates->get($template_name);
        if(!$template)
        {
                return '';
        }

        // The parameter cannot be called $title: extract() would then skip the
        // sponsor's own $title (EXTR_SKIP never overwrites), printing the
        // template name where the card heading should be.
        extract($vars, EXTR_SKIP);
        eval('$asm_output = "'.$template.'";');

        return $asm_output;
}

function advanced_sponsor_manager_render_products($sid)
{
        global $db;

        $query = $db->simple_select('asm_items', '*', "sid='".(int)$sid."' AND active='1'", array('order_by' => 'disporder', 'order_dir' => 'asc'));

        $out = '';
        while($item = $db->fetch_array($query))
        {
                $url = advanced_sponsor_manager_safe_url($item['url']);

                $out .= advanced_sponsor_manager_template('advanced_sponsor_product', array(
                        'href'   => $url !== '' ? advanced_sponsor_manager_track_url($sid, $item['iid']) : '#',
                        'target' => advanced_sponsor_manager_target_attr(),
                        'image'  => advanced_sponsor_manager_image_or_placeholder($item['image']),
                        'title'  => htmlspecialchars_uni($item['title']),
                        'status' => $url !== '' ? 'active' : 'soon',
                        'soon'   => $url === '' ? advanced_sponsor_manager_template('advanced_sponsor_soon', array()) : '',
                ));
        }

        return $out;
}

function advanced_sponsor_manager_render_card($sponsor, $slot_label = '')
{
        global $mybb;

        $url = advanced_sponsor_manager_safe_url($sponsor['url']);
        $soon = ($sponsor['status'] == 'soon' || $url === '');

        // Click counts are internal by default: the public card never prints
        // them, but a staff member - or every visitor when asm_show_clicks is on
        // - sees a small counter.
        $show_all = !empty($mybb->settings['asm_show_clicks']);
        $staff = !empty($mybb->usergroup['cancp']);
        $clicks_badge = '';
        if(!$soon && ($show_all || $staff))
        {
                $note = $show_all ? 'Tıklama sayısı' : 'Yalnızca yönetim görür';
                $clicks_badge = '<span class="asm-clicks" title="'.$note.'"><i class="fa-solid fa-arrow-pointer"></i> '.my_number_format((int)$sponsor['clicks']).' tıklama</span>';
        }

        $vars = array(
                'href'       => $soon ? '#' : advanced_sponsor_manager_track_url($sponsor['sid']),
                'target'     => $soon ? '' : advanced_sponsor_manager_target_attr(),
                'image'      => advanced_sponsor_manager_image_or_placeholder($sponsor['logo']),
                'title'      => htmlspecialchars_uni($sponsor['title']),
                'tagline'    => htmlspecialchars_uni($sponsor['tagline']),
                'status'     => $soon ? 'soon' : $sponsor['status'],
                'slot_label' => htmlspecialchars_uni($slot_label),
                'button'     => $soon ? 'Yakında' : 'İncele',
                'clicks'     => my_number_format((int)$sponsor['clicks']),
                'clicks_badge' => $clicks_badge,
                'soon'       => $soon ? advanced_sponsor_manager_template('advanced_sponsor_soon', array()) : '',
                'edge'       => htmlspecialchars_uni($sponsor['edge']),
        );

        switch($sponsor['slot'])
        {
                case 'forum':
                        $vars['label'] = htmlspecialchars_uni($sponsor['_label']);
                        $vars['products'] = advanced_sponsor_manager_render_products($sponsor['sid']);
                        return advanced_sponsor_manager_template('advanced_sponsor_forum', $vars);

                case 'edge':
                        return advanced_sponsor_manager_template('advanced_sponsor_edge', $vars);

                default:
                        return advanced_sponsor_manager_template('advanced_sponsor_single', $vars);
        }
}

/**
 * Render one zone into a single HTML string. Returns '' when the zone is empty
 * so the surrounding template keeps no stray wrapper.
 */
/**
 * The small overline above a card heading. Board-wide label plus a hint of the
 * slot, so a "sponsor" and a "sponsor group" are distinguishable at a glance.
 */
function advanced_sponsor_manager_card_label($slot)
{
        global $mybb;

        $label = trim((string)$mybb->settings['asm_label']);
        if($label === '')
        {
                $label = 'Sponsor';
        }

        if($slot === 'duo')
        {
                return $label.' Grubu';
        }

        return $label;
}

function advanced_sponsor_manager_render_zone($position, $edge = '', $fid = 0, $limit = 0)
{
        if(!advanced_sponsor_manager_visible_to_current_user())
        {
                return '';
        }

        $sponsors = advanced_sponsor_manager_fetch($position, $edge, $fid, $limit);
        if(!$sponsors)
        {
                return '';
        }

        if($position === 'edge')
        {
                $cards = '';
                foreach($sponsors as $sponsor)
                {
                        $cards .= advanced_sponsor_manager_render_card($sponsor, advanced_sponsor_manager_card_label($sponsor['slot']));
                }

                return $cards;
        }

        $cards = '';
        $singles = array();

        foreach($sponsors as $sponsor)
        {
                if($sponsor['slot'] == 'duo')
                {
                        $singles[] = $sponsor;
                }
                else
                {
                        $cards .= advanced_sponsor_manager_render_card($sponsor, advanced_sponsor_manager_card_label($sponsor['slot']));
                }
        }

        if($singles)
        {
                $items = '';
                foreach($singles as $sponsor)
                {
                        $items .= advanced_sponsor_manager_render_card($sponsor, advanced_sponsor_manager_card_label($sponsor['slot']));
                }

                $cards .= advanced_sponsor_manager_template('advanced_sponsor_duo', array('items' => $items));
        }

        return advanced_sponsor_manager_template('advanced_sponsor_zone', array(
                'position' => htmlspecialchars_uni($position),
                'cards'    => $cards,
        ));
}

/**
 * Category sponsor card for one forum id. The board asked for this to show on
 * the "high traffic / ad category" pages, which is why it is fetched by fid
 * rather than injected board-wide: a sponsor bought for one section must not
 * appear on every other section.
 */
function advanced_sponsor_manager_render_category($fid)
{
        global $db, $mybb;

        if($fid <= 0 || !advanced_sponsor_manager_visible_to_current_user())
        {
                return '';
        }

        $label = trim($mybb->settings['asm_label']) !== '' ? $mybb->settings['asm_label'] : 'Sponsor';

        $query = $db->simple_select('asm_sponsors', '*',
                "slot='forum' AND status IN ('active','soon') AND (fid='".(int)$fid."' OR fid='0')",
                array('order_by' => 'disporder', 'order_dir' => 'asc', 'limit' => 3));

        $out = '';
        while($sponsor = $db->fetch_array($query))
        {
                $sponsor['_label'] = $label;
                $out .= advanced_sponsor_manager_render_card($sponsor, $label);
        }

        return $out;
}

/* ------------------------------------------------------------- hooks --- */

/**
 * global_start runs before global.php evals the header and footer, so this is
 * where the board-wide placeholders have to be filled. It also caches the ModCP
 * panel decision once, before any page logic asks for it.
 */
function advanced_sponsor_manager_global_start()
{
        global $mybb, $lang;

        $GLOBALS['advanced_sponsor_manager_panel_ok'] = false;
        $GLOBALS['asm_styles_done'] = false;

        if(!advanced_sponsor_manager_enabled())
        {
                return;
        }

        advanced_sponsor_manager_board_placeholders();

        if(empty($mybb->settings['asm_modcp_enabled']))
        {
                return;
        }

        // The "Sponsor Özel Yönetim" usergroup permission, or any admin.
        if(!empty($mybb->usergroup['asm_sponsor_panel']) || !empty($mybb->usergroup['cancp']))
        {
                $GLOBALS['advanced_sponsor_manager_panel_ok'] = true;
        }
}

/**
 * global_start has already filled the top/bottom variables by the time the page
 * exists, so this hook only paints the stylesheet into <head> and keeps the
 * sticky edge banners out of the flow.
 */
function advanced_sponsor_manager_pre_output($contents)
{
        if(!advanced_sponsor_manager_enabled() || !is_string($contents) || $contents === '')
        {
                return $contents;
        }

        // The ACP builds its own pages and never carries this markup.
        if(defined('IN_ADMINCP'))
        {
                return $contents;
        }

        $left = advanced_sponsor_manager_render_zone('edge', 'left');
        $right = advanced_sponsor_manager_render_zone('edge', 'right');

        if($left !== '' || $right !== '')
        {
                $edges = "\n".'<div class="asm-edge-wrap">'.$left.$right.'</div>';
                if(strpos($contents, '<div id="container">') !== false)
                {
                        $contents = str_replace('<div id="container">', '<div id="container">'.$edges, $contents);
                }
                else
                {
                        $contents = str_replace('</body>', $edges."\n".'</body>', $contents);
                }
        }

        $contents = str_replace('</head>', advanced_sponsor_manager_styles()."\n".'</head>', $contents);

        return $contents;
}

/**
 * Fills the header/footer placeholders. Called from global_start, which MyBB
 * runs before the header template is eval()'d, so the variables already exist
 * when the page is assembled.
 *
 * The top/bottom zones are homepage-only: the header/footer templates are
 * shared with forumdisplay/showthread, so without the THIS_SCRIPT guard the
 * homepage banners reappeared inside every category. Those pages get the
 * category slot and the sticky edge banner instead.
 */
function advanced_sponsor_manager_board_placeholders()
{
        $GLOBALS['advanced_sponsors_top'] = '';
        $GLOBALS['advanced_sponsors_bottom'] = '';

        if(defined('THIS_SCRIPT') && THIS_SCRIPT !== 'index.php')
        {
                return;
        }

        $GLOBALS['advanced_sponsors_top'] = advanced_sponsor_manager_render_zone('top');
        $GLOBALS['advanced_sponsors_bottom'] = advanced_sponsor_manager_render_zone('bottom');
}

function advanced_sponsor_manager_forum_start()
{
        global $fid;

        if(advanced_sponsor_manager_enabled())
        {
                $GLOBALS['advanced_category_sponsor'] = advanced_sponsor_manager_render_category((int)$fid);
        }
}

function advanced_sponsor_manager_thread_start()
{
        global $thread, $GLOBALS;

        if(advanced_sponsor_manager_enabled() && isset($thread['fid']))
        {
                $GLOBALS['advanced_category_sponsor'] = advanced_sponsor_manager_render_category((int)$thread['fid']);
                $GLOBALS['asm_thread_ad'] = advanced_sponsor_manager_render_thread_ad((int)$thread['tid']);
        }
}

/* ------------------------------------- moderator thread-level ads --- */

/**
 * Fills the picker on the new-thread form. newthread_start runs after $fid is
 * resolved, so the moderator check has the right forum.
 */
function advanced_sponsor_manager_newthread_start()
{
        $GLOBALS['asm_thread_ad_selector'] = advanced_sponsor_manager_thread_ad_selector();
}

/**
 * A moderator picks one sponsor for the thread they are starting. The picker
 * is only offered to users who actually moderate that forum, and the sponsor
 * list is limited to that forum's category sponsors - a moderator of fid=2
 * cannot attach fid=9's sponsor.
 */
function advanced_sponsor_manager_thread_ad_selector()
{
        global $db, $mybb, $fid;

        if(empty($mybb->settings['asm_thread_ad_enabled']) || !advanced_sponsor_manager_enabled())
        {
                return '';
        }

        $fid = (int)$fid;
        if($fid <= 0 || (int)$mybb->user['uid'] <= 0 || !is_moderator($fid))
        {
                return '';
        }

        $query = $db->simple_select('asm_sponsors', 'sid,title',
                "slot='forum' AND status='active' AND fid='{$fid}'",
                array('order_by' => 'disporder', 'order_dir' => 'asc'));

        $options = '<option value="0">— Reklam yok —</option>';
        $count = 0;
        while($row = $db->fetch_array($query))
        {
                ++$count;
                $options .= '<option value="'.(int)$row['sid'].'">'.htmlspecialchars_uni($row['title']).'</option>';
        }

        if($count === 0)
        {
                return '';
        }

        return '<tr><td class="trow1"><strong>Konu reklamı</strong><br /><span class="smalltext">Bu konunun üstünde görünecek kategori sponsorunu seçin.</span></td>'
                . '<td class="trow1"><select name="asm_thread_sid" class="textbox">'.$options.'</select></td></tr>';
}

/**
 * Persist the moderator's choice for the just-created thread. Runs from
 * newthread_do_newthread_end, where $tid and $fid are in scope.
 */
function advanced_sponsor_manager_thread_ad_save()
{
        global $db, $mybb, $tid, $fid;

        $t = (int)$tid;
        $f = (int)$fid;

        if($t <= 0 || $f <= 0 || empty($mybb->settings['asm_thread_ad_enabled']) || !advanced_sponsor_manager_enabled())
        {
                return;
        }

        if(!is_moderator($f))
        {
                return;
        }

        $sid = $mybb->get_input('asm_thread_sid', MyBB::INPUT_INT);

        // Re-posting a draft can run this path twice for one thread.
        $db->delete_query('asm_thread_ads', "tid='{$t}'");

        if($sid <= 0)
        {
                return;
        }

        // The sponsor must belong to this forum. Without the check a crafted
        // POST could attach any sponsor id, including a hidden/other category.
        $owned = $db->fetch_field($db->simple_select('asm_sponsors', 'sid',
                "sid='{$sid}' AND slot='forum' AND status='active' AND fid='{$f}'"), 'sid');
        if(!$owned)
        {
                return;
        }

        $db->insert_query('asm_thread_ads', array(
                'tid' => $t,
                'sid' => (int)$sid,
                'uid' => (int)$mybb->user['uid'],
                'dateline' => TIME_NOW,
        ));
}

/**
 * The banner a moderator attached to a thread. Rendered above the first post,
 * below the category slot, so the two never look like the same ad.
 */
function advanced_sponsor_manager_render_thread_ad($tid)
{
        global $db;

        $tid = (int)$tid;
        if($tid <= 0 || !advanced_sponsor_manager_enabled() || !advanced_sponsor_manager_visible_to_current_user())
        {
                return '';
        }

        $sponsor = $db->fetch_array($db->simple_select('asm_thread_ads', 'sid', "tid='{$tid}'", array('limit' => 1)));
        if(empty($sponsor['sid']))
        {
                return '';
        }

        $row = $db->fetch_array($db->simple_select('asm_sponsors', '*',
                "sid='".(int)$sponsor['sid']."' AND status='active'", array('limit' => 1)));
        if(empty($row['sid']))
        {
                return '';
        }

        $row['_label'] = '';
        return '<div class="asm-thread-ad">'.advanced_sponsor_manager_render_card($row, 'Konu Sponsoru').'</div>';
}

/* --------------------------------------------------------- click path --- */

function advanced_sponsor_manager_handle_click()
{
        global $db, $mybb;

        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : 0;
        $iid = isset($_GET['iid']) ? (int)$_GET['iid'] : 0;

        $fallback = htmlspecialchars_uni($mybb->settings['bburl']).'/index.php';

        if($sid <= 0 || !advanced_sponsor_manager_enabled())
        {
                header('Location: '.$fallback);
                exit;
        }

        $sponsor = $db->fetch_array($db->simple_select('asm_sponsors', '*', "sid='{$sid}'"));
        if(!$sponsor || !in_array($sponsor['status'], array('active', 'soon')))
        {
                header('Location: '.$fallback);
                exit;
        }

        $url = advanced_sponsor_manager_safe_url($sponsor['url']);
        $target_sid = $sid;
        $item = null;

        if($iid > 0)
        {
                $item = $db->fetch_array($db->simple_select('asm_items', '*', "iid='{$iid}' AND sid='{$sid}' AND active='1'"));
                if($item)
                {
                        $item_url = advanced_sponsor_manager_safe_url($item['url']);
                        if($item_url !== '')
                        {
                                $url = $item_url;
                        }
                }
        }

        if($url === '')
        {
                header('Location: '.$fallback);
                exit;
        }

        // The tracking code is appended here, server-side, from data the sponsor
        // panel never exposes. A sponsor cannot strip or replace it.
        $affiliate = trim($sponsor['affiliate']);
        if($affiliate === '')
        {
                $affiliate = trim($mybb->settings['asm_affiliate_default']);
        }

        if($affiliate !== '')
        {
                $url .= (strpos($url, '?') !== false ? '&' : '?').$affiliate;
        }

        advanced_sponsor_manager_count_click($target_sid, $item ? (int)$item['iid'] : 0);

        header('Location: '.$url);
        exit;
}

function advanced_sponsor_manager_count_click($sid, $iid = 0)
{
        global $db, $mybb;

        $ip = get_ip();

        $dedupe = (int)$mybb->settings['asm_click_dedupe'];
        if($dedupe > 0)
        {
                $where = "sid='".(int)$sid."' AND ip='".$db->escape_string($ip)."' AND dateline>'".(TIME_NOW - $dedupe)."'";
                $recent = $db->simple_select('asm_clicks', 'cid', $where, array('limit' => 1));
                if($db->num_rows($recent))
                {
                        return;
                }
        }

        $db->insert_query('asm_clicks', array(
                'sid' => (int)$sid,
                'iid' => (int)$iid,
                'uid' => (int)$mybb->user['uid'],
                'ip' => $db->escape_string($ip),
                'dateline' => TIME_NOW,
        ));

        $db->write_query("UPDATE ".TABLE_PREFIX."asm_sponsors SET clicks=clicks+1 WHERE sid='".(int)$sid."'");

        if($iid > 0)
        {
                $db->write_query("UPDATE ".TABLE_PREFIX."asm_items SET clicks=clicks+1 WHERE iid='".(int)$iid."'");
        }
}

/* ------------------------------------------------------ ModCP sponsor --- */

function advanced_sponsor_manager_modcp_nav()
{
        global $nav_asm_sponsor, $lang;

        $nav_asm_sponsor = '';

        if(empty($GLOBALS['advanced_sponsor_manager_panel_ok']))
        {
                return;
        }

        $nav_asm_sponsor = '<tr><td class="trow1 smalltext"><a href="modcp.php?action=asm_sponsor" class="modcp_nav_item"><i class="fa-solid fa-handshake"></i> Sponsor Özel Yönetim</a></td></tr>';
}

function advanced_sponsor_manager_modcp_page()
{
        global $mybb, $db, $lang, $templates, $headerinclude, $header, $footer, $theme;

        if(empty($GLOBALS['advanced_sponsor_manager_panel_ok']) || $mybb->get_input('action') !== 'asm_sponsor')
        {
                return;
        }

        if(!advanced_sponsor_manager_enabled())
        {
                error('Sponsor yönetimi şu anda kapalı.');
        }

        $uid = (int)$mybb->user['uid'];

        if($mybb->request_method == 'post')
        {
                verify_post_check($mybb->get_input('my_post_key'));

                if($mybb->get_input('asm_do') === 'save' && $mybb->get_input('sid', MyBB::INPUT_INT))
                {
                        $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
                        advanced_sponsor_manager_panel_owned($sid, $uid);

                        $update = array(
                                'title' => $db->escape_string($mybb->get_input('title')),
                                'tagline' => $db->escape_string($mybb->get_input('tagline')),
                                'logo' => $db->escape_string($mybb->get_input('logo')),
                                'status' => in_array($mybb->get_input('asm_status'), array('active', 'draft')) ? $mybb->get_input('asm_status') : 'draft',
                                'updated' => TIME_NOW,
                        );

                        $new_url = advanced_sponsor_manager_safe_url($mybb->get_input('url'));
                        if($new_url !== '')
                        {
                                $update['url'] = $db->escape_string($new_url);
                        }

                        // The sponsor may propose a link, but every outbound hop still
                        // goes through reklam.php, so the tracking code stays intact.
                        $db->update_query('asm_sponsors', $update, "sid='{$sid}'");

                        redirect('modcp.php?action=asm_sponsor&sid='.$sid, 'Sponsor bilgileri kaydedildi.');
                }

                if($mybb->get_input('asm_do') === 'save_item')
                {
                        $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
                        advanced_sponsor_manager_panel_owned($sid, $uid);

                        $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
                        $item = array(
                                'sid' => $sid,
                                'title' => $db->escape_string($mybb->get_input('item_title')),
                                'image' => $db->escape_string($mybb->get_input('item_image')),
                                'url' => $db->escape_string(advanced_sponsor_manager_safe_url($mybb->get_input('item_url'))),
                                'active' => $mybb->get_input('item_active', MyBB::INPUT_INT) ? 1 : 0,
                                'disporder' => $mybb->get_input('item_disporder', MyBB::INPUT_INT),
                        );

                        if($iid > 0)
                        {
                                $owned = $db->fetch_field($db->simple_select('asm_items', 'iid', "iid='{$iid}' AND sid='{$sid}'"), 'iid');
                                if($owned)
                                {
                                        $db->update_query('asm_items', $item, "iid='{$iid}'");
                                }
                        }
                        else
                        {
                                $item['dateline'] = TIME_NOW;
                                $db->insert_query('asm_items', $item);
                        }

                        redirect('modcp.php?action=asm_sponsor&sid='.$sid, 'Ürün kaydedildi.');
                }

                if($mybb->get_input('asm_do') === 'delete_item')
                {
                        $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
                        advanced_sponsor_manager_panel_owned($sid, $uid);

                        $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
                        $db->delete_query('asm_items', "iid='{$iid}' AND sid='{$sid}'");

                        redirect('modcp.php?action=asm_sponsor&sid='.$sid, 'Ürün silindi.');
                }
        }

        $page_title = 'Sponsor Özel Yönetim';

        $own = $db->simple_select('asm_sponsors', '*', "uid='{$uid}'", array('order_by' => 'disporder', 'order_dir' => 'asc'));

        $list = '';
        $count = 0;
        while($sponsor = $db->fetch_array($own))
        {
                ++$count;
                $status_label = advanced_sponsor_manager_statuses();
                $list .= '<tr><td class="trow1"><strong>'.htmlspecialchars_uni($sponsor['title']).'</strong><br /><small>'.htmlspecialchars_uni(advanced_sponsor_manager_slots()[$sponsor['slot']] ?? $sponsor['slot']).'</small></td>'
                        . '<td class="trow1 align_center">'.htmlspecialchars_uni($status_label[$sponsor['status']] ?? $sponsor['status']).'</td>'
                        . '<td class="trow1 align_center">'.my_number_format((int)$sponsor['clicks']).'</td>'
                        . '<td class="trow1 align_center"><a href="modcp.php?action=asm_sponsor&amp;sid='.(int)$sponsor['sid'].'">düzenle</a></td></tr>';
        }

        if($count == 0)
        {
                $list = '<tr><td class="trow1" colspan="4">Üzerinize tanımlı bir sponsor kaydı yok. Yönetimle iletişime geçin.</td></tr>';
        }

        $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
        $form = '';
        $items_block = '';
        $sponsor = null;

        if($sid > 0)
        {
                $sponsor = advanced_sponsor_manager_panel_owned($sid, $uid);

                $selected = $sponsor['status'] == 'active' ? 'active' : 'draft';

                $form = '<form action="modcp.php?action=asm_sponsor" method="post">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                        <input type="hidden" name="asm_do" value="save" />
                        <input type="hidden" name="sid" value="'.(int)$sponsor['sid'].'" />
                        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
                        <tr><td class="thead" colspan="2"><strong>'.htmlspecialchars_uni($sponsor['title']).'</strong></td></tr>
                        <tr><td class="trow1" width="25%">Başlık</td><td class="trow1"><input type="text" name="title" value="'.htmlspecialchars_uni($sponsor['title']).'" class="textbox" style="width:70%" /></td></tr>
                        <tr><td class="trow2">Slogan</td><td class="trow2"><input type="text" name="tagline" value="'.htmlspecialchars_uni($sponsor['tagline']).'" class="textbox" style="width:70%" /></td></tr>
                        <tr><td class="trow1">Logo URL</td><td class="trow1"><input type="text" name="logo" value="'.htmlspecialchars_uni($sponsor['logo']).'" class="textbox" style="width:70%" /></td></tr>
                        <tr><td class="trow2">Hedef link</td><td class="trow2"><input type="text" name="url" value="'.htmlspecialchars_uni($sponsor['url']).'" class="textbox" style="width:70%" /></td></tr>
                        <tr><td class="trow1">Durum</td><td class="trow1"><select name="asm_status"><option value="active"'.($selected == 'active' ? ' selected="selected"' : '').'>Aktif</option><option value="draft"'.($selected == 'draft' ? ' selected="selected"' : '').'>Taslak</option></select></td></tr>
                        <tr><td class="trow2" colspan="2" align="center"><input type="submit" class="button" value="Kaydet" /></td></tr>
                        </table></form>';

                $item_query = $db->simple_select('asm_items', '*', "sid='".(int)$sponsor['sid']."'", array('order_by' => 'disporder', 'order_dir' => 'asc'));
                $item_rows = '';
                while($item = $db->fetch_array($item_query))
                {
                        $item_rows .= '<tr><td class="trow1">'.htmlspecialchars_uni($item['title']).'</td>'
                                . '<td class="trow1 align_center">'.($item['active'] ? 'aktif' : 'kapalı').'</td>'
                                . '<td class="trow1 align_center"><form action="modcp.php?action=asm_sponsor" method="post"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="asm_do" value="delete_item" /><input type="hidden" name="sid" value="'.(int)$sponsor['sid'].'" /><input type="hidden" name="iid" value="'.(int)$item['iid'].'" /><input type="submit" class="button" value="Sil" /></form></td></tr>';
                }

                if($item_rows === '')
                {
                        $item_rows = '<tr><td class="trow1" colspan="3">Henüz ürün eklenmemiş.</td></tr>';
                }

                $items_block = '<form action="modcp.php?action=asm_sponsor" method="post">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                        <input type="hidden" name="asm_do" value="save_item" />
                        <input type="hidden" name="sid" value="'.(int)$sponsor['sid'].'" />
                        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
                        <tr><td class="thead" colspan="3"><strong>Ürünler</strong></td></tr>
                        '.$item_rows.'
                        <tr><td class="trow2">Yeni ürün başlığı</td><td class="trow2"><input type="text" name="item_title" class="textbox" /></td><td class="trow2"><input type="text" name="item_image" class="textbox" placeholder="görsel URL" /></td></tr>
                        <tr><td class="trow1">Hedef link</td><td class="trow1"><input type="text" name="item_url" class="textbox" /></td><td class="trow1">Sıra <input type="text" name="item_disporder" value="0" size="3" class="textbox" /> <label><input type="checkbox" name="item_active" value="1" checked="checked" /> aktif</label></td></tr>
                        <tr><td class="trow2" colspan="3" align="center"><input type="submit" class="button" value="Ürünü Kaydet" /></td></tr>
                        </table></form>';
        }

        $body = '<br />
<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
<tr><td class="thead" colspan="4"><strong>Sponsorlarım</strong></td></tr>
<tr><td class="tcat">Sponsor</td><td class="tcat" align="center">Durum</td><td class="tcat" align="center">Tıklama</td><td class="tcat" align="center">İşlem</td></tr>
'.$list.'
</table>
<br />'.$form.'<br />'.$items_block;

        // Render the whole page here and stop modcp.php before it assembles its
        // own default page. modcp_end is late enough that $modcp_nav exists and
        // early enough that nothing has been printed yet.
        $page = '<html>
<head>
<title>'.htmlspecialchars_uni($mybb->settings['bbname']).' - '.htmlspecialchars_uni($page_title).'</title>
'.$headerinclude.'
</head>
<body>
'.$header.'
<table width="100%" border="0" align="center">
<tr>
'.$GLOBALS['modcp_nav'].'
<td valign="top">
'.$body.'
</td>
</tr>
</table>
'.$footer.'
</body>
</html>';

        output_page($page);

        // modcp.php would otherwise fall through to its own default page and
        // print a second document.
        exit;
}

/**
 * Returns the sponsor when the current user owns it, and stops the request
 * otherwise. Ownership is the whole point: a sponsor can only touch their own
 * row.
 */
function advanced_sponsor_manager_panel_owned($sid, $uid)
{
        global $db;

        $sponsor = $db->fetch_array($db->simple_select('asm_sponsors', '*', "sid='".(int)$sid."'"));

        if(!$sponsor || (int)$sponsor['uid'] !== (int)$uid)
        {
                error_no_permission();
        }

        return $sponsor;
}

/* ----------------------------------------------------------- usergroup --- */

/**
 * Draws the "Sponsor Özel Yönetim" checkbox inside the usergroup editor.
 *
 * It rides on the core "Moderation and Administration Options" row rather than
 * appending a new one, so it sits next to the other access flags instead of at
 * the bottom of an unrelated tab. The row is only touched when its description
 * is the admin-options one, which keeps the hook inert for every other row on
 * every other page.
 */
function advanced_sponsor_manager_groups_form_row(&$args)
{
        global $form, $lang, $mybb;

        if(!is_object($form) || empty($args['row_options']))
        {
                return;
        }

        if(!isset($args['title']) || $args['title'] !== $lang->moderation_administration_options)
        {
                return;
        }

        $gid = (int)$mybb->input['gid'];

        $checked = false;
        if($gid > 0)
        {
                $group = $GLOBALS['usergroup'];
                if(is_array($group) && (int)$group['gid'] === $gid)
                {
                        $checked = !empty($group['asm_sponsor_panel']);
                }
        }

        $bit = $form->generate_check_box(
                'asm_sponsor_panel',
                1,
                'Sponsor Özel Yönetim',
                array('checked' => $checked)
        );

        $args['content'] .= '<div class="group_settings_bit">'.$bit.'</div>';
}

function advanced_sponsor_manager_groups_edit_commit()
{
        global $db, $mybb, $usergroup;

        if(!empty($usergroup['gid']))
        {
                $db->update_query('usergroups', array(
                        'asm_sponsor_panel' => $mybb->get_input('asm_sponsor_panel', MyBB::INPUT_INT) ? 1 : 0,
                ), "gid='".(int)$usergroup['gid']."'");
        }
}

function advanced_sponsor_manager_groups_add_commit()
{
        global $db, $mybb;

        $gid = (int)$db->insert_id();
        if($gid > 0)
        {
                $db->update_query('usergroups', array(
                        'asm_sponsor_panel' => $mybb->get_input('asm_sponsor_panel', MyBB::INPUT_INT) ? 1 : 0,
                ), "gid='{$gid}'");
        }
}

/* --------------------------------------------------------------- CSS --- */

function advanced_sponsor_manager_styles()
{
        static $done = false;
        if($done)
        {
                return '';
        }
        $done = true;

        return '<style type="text/css" id="asm-styles">
.asm-zone { margin: 16px 0; }
.asm-zone--top { margin: 12px 0 22px; }
.asm-card, .asm-forum-card { position: relative; display: flex; gap: 18px; overflow: hidden; border: 1px solid rgba(96,165,250,.18); border-radius: 16px; background: linear-gradient(135deg, rgba(34,48,74,.94), rgba(12,19,34,.97)); box-shadow: 0 12px 32px rgba(2,8,23,.5); backdrop-filter: blur(10px); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.asm-card:hover, .asm-forum-card:hover { transform: translateY(-2px); border-color: rgba(96,165,250,.45); box-shadow: 0 18px 40px rgba(2,8,23,.6); }
.asm-card-media { position: relative; flex: 0 0 42%; min-height: 180px; overflow: hidden; background: var(--bg-input); }
.asm-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
.asm-card:hover .asm-card-media img { transform: scale(1.04); }
.asm-card-body { display: flex; flex-direction: column; gap: 8px; padding: 20px 22px; }
.asm-card-kicker { font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--accent); }
.asm-card-title { margin: 0; font-size: 20px; line-height: 1.25; }
.asm-card-title a { color: inherit; }
.asm-card-tagline { margin: 0; color: var(--text-muted); }
.asm-card-foot { display: flex; align-items: center; gap: 12px; margin-top: auto; }
.asm-clicks { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; padding: 4px 10px; border: 1px solid rgba(148,163,184,.35); border-radius: 999px; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; }
.asm-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 999px; color: #eff6ff; background: linear-gradient(145deg, #60a5fa, #2563eb); font-weight: 700; box-shadow: 0 6px 18px rgba(37,99,235,.35); }
.asm-btn:hover { color: #fff; filter: brightness(1.08); }
.asm-duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin: 18px 0; }
.asm-duo .asm-card { flex-direction: column; }
.asm-duo .asm-card-media { flex: 0 0 auto; min-height: 150px; }
.asm-status-passive, .asm-status-draft { opacity: .55; filter: grayscale(.4); }
.asm-soon { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(8,15,28,.72); }
.asm-soon-badge { padding: 7px 14px; border: 1px solid rgba(250,204,21,.5); border-radius: 999px; color: #fde68a; background: rgba(250,204,21,.12); font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
/* Homepage banner: half the height of the base card. */
.asm-zone--top .asm-card { gap: 14px; }
.asm-zone--top .asm-card-media { flex: 0 0 30%; min-height: 90px; }
.asm-zone--top .asm-card-body { padding: 12px 16px; gap: 4px; }
.asm-zone--top .asm-card-kicker { font-size: 10px; }
.asm-zone--top .asm-card-title { font-size: 16px; }
.asm-zone--top .asm-card-tagline { font-size: 12.5px; }
.asm-zone--top .asm-btn { padding: 7px 15px; font-size: 12.5px; }
.asm-zone--top .asm-duo { margin: 0; gap: 12px; }
.asm-zone--top .asm-duo .asm-card-media { min-height: 82px; }
/* Category sponsor card inside a forum: also half height. */
.asm-forum-slot { margin: 14px 0; }
.asm-forum-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 6px; }
.asm-forum-label { font-size: 12px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--vip); }
.asm-forum-hint { font-size: 12px; color: var(--text-dim); }
.asm-forum-card { flex-direction: column; padding: 9px 12px; }
.asm-forum-brand { display: flex; gap: 10px; align-items: center; }
.asm-forum-brand img { width: 36px; height: 36px; border-radius: 9px; object-fit: cover; }
.asm-forum-brand h3 { margin: 0; font-size: 14px; }
.asm-forum-brand p { margin: 2px 0 0; font-size: 12px; color: var(--text-muted); }
.asm-forum-products { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px; margin-top: 8px; }
.asm-product { position: relative; display: flex; flex-direction: column; gap: 6px; padding: 7px; border: 1px solid var(--border); border-radius: 10px; background: rgba(17,28,48,.7); }
.asm-product-image { position: relative; display: block; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 7px; background: var(--bg-input); }
.asm-product-image img { width: 100%; height: 100%; object-fit: cover; }
.asm-product-title { font-size: 12px; font-weight: 600; color: var(--text-main); }
/* Edge banner: +10% width, +80% height. */
.asm-edge-wrap { position: fixed; inset: 0; z-index: 5; pointer-events: none; }
.asm-edge { position: absolute; top: 50%; transform: translateY(-50%); width: 154px; pointer-events: auto; }
.asm-edge--left { left: 8px; }
.asm-edge--right { right: 8px; }
.asm-edge img { width: 100%; height: 142px; object-fit: cover; border-radius: 14px; border: 1px solid rgba(96,165,250,.25); box-shadow: 0 10px 26px rgba(2,8,23,.5); }
.asm-edge-title { display: block; margin-top: 6px; font-size: 11px; text-align: center; color: var(--text-muted); }
/* Thread ad (moderator): compact banner above the first post. */
.asm-thread-ad { margin: 12px 0; }
.asm-thread-ad .asm-card { align-items: center; gap: 14px; padding: 0; }
.asm-thread-ad .asm-card-media { flex: 0 0 30%; min-height: 84px; }
.asm-thread-ad .asm-card-body { padding: 10px 16px; gap: 4px; }
.asm-thread-ad .asm-card-title { font-size: 15px; }
.asm-thread-ad .asm-card-tagline { font-size: 12.5px; }
.asm-thread-ad .asm-btn { padding: 6px 14px; font-size: 12.5px; }
.asm-modlink { display: inline-flex; align-items: center; gap: 6px; margin: 6px 0; padding: 5px 12px; border: 1px dashed rgba(96,165,250,.4); border-radius: 999px; font-size: 12px; color: var(--accent); }
@media (max-width: 1200px) { .asm-edge-wrap { display: none; } }
@media (max-width: 760px) { .asm-card { flex-direction: column; } .asm-card-media, .asm-zone--top .asm-card-media { flex: 0 0 auto; min-height: 130px; } }
</style>';
}

/* ----------------------------------------------------------------- ACP --- */

function advanced_sponsor_manager_acp_meta()
{
        global $page, $plugins;

        $sub_menu = array();
        $sub_menu['10'] = array('id' => 'sponsors', 'title' => 'Sponsorlar', 'link' => 'index.php?module=advanced_sponsor_manager-sponsors');
        $sub_menu['20'] = array('id' => 'items', 'title' => 'Ürünler', 'link' => 'index.php?module=advanced_sponsor_manager-items');
        // Raw '&': add_menu_items() runs the link through htmlspecialchars_uni(),
        // so a pre-escaped &amp; would render as &amp;amp;.
        $sub_menu['30'] = array('id' => 'settings', 'title' => 'Ayarlar', 'link' => 'index.php?module=config-settings&action=change&gid='.advanced_sponsor_manager_gid());

        $sub_menu = $plugins->run_hooks('admin_advanced_sponsor_manager_menu', $sub_menu);

        $page->add_menu_item('Sponsor Yönetimi', 'advanced_sponsor_manager', 'index.php?module=advanced_sponsor_manager', 67, $sub_menu);

        return true;
}

function advanced_sponsor_manager_acp_action_handler($action)
{
        global $page;

        $page->active_module = 'advanced_sponsor_manager';

        $actions = array(
                'sponsors' => array('active' => 'sponsors', 'file' => 'index.php'),
                'items'    => array('active' => 'items', 'file' => 'index.php'),
        );

        if(isset($actions[$action]))
        {
                $page->active_action = $actions[$action]['active'];
                return $actions[$action]['file'];
        }

        $page->active_action = 'sponsors';
        return 'index.php';
}

function advanced_sponsor_manager_acp_permissions()
{
        return array(
                'name' => 'Sponsor Yönetimi',
                'permissions' => array(
                        'sponsors' => 'Sponsorları ekle, düzenle ve sil',
                        'items'    => 'Sponsor ürünlerini (vitrin kartları) yönet',
                ),
                'disporder' => 69,
        );
}

function advanced_sponsor_manager_acp_page()
{
        global $mybb, $db, $page, $lang, $form, $plugins;

        $action = $GLOBALS['page']->active_action;
        if($action !== 'items')
        {
                $action = 'sponsors';
        }

        $page->add_breadcrumb_item('Sponsor Yönetimi', 'index.php?module=advanced_sponsor_manager');
        $page->output_header('Sponsor Yönetimi');

        if(!advanced_sponsor_manager_is_installed())
        {
                $page->output_error('<b>asm_sponsors tablosu bulunamadı.</b> Eklentiyi devre dışı bırakıp yeniden etkinleştirin.');
                $page->output_footer();
                return;
        }

        // The click path needs reklam.php at the board root; rewrite it if the
        // file was lost (a fresh checkout, for instance).
        if(!file_exists(MYBB_ROOT.'reklam.php'))
        {
                advanced_sponsor_manager_write_redirect_script();
        }

        if($mybb->request_method == 'post')
        {
                verify_post_check($mybb->get_input('my_post_key'));
                advanced_sponsor_manager_acp_commit();
        }

        advanced_sponsor_manager_acp_stats();

        if($action === 'items')
        {
                advanced_sponsor_manager_acp_items();
        }
        else
        {
                advanced_sponsor_manager_acp_sponsors();
        }

        $page->output_footer();
}

/**
 * A quick usage summary — how many sponsors sit in each slot, how many clicks
 * have been recorded and which banner is "coming soon". Cheap enough to run on
 * every page load and it is the first thing an admin wants to see.
 */
function advanced_sponsor_manager_acp_stats()
{
        global $db, $page;

        $total = (int)$db->fetch_field($db->simple_select('asm_sponsors', 'COUNT(*) AS n'), 'n');
        $active = (int)$db->fetch_field($db->simple_select('asm_sponsors', 'COUNT(*) AS n', "status='active'"), 'n');
        $clicks = (int)$db->fetch_field($db->simple_select('asm_sponsors', 'SUM(clicks) AS n'), 'n');
        $items = (int)$db->fetch_field($db->simple_select('asm_items', 'COUNT(*) AS n'), 'n');

        $table = new Table;
        $table->construct_header('Toplam sponsor');
        $table->construct_header('Aktif');
        $table->construct_header('Vitrin ürünü');
        $table->construct_header('Toplam tıklama');
        $table->construct_cell((string)$total);
        $table->construct_cell((string)$active);
        $table->construct_cell((string)$items);
        $table->construct_cell(my_number_format($clicks));
        $table->construct_row();
        $table->output('Özet');
}

function advanced_sponsor_manager_acp_commit()
{
        global $db, $mybb, $page;

        $do = $mybb->get_input('asm_do');

        if($do === 'delete')
        {
                $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
                $db->delete_query('asm_sponsors', "sid='{$sid}'");
                $db->delete_query('asm_items', "sid='{$sid}'");
                $db->delete_query('asm_clicks', "sid='{$sid}'");
                log_admin_action('asm_sponsors', 'delete', $sid);
                flash_message('Sponsor silindi.', 'success');
                admin_redirect('index.php?module=advanced_sponsor_manager-sponsors');
        }

        if($do === 'toggle')
        {
                $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
                $current = $db->fetch_field($db->simple_select('asm_sponsors', 'status', "sid='{$sid}'"), 'status');
                $new = $current === 'active' ? 'passive' : 'active';
                $db->update_query('asm_sponsors', array('status' => $new, 'updated' => TIME_NOW), "sid='{$sid}'");
                flash_message('Sponsor durumu güncellendi.', 'success');
                admin_redirect('index.php?module=advanced_sponsor_manager-sponsors');
        }

        if($do === 'delete_item')
        {
                $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
                $db->delete_query('asm_items', "iid='{$iid}'");
                flash_message('Ürün silindi.', 'success');
                admin_redirect('index.php?module=advanced_sponsor_manager-items');
        }

        if($do === 'save_item')
        {
                $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
                $item = array(
                        'sid' => $mybb->get_input('sid', MyBB::INPUT_INT),
                        'title' => $db->escape_string($mybb->get_input('item_title')),
                        'image' => $db->escape_string($mybb->get_input('item_image')),
                        'url' => $db->escape_string(advanced_sponsor_manager_safe_url($mybb->get_input('item_url'))),
                        'active' => $mybb->get_input('item_active', MyBB::INPUT_INT) ? 1 : 0,
                        'disporder' => $mybb->get_input('item_disporder', MyBB::INPUT_INT),
                );

                if($iid > 0)
                {
                        $db->update_query('asm_items', $item, "iid='{$iid}'");
                }
                else
                {
                        $item['dateline'] = TIME_NOW;
                        $db->insert_query('asm_items', $item);
                }

                flash_message('Ürün kaydedildi.', 'success');
                admin_redirect('index.php?module=advanced_sponsor_manager-items');
        }

        if($do === 'save')
        {
                $sid = $mybb->get_input('sid', MyBB::INPUT_INT);

                $title = trim($mybb->get_input('title'));
                if($title === '')
                {
                        flash_message('Sponsor başlığı zorunlu.', 'error');
                        admin_redirect('index.php?module=advanced_sponsor_manager-sponsors');
                }

                $slots = advanced_sponsor_manager_slots();
                $slot = $mybb->get_input('slot');
                if(!isset($slots[$slot]))
                {
                        $slot = 'single';
                }

                $positions = advanced_sponsor_manager_positions();
                $position = $mybb->get_input('position');
                if(!isset($positions[$position]))
                {
                        $position = 'top';
                }

                $edges = advanced_sponsor_manager_edges();
                $edge = $mybb->get_input('edge');
                if(!isset($edges[$edge]))
                {
                        $edge = '';
                }

                $statuses = advanced_sponsor_manager_statuses();
                $status = $mybb->get_input('status');
                if(!isset($statuses[$status]))
                {
                        $status = 'active';
                }

                $data = array(
                        'title' => $db->escape_string($title),
                        'tagline' => $db->escape_string($mybb->get_input('tagline')),
                        'logo' => $db->escape_string($mybb->get_input('logo')),
                        'url' => $db->escape_string(advanced_sponsor_manager_safe_url($mybb->get_input('url'))),
                        // Written only here. The sponsor panel never submits this field.
                        'affiliate' => $db->escape_string(trim($mybb->get_input('affiliate'))),
                        'slot' => $db->escape_string($slot),
                        'position' => $db->escape_string($position),
                        'edge' => ($position === 'edge') ? $db->escape_string($edge) : '',
                        'fid' => ($slot === 'forum') ? $mybb->get_input('fid', MyBB::INPUT_INT) : 0,
                        'uid' => $mybb->get_input('uid', MyBB::INPUT_INT),
                        'status' => $db->escape_string($status),
                        'disporder' => $mybb->get_input('disporder', MyBB::INPUT_INT),
                        'updated' => TIME_NOW,
                );

                if($sid > 0)
                {
                        $db->update_query('asm_sponsors', $data, "sid='{$sid}'");
                        log_admin_action('asm_sponsors', 'update', $sid);
                        flash_message('Sponsor güncellendi.', 'success');
                }
                else
                {
                        $data['dateline'] = TIME_NOW;
                        $sid = (int)$db->insert_query('asm_sponsors', $data);
                        log_admin_action('asm_sponsors', 'add', $sid);
                        flash_message('Sponsor eklendi.', 'success');
                }

                admin_redirect('index.php?module=advanced_sponsor_manager-sponsors&sid='.$sid);
        }

        if($do === 'seed')
        {
                advanced_sponsor_manager_seed_demo();
                flash_message('Örnek sponsorlar eklendi.', 'success');
                admin_redirect('index.php?module=advanced_sponsor_manager-sponsors');
        }
}

function advanced_sponsor_manager_acp_sponsors()
{
        global $mybb, $db, $page, $form;

        $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
        $editing = null;

        if($sid > 0)
        {
                $editing = $db->fetch_array($db->simple_select('asm_sponsors', '*', "sid='{$sid}'"));
        }

        $slots = advanced_sponsor_manager_slots();
        $positions = advanced_sponsor_manager_positions();
        $statuses = advanced_sponsor_manager_statuses();
        $edges = advanced_sponsor_manager_edges();

        $slot_options = array();
        foreach($slots as $k => $v)
        {
                $slot_options[$k] = $v;
        }

        $position_options = array();
        foreach($positions as $k => $v)
        {
                $position_options[$k] = $v;
        }

        $status_options = array();
        foreach($statuses as $k => $v)
        {
                $status_options[$k] = $v;
        }

        $edge_options = array();
        foreach($edges as $k => $v)
        {
                $edge_options[$k] = $v;
        }

        $form = new Form('index.php?module=advanced_sponsor_manager-sponsors', 'post');
        $form_container = new FormContainer($editing ? 'Sponsoru Düzenle' : 'Yeni Sponsor Ekle');

        $form_container->output_row('Başlık <em>*</em>', 'Kartta görünen sponsor adı.', $form->generate_text_box('title', $editing ? $editing['title'] : '', array('id' => 'asm_title')));
        $form_container->output_row('Slogan', 'Kısa açıklama.', $form->generate_text_box('tagline', $editing ? $editing['tagline'] : '', array('id' => 'asm_tagline')));
        $form_container->output_row('Logo URL', 'Boş bırakılırsa tema görseli kullanılır.', $form->generate_text_box('logo', $editing ? $editing['logo'] : '', array('id' => 'asm_logo')));
        $form_container->output_row('Hedef link', 'Yalnızca http(s) kabul edilir. Link ziyaretçiye her zaman reklam.php üzerinden gider.', $form->generate_text_box('url', $editing ? $editing['url'] : '', array('id' => 'asm_url')));
        $form_container->output_row('Affiliate / takip kodu', 'Linklerin sonuna eklenir (örn. <code>ref=site&amp;utm_source=forum</code>). Boşsa genel ayar kullanılır. <strong>Sponsor panelinde gösterilmez.</strong>', $form->generate_text_box('affiliate', $editing ? $editing['affiliate'] : '', array('id' => 'asm_affiliate')));
        $form_container->output_row('Slot', 'Kartın ön yüzde nerede durduğu.', $form->generate_select_box('slot', $slot_options, $editing ? $editing['slot'] : 'single', array('id' => 'asm_slot')));
        $form_container->output_row('Konum', 'Üst/alt ya da kenar yerleşimi.', $form->generate_select_box('position', $position_options, $editing ? $editing['position'] : 'top', array('id' => 'asm_position')));
        $form_container->output_row('Kenar', 'Yalnızca konum "Kenar" ise geçerli.', $form->generate_select_box('edge', $edge_options, $editing ? $editing['edge'] : '', array('id' => 'asm_edge')));
        $form_container->output_row('Durum', '"Çok Yakında" seçilirse link pasif kalır ve kart üzerinde rozet görünür.', $form->generate_select_box('status', $status_options, $editing ? $editing['status'] : 'active', array('id' => 'asm_status')));
        $form_container->output_row('Sıra', 'Küçük değer önce gösterilir.', $form->generate_numeric_field('disporder', $editing ? $editing['disporder'] : 0, array('id' => 'asm_disporder', 'min' => 0)));
        $form_container->output_row('Sahip (uid)', 'Sponsor panelini kullanacak üyenin uid değeri. 0 = yalnızca yönetici görür.', $form->generate_numeric_field('uid', $editing ? $editing['uid'] : 0, array('id' => 'asm_uid', 'min' => 0)));
        $form_container->output_row('Kategori (forum)', 'Slot "Kategori / Forum Sponsor Kartı" ise hangi forumda görüneceği.', $form->generate_forum_select('fid', $editing ? $editing['fid'] : 0, array('id' => 'asm_fid', 'main_option' => 'Tüm forumlar')));

        $form_container->end();

        $buttons = array($form->generate_submit_button($editing ? 'Güncelle' : 'Ekle'));
        $form->output_submit_wrapper($buttons);

        if($editing)
        {
                echo '<input type="hidden" name="asm_do" value="save" />';
                echo '<input type="hidden" name="sid" value="'.(int)$editing['sid'].'" />';
        }
        else
        {
                echo '<input type="hidden" name="asm_do" value="save" />';
        }

        echo '<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
        $form->end();

        // ------------------------------------------------------------- list ---

        $table = new Table;
        $table->construct_header('Sponsor');
        $table->construct_header('Slot / Konum', array('class' => 'align_center', 'width' => '18%'));
        $table->construct_header('Durum', array('class' => 'align_center', 'width' => '10%'));
        $table->construct_header('Tıklama', array('class' => 'align_center', 'width' => '8%'));
        $table->construct_header('İşlem', array('class' => 'align_center', 'width' => '18%'));

        $query = $db->simple_select('asm_sponsors', '*', '', array('order_by' => 'disporder, sid', 'order_dir' => 'asc'));

        // One grouped pass instead of a COUNT per row: the list is short, but the
        // "ürünler (n)" hint would otherwise add a query per sponsor.
        $item_counts = array();
        $item_query = $db->simple_select('asm_items', 'sid, COUNT(*) AS n', '', array('group_by' => 'sid'));
        while($c = $db->fetch_array($item_query))
        {
                $item_counts[(int)$c['sid']] = (int)$c['n'];
        }

        $rows = 0;
        while($row = $db->fetch_array($query))
        {
                ++$rows;

                $row['_item_count'] = $item_counts[(int)$row['sid']] ?? 0;

                $slot_label = $slots[$row['slot']] ?? $row['slot'];
                $pos_label = $positions[$row['position']] ?? $row['position'];
                if($row['position'] === 'edge' && $row['edge'] !== '')
                {
                        $pos_label .= ' / '.($edges[$row['edge']] ?? $row['edge']);
                }
                if($row['slot'] === 'forum' && $row['fid'] > 0)
                {
                        $fname = $db->fetch_field($db->simple_select('forums', 'name', "fid='".(int)$row['fid']."'"), 'name');
                        $pos_label .= '<br /><small>'.htmlspecialchars_uni($fname).'</small>';
                }

                $status_label = $statuses[$row['status']] ?? $row['status'];
                $toggle = $row['status'] === 'active' ? 'pasifleştir' : 'aktifleştir';

                $actions = '<a href="index.php?module=advanced_sponsor_manager-sponsors&amp;sid='.(int)$row['sid'].'">düzenle</a>'
                        . ' &middot; <a href="index.php?module=advanced_sponsor_manager-items&amp;sid='.(int)$row['sid'].'">ürünler ('.(int)$row['_item_count'].')</a>';

                $aff = trim($row['affiliate']) !== '' ? '<br /><small style="color:#64748b;">affiliate: <code>'.htmlspecialchars_uni($row['affiliate']).'</code></small>' : '';

                $table->construct_cell('<strong>'.htmlspecialchars_uni($row['title']).'</strong><br /><small>'.htmlspecialchars_uni($row['tagline']).'</small>'.$aff);
                $table->construct_cell(htmlspecialchars_uni($slot_label).'<br /><small>'.htmlspecialchars_uni($pos_label).'</small>', array('class' => 'align_center'));
                $table->construct_cell(htmlspecialchars_uni($status_label), array('class' => 'align_center'));
                $table->construct_cell(my_number_format((int)$row['clicks']), array('class' => 'align_center'));
                $table->construct_cell($actions.'<br />'
                        . '<form action="index.php?module=advanced_sponsor_manager-sponsors" method="post" style="display:inline;"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="asm_do" value="toggle" /><input type="hidden" name="sid" value="'.(int)$row['sid'].'" /><input type="submit" class="button" value="'.htmlspecialchars_uni($toggle).'" /></form> '
                        . '<form action="index.php?module=advanced_sponsor_manager-sponsors" method="post" style="display:inline;"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="asm_do" value="delete" /><input type="hidden" name="sid" value="'.(int)$row['sid'].'" /><input type="submit" class="button" value="sil" onclick="return confirm(\'Bu sponsor ve ürünleri silinsin mi?\');" /></form>', array('class' => 'align_center'));
                $table->construct_row();
        }

        if($rows == 0)
        {
                $table->construct_cell('Henüz sponsor eklenmemiş. Aşağıdaki düğmeyle örnek bir set oluşturabilirsiniz.', array('colspan' => 5, 'class' => 'align_center'));
                $table->construct_row();
        }

        $table->output('Sponsorlar');

        if($rows == 0)
        {
                $seed_form = new Form('index.php?module=advanced_sponsor_manager-sponsors', 'post');
                $seed_form->output_submit_wrapper(array($seed_form->generate_submit_button('Örnek sponsorları ekle')));
                echo '<input type="hidden" name="asm_do" value="seed" />';
                echo '<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
                $seed_form->end();
        }

        // A small anchor target so the "ürünler" list link lands on the item
        // manager further down the page.
        echo '<a name="asm_items" id="asm_items"></a>';
}

function advanced_sponsor_manager_acp_items()
{
        global $mybb, $db, $page, $form;

        $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
        $sid_filter = $mybb->get_input('sid', MyBB::INPUT_INT);
        $editing = null;

        if($iid > 0)
        {
                $editing = $db->fetch_array($db->simple_select('asm_items', '*', "iid='{$iid}'"));
                if($editing)
                {
                        $sid_filter = (int)$editing['sid'];
                }
        }

        $sponsor_options = array(0 => 'Seçiniz...');
        $sponsors = $db->simple_select('asm_sponsors', 'sid, title', '', array('order_by' => 'title', 'order_dir' => 'asc'));
        while($s = $db->fetch_array($sponsors))
        {
                $sponsor_options[(int)$s['sid']] = htmlspecialchars_uni($s['title']);
        }

        $form = new Form('index.php?module=advanced_sponsor_manager-items', 'post');
        $form_title = $editing ? 'Ürünü Düzenle' : 'Yeni Ürün Ekle';
        if($sid_filter > 0 && isset($sponsor_options[$sid_filter]))
        {
                $form_title .= ' — '.$sponsor_options[$sid_filter];
        }
        $form_container = new FormContainer($form_title);
        $form_container->output_row('Sponsor', 'Ürünün ait olduğu sponsor.', $form->generate_select_box('sid', $sponsor_options, $editing ? $editing['sid'] : ($sid_filter > 0 ? $sid_filter : 0), array('id' => 'asm_item_sid')));
        $form_container->output_row('Ürün başlığı', '', $form->generate_text_box('item_title', $editing ? $editing['title'] : '', array('id' => 'asm_item_title')));
        $form_container->output_row('Görsel URL', '', $form->generate_text_box('item_image', $editing ? $editing['image'] : '', array('id' => 'asm_item_image')));
        $form_container->output_row('Hedef link', 'http(s) olmalı. Link reklam.php üzerinden sayılır.', $form->generate_text_box('item_url', $editing ? $editing['url'] : '', array('id' => 'asm_item_url')));
        $form_container->output_row('Sıra', '', $form->generate_numeric_field('item_disporder', $editing ? $editing['disporder'] : 0, array('id' => 'asm_item_disporder', 'min' => 0)));
        $form_container->output_row('Aktif', '', $form->generate_yes_no_radio('item_active', $editing ? (int)$editing['active'] : 1));
        $form_container->end();

        $buttons = array($form->generate_submit_button($editing ? 'Güncelle' : 'Ekle'));
        $form->output_submit_wrapper($buttons);

        echo '<input type="hidden" name="asm_do" value="save_item" />';
        if($editing)
        {
                echo '<input type="hidden" name="iid" value="'.(int)$editing['iid'].'" />';
        }
        echo '<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
        $form->end();

        $table = new Table;
        $table->construct_header('Ürün');
        $table->construct_header('Sponsor', array('width' => '25%'));
        $table->construct_header('Durum', array('class' => 'align_center', 'width' => '10%'));
        $table->construct_header('Tıklama', array('class' => 'align_center', 'width' => '8%'));
        $table->construct_header('İşlem', array('class' => 'align_center', 'width' => '15%'));

        $where = $sid_filter > 0 ? "sid='{$sid_filter}'" : '';
        $query = $db->simple_select('asm_items', '*', $where, array('order_by' => 'sid, disporder, iid', 'order_dir' => 'asc'));

        $rows = 0;
        while($item = $db->fetch_array($query))
        {
                ++$rows;
                $sponsor_title = $db->fetch_field($db->simple_select('asm_sponsors', 'title', "sid='".(int)$item['sid']."'"), 'title');

                $table->construct_cell('<strong>'.htmlspecialchars_uni($item['title']).'</strong>');
                $table->construct_cell(htmlspecialchars_uni($sponsor_title));
                $table->construct_cell($item['active'] ? 'aktif' : 'kapalı', array('class' => 'align_center'));
                $table->construct_cell(my_number_format((int)$item['clicks']), array('class' => 'align_center'));
                $table->construct_cell('<a href="index.php?module=advanced_sponsor_manager-items&amp;iid='.(int)$item['iid'].'">düzenle</a> '
                        . '<form action="index.php?module=advanced_sponsor_manager-items" method="post" style="display:inline;"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="asm_do" value="delete_item" /><input type="hidden" name="iid" value="'.(int)$item['iid'].'" /><input type="submit" class="button" value="sil" /></form>', array('class' => 'align_center'));
                $table->construct_row();
        }

        if($rows == 0)
        {
                $msg = $sid_filter > 0 ? 'Bu sponsora ait ürün yok.' : 'Henüz ürün eklenmemiş.';
                $table->construct_cell($msg, array('colspan' => 5, 'class' => 'align_center'));
                $table->construct_row();
        }

        $table->output('Ürünler');
}

/**
 * A small, honest starter set so the ACP is not an empty table on first visit.
 * Nothing here fakes a real partnership — the copy says "örnek" and the links
 * point at the board itself.
 */
function advanced_sponsor_manager_seed_demo()
{
        global $db, $mybb;

        $existing = (int)$db->fetch_field($db->simple_select('asm_sponsors', 'COUNT(*) AS n'), 'n');
        if($existing > 0)
        {
                return;
        }

        $bburl = $mybb->settings['bburl'];

        $rows = array(
                array('Sponsorlu Alan (Örnek)', 'Bu alan kiralanabilir sponsor kartıdır.', 'single', 'top', '', 1, 'active', ''),
                array('Kenar Banner (Örnek)', 'Sol/sağ kenarda sabit görünür.', 'edge', 'edge', 'right', 2, 'active', ''),
                array('Yakında Açılacak Sponsor', 'Kart üzerinde "Çok Yakında" rozeti görünür.', 'single', 'top', '', 3, 'soon', ''),
        );

        foreach($rows as $r)
        {
                $db->insert_query('asm_sponsors', array(
                        'title' => $db->escape_string($r[0]),
                        'tagline' => $db->escape_string($r[1]),
                        'logo' => '',
                        'url' => '',
                        'affiliate' => '',
                        'slot' => $db->escape_string($r[2]),
                        'position' => $db->escape_string($r[3]),
                        'edge' => $db->escape_string($r[4]),
                        'fid' => 0,
                        'uid' => 0,
                        'status' => $db->escape_string($r[6]),
                        'disporder' => (int)$r[5],
                        'dateline' => TIME_NOW,
                        'updated' => TIME_NOW,
                ));
        }
}
