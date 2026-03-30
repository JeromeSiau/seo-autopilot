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

- `system:health` aggregates webhook failures, hosted deployment/export issues, high-risk AI visibility alerts and the agent bridge heartbeat.
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
3. Use the built-in webhook test endpoint before changing secrets.

## AI visibility troubleshooting

If AI visibility looks stale:

1. Run `php artisan ai-visibility:sync-prompts {siteId}`.
2. Run `php artisan ai-visibility:check {siteId}`.
3. Inspect `system:health` for open high-risk alerts.

## Refresh troubleshooting

If refresh suggestions look outdated:

1. Run `php artisan refresh:detect {siteId}`.
2. Open the Needs Refresh queue.
3. Check whether the article has a `review_ready` refresh draft waiting in the review queue.
