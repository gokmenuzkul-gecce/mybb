<?php
/**
 * Scheduled task: pull each RSS category into the approval queue.
 *
 * The guard in rss_news_bot_fetch_categories() keeps this to one run per
 * category per day, so the hourly schedule only wakes the task up; it does not
 * multiply the volume. Each run queues up to the category's per_run cap (7 by
 * default) and never writes anything onto the board directly.
 */

function task_rss_news_bot($task)
{
        global $mybb;

        if($mybb->settings['rss_bot_enabled'] != 1)
        {
                add_task_log($task, 'RSS Haber Botu kapali, cekim yapilmadi.');
                return true;
        }

        $report = rss_news_bot_fetch_categories();

        $message = 'Kategori: '.(int)$report['categories'].', kuyruga eklenen yeni haber: '.(int)$report['new'].'.';

        if(!empty($report['errors']))
        {
                $message .= ' Hatalar: '.implode(' | ', $report['errors']);
        }

        add_task_log($task, $message);

        return true;
}
