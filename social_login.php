<?php
/**
 * OAuth entry and callback endpoint for the social_login plugin.
 *
 * Kept as a root-level script so providers can redirect straight back to it
 * without going through member.php's action dispatch.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'social_login.php');
define('NO_ONLINE', 1);

require_once './global.php';

if(!function_exists('social_login_enabled_providers'))
{
        // Plugin disabled: nothing to serve.
        error_no_permission();
}

$action = isset($mybb->input['action']) ? (string)$mybb->input['action'] : 'start';
$provider = isset($mybb->input['provider']) ? preg_replace('/[^a-z]/', '', strtolower((string)$mybb->input['provider'])) : '';

if($action === 'start')
{
        if(social_login_enabled_providers() === array())
        {
                error('Sosyal giriş şu anda kullanılamıyor.');
        }

        $url = social_login_start($provider);
        if($url === false)
        {
                error('Bu sağlayıcı etkin değil.');
        }

        header('Location: '.$url);
        exit;
}

if($action === 'callback')
{
        // The provider reports a denied consent as error=access_denied.
        if(isset($mybb->input['error']))
        {
                redirect($mybb->settings['bburl'].'/member.php?action=login', 'Giriş iptal edildi.');
        }

        $code = isset($mybb->input['code']) ? (string)$mybb->input['code'] : '';
        $state = isset($mybb->input['state']) ? (string)$mybb->input['state'] : '';

        if($code === '' || $state === '')
        {
                error('Eksik OAuth yanıtı.');
        }

        $profile = social_login_callback($provider, $code, $state);

        if(isset($profile['error']))
        {
                switch($profile['error'])
                {
                        case 'state':
                        case 'expired':
                                error('Güvenlik doğrulaması başarısız oldu (state). Lütfen yeniden deneyin.');
                                break;
                        case 'email_taken':
                                error('Bu e-posta adresiyle bir hesap zaten var. Lütfen normal giriş yapın; sosyal hesabınızı güvenlik nedeniyle otomatik bağlamıyoruz.');
                                break;
                        case 'register_disabled':
                                error('Bu e-posta ile kayıtlı hesap yok ve sosyal kayıt şu anda kapalı.');
                                break;
                        case 'no_email':
                                error('Sağlayıcı e-posta adresi paylaşmadı. E-posta izni olmadan hesap oluşturulamaz.');
                                break;
                        default:
                                error('Giriş tamamlanamadı. Lütfen tekrar deneyin.');
                }
        }

        $resolution = social_login_resolve_member($provider, $profile);

        if(isset($resolution['error']))
        {
                error('Hesap oluşturulamadı. Lütfen normal kayıt yolunu kullanın.');
        }

        if(!empty($resolution['created']))
        {
                // The new member's session is established by social_login_set_cookie()
                // below using the loginkey the datahandler just generated.
        }

        social_login_set_cookie((int)$resolution['uid']);

        $target = $mybb->settings['bburl'].'/index.php';
        if(!empty($profile['redirect']))
        {
                $candidate = (string)$profile['redirect'];
                if(strpos($candidate, '//') === false && strpos($candidate, ':') === false)
                {
                        $target = $mybb->settings['bburl'].'/'.ltrim($candidate, '/');
                }
        }

        redirect($target, 'Giriş başarılı, yönlendiriliyorsunuz.');
}

error('Bilinmeyen işlem.');