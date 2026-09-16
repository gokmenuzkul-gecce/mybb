<?php
/**
 * Public sponsor page: the sponsor wall plus the "become a sponsor" form.
 *
 * The form is open to guests on purpose — an advertiser usually is not a member
 * yet. That means the spam controls have to stand on their own: a honeypot field
 * that only a bot will fill, plus a per-IP cooldown. The submit also requires the
 * board's post key, which every session carries, guest included.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'sponsor.php');

$templatelist = 'promo_sponsor_page';

require_once './global.php';


if(!function_exists('board_promos_active_sponsors'))
{
        error('Duyuru, reklam ve sponsor eklentisi devre dışı.');
}

if($mybb->settings['promo_sponsors_on'] != 1)
{
        error('Sponsorluk bölümü şu anda kapalı.');
}

add_breadcrumb('Sponsorluk', 'sponsor.php');

$errors = array();
$notice = '';
$done = false;

$fields = array('name' => '', 'company' => '', 'email' => '', 'budget' => '', 'message' => '');

if($mybb->request_method == 'post')
{
        verify_post_check($mybb->get_input('my_post_key'));

        // Kicked before validation: reading a filled honeypot as a field error would
        // tell the bot exactly which field gave it away.
        if(trim((string)$mybb->get_input('website')) !== '')
        {
                $done = true;
        }
        else
        {
                foreach($fields as $key => $unused)
                {
                        $fields[$key] = trim((string)$mybb->get_input($key));
                }

                if($fields['name'] === '')
                {
                        $errors[] = 'Adınızı yazın.';
                }
                if(!validate_email_format($fields['email']))
                {
                        $errors[] = 'Geçerli bir e-posta adresi yazın.';
                }
                if(my_strlen($fields['message']) < 20)
                {
                        $errors[] = 'Mesajınız en az 20 karakter olmalı; hangi tür iş birliği istediğinizi kısaca anlatın.';
                }
                if(my_strlen($fields['message']) > 4000)
                {
                        $errors[] = 'Mesajınız çok uzun.';
                }

                $cooldown = (int)$mybb->settings['promo_sponsor_cooldown'];
                if($cooldown > 0 && !$errors)
                {
                        $ip = $db->escape_string((string)$mybb->user['ip']);
                        $since = TIME_NOW - $cooldown;
                        $recent = (int)$db->fetch_field(
                                $db->simple_select('promo_sponsor_requests', 'COUNT(*) AS c', "ip='{$ip}' AND dateline>'{$since}'"),
                                'c'
                        );
                        if($recent > 0)
                        {
                                $errors[] = 'Kısa süre önce başvuru aldık. Lütfen birazdan tekrar deneyin.';
                        }
                }

                if(!$errors)
                {
                        $request = array(
                                'name' => $db->escape_string($fields['name']),
                                'company' => $db->escape_string(my_substr($fields['company'], 0, 160)),
                                'email' => $db->escape_string($fields['email']),
                                'budget' => $db->escape_string(my_substr($fields['budget'], 0, 60)),
                                'message' => $db->escape_string($fields['message']),
                                'status' => 'new',
                                'ip' => $db->escape_string((string)$mybb->user['ip']),
                                'dateline' => TIME_NOW,
                        );

                        $rid = $db->insert_query('promo_sponsor_requests', $request);

                        board_promos_notify_sponsor_request(array(
                                'name' => $fields['name'],
                                'company' => $fields['company'],
                                'email' => $fields['email'],
                                'budget' => $fields['budget'],
                                'message' => $fields['message'],
                        ));

                        $plugins->run_hooks('board_promos_sponsor_request_created', $rid);

                        // Clear the form so a refresh does not resubmit the same text.
                        foreach($fields as $key => $unused)
                        {
                                $fields[$key] = '';
                        }
                        $done = true;
                }
        }
}

$promo_intro = htmlspecialchars_uni($mybb->settings['promo_sponsor_intro']);
$promo_notice = '';
$promo_sponsors = '';
$promo_form = '';

if($done)
{
        $promo_notice = '<div class="nextgen-sponsor-notice is-success">'
                . '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>'
                . '<div><strong>Başvurunuz bize ulaştı.</strong>'
                . '<p>Ekibimiz en kısa sürede belirttiğiniz e-posta adresinden dönüş yapacak.</p></div></div>';
}

if($errors)
{
        $promo_notice = '<div class="nextgen-sponsor-notice is-error">'
                . '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>'
                . '<div><strong>Başvuru gönderilemedi.</strong><ul>';
        foreach($errors as $e)
        {
                $promo_notice .= '<li>'.htmlspecialchars_uni($e).'</li>';
        }
        $promo_notice .= '</ul></div></div>';
}

$sponsors = board_promos_active_sponsors();
if($sponsors)
{
        $promo_sponsors = '<div class="nextgen-sponsor-wall">';
        foreach($sponsors as $s)
        {
                $name = htmlspecialchars_uni($s['name']);
                $tier = htmlspecialchars_uni($s['tier']);
                $desc = htmlspecialchars_uni($s['description']);
                $logo = board_promos_safe_url($s['logo_url']);
                $target = board_promos_safe_url($s['url']);

                $mark = $logo !== ''
                        ? '<img src="'.htmlspecialchars_uni($logo).'" alt="'.$name.'" loading="lazy" />'
                        : '<span class="nextgen-sponsor-initial">'.htmlspecialchars_uni(my_substr($s['name'], 0, 1)).'</span>';

                $inner = '<span class="nextgen-sponsor-logo">'.$mark.'</span>'
                        . '<span class="nextgen-sponsor-meta"><strong>'.$name.'</strong>'
                        . ($tier !== '' ? '<small>'.$tier.'</small>' : '').'</span>'
                        . ($desc !== '' ? '<p>'.$desc.'</p>' : '');

                if($target !== '')
                {
                        $href = htmlspecialchars_uni($mybb->settings['bburl'].'/promo.php?go=sponsor&id='.(int)$s['sid']);
                        $promo_sponsors .= '<a class="nextgen-sponsor-card" href="'.$href.'" rel="sponsored noopener">'.$inner.'</a>';
                }
                else
                {
                        $promo_sponsors .= '<div class="nextgen-sponsor-card">'.$inner.'</div>';
                }
        }
        $promo_sponsors .= '</div>';
}

$promo_form = '<section class="nextgen-sponsor-form-wrap" aria-labelledby="nextgen-sponsor-form-title">'
        . '<h2 id="nextgen-sponsor-form-title">Sponsorluk başvurusu</h2>'
        . '<p class="nextgen-sponsor-form-sub">Formu doldurun; hangi alanların size uygun olduğunu birlikte belirleyelim.</p>'
        . '<form action="sponsor.php" method="post">'
        . '<input type="hidden" name="my_post_key" value="'.htmlspecialchars_uni($mybb->post_code).'" />'
        // Honeypot: hidden from people, irresistible to bots.
        . '<div class="nextgen-sponsor-honeypot" aria-hidden="true"><label for="website">Web sitesi</label>'
        . '<input type="text" name="website" id="website" tabindex="-1" autocomplete="off" value="" /></div>'
        . '<div class="nextgen-sponsor-fields">'
        . '<label class="nextgen-sponsor-field"><span>Adınız <em>*</em></span>'
        . '<input type="text" name="name" id="name" maxlength="160" value="'.htmlspecialchars_uni($fields['name']).'" /></label>'
        . '<label class="nextgen-sponsor-field"><span>Şirket / proje</span>'
        . '<input type="text" name="company" id="company" maxlength="160" value="'.htmlspecialchars_uni($fields['company']).'" /></label>'
        . '<label class="nextgen-sponsor-field"><span>E-posta <em>*</em></span>'
        . '<input type="text" name="email" id="email" maxlength="160" value="'.htmlspecialchars_uni($fields['email']).'" /></label>'
        . '<label class="nextgen-sponsor-field"><span>Aylık bütçe aralığı</span>'
        . '<input type="text" name="budget" id="budget" maxlength="60" value="'.htmlspecialchars_uni($fields['budget']).'" /></label>'
        . '</div>'
        . '<label class="nextgen-sponsor-field nextgen-sponsor-field-wide"><span>Mesajınız <em>*</em></span>'
        . '<textarea name="message" id="message" rows="6">'.htmlspecialchars_uni($fields['message']).'</textarea></label>'
        . '<div class="nextgen-sponsor-submit"><input type="submit" class="button" value="Başvuruyu Gönder" /></div>'
        . '</form></section>';

eval('$promo_body = "'.$templates->get('promo_sponsor_page').'";');

output_page($promo_body);
