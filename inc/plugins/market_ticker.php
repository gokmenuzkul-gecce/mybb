<?php
/**
 * Market Ticker — a compact live crypto price strip for the home page.
 *
 * Prices come from CoinGecko's public /simple/price endpoint, which needs no
 * API key. Responses are cached in the datacache so a page view does not cost
 * a request: the task refreshes the cache on a schedule, and the renderer
 * serves whatever is cached. If the fetch fails the last good snapshot is kept
 * rather than blanking the strip.
 *
 * The coins and the refresh cadence are admin settings, so the board owner can
 * track a different set without touching code.
 */

if(!defined('IN_MYBB'))
{
        die('Bu dosyaya doğrudan erişilemez.');
}

$plugins->add_hook('pre_output_page', 'market_ticker_render');
$plugins->add_hook('index_start', 'market_ticker_index_start');

function market_ticker_info()
{
        return array(
                'name'          => 'Canlı Borsa Tablosu',
                'description'   => 'Ana sayfada CoinGecko verisiyle çalışan sade, göz yormayan canlı fiyat şeridi.',
                'website'       => '',
                'author'        => 'Crypton Web3 Community',
                'authorsite'    => '',
                'version'       => '1.0',
                'guid'          => 'd4f81c60a7e2451b9c3d6a0e5f2b8471',
                'compatibility' => '18*',
        );
}

function market_ticker_install()
{
        global $cache;

        market_ticker_create_settings();
        market_ticker_create_task();

        // Prime the cache so the strip has something to show immediately.
        market_ticker_refresh(true);

        echo 'Canlı borsa tablosu kuruldu.';
}

function market_ticker_is_installed()
{
        global $db;
        return $db->table_exists('settings') && market_ticker_setting_exists('market_ticker_on');
}

function market_ticker_uninstall()
{
        global $db, $cache;

        $db->delete_query('settings', "name IN ('market_ticker_on','market_ticker_coins','market_ticker_currency','market_ticker_refresh','market_ticker_compact')");
        $db->delete_query('settinggroups', "name='market_ticker'");
        $db->delete_query('tasks', "file='market_ticker'");
        $cache->delete('market_ticker');

        rebuild_settings();
}

function market_ticker_activate()
{
        require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

        market_ticker_create_task();
        rebuild_settings();
}

function market_ticker_deactivate()
{
        // Nothing template-level to undo; the hook simply stops being registered.
}

/* -------------------------------------------------------- installation --- */

function market_ticker_setting_exists($name)
{
        global $db;
        $q = $db->simple_select('settings', 'sid', "name='".$db->escape_string($name)."'");
        return (bool)$db->num_rows($q);
}

