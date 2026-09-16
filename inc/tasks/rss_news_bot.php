<?php
/**
 * Scheduled task: pull the RSS feeds into the approval queue.
 *
 * This only ever writes to the queue table. Publishing stays a deliberate
 * admin action in the ACP, so a badly-behaved or hijacked feed can never put
 * content straight onto the board.
 */

function task_rss_news_bot($task)
{
	global $mybb;

	if($mybb->settings['rss_bot_enabled'] != 1)
	{
		add_task_log($task, 'RSS Haber Botu kapalı, çekim yapılmadı.');
		return true;
	}

	$report = rss_news_bot_fetch_all();

	$message = 'Beslemeler: '.(int)$report['feeds'].', kuyruğa eklenen yeni haber: '.(int)$report['new'].'.';

	if(!empty($report['errors']))
	{
		$message .= ' Hatalar: '.implode(' | ', $report['errors']);
	}

	add_task_log($task, $message);

	return true;
}