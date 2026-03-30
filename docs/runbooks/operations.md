# Operations Runbook

## Core processes

Production should keep these processes running continuously:

- `php artisan queue:work --queue=default,crawl --tries=1 --timeout=900`
- `php artisan reverb:start`
- `php artisan agents:process-events`

## Daily checks

Run:

```bash
php artisan system:health
php artisan agents:health
```

Interpretation:

- `system:health` now aggregates:
  - agent bridge heartbeat and Redis queue depth
  - database queue backlog, stale pending jobs and failed jobs
  - webhook retries that are due or stale
  - hosted pending states that have been stuck too long
  - stale/running hosted exports
  - AI visibility freshness, high-risk alerts and whether `ai_overviews` is using the real DataForSEO provider or the fallback estimator
- `agents:health` is the low-level heartbeat check for the Redis bridge only.

## Hosted troubleshooting

If a hosted site is stuck:

1. Open the site hosting screen and inspect the Operations panel.
2. Check `last_error`, recent deploy events and recent export runs.
3. If SSL or DNS is pending for too long, verify the domain configuration outside the app and replay the relevant hosting action.

## Webhook troubleshooting

If outbound automations stop working:

1. Open Settings -> Notifications.
2. Inspect recent deliveries, retry state and response codes.
3. Run `php artisan system:health --json` and look at `webhooks.due_retries` / `webhooks.stale_retries`.
4. Use the built-in webhook test endpoint before changing secrets.

## AI visibility troubleshooting

If AI visibility looks stale:

1. Run `php artisan ai-visibility:sync-prompts {siteId}`.
2. Run `php artisan ai-visibility:check {siteId}`.
3. Check whether `DATAFORSEO_LOGIN` and `DATAFORSEO_PASSWORD` are configured if you expect real `ai_overviews` checks.
4. Inspect `system:health` for `ai_visibility.stale_sites` and `sites_missing_real_ai_overview_provider`.

## Queue troubleshooting

If queue-backed flows stop progressing:

1. Run `php artisan system:health --json`.
2. Inspect `queues.stale_pending_jobs`, `queues.pending_by_queue` and `queues.failed_last_24h`.
3. If stale jobs are piling up, verify that `php artisan queue:work --queue=default,crawl --tries=1 --timeout=900` is running and not blocked.

## Hosted operations troubleshooting

If hosted deployment/export activity looks stuck:

1. Run `php artisan system:health --json`.
2. Inspect `hosting.stale_pending_sites`, `hosting.recent_deploy_errors_last_24h`, `exports.stale_running_exports` and `exports.stale_pending_exports`.
3. Open the site hosting screen and compare those signals with the Operations panel history.

## Refresh troubleshooting

If refresh suggestions look outdated:

1. Run `php artisan refresh:detect {siteId}`.
2. Open the Needs Refresh queue.
3. Check whether the article has a `review_ready` refresh draft waiting in the review queue.