function market_ticker_create_settings()
{
        global $db;

        $existing = $db->simple_select('settinggroups', 'gid', "name='market_ticker'");
        if($db->num_rows($existing))
        {
                $gid = (int)$db->fetch_field($existing, 'gid');
        }
        else
        {
                $gid = $db->insert_query('settinggroups', array(
                        'name' => 'market_ticker',
                        'title' => 'Canlı Borsa Tablosu',
                        'description' => 'Ana sayfadaki canlı kripto fiyat şeridi.',
                        'disporder' => 63,
                        'isdefault' => 0,
                ));
        }

        $settings = array(
                array('market_ticker_on', 'Borsa şeridi açık', '1', 'yesno', 'Ana sayfada canlı fiyat şeridini gösterir.', 1),
                array('market_ticker_coins', 'Takip edilen coinler', 'bitcoin,ethereum,solana,ripple,binancecoin,cardano', 'text', 'CoinGecko kimlikleri, virgülle ayrılır. En fazla 12 coin gösterilir.', 2),
                array('market_ticker_currency', 'Para birimi', 'usd', 'text', 'CoinGecko para birimi kodu (usd, eur, try...).', 3),
                array('market_ticker_refresh', 'Yenileme sıklığı (dakika)', '5', 'text', 'Fiyatların sunucuda yenilenme aralığı. Cache sayesinde ziyaretçi başına istek yapılmaz.', 4),
                array('market_ticker_compact', 'Sade görünüm', '1', 'yesno', 'Şeridi daha az yer kaplayacak sadeleştirilmiş biçimde gösterir.', 5),
        );

        foreach($settings as $s)
        {
                if(market_ticker_setting_exists($s[0]))
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

function market_ticker_create_task()
{
        global $db;

        $exists = $db->simple_select('tasks', 'tid', "file='market_ticker'");
        if($db->num_rows($exists))
        {
                return;
        }

        $db->insert_query('tasks', array(
                'title' => $db->escape_string('Canlı Borsa Fiyat Yenilemesi'),
                'description' => $db->escape_string('CoinGecko fiyatlarını çeker ve ana sayfa şeridinin cache\'ini günceller.'),
                'file' => 'market_ticker',
                'minute' => '*/5',
                'hour' => '*',
                'day' => '*',
                'weekday' => '*',
                'month' => '*',
                'enabled' => 1,
                'logging' => 1,
        ));
}

/* ------------------------------------------------------------ fetching --- */

function market_ticker_coin_list()
{
        global $mybb;

        $raw = (string)$mybb->settings['market_ticker_coins'];
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $coins = array();

        foreach($parts as $p)
        {
                // CoinGecko ids are lowercase alphanumerics and dashes.
                $p = strtolower(preg_replace('/[^a-z0-9-]/i', '', $p));
                if($p !== '' && !in_array($p, $coins, true))
                {
                        $coins[] = $p;
                }
                if(count($coins) >= 12)
                {
                        break;
                }
        }

        return $coins;
}

/**
 * Fetch fresh prices and cache them. Returns the snapshot array.
 */
function market_ticker_refresh($force = false)
{
        global $cache;

        if(!$force)
        {
                $cached = $cache->read('market_ticker');
                if(is_array($cached) && $cached['dateline'] > TIME_NOW - 60)
                {
                        return $cached;
                }
        }

        $coins = market_ticker_coin_list();
        if(!$coins)
        {
                return false;
        }

        $currency = market_ticker_currency();
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids='.urlencode(implode(',', $coins))
                .'&vs_currencies='.urlencode($currency).'&include_24hr_change=true';

        $body = market_ticker_http_get($url);
        if($body === false)
        {
                // Keep the last good snapshot rather than blanking the strip.
                return $cache->read('market_ticker');
        }

        $json = @json_decode($body, true);
        if(!is_array($json) || !$json)
        {
                return $cache->read('market_ticker');
        }

        $rows = array();
        foreach($coins as $id)
        {
                if(!isset($json[$id][$currency]))
                {
                        continue;
                }
                $price = (float)$json[$id][$currency];
                $change = isset($json[$id][$currency.'_24h_change']) ? (float)$json[$id][$currency.'_24h_change'] : null;

                $rows[] = array(
                        'id' => $id,
                        'symbol' => market_ticker_symbol($id),
                        'price' => $price,
                        'change' => $change,
                );
        }

        if(!$rows)
        {
                return $cache->read('market_ticker');
        }

        $snapshot = array(
                'dateline' => TIME_NOW,
                'currency' => $currency,
                'rows' => $rows,
        );

        $cache->update('market_ticker', $snapshot);
        return $snapshot;
}

/**
 * A minimal HTTP GET. Kept local so the plugin does not depend on whether
 * fetch_remote_file() is reachable from the task context.
 */
function market_ticker_http_get($url)
{
        if(function_exists('curl_init'))
        {
                $ch = curl_init();
                curl_setopt_array($ch, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 12,
                        CURLOPT_CONNECTTIMEOUT => 6,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_USERAGENT => 'CryptonWeb3-MarketTicker/1.0',
                        CURLOPT_HTTPHEADER => array('Accept: application/json'),
                ));
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if($body !== false && $code >= 200 && $code < 300)
                {
                        return $body;
                }
                return false;
        }

        if(!ini_get('allow_url_fopen'))
        {
                return false;
        }

        $ctx = stream_context_create(array(
                'http' => array(
                        'method' => 'GET',
                        'timeout' => 12,
                        'header' => "Accept: application/json\r\nUser-Agent: CryptonWeb3-MarketTicker/1.0\r\n",
                ),
        ));
        $body = @file_get_contents($url, false, $ctx);
        return ($body === false) ? false : $body;
}

function market_ticker_currency()
{
        global $mybb;

        $c = strtolower(preg_replace('/[^a-z]/i', '', (string)$mybb->settings['market_ticker_currency']));
        return $c !== '' ? $c : 'usd';
}

function market_ticker_symbol($id)
{
        $map = array(
                'bitcoin' => 'BTC', 'ethereum' => 'ETH', 'solana' => 'SOL',
                'ripple' => 'XRP', 'binancecoin' => 'BNB', 'cardano' => 'ADA',
                'dogecoin' => 'DOGE', 'tron' => 'TRX', 'polkadot' => 'DOT',
                'matic-network' => 'MATIC', 'chainlink' => 'LINK', 'litecoin' => 'LTC',
                'avalanche-2' => 'AVAX', 'toncoin' => 'TON', 'shiba-inu' => 'SHIB',
        );

        if(isset($map[$id]))
        {
                return $map[$id];
        }
        return strtoupper(substr($id, 0, 4));
}

function market_ticker_format_price($price, $currency)
{
        if($price >= 1000)
        {
                $out = number_format($price, 0, '.', ',');
        }
        elseif($price >= 1)
        {
                $out = number_format($price, 2, '.', ',');
        }
        elseif($price >= 0.01)
        {
                $out = number_format($price, 4, '.', ',');
        }
        else
        {
                $out = number_format($price, 8, '.', ',');
        }

        $symbols = array('usd' => '$', 'eur' => '€', 'try' => '₺', 'gbp' => '£');
        $prefix = isset($symbols[$currency]) ? $symbols[$currency] : '';

        return $prefix.$out;
}

/* ------------------------------------------------------------- render --- */

function market_ticker_index_start()
{
        global $mybb;

        if($mybb->settings['market_ticker_on'] != 1)
        {
                return;
        }

        // Refresh opportunistically but at most once per the configured window.
        $minutes = (int)$mybb->settings['market_ticker_refresh'];
        if($minutes < 1)
        {
                $minutes = 5;
        }

        global $cache;
        $cached = $cache->read('market_ticker');
        if(!is_array($cached) || $cached['dateline'] < TIME_NOW - ($minutes * 60))
        {
                market_ticker_refresh(true);
        }
}

function market_ticker_render($contents)
{
        global $mybb;

        if(THIS_SCRIPT != 'index.php' || $mybb->settings['market_ticker_on'] != 1)
        {
                return $contents;
        }

        if(strpos($contents, 'data-market-ticker="1"') !== false)
        {
                return $contents;
        }

        global $cache;
        $snapshot = $cache->read('market_ticker');

        if(!is_array($snapshot) || empty($snapshot['rows']))
        {
                $snapshot = market_ticker_refresh(true);
        }

        if(!is_array($snapshot) || empty($snapshot['rows']))
        {
                return $contents;
        }

        $currency = $snapshot['currency'];
        $compact = ($mybb->settings['market_ticker_compact'] == 1);

        $items = '';
        foreach($snapshot['rows'] as $row)
        {
                $change = $row['change'];
                if($change === null)
                {
                        $dir = 'flat';
                        $change_html = '<span class="nextgen-ticker-change is-flat">—</span>';
                }
                else
                {
                        $dir = ($change > 0.005) ? 'up' : (($change < -0.005) ? 'down' : 'flat');
                        $sign = ($change > 0) ? '+' : '';
                        $change_html = '<span class="nextgen-ticker-change is-'.$dir.'">'
                                . '<i class="fa-solid fa-'.(($dir === 'up') ? 'arrow-trend-up' : (($dir === 'down') ? 'arrow-trend-down' : 'minus')).'" aria-hidden="true"></i>'
                                . $sign.number_format($change, 2, '.', '').'%</span>';
                }

                $items .= '<div class="nextgen-ticker-item">'
                        . '<span class="nextgen-ticker-symbol">'.htmlspecialchars_uni($row['symbol']).'</span>'
                        . '<span class="nextgen-ticker-price">'.htmlspecialchars_uni(market_ticker_format_price($row['price'], $currency)).'</span>'
                        . $change_html
                        . '</div>';
        }

        $ago = my_date('relative', $snapshot['dateline']);
        $block = '<section class="nextgen-market-ticker'.($compact ? ' is-compact' : '').'" data-market-ticker="1" aria-label="Canlı kripto fiyatları">'
                . '<div class="nextgen-ticker-head"><span class="nextgen-ticker-live"><span class="nextgen-ticker-dot" aria-hidden="true"></span>CANLI</span>'
                . '<span class="nextgen-ticker-updated">'.$ago.' güncellendi</span></div>'
                . '<div class="nextgen-ticker-track">'.$items.'</div>'
                . '</section>';

        // Place it just under the hero, above the announcements strip.
        foreach(array('<section class="nextgen-ad-slot"', 'data-promo="announcements"', '<section class="nextgen-vip-showcase"', '<section class="nextgen-forum-directory"') as $needle)
        {
                $pos = strpos($contents, $needle);
                if($pos !== false)
                {
                        return substr_replace($contents, $block.$needle, $pos, strlen($needle));
                }
        }

        return str_replace('<main id="content">', '<main id="content">'.$block, $contents);
}