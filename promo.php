<?php
/**
 * Outbound click tracker.
 *
 * Sponsor and ad links point here instead of straight at the destination, so a
 * referral can be recorded before the browser leaves. The target comes from the
 * database and is re-validated on the way out — this must never become an open
 * redirect, so an unknown or inactive reference simply bounces home.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'promo.php');

require_once './global.php';

if(!function_exists('board_promos_track_click'))
{
        error('Duyuru, reklam ve sponsor eklentisi devre dışı.');
}

$go = $mybb->get_input('go');
$id = $mybb->get_input('id', MyBB::INPUT_INT);

$target = '';

if($go === 'ad' || $go === 'sponsor')
{
        $target = board_promos_track_click($go, $id);
}

// A missed click is not worth an error page: the visitor wanted a destination we
// can no longer resolve, so send them somewhere sensible rather than dead-ending.
header('Cache-Control: no-store, no-cache, must-revalidate');

if($target === '')
{
        header('Location: '.$mybb->settings['bburl'].'/index.php');
        exit;
}

header('Location: '.$target);
exit;
