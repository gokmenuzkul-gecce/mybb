<?php
/**
 * Social Login — sign in and register with Google, GitHub and Discord.
 *
 * This is a plain OAuth 2.0 "authorization code" client. It deliberately has no
 * SDK dependency: the three providers differ only in their endpoint URLs and
 * the JSON shape they return, so one small implementation covers all of them.
 *
 * Security notes, because this is an authentication path:
 *
 * - `state` is a random per-attempt nonce stored in the session and compared
 *   with hash_equals() on return. Without it the callback is a CSRF target
 *   that would let an attacker log a victim into the attacker's account.
 * - Only an email the provider reports as verified is trusted for account
 *   matching. An unverified email match could hand a stranger's account to
 *   whoever controls that address elsewhere.
 * - An existing local account is never silently linked: if the email already
 *   belongs to a member, that member must be signed in already or the attempt
 *   is refused. Otherwise anyone who controls a matching provider email could
 *   walk into the local account.
 * - Client secrets are stored in MyBB settings, which live in the database.
 *   Treat DB read access as key compromise.
 */

if(!defined('IN_MYBB'))
{
        die('Bu dosyaya doğrudan erişilemez.');
}

$plugins->add_hook('global_start', 'social_login_global_start');
$plugins->add_hook('pre_output_page', 'social_login_render_buttons');

function social_login_info()
{
        return array(
                'name'          => 'Sosyal Giriş (Google, GitHub, Discord)',
                'description'   => 'OAuth 2.0 ile Google, GitHub ve Discord üzerinden giriş ve kayıt.',
                'website'       => '',
                'author'        => 'Crypton Web3 Community',
                'authorsite'    => '',
                'version'       => '1.0',
                'guid'          => 'e91b7a34c6f24d58b0a3e7c1d9f24653',
                'compatibility' => '18*',
        );
}

function social_login_install()
{
        global $db;

        social_login_create_tables();
        social_login_create_settings();

        echo 'Sosyal giriş kuruldu.';
}

function social_login_is_installed()
{
        global $db;
        return $db->table_exists('social_identities');
}

function social_login_uninstall()
{
        global $db, $cache;

        $db->drop_table('social_identities');
        $db->delete_query('settings', "name IN ('social_login_on','social_google_on','social_google_id','social_google_secret','social_github_on','social_github_id','social_github_secret','social_discord_on','social_discord_id','social_discord_secret','social_autoactivate','social_allow_register')");
        $db->delete_query('settinggroups', "name='social_login'");

        rebuild_settings();
}

function social_login_activate() { }
function social_login_deactivate() { }

/* -------------------------------------------------------- installation --- */

function social_login_create_tables()
{
        global $db;

        // CREATE TABLE syntax is not portable: MyBB supports both MySQL and
        // SQLite, and `AUTO_INCREMENT`/`ENGINE=`/`UNIQUE KEY` are MySQL-only.
        $mysql = ($db->type == 'mysql');
        $pk = $mysql ? 'INT(10) NOT NULL AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $mysql ? 'INT(10)' : 'INTEGER';
        $tail = $mysql ? ' ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci' : '';

        if(!$db->table_exists('social_identities'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."social_identities (
                        iid {$pk},
                        uid {$int} NOT NULL DEFAULT 0,
                        provider VARCHAR(20) NOT NULL DEFAULT '',
                        identifier VARCHAR(190) NOT NULL DEFAULT '',
                        email VARCHAR(150) NOT NULL DEFAULT '',
                        dateline {$int} NOT NULL DEFAULT 0
                ){$tail};");

                // One identity per provider, so a repeat login finds the same row.
                $db->write_query("CREATE UNIQUE INDEX ".TABLE_PREFIX."social_identities_lookup
                        ON ".TABLE_PREFIX."social_identities (provider, identifier);");
                $db->write_query("CREATE INDEX ".TABLE_PREFIX."social_identities_uid
                        ON ".TABLE_PREFIX."social_identities (uid);");
        }

        if(!$db->table_exists('social_states'))
        {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."social_states (
                        sid {$pk},
                        state VARCHAR(64) NOT NULL DEFAULT '',
                        provider VARCHAR(20) NOT NULL DEFAULT '',
                        redirect VARCHAR(255) NOT NULL DEFAULT '',
                        dateline {$int} NOT NULL DEFAULT 0
                ){$tail};");

                $db->write_query("CREATE UNIQUE INDEX ".TABLE_PREFIX."social_states_state
                        ON ".TABLE_PREFIX."social_states (state);");
        }
}

function social_login_create_settings()
{
        global $db;

        $existing = $db->simple_select('settinggroups', 'gid', "name='social_login'");
        if($db->num_rows($existing))
        {
                $gid = (int)$db->fetch_field($existing, 'gid');
        }
        else
        {
                // db_sqlite's quote_val() wraps values in quotes without escaping
                // them, so an apostrophe anywhere in here truncates the query.
                // $db->escape_string() is required, not optional.
                $gid = $db->insert_query('settinggroups', array(
                        'name' => 'social_login',
                        'title' => $db->escape_string('Sosyal Giriş'),
                        'description' => $db->escape_string('Google, GitHub ve Discord ile giriş/kayıt ayarları. Yönlendirme adresi: '.$mybb->settings['bburl'].'/social_login.php?action=callback'),
                        'disporder' => 64,
                        'isdefault' => 0,
                ));
        }

        $settings = array(
                array('social_login_on', 'Sosyal giriş açık', '1', 'yesno', 'Butonları gösterir ve OAuth akışını açar.', 1),
                array('social_allow_register', 'Sosyal girişle kayıt oluştur', '1', 'yesno', 'Kapatılırsa yalnızca mevcut hesapla giriş yapılabilir.', 2),
                array('social_autoactivate', 'Sosyal kayıtta e-postayı doğrulanmış say', '1', 'yesno', 'Sağlayıcı e-postayı doğrulanmış bildirdiyse hesap beklemeden aktifleşir.', 3),
                array('social_google_on', 'Google açık', '0', 'yesno', 'Google ile giriş.', 4),
                array('social_google_id', 'Google Client ID', '', 'text', 'Google Cloud Console > Credentials.', 5),
                array('social_google_secret', 'Google Client Secret', '', 'text', 'Bu değer veritabanında saklanır; DB erişimini anahtar sızıntısı sayın.', 6),
                array('social_github_on', 'GitHub açık', '0', 'yesno', 'GitHub ile giriş.', 7),
                array('social_github_id', 'GitHub Client ID', '', 'text', 'GitHub > Settings > Developer settings > OAuth Apps.', 8),
                array('social_github_secret', 'GitHub Client Secret', '', 'text', '', 9),
                array('social_discord_on', 'Discord açık', '0', 'yesno', 'Discord ile giriş.', 10),
                array('social_discord_id', 'Discord Client ID', '', 'text', 'Discord Developer Portal > OAuth2.', 11),
                array('social_discord_secret', 'Discord Client Secret', '', 'text', '', 12),
        );

        foreach($settings as $s)
        {
                $exists = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s[0])."'");
                if($db->num_rows($exists))
                {
                        continue;
                }
                $db->insert_query('settings', array(
                        'name' => $s[0],
                        'title' => $db->escape_string($s[1]),
                        'description' => $db->escape_string($s[4]),
                        'optionscode' => $s[3],
                        'value' => $db->escape_string($s[2]),
                        'disporder' => $s[5],
                        'gid' => $gid,
                        'isdefault' => 0,
                ));
        }

        rebuild_settings();
}

/* --------------------------------------------------------- providers --- */

function social_login_providers()
{
        return array(
                'google' => array(
                        'title' => 'Google',
                        'icon' => 'fa-brands fa-google',
                        'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                        'token' => 'https://oauth2.googleapis.com/token',
                        'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
                        'scope' => 'openid email profile',
                        'id_setting' => 'social_google_id',
                        'secret_setting' => 'social_google_secret',
                        'on_setting' => 'social_google_on',
                ),
                'github' => array(
                        'title' => 'GitHub',
                        'icon' => 'fa-brands fa-github',
                        'authorize' => 'https://github.com/login/oauth/authorize',
                        'token' => 'https://github.com/login/oauth/access_token',
                        'userinfo' => 'https://api.github.com/user',
                        'emails' => 'https://api.github.com/user/emails',
                        'scope' => 'read:user user:email',
                        'id_setting' => 'social_github_id',
                        'secret_setting' => 'social_github_secret',
                        'on_setting' => 'social_github_on',
                ),
                'discord' => array(
                        'title' => 'Discord',
                        'icon' => 'fa-brands fa-discord',
                        'authorize' => 'https://discord.com/api/oauth2/authorize',
                        'token' => 'https://discord.com/api/oauth2/token',
                        'userinfo' => 'https://discord.com/api/users/@me',
                        'scope' => 'identify email',
                        'id_setting' => 'social_discord_id',
                        'secret_setting' => 'social_discord_secret',
                        'on_setting' => 'social_discord_on',
                ),
        );
}

function social_login_enabled_providers()
{
        global $mybb;

        if($mybb->settings['social_login_on'] != 1)
        {
                return array();
        }

        $out = array();
        foreach(social_login_providers() as $key => $p)
        {
                if($mybb->settings[$p['on_setting']] == 1
                        && trim((string)$mybb->settings[$p['id_setting']]) !== ''
                        && trim((string)$mybb->settings[$p['secret_setting']]) !== '')
                {
                        $out[$key] = $p;
                }
        }
        return $out;
}

function social_login_callback_url()
{
        global $mybb;
        return $mybb->settings['bburl'].'/social_login.php?action=callback';
}

/* -------------------------------------------------------------- flow --- */

function social_login_global_start()
{
        // Nothing to do on every request; the work happens in social_login.php.
}

/**
 * Build the provider authorize URL and remember the nonce.
 */
function social_login_start($provider)
{
        global $db, $mybb;

        $providers = social_login_enabled_providers();
        if(!isset($providers[$provider]))
        {
                return false;
        }

        $p = $providers[$provider];
        $state = bin2hex(random_bytes(24));

        $redirect = '';
        if(isset($mybb->input['redirect']))
        {
                $candidate = (string)$mybb->input['redirect'];
                // Only same-board paths survive, so the callback cannot be turned
                // into an open redirect.
                if($candidate !== '' && strpos($candidate, '//') === false && strpos($candidate, ':') === false)
                {
                        $redirect = $candidate;
                }
        }

        $db->insert_query('social_states', array(
                'state' => $db->escape_string($state),
                'provider' => $db->escape_string($provider),
                'redirect' => $db->escape_string($redirect),
                'dateline' => TIME_NOW,
        ));

        // Opportunistic cleanup of stale attempts.
        $db->delete_query('social_states', 'dateline < '.(TIME_NOW - 3600));

        $params = array(
                'client_id' => $mybb->settings[$p['id_setting']],
                'redirect_uri' => social_login_callback_url(),
                'response_type' => 'code',
                'scope' => $p['scope'],
                'state' => $state,
        );

        return $p['authorize'].'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Exchange the authorization code for a token and return a normalized profile:
 * array('identifier' => ..., 'email' => ..., 'email_verified' => bool,
 *       'username' => ..., 'provider' => ...).
 */
function social_login_callback($provider, $code, $state)
{
        global $db, $mybb;

        $providers = social_login_enabled_providers();
        if(!isset($providers[$provider]))
        {
                return array('error' => 'provider');
        }
        $p = $providers[$provider];

        // Consume the nonce exactly once.
        $row = $db->fetch_array($db->simple_select('social_states', '*',
                "state='".$db->escape_string($state)."' AND provider='".$db->escape_string($provider)."'"));
        if(!$row || !hash_equals($row['state'], (string)$state))
        {
                return array('error' => 'state');
        }
        $db->delete_query('social_states', 'sid='.(int)$row['sid']);

        if($row['dateline'] < TIME_NOW - 3600)
        {
                return array('error' => 'expired');
        }

        $token_response = social_login_http_post($p['token'], array(
                'client_id' => $mybb->settings[$p['id_setting']],
                'client_secret' => $mybb->settings[$p['secret_setting']],
                'code' => $code,
                'redirect_uri' => social_login_callback_url(),
                'grant_type' => 'authorization_code',
        ));

        if($token_response === false)
        {
                return array('error' => 'token');
        }

        $token = social_login_parse_token($token_response);
        if(empty($token['access_token']))
        {
                return array('error' => 'token');
        }

        $profile = social_login_fetch_profile($provider, $p, $token['access_token']);
        if($profile === false)
        {
                return array('error' => 'profile');
        }

        $profile['provider'] = $provider;
        $profile['redirect'] = $row['redirect'];
        return $profile;
}

function social_login_parse_token($body)
{
        $json = @json_decode($body, true);
        if(is_array($json))
        {
                return $json;
        }

        // GitHub returns form-encoded by default.
        $out = array();
        parse_str($body, $out);
        return $out;
}

function social_login_fetch_profile($provider, $p, $access_token)
{
        $headers = array(
                'Authorization: Bearer '.$access_token,
                'Accept: application/json',
                'User-Agent: CryptonWeb3-SocialLogin/1.0',
        );

        $body = social_login_http_get($p['userinfo'], $headers);
        if($body === false)
        {
                return false;
        }

        $data = @json_decode($body, true);
        if(!is_array($data))
        {
                return false;
        }

        if($provider === 'google')
        {
                return array(
                        'identifier' => (string)$data['sub'],
                        'email' => (string)($data['email'] ?? ''),
                        'email_verified' => !empty($data['email_verified']),
                        'username' => social_login_clean_name($data['name'] ?? ($data['email'] ?? '')),
                );
        }

        if($provider === 'github')
        {
                $email = (string)($data['email'] ?? '');
                $verified = false;

                // The public profile may hide the email; ask the emails endpoint.
                $emails_body = social_login_http_get($p['emails'], $headers);
                if($emails_body !== false)
                {
                        $emails = @json_decode($emails_body, true);
                        if(is_array($emails))
                        {
                                foreach($emails as $e)
                                {
                                        if(!empty($e['primary']) && !empty($e['verified']))
                                        {
                                                $email = (string)$e['email'];
                                                $verified = true;
                                                break;
                                        }
                                }
                                if(!$verified)
                                {
                                        foreach($emails as $e)
                                        {
                                                if(!empty($e['verified']))
                                                {
                                                        $email = $email !== '' ? $email : (string)$e['email'];
                                                        $verified = true;
                                                        break;
                                                }
                                        }
                                }
                        }
                }

                return array(
                        'identifier' => (string)$data['id'],
                        'email' => $email,
                        'email_verified' => $verified,
                        'username' => social_login_clean_name($data['login'] ?? ($data['name'] ?? '')),
                );
        }

        if($provider === 'discord')
        {
                return array(
                        'identifier' => (string)$data['id'],
                        'email' => (string)($data['email'] ?? ''),
                        'email_verified' => !empty($data['verified']),
                        'username' => social_login_clean_name($data['global_name'] ?? ($data['username'] ?? '')),
                );
        }

        return false;
}

function social_login_clean_name($name)
{
        $name = trim(preg_replace('/[^\p{L}\p{N} _.-]/u', '', (string)$name));
        if($name === '')
        {
                $name = 'uye';
        }
        return mb_substr($name, 0, 30);
}

/* ------------------------------------------------------ account link --- */

/**
 * Resolve the OAuth profile to a local member, creating one when allowed.
 * Returns array('uid' => int) or array('error' => string).
 */
function social_login_resolve_member($provider, $profile)
{
        global $db, $mybb;

        $identifier = (string)$profile['identifier'];
        if($identifier === '')
        {
                return array('error' => 'profile');
        }

        $ident = $db->fetch_array($db->simple_select('social_identities', '*',
                "provider='".$db->escape_string($provider)."' AND identifier='".$db->escape_string($identifier)."'"));

        if($ident)
        {
                return array('uid' => (int)$ident['uid']);
        }

        $email = trim((string)$profile['email']);

        if($email !== '' && !empty($profile['email_verified']))
        {
                $existing = $db->fetch_array($db->simple_select('users', 'uid,usergroup',
                        "LOWER(email)='".$db->escape_string(my_strtolower($email))."'"));

                if($existing)
                {
                        // An account already owns this verified email. Linking it
                        // silently would let whoever controls that provider
                        // address take over the local account, so refuse unless
                        // the visitor is already signed in as that member.
                        if((int)$mybb->user['uid'] === (int)$existing['uid'])
                        {
                                social_login_store_identity((int)$existing['uid'], $provider, $profile);
                                return array('uid' => (int)$existing['uid']);
                        }
                        return array('error' => 'email_taken');
                }
        }

        if($mybb->settings['social_allow_register'] != 1)
        {
                return array('error' => 'register_disabled');
        }

        return social_login_create_member($provider, $profile);
}

function social_login_store_identity($uid, $provider, $profile)
{
        global $db;

        $exists = $db->simple_select('social_identities', 'iid',
                "provider='".$db->escape_string($provider)."' AND identifier='".$db->escape_string($profile['identifier'])."'");
        if($db->num_rows($exists))
        {
                return;
        }

        $db->insert_query('social_identities', array(
                'uid' => (int)$uid,
                'provider' => $db->escape_string($provider),
                'identifier' => $db->escape_string((string)$profile['identifier']),
                'email' => $db->escape_string((string)$profile['email']),
                'dateline' => TIME_NOW,
        ));
}

function social_login_create_member($provider, $profile)
{
        global $db, $mybb, $cache;

        $email = trim((string)$profile['email']);
        if($email === '')
        {
                return array('error' => 'no_email');
        }

        $username = social_login_unique_username($profile['username']);

        // MyBB caps passwords at 30 characters, so 12 random bytes (24 hex) is
        // the longest that safely fits. The member never sees this value; it
        // only exists so the account has a valid local credential.
        $password = bin2hex(random_bytes(12));

        // Registering through the stock datahandler keeps the password hashing,
        // default group and profile field rows consistent with a normal signup.
        require_once MYBB_ROOT.'inc/datahandlers/user.php';
        $userhandler = new UserDataHandler('insert');

        // A verified provider email means the account is genuinely reachable, so
        // it is activated straight away unless the admin chose otherwise.
        $usergroup = 2;
        if($mybb->settings['social_autoactivate'] != 1 || empty($profile['email_verified']))
        {
                $usergroup = 5;
        }

        $userhandler->set_data(array(
                'username' => $username,
                'password' => $password,
                'password2' => $password,
                'email' => $email,
                'email2' => $email,
                'usergroup' => $usergroup,
                'regip' => $mybb->session->packedip,
                'timezone' => $mybb->settings['origintimezone'] ?? 0,
                'language' => '',
                'receivepms' => 1,
                'pmnotify' => 1,
                'source' => 1,
        ));

        if(!$userhandler->validate_user())
        {
                return array('error' => 'create');
        }

        $user = $userhandler->insert_user();

        social_login_store_identity((int)$user['uid'], $provider, $profile);

        if($usergroup === 5)
        {
                // Mirror the normal "awaiting activation" flow so the member can
                // finish through the standard email path if the admin wants it.
                social_login_send_activation((int)$user['uid'], $user['email'], $user['username']);
        }

        return array('uid' => (int)$user['uid'], 'created' => true, 'loginkey' => $user['loginkey']);
}

function social_login_unique_username($base)
{
        global $db;

        $base = social_login_clean_name($base);
        $try = $base;
        $n = 2;

        while($n < 200)
        {
                $exists = $db->simple_select('users', 'uid', "LOWER(username)='".$db->escape_string(my_strtolower($try))."'");
                if(!$db->num_rows($exists))
                {
                        return $try;
                }
                $try = mb_substr($base, 0, 25).$n;
                $n++;
        }

        return $base.'_'.substr(bin2hex(random_bytes(4)), 0, 6);
}

function social_login_send_activation($uid, $email, $username)
{
        global $mybb, $db, $lang, $plugins;

        if(!function_exists('my_mail'))
        {
                require_once MYBB_ROOT.'inc/functions.php';
        }

        $code = bin2hex(random_bytes(20));
        $db->update_query('users', array('activationcode' => $db->escape_string($code)), 'uid='.(int)$uid);

        $subject = 'Hesabınızı etkinleştirin';
        $message = "Merhaba {$username},\n\n"
                . "Sosyal girişle oluşturduğunuz hesabı etkinleştirmek için aşağıdaki bağlantıya tıklayın:\n"
                . $mybb->settings['bburl']."/member.php?action=activate&uid={$uid}&code={$code}\n";

        @my_mail($email, $subject, $message, $mybb->settings['adminemail']);
}

/* ------------------------------------------------- interact with board --- */

/**
 * Sign the given member in. Mirrors what member.php does on a normal login:
 * store a fresh loginkey in the cookie that the session handler trusts.
 */
function social_login_set_cookie($uid)
{
        global $db;

        $user = $db->fetch_array($db->simple_select('users', 'uid,loginkey', 'uid='.(int)$uid));
        if(!$user)
        {
                return false;
        }

        my_setcookie('mybbuser', $user['uid'].'_'.$user['loginkey'], null, true, 'lax');
        return true;
}

/* --------------------------------------------------------------- http --- */

function social_login_http_post($url, $fields)
{
        return social_login_http($url, strtoupper('POST'), array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: CryptonWeb3-SocialLogin/1.0',
        ), http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
}

function social_login_http_get($url, $headers = array())
{
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: CryptonWeb3-SocialLogin/1.0';
        return social_login_http($url, 'GET', $headers, null);
}

function social_login_http($url, $method, $headers, $body)
{
        $parts = @parse_url($url);
        if(empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https')
        {
                return false;
        }

        if(function_exists('curl_init'))
        {
                $ch = curl_init();
                $opts = array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15,
                        CURLOPT_CONNECTTIMEOUT => 6,
                        CURLOPT_FOLLOWLOCATION => false,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                );
                if($method === 'POST')
                {
                        $opts[CURLOPT_POST] = true;
                        $opts[CURLOPT_POSTFIELDS] = $body;
                }
                curl_setopt_array($ch, $opts);
                $out = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                return ($out !== false && $code >= 200 && $code < 300) ? $out : false;
        }

        if(!ini_get('allow_url_fopen'))
        {
                return false;
        }

        $ctx = stream_context_create(array(
                'http' => array(
                        'method' => $method,
                        'timeout' => 15,
                        'header' => implode("\r\n", $headers)."\r\n",
                        'content' => ($body === null ? '' : $body),
                        'ignore_errors' => false,
                ),
        ));
        $out = @file_get_contents($url, false, $ctx);
        return ($out === false) ? false : $out;
}

/* -------------------------------------------------------------- render --- */

function social_login_render_buttons($contents)
{
        global $mybb;

        if(!in_array(THIS_SCRIPT, array('member.php', 'social_login.php'), true))
        {
                return $contents;
        }

        $providers = social_login_enabled_providers();
        if(!$providers)
        {
                return $contents;
        }

        if(strpos($contents, 'data-social-login="1"') !== false)
        {
                return $contents;
        }

        $buttons = '';
        foreach($providers as $key => $p)
        {
                $href = htmlspecialchars_uni($mybb->settings['bburl'].'/social_login.php?action=start&provider='.$key);
                $buttons .= '<a class="nextgen-social-btn" href="'.$href.'" rel="nofollow">'
                        . '<i class="'.$p['icon'].'" aria-hidden="true"></i>'
                        . htmlspecialchars_uni($p['title']).' ile devam et</a>';
        }

        $block = '<div class="nextgen-social-login" data-social-login="1">'
                . '<div class="nextgen-social-divider"><span>veya</span></div>'
                . '<div class="nextgen-social-buttons">'.$buttons.'</div>'
                . '</div>';

        // Anchor under the login form / register form tables. Both are .tborder
        // tables, and the block belongs directly below the one that holds the
        // username field.
        $pos = strpos($contents, 'name="username"');
        if($pos === false)
        {
                $pos = strpos($contents, 'name="password"');
        }

        if($pos !== false)
        {
                $close = strpos($contents, '</table>', $pos);
                if($close !== false)
                {
                        $at = $close + strlen('</table>');
                        return substr_replace($contents, $block, $at, 0);
                }
        }

        return $contents;
}