<?php
/**
 * Board Promos — announcements, ad slots and sponsor/affiliate tracking.
 *
 * Three surfaces that share one ACP section because they are one job: filling
 * the board with things the community should read, and monetising the space
 * around them.
 *
 *  - Duyurular: a rotating strip on the index, above the VIP plate.
 *  - Reklamlar: fixed placements on the index and above the footer, each with
 *    its own size, plus an optional "advertise here" placeholder while a slot
 *    is unsold.
 *  - Sponsorlar: a public page with a contact form, and an outbound redirect
 *    that records the click before forwarding so affiliate referrals are
 *    attributable.
 *
 * Clicks are counted; impressions deliberately are not. An impression counter
 * would write to the database on every page view of every visitor, which is a
 * lot of write traffic for a number nobody asked for.
 */

if(!defined('IN_MYBB'))
{
        die('Bu dosyaya doğrudan erişilemez.');
}

$plugins->add_hook('global_start', 'board_promos_footer_ad');
$plugins->add_hook('pre_output_page', 'board_promos_announcements');
$plugins->add_hook('pre_output_page', 'board_promos_ads');
$plugins->add_hook('pre_output_page', 'board_promos_sponsor_strip');
$plugins->add_hook('admin_load', 'board_promos_admin_load');
$plugins->add_hook('admin_home_index_output_message', 'board_promos_admin_notice');

function board_promos_info()
{
        return array(
                'name'          => 'Duyuru, Reklam ve Sponsor Yönetimi',
                'description'   => 'Ana sayfa duyuru şeridi, reklam alanları ve sponsor/affiliate takibi.',
                'website'       => '',
                'author'        => 'Crypton Web3 Community',
                'authorsite'    => '',
                'version'       => '1.0',
                'guid'          => 'c4f8a1d6e27b4390ab5c8d1f6e93b204',
                'compatibility' => '18*',
        );
}

function board_promos_install()
{
        global $db, $cache;

        board_promos_create_tables();
        board_promos_create_settings();
        board_promos_create_templates();

        echo 'Duyuru, reklam ve sponsor yönetimi kuruldu.';
}

function board_promos_is_installed()
{
        global $db;
        return $db->table_exists('promo_announcements');
}

function board_promos_uninstall()
{
        global $db;

        foreach(array('promo_announcements', 'promo_ads', 'promo_sponsors', 'promo_sponsor_requests', 'promo_clicks') as $t)
        {
                $db->drop_table($t);
        }

        $db->delete_query('settings', "name IN ('promo_announcements_on','promo_ads_on','promo_ads_placeholder','promo_sponsors_on','promo_sponsor_notify_uid','promo_sponsor_intro','promo_sponsor_cooldown')");
        $db->delete_query('settinggroups', "name='board_promos'");

        $db->delete_query('templates', "title IN ('promo_announcements','promo_sponsor_page','promo_sponsor_strip') AND sid='-2'");

        rebuild_settings();
        echo 'Duyuru, reklam ve sponsor yönetimi kaldırıldı.';
}

function board_promos_activate()
{
        require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

        // The footer is the one anchor present on every page, which is what makes a
        // global ad slot possible without touching each theme.
        find_replace_templatesets('footer', '#'.preg_quote('<nav class="nextgen-mobile-nav"').'#', '{$promo_footer_ad}<nav class="nextgen-mobile-nav"');
}

function board_promos_deactivate()
{
        require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

        find_replace_templatesets('footer', '#'.preg_quote('{$promo_footer_ad}').'#', '', false);
}

/* ------------------------------------------------------------- schema --- */

/**
 * Column types differ between MySQL and SQLite, so the DDL is built per engine.
 */
function board_promos_dialect()
{
        global $db;

        $mysql = ($db->engine == 'mysql');

        return array(
                'mysql' => $mysql,
                'tail'  => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '',
                'pk'    => $mysql ? 'INT(11) NOT NULL AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'int'   => $mysql ? 'INT(11)' : 'INTEGER',
                'tiny'  => $mysql ? 'TINYINT(1)' : 'INTEGER',
        );
}

function board_promos_create_tables()
{
        global $db;

        $d = board_promos_dialect();
        $mysql = $d['mysql'];
        $tail = $d['tail'];

        if(!$db->table_exists('promo_announcements'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."promo_announcements (
                        aid {$d['pk']},
                        title VARCHAR(160) NOT NULL,
                        body TEXT,
                        icon VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-bullhorn',
                        accent VARCHAR(20) NOT NULL DEFAULT '#f97316',
                        link_url VARCHAR(255) NOT NULL DEFAULT '',
                        link_text VARCHAR(60) NOT NULL DEFAULT '',
                        sticky {$d['tiny']} NOT NULL DEFAULT 0,
                        active {$d['tiny']} NOT NULL DEFAULT 1,
                        start_date {$d['int']} NOT NULL DEFAULT 0,
                        end_date {$d['int']} NOT NULL DEFAULT 0,
                        disporder {$d['int']} NOT NULL DEFAULT 0,
                        dateline {$d['int']} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (aid)" : "")."
                ){$tail};");

                // The SQLite driver wraps values in quotes without escaping them, so
                // every seeded string is escaped here. The apostrophe in "VIP Club'ı"
                // is exactly the kind of value that otherwise truncates the INSERT.
                $seed = array(
                        array(
                                'title' => 'VIP Club açıldı',
                                'body' => 'Balina sinyalleri, erken aşama airdrop rehberleri ve otomasyon scriptleri artık VIP üyelerin erişiminde.',
                                'icon' => 'fa-solid fa-crown',
                                'accent' => '#facc15',
                                'link_url' => 'vip.php',
                                'link_text' => 'VIP Club\'ı keşfet',
                                'sticky' => 1,
                                'active' => 1,
                                'disporder' => 1,
                        ),
                        array(
                                'title' => 'Topluluk kuralları güncellendi',
                                'body' => 'Yatırım tavsiyesi paylaşımı ve referans linkleri için yeni kurallar geçerli. Konu açmadan önce göz atın.',
                                'icon' => 'fa-solid fa-scale-balanced',
                                'accent' => '#38bdf8',
                                'link_url' => '',
                                'link_text' => '',
                                'sticky' => 0,
                                'active' => 1,
                                'disporder' => 2,
                        ),
                );

                foreach($seed as $row)
                {
                        $row['dateline'] = TIME_NOW;
                        foreach($row as $k => $v)
                        {
                                if(is_string($v))
                                {
                                        $row[$k] = $db->escape_string($v);
                                }
                        }
                        $db->insert_query('promo_announcements', $row);
                }
        }

        if(!$db->table_exists('promo_ads'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."promo_ads (
                        adid {$d['pk']},
                        title VARCHAR(160) NOT NULL,
                        type VARCHAR(20) NOT NULL DEFAULT 'image',
                        placement VARCHAR(40) NOT NULL DEFAULT 'index_mid',
                        size VARCHAR(30) NOT NULL DEFAULT 'leaderboard',
                        body TEXT,
                        image_url VARCHAR(255) NOT NULL DEFAULT '',
                        link_url VARCHAR(255) NOT NULL DEFAULT '',
                        button_text VARCHAR(60) NOT NULL DEFAULT '',
                        active {$d['tiny']} NOT NULL DEFAULT 1,
                        start_date {$d['int']} NOT NULL DEFAULT 0,
                        end_date {$d['int']} NOT NULL DEFAULT 0,
                        clicks {$d['int']} NOT NULL DEFAULT 0,
                        disporder {$d['int']} NOT NULL DEFAULT 0,
                        dateline {$d['int']} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (adid)" : "")."
                ){$tail};");

                if(!$mysql)
                {
                        $db->write_query("CREATE INDEX ".TABLE_PREFIX."promo_ads_placement ON ".TABLE_PREFIX."promo_ads (placement);");
                }

                $ad_seed = array(
                        'title' => 'Örnek metin reklamı',
                        'type' => 'text',
                        'placement' => 'index_mid',
                        'size' => 'fluid',
                        'body' => 'Bu alan sponsor içerikleri için ayrıldı. Kripto projeleri ve hizmet sağlayıcıları buradan topluluğa ulaşabilir.',
                        'link_url' => 'sponsor.php',
                        'button_text' => 'Sponsor Ol',
                        'active' => 1,
                        'disporder' => 1,
                        'dateline' => TIME_NOW,
                );
                foreach($ad_seed as $k => $v)
                {
                        if(is_string($v))
                        {
                                $ad_seed[$k] = $db->escape_string($v);
                        }
                }
                $db->insert_query('promo_ads', $ad_seed);
        }

        if(!$db->table_exists('promo_sponsors'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."promo_sponsors (
                        sid {$d['pk']},
                        name VARCHAR(160) NOT NULL,
                        tier VARCHAR(60) NOT NULL DEFAULT '',
                        description TEXT,
                        logo_url VARCHAR(255) NOT NULL DEFAULT '',
                        url VARCHAR(255) NOT NULL DEFAULT '',
                        affiliate_code VARCHAR(60) NOT NULL DEFAULT '',
                        active {$d['tiny']} NOT NULL DEFAULT 1,
                        disporder {$d['int']} NOT NULL DEFAULT 0,
                        dateline {$d['int']} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (sid)" : "")."
                ){$tail};");
        }

        if(!$db->table_exists('promo_sponsor_requests'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."promo_sponsor_requests (
                        rid {$d['pk']},
                        name VARCHAR(160) NOT NULL,
                        company VARCHAR(160) NOT NULL DEFAULT '',
                        email VARCHAR(160) NOT NULL,
                        budget VARCHAR(60) NOT NULL DEFAULT '',
                        message TEXT,
                        status VARCHAR(20) NOT NULL DEFAULT 'new',
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        dateline {$d['int']} NOT NULL DEFAULT 0,
                        handled_by {$d['int']} NOT NULL DEFAULT 0,
                        handled_at {$d['int']} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (rid),\n\t\t\tKEY status (status)" : "")."
                ){$tail};");

                if(!$mysql)
                {
                        $db->write_query("CREATE INDEX ".TABLE_PREFIX."promo_req_status ON ".TABLE_PREFIX."promo_sponsor_requests (status);");
                }
        }

        if(!$db->table_exists('promo_clicks'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."promo_clicks (
                        cid {$d['pk']},
                        kind VARCHAR(20) NOT NULL DEFAULT 'sponsor',
                        refid {$d['int']} NOT NULL DEFAULT 0,
                        uid {$d['int']} NOT NULL DEFAULT 0,
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        referer VARCHAR(255) NOT NULL DEFAULT '',
                        dateline {$d['int']} NOT NULL DEFAULT 0".($mysql ? ",\n\t\t\tPRIMARY KEY (cid),\n\t\t\tKEY ref (kind, refid)" : "")."
                ){$tail};");

                if(!$mysql)
                {
                        $db->write_query("CREATE INDEX ".TABLE_PREFIX."promo_clicks_ref ON ".TABLE_PREFIX."promo_clicks (kind, refid);");
                }
        }
}

function board_promos_create_settings()
{
        global $db;

        $existing = $db->simple_select('settinggroups', 'gid', "name='board_promos'");
        if($db->num_rows($existing))
        {
                $gid = (int)$db->fetch_field($existing, 'gid');
        }
        else
        {
                $gid = $db->insert_query('settinggroups', array(
                        'name' => 'board_promos',
                        'title' => 'Duyuru, Reklam ve Sponsor',
                        'description' => 'Ana sayfa duyuruları, reklam alanları ve sponsor yönetimi.',
                        'disporder' => 62,
                        'isdefault' => 0,
                ));
        }

        $settings = array(
                array('promo_announcements_on', 'Duyuru şeridi açık', '1', 'yesno', 'Ana sayfada dönen duyuru şeridini gösterir.', 1),
                array('promo_ads_on', 'Reklam alanları açık', '1', 'yesno', 'Tanımlı reklam alanlarını gösterir.', 2),
                array('promo_ads_placeholder', 'Boş alanlara "reklam verebilirsiniz" yaz', '1', 'yesno', 'Satılmamış alanlarda sponsorluk daveti gösterilir. Yönetim ve VIP üyeler bu daveti görmez.', 3),
                array('promo_sponsors_on', 'Sponsor bölümü açık', '1', 'yesno', 'Ana sayfadaki sponsor şeridini ve sponsor.php sayfasını açar.', 4),
                array('promo_sponsor_notify_uid', 'Sponsor başvurusu bildirilecek kullanıcı ID', '1', 'text', 'Yeni sponsor başvurusunda bu kullanıcıya özel mesaj gider.', 5),
                array('promo_sponsor_intro', 'Sponsor sayfası tanıtım metni', 'Markanızı kripto ve web3 topluluğuna tanıtın. Aşağıdaki formu doldurun, ekibimiz en kısa sürede dönüş yapar.', 'textarea', 'sponsor.php sayfasının üstünde görünür.', 6),
                array('promo_sponsor_cooldown', 'Aynı IP için bekleme süresi (saniye)', '120', 'text', 'Form spam\'ini sınırlar. 0 yazarsanız sınır kaldırılır.', 7),
        );

        foreach($settings as $s)
        {
                $exists = $db->simple_select('settings', 'sid', "name='{$s[0]}'");
                if($db->num_rows($exists))
                {
                        continue;
                }
                $db->insert_query('settings', array(
                        'name' => $s[0], 'title' => $s[1], 'description' => $s[4],
                        'optionscode' => $s[3], 'value' => $s[2], 'disporder' => $s[5],
                        'gid' => $gid, 'isdefault' => 0,
                ));
        }

        rebuild_settings();
}

function board_promos_create_templates()
{
        global $db;

        $templates = array(
                'promo_sponsor_page' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - Sponsorluk</title>
{$headerinclude}
</head>
<body>
{$header}
<section class="nextgen-promo-hero">
  <span class="nextgen-eyebrow"><i class="fa-solid fa-handshake" aria-hidden="true"></i> SPONSORLUK</span>
  <h1>Markanızı topluluğa tanıtın</h1>
  <p>{$promo_intro}</p>
</section>
<div class="wrapper nextgen-sponsor-wrap">
  {$promo_notice}
  {$promo_sponsors}
  {$promo_form}
</div>
{$footer}
</body>
</html>',
                'promo_sponsor_strip' => '<section class="nextgen-sponsor-strip" aria-label="Sponsorlar">
  <span class="nextgen-sponsor-strip-label">Sponsorlar</span>
  <div class="nextgen-sponsor-strip-row">{$promo_strip_items}</div>
  <a class="nextgen-sponsor-strip-cta" href="{$promo_sponsor_url}">Sponsor Ol <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
</section>',
        );

        foreach($templates as $title => $tpl)
        {
                // The SQLite driver's quote_val() only wraps values in quotes; it does
                // not escape them, so the caller must escape first.
                $tpl_e = $db->escape_string($tpl);
                $title_e = $db->escape_string($title);

                $exists = $db->simple_select('templates', 'tid', "title='{$title_e}' AND sid='-2'");
                if($db->num_rows($exists))
                {
                        $db->update_query('templates', array('template' => $tpl_e, 'dateline' => TIME_NOW), "title='{$title_e}' AND sid='-2'");
                }
                else
                {
                        // The SQLite driver does not escape on the way in, so the
                        // escaped copies are what both branches must use. The raw
                        // $tpl contains apostrophes (settings['bbname']) and would
                        // otherwise terminate the INSERT early.
                        $db->insert_query('templates', array(
                                'title' => $title_e, 'template' => $tpl_e, 'sid' => -2,
                                'version' => 1820, 'status' => 0, 'dateline' => TIME_NOW,
                        ));
                }
        }
}

/* ----------------------------------------------------------- helpers --- */

/**
 * Only http(s), site-absolute and board-relative targets are allowed through.
 *
 * Everything here is typed by an admin, but a stored `javascript:` value would
 * be served to every member as a live link, so the scheme is checked on the way
 * in and again on the way out.
 *
 * A bare "tronscan.org/x" is deliberately NOT auto-prefixed: "sponsor.php" is a
 * legal relative path that the same heuristic would rewrite into
 * "https://sponsor.php". External links must spell out their scheme.
 */
function board_promos_safe_url($url)
{
        $url = trim((string)$url);
        if($url === '')
        {
                return '';
        }
        if(preg_match('#^https?://#i', $url))
        {
                return $url;
        }
        if($url[0] === '/' || $url[0] === '#')
        {
                return $url;
        }
        // Any other scheme (javascript:, data:, mailto:) is refused outright.
        if(strpos($url, ':') !== false || strpos($url, '//') === 0)
        {
                return '';
        }
        return $url;
}

/**
 * Turn an accepted target into something a browser can navigate to.
 */
function board_promos_abs_url($url, $bburl)
{
        if($url === '' || $url[0] === '#')
        {
                return $url;
        }
        if(preg_match('#^https?://#i', $url))
        {
                return $url;
        }
        if($url[0] === '/')
        {
                return $bburl.$url;
        }
        return $bburl.'/'.$url;
}

/**
 * Append the affiliate code, honouring an explicit {code} placeholder.
 */
function board_promos_affiliate_url($url, $code)
{
        $code = trim((string)$code);
        if($code === '')
        {
                return $url;
        }
        if(strpos($url, '{code}') !== false)
        {
                return str_replace('{code}', rawurlencode($code), $url);
        }
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        return $url.$sep.'ref='.rawurlencode($code);
}

/**
 * Accept a typed date and turn it into a timestamp, or 0 for "always".
 *
 * Returns false when the text is present but unparseable, so the caller can
 * complain instead of silently saving a row that never shows.
 */
function board_promos_parse_date($value)
{
        $value = trim((string)$value);
        if($value === '')
        {
                return 0;
        }

        $ts = strtotime($value);
        if($ts === false || $ts <= 0)
        {
                return false;
        }
        return $ts;
}

function board_promos_active_announcements()
{
        global $db;

        $now = TIME_NOW;
        $out = array();
        $q = $db->simple_select('promo_announcements', '*',
                "active='1' AND (start_date='0' OR start_date<='{$now}') AND (end_date='0' OR end_date>='{$now}')",
                array('order_by' => 'sticky DESC, disporder', 'order_dir' => 'ASC', 'limit' => 6));

        while($row = $db->fetch_array($q))
        {
                $out[] = $row;
        }
        return $out;
}

function board_promos_active_ads($placement)
{
        global $db;

        $now = TIME_NOW;
        $placement = $db->escape_string($placement);
        $out = array();
        $q = $db->simple_select('promo_ads', '*',
                "active='1' AND placement='{$placement}' AND (start_date='0' OR start_date<='{$now}') AND (end_date='0' OR end_date>='{$now}')",
                array('order_by' => 'disporder', 'order_dir' => 'ASC'));

        while($row = $db->fetch_array($q))
        {
                $out[] = $row;
        }
        return $out;
}

function board_promos_active_sponsors()
{
        global $db;

        $out = array();
        $q = $db->simple_select('promo_sponsors', '*', "active='1'",
                array('order_by' => 'disporder', 'order_dir' => 'ASC'));

        while($row = $db->fetch_array($q))
        {
                $out[] = $row;
        }
        return $out;
}

/**
 * Staff do not need to be sold to, and VIP members already converted.
 */
function board_promos_should_show_promo()
{
        global $mybb;

        if(!empty($mybb->usergroup['canmodcp']) || !empty($mybb->usergroup['cancp']))
        {
                return false;
        }

        $vip_gid = (int)$mybb->settings['vip_group'];
        if($vip_gid && (int)$mybb->user['uid'] > 0 && (int)$mybb->user['usergroup'] == $vip_gid)
        {
                return false;
        }

        return true;
}

/* ------------------------------------------------- announcement strip --- */

function board_promos_announcements($contents)
{
        global $mybb;

        if(THIS_SCRIPT != 'index.php' || $mybb->settings['promo_announcements_on'] != 1)
        {
                return $contents;
        }

        // Guard on the markup, not a bare class prefix: this plugin also renders a
        // sponsor strip, and a loose guard would match the wrong block.
        if(strpos($contents, 'data-promo="announcements"') !== false)
        {
                return $contents;
        }

        $items = board_promos_active_announcements();
        if(!$items)
        {
                return $contents;
        }

        $slides = '';
        $dots = '';
        $i = 0;

        foreach($items as $a)
        {
                $active = ($i === 0) ? ' is-active' : '';
                $icon = htmlspecialchars_uni($a['icon'] ? $a['icon'] : 'fa-solid fa-bullhorn');
                $accent = htmlspecialchars_uni($a['accent'] ? $a['accent'] : '#f97316');
                $title = htmlspecialchars_uni($a['title']);
                $body = htmlspecialchars_uni($a['body']);

                $link = '';
                $url = board_promos_safe_url($a['link_url']);
                if($url !== '' && trim((string)$a['link_text']) !== '')
                {
                        $href = htmlspecialchars_uni(board_promos_abs_url($url, $mybb->settings['bburl']));
                        $link = '<a class="nextgen-announce-link" href="'.$href.'">'.htmlspecialchars_uni($a['link_text'])
                                .' <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>';
                }

                $slides .= '<article class="nextgen-announce-slide'.$active.'" style="--announce-accent: '.$accent.'">'
                        . '<span class="nextgen-announce-icon"><i class="'.$icon.'" aria-hidden="true"></i></span>'
                        . '<div class="nextgen-announce-copy"><strong>'.$title.'</strong>'
                        . ($body !== '' ? '<p>'.$body.'</p>' : '').'</div>'
                        . $link
                        . '</article>';

                $dots .= '<button type="button" class="nextgen-announce-dot'.($i === 0 ? ' is-active' : '').'" data-announce-dot="'.$i.'" aria-label="'.($i + 1).'. duyuru"></button>';
                $i++;
        }

        $block = '<section class="nextgen-announce" data-promo="announcements" aria-label="Duyurular">'
                . '<div class="nextgen-announce-viewport">'.$slides.'</div>'
                . ($i > 1 ? '<div class="nextgen-announce-dots" hidden>'.$dots.'</div>' : '')
                . '</section>';

        // Rotate only when there is more than one slide, and honour a reduced-motion
        // preference instead of animating regardless.
        if($i > 1)
        {
                $block .= <<<'HTML'
<script>
(function () {
  var root = document.querySelector('[data-promo="announcements"]');
  if (!root) { return; }
  var slides = root.querySelectorAll('.nextgen-announce-slide');
  var dots = root.querySelectorAll('[data-announce-dot]');
  var dotsWrap = root.querySelector('.nextgen-announce-dots');
  if (slides.length < 2) { return; }
  if (dotsWrap) { dotsWrap.hidden = false; }

  var index = 0, timer = null;
  var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function show(next) {
    index = (next + slides.length) % slides.length;
    for (var i = 0; i < slides.length; i++) {
      slides[i].classList.toggle('is-active', i === index);
      if (dots[i]) { dots[i].classList.toggle('is-active', i === index); }
    }
  }

  function start() {
    if (calm) { return; }
    stop();
    timer = window.setInterval(function () { show(index + 1); }, 7000);
  }
  function stop() {
    if (timer) { window.clearInterval(timer); timer = null; }
  }

  for (var i = 0; i < dots.length; i++) {
    dots[i].addEventListener('click', function (event) {
      show(parseInt(event.currentTarget.getAttribute('data-announce-dot'), 10));
      start();
    });
  }

  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  start();
})();
</script>
HTML;
        }

        // Sit above the VIP plate when it is present, otherwise above the forum
        // directory. Both anchors leave the strip in the same place visually, so the
        // hook order between this plugin and the VIP plugin does not matter.
        foreach(array('<section class="nextgen-vip-showcase"', '<section class="nextgen-forum-directory"') as $needle)
        {
                $pos = strpos($contents, $needle);
                if($pos !== false)
                {
                        return substr_replace($contents, $block.$needle, $pos, strlen($needle));
                }
        }

        return str_replace('<main id="content">', '<main id="content">'.$block, $contents);
}

/* --------------------------------------------------------------- ads --- */

function board_promos_render_ad($ad)
{
        global $mybb;

        $size = preg_replace('/[^a-z_]/', '', (string)$ad['size']);
        $type = $ad['type'];
        $url = board_promos_safe_url($ad['link_url']);

        // Ads route through the click counter so a paid placement can be reported on.
        $href = '';
        if($url !== '')
        {
                $href = htmlspecialchars_uni($mybb->settings['bburl'].'/promo.php?go=ad&id='.(int)$ad['adid']);
        }

        $inner = '';

        if($type === 'image')
        {
                $img = board_promos_safe_url($ad['image_url']);
                $alt = htmlspecialchars_uni($ad['title']);
                $img_html = $img !== ''
                        ? '<img src="'.htmlspecialchars_uni($img).'" alt="'.$alt.'" loading="lazy" />'
                        : '<span class="nextgen-ad-missing">Görsel eklenmedi</span>';
                $inner = $href !== ''
                        ? '<a class="nextgen-ad-link" href="'.$href.'">'.$img_html.'</a>'
                        : $img_html;
        }
        elseif($type === 'button')
        {
                $label = htmlspecialchars_uni($ad['button_text'] ? $ad['button_text'] : $ad['title']);
                $body = htmlspecialchars_uni($ad['body']);
                $inner = ($body !== '' ? '<span class="nextgen-ad-copy">'.$body.'</span>' : '');
                $inner .= $href !== ''
                        ? '<a class="nextgen-ad-button" href="'.$href.'">'.$label.'</a>'
                        : '<span class="nextgen-ad-button">'.$label.'</span>';
        }
        else
        {
                $body = htmlspecialchars_uni($ad['body']);
                $label = htmlspecialchars_uni($ad['button_text']);
                $inner = '<span class="nextgen-ad-copy">'.$body.'</span>';
                if($href !== '' && $label !== '')
                {
                        $inner .= '<a class="nextgen-ad-button" href="'.$href.'">'.$label.'</a>';
                }
        }

        return '<div class="nextgen-ad nextgen-ad-'.$size.'" data-ad-type="'.htmlspecialchars_uni($type).'">'
                . '<span class="nextgen-ad-label">Reklam</span>'.$inner.'</div>';
}

function board_promos_ads($contents)
{
        global $mybb;

        if($mybb->settings['promo_ads_on'] != 1)
        {
                return $contents;
        }

        $is_index = (THIS_SCRIPT == 'index.php');
        $slots = array();

        if($is_index)
        {
                $slots['index_top'] = '<section class="nextgen-promo-grid"';
                $slots['index_mid'] = '<section class="nextgen-community-notice"';
                $slots['index_bottom'] = '<dl class="forum_legend';
        }

        $placeholder = '';

        // The unsold-slot invitation is itself a call to action, so it is withheld
        // from staff and from members who already pay for VIP.
        if($mybb->settings['promo_ads_placeholder'] == 1 && board_promos_should_show_promo())
        {
                $placeholder = '<div class="nextgen-ad nextgen-ad-placeholder"><span class="nextgen-ad-label">Reklam</span>'
                        . '<span class="nextgen-ad-copy">Buraya reklam verebilirsiniz</span>'
                        . '<a class="nextgen-ad-button" href="'.htmlspecialchars_uni($mybb->settings['bburl'].'/sponsor.php').'">Sponsor Ol</a></div>';
        }

        foreach($slots as $slot => $needle)
        {
                $ads = board_promos_active_ads($slot);
                if(!$ads && $placeholder === '')
                {
                        continue;
                }

                $inner = '';
                foreach($ads as $ad)
                {
                        $inner .= board_promos_render_ad($ad);
                }
                if($inner === '')
                {
                        $inner = $placeholder;
                }

                $block = '<section class="nextgen-ad-slot" data-promo-slot="'.$slot.'">'.$inner.'</section>';

                $pos = strpos($contents, $needle);
                if($pos !== false)
                {
                        $contents = substr_replace($contents, $block.$needle, $pos, strlen($needle));
                }
        }

        return $contents;
}

/**
 * The footer anchor is a template variable, so it is filled in on every page.
 */
function board_promos_footer_ad()
{
        global $mybb, $templates, $promo_footer_ad;

        $promo_footer_ad = '';

        if($mybb->settings['promo_ads_on'] != 1)
        {
                return;
        }

        $ads = board_promos_active_ads('global_footer');
        $inner = '';
        foreach($ads as $ad)
        {
                $inner .= board_promos_render_ad($ad);
        }

        if($inner === '' && $mybb->settings['promo_ads_placeholder'] == 1 && board_promos_should_show_promo())
        {
                $inner = '<div class="nextgen-ad nextgen-ad-placeholder"><span class="nextgen-ad-label">Reklam</span>'
                        . '<span class="nextgen-ad-copy">Buraya reklam verebilirsiniz</span>'
                        . '<a class="nextgen-ad-button" href="'.htmlspecialchars_uni($mybb->settings['bburl'].'/sponsor.php').'">Sponsor Ol</a></div>';
        }

        if($inner !== '')
        {
                $promo_footer_ad = '<section class="nextgen-ad-slot" data-promo-slot="global_footer">'.$inner.'</section>';
        }
}

/* ---------------------------------------------------------- sponsors --- */

function board_promos_sponsor_strip($contents)
{
        global $mybb, $templates;

        if(THIS_SCRIPT != 'index.php' || $mybb->settings['promo_sponsors_on'] != 1)
        {
                return $contents;
        }

        if(strpos($contents, 'class="nextgen-sponsor-strip"') !== false)
        {
                return $contents;
        }

        $sponsors = board_promos_active_sponsors();
        if(!$sponsors)
        {
                return $contents;
        }

        $items = '';
        foreach($sponsors as $s)
        {
                $name = htmlspecialchars_uni($s['name']);
                $tier = htmlspecialchars_uni($s['tier']);
                $logo = board_promos_safe_url($s['logo_url']);
                $target = board_promos_safe_url($s['url']);

                $inner = $logo !== ''
                        ? '<img src="'.htmlspecialchars_uni($logo).'" alt="'.$name.'" loading="lazy" />'
                        : '<span class="nextgen-sponsor-initial">'.htmlspecialchars_uni(my_substr($s['name'], 0, 1)).'</span>';

                // Outbound sponsor links go through the click counter too, so an
                // affiliate referral can be attributed to the exact listing.
                $inner .= '<span class="nextgen-sponsor-meta"><strong>'.$name.'</strong>'
                        . ($tier !== '' ? '<small>'.$tier.'</small>' : '').'</span>';

                if($target !== '')
                {
                        $href = htmlspecialchars_uni($mybb->settings['bburl'].'/promo.php?go=sponsor&id='.(int)$s['sid']);
                        $items .= '<a class="nextgen-sponsor-card" href="'.$href.'" rel="sponsored noopener">'.$inner.'</a>';
                }
                else
                {
                        $items .= '<span class="nextgen-sponsor-card">'.$inner.'</span>';
                }
        }

        $promo_strip_items = $items;
        $promo_sponsor_url = htmlspecialchars_uni($mybb->settings['bburl'].'/sponsor.php');

        eval('$strip = "'.$templates->get('promo_sponsor_strip').'";');

        $needle = '<section class="nextgen-community-notice"';
        $pos = strpos($contents, $needle);
        if($pos !== false)
        {
                return substr_replace($contents, $strip.$needle, $pos, strlen($needle));
        }

        return $contents;
}

/**
 * Resolve an outbound target, record the click and hand back the URL to send the
 * browser to. Returning '' means the reference was unknown or inactive.
 */
function board_promos_track_click($kind, $refid)
{
        global $db, $mybb;

        $refid = (int)$refid;
        if(!$refid)
        {
                return '';
        }

        $table = ($kind === 'ad') ? 'promo_ads' : 'promo_sponsors';
        $idcol = ($kind === 'ad') ? 'adid' : 'sid';

        $row = $db->fetch_array($db->simple_select($table, '*', "{$idcol}='{$refid}' AND active='1'"));
        if(!$row)
        {
                return '';
        }

        // Ads and sponsors keep the outgoing address in different columns.
        $raw = ($kind === 'ad') ? $row['link_url'] : $row['url'];
        $target = board_promos_safe_url($raw);
        $target = board_promos_abs_url($target, $mybb->settings['bburl']);
        if($target === '')
        {
                return '';
        }

        if($kind === 'sponsor')
        {
                $target = board_promos_affiliate_url($target, $row['affiliate_code']);
        }

        $db->insert_query('promo_clicks', array(
                'kind' => $kind,
                'refid' => $refid,
                'uid' => (int)$mybb->user['uid'],
                'ip' => $db->escape_string((string)$mybb->user['ip']),
                'referer' => $db->escape_string(my_substr((string)($mybb->input['referer'] ?? ''), 0, 255)),
                'dateline' => TIME_NOW,
        ));

        if($kind === 'ad')
        {
                $db->write_query("UPDATE ".TABLE_PREFIX."promo_ads SET clicks=clicks+1 WHERE adid='{$refid}'");
        }

        return $target;
}

/**
 * A new request is worth interrupting the admin for: it is money on the table.
 */
function board_promos_notify_sponsor_request($request)
{
        global $db, $mybb;

        $uid = (int)$mybb->settings['promo_sponsor_notify_uid'];
        if(!$uid || !function_exists('send_pm'))
        {
                return;
        }

        $name = $request['name'];
        $message = "[b]Yeni sponsor başvurusu[/b]\n\n"
                . "Ad: [b]".$name."[/b]\n"
                . "Şirket: ".$request['company']."\n"
                . "E-posta: ".$request['email']."\n"
                . "Bütçe: ".($request['budget'] !== '' ? $request['budget'] : 'belirtilmedi')."\n"
                . "Tarih: ".my_date($mybb->settings['dateformat'], TIME_NOW)."\n\n"
                . "[b]Mesaj[/b]\n".$request['message']."\n\n"
                . "Başvuruyu yönetim panelinden yanıtlayabilirsiniz: "
                . $mybb->settings['bburl']."/admin/index.php?module=board_promos-requests";

        send_pm(array(
                'subject' => 'Yeni sponsor başvurusu: '.$name,
                'message' => $message,
                'touid' => $uid,
        ), 0, true);
}

function board_promos_new_request_count()
{
        global $db;

        static $count = null;
        if($count !== null)
        {
                return $count;
        }

        $count = (int)$db->fetch_field(
                $db->simple_select('promo_sponsor_requests', 'COUNT(*) AS c', "status='new'"),
                'c'
        );
        return $count;
}

/* ----------------------------------------------------------- adminCP --- */

function board_promos_admin_load()
{
        global $mybb, $db, $page;

        if($mybb->get_input('module') != 'board_promos')
        {
                return;
        }

        if(!board_promos_is_installed())
        {
                flash_message('Duyuru, reklam ve sponsor eklentisi kurulu değil.', 'error');
                admin_redirect('index.php?module=config-plugins');
        }
}

function board_promos_admin_notice()
{
        global $page;

        if(!board_promos_is_installed())
        {
                return;
        }

        $count = board_promos_new_request_count();
        if($count > 0)
        {
                $page->output_error('<p><strong>'.$count.' yeni sponsor başvurusu</strong> yanıt bekliyor. '
                        . '<a href="index.php?module=board_promos-requests">Başvuruları görüntüle</a></p>');
        }
}
