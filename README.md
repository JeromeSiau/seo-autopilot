# SEO Autopilot

Plateforme Laravel + Inertia pour piloter la génération SEO, la planification éditoriale, la publication CMS et l’orchestration d’agents Python.

## Stack

- Backend: Laravel 12, Sanctum, Horizon, Reverb, Filament
- Frontend: React, TypeScript, Inertia, Vite, Tailwind
- Agents: Python, Crawl4AI, Redis, SQLite vector store local
- Publication: WordPress, Webflow, Shopify, Ghost

## Flux principaux

- Onboarding site: création du site, inventaire sitemap/GSC, configuration éditoriale, intégration CMS, lancement du content plan
- Génération d’article: `pending -> queued -> generating -> completed` côté keyword et `draft -> generating -> review|approved -> published|failed` côté article
- Publication automatique: uniquement pour les articles `approved`, avec `auto_publish=true` et une intégration active
- Indexation site: Laravel transmet des `seed_urls` à l’agent Python depuis `site_pages`; la homepage ne sert que de fallback

## Démarrage local

Prérequis:

- PHP 8.2+
- Node.js 22+
- `uv`
- Redis

Installation:

```bash
composer setup
```

Développement:

```bash
composer dev
```

`composer dev` démarre maintenant:

- `php artisan serve`
- `php artisan queue:listen --queue=default,crawl --tries=1 --timeout=900`
- `php artisan reverb:start`
- `php artisan pail --timeout=0`
- `npm run dev`
- `php artisan agents:process-events`

## Vérifications locales

```bash
php artisan test
npm run build
uv run --project agents-python pytest
php artisan agents:health
php artisan system:health
```

## Variables utiles

- `QUEUE_CONNECTION`
- `SITE_INDEXER_STORAGE_PATH`
- `OPENROUTER_API_KEY`
- `VOYAGE_API_KEY`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `DATAFORSEO_LOGIN`
- `DATAFORSEO_PASSWORD`
- `REPLICATE_API_KEY`
- `REDIS_*`

## Exploitation production

Processus à faire tourner en continu:

- worker queue Laravel sur `default,crawl`
- `php artisan reverb:start`
- `php artisan agents:process-events`

Points de contrôle:

- `php artisan agents:health` vérifie le heartbeat du pont Redis des agents
- `php artisan system:health` agrège agents, webhooks, hosted et alertes AI visibility
- `storage/indexes` ou `SITE_INDEXER_STORAGE_PATH` contient les index SQLite des sites
- les workers queue doivent avoir un `retry_after` supérieur au plus long job
- les runbooks d’exploitation et de release sont dans `docs/runbooks/`

## CI

- `.github/workflows/tests.yml` exécute `php artisan test`, `npm run build` et `uv run --project agents-python pytest`
- `.github/workflows/dusk.yml` garde Dusk séparé, déclenché manuellement ou la nuit

## Notes d’architecture

- Les credentials d’intégration sont normalisés côté backend avec des clés provider-native
- Les secrets stockés ne sont jamais renvoyés au frontend
- Le dashboard utilise un payload canonique unique pour éviter la dérive backend/frontend
- Les articles ne passent en `approved` automatiquement que si le site peut réellement auto-publier
- AI visibility utilise `dataforseo_ai_overview` pour `ai_overviews` quand `DATAFORSEO_*` est configuré; les autres moteurs restent sur le provider `estimated`
