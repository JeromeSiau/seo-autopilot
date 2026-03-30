# Release Checklist

## Before merge

- `php artisan test`
- `php artisan test --filter=CriticalFlowsSmokeTest`
- `npm run build`
- `uv run --project agents-python pytest`
- `php artisan ziggy:generate resources/js/ziggy.js`

## Before deploy

- Run `php artisan system:health --json` and confirm there is no `degraded` status.
- Confirm no critical hosted errors are blocking exports or deployments.
- Confirm webhook failures are understood or cleared.

## After deploy

- Re-run `php artisan system:health`
- Open one hosted site and verify:
  - home page renders
  - one article renders
  - sitemap responds
  - feed responds
- Open analytics and review queue once to verify the new payloads hydrate correctly.

## Smoke flows

- Run `php artisan test --filter=CriticalFlowsSmokeTest`
- Generate one article
- Approve or request approval
- Run refresh detection
- Generate a refresh draft
- Push refresh draft back to review
- Trigger one webhook test
