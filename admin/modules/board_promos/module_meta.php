<?php
/**
 * ACP module meta for announcements, ads and sponsors.
 *
 * The four screens are ordered by how often they need attention: incoming
 * sponsor requests first, then the content that goes out.
 */
if(!defined('IN_MYBB'))
{
        die('Direct initialization of this file is not allowed.');
}

function board_promos_meta()
{
        global $page, $plugins;

        $sub_menu = array();
        $sub_menu['10'] = array('id' => 'requests', 'title' => 'Sponsor Başvuruları', 'link' => 'index.php?module=board_promos-requests');
        $sub_menu['20'] = array('id' => 'announcements', 'title' => 'Duyurular', 'link' => 'index.php?module=board_promos-announcements');
        $sub_menu['30'] = array('id' => 'ads', 'title' => 'Reklamlar', 'link' => 'index.php?module=board_promos-ads');
        $sub_menu['40'] = array('id' => 'sponsors', 'title' => 'Sponsorlar', 'link' => 'index.php?module=board_promos-sponsors');

        $sub_menu = $plugins->run_hooks('admin_board_promos_menu', $sub_menu);

        $page->add_menu_item('Duyuru & Reklam', 'board_promos', 'index.php?module=board_promos', 66, $sub_menu);
        return true;
}

function board_promos_action_handler($action)
{
        global $page, $plugins;

        $page->active_module = 'board_promos';

        $actions = array(
                'requests' => array('active' => 'requests', 'file' => 'requests.php'),
                'announcements' => array('active' => 'announcements', 'file' => 'announcements.php'),
                'ads' => array('active' => 'ads', 'file' => 'ads.php'),
                'sponsors' => array('active' => 'sponsors', 'file' => 'sponsors.php'),
        );

        if(isset($actions[$action]))
        {
                $page->active_action = $actions[$action]['active'];
                return $actions[$action]['file'];
        }

        $page->active_action = 'requests';
        return 'requests.php';
}

function board_promos_admin_permissions()
{
        return array(
                'board_promos' => array(
                        'requests' => 'Sponsor başvurularını görüntüle ve yanıtla',
                        'announcements' => 'Duyuruları yönet',
                        'ads' => 'Reklam alanlarını yönet',
                        'sponsors' => 'Sponsorları ve affiliate kodlarını yönet',
                ),
        );
}
