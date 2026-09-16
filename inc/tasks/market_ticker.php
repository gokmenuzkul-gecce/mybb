<?php
/**
 * Scheduled task: refresh the cached CoinGecko prices.
 *
 * The cache is what the home page reads, so a page view never waits on the
 * API. If the fetch fails, market_ticker_refresh() returns the previous
 * snapshot and the strip simply shows slightly older numbers rather than
 * disappearing.
 */

function task_market_ticker($task)
{
        global $mybb;

        if($mybb->settings['market_ticker_on'] != 1)
        {
                add_task_log($task, 'Borsa şeridi kapalı, fiyat çekilmedi.');
                return true;
        }

        $snapshot = market_ticker_refresh(true);

        if(!is_array($snapshot) || empty($snapshot['rows']))
        {
                add_task_log($task, 'Fiyatlar alınamadı; önceki kayıt korundu.');
                return true;
        }

        add_task_log($task, count($snapshot['rows']).' coin güncellendi ('.$snapshot['currency'].').');

        return true;
}
