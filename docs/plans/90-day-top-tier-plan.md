# 90-Day Top-Tier Evolution Plan

Last updated: 2026-03-30
Status: Core roadmap implemented, remaining work is production depth and polish
Scope: Product, backend, frontend, jobs, analytics, ops

Note: This version reflects the introduction of first-party hosted blogs in addition to external CMS publishing.

## Implementation Snapshot

Current progress snapshot:

- Phase 1 foundations: implemented
- Hosted lane: strongly advanced, including navigation, sections, SEO controls, authors, taxonomies, assets and ops telemetry
- AI visibility: implemented as a durable product layer with alert history, prompt sets, provider registry and DataForSEO-backed AI Overviews
- Refresh autopilot: implemented with planner UI, business attribution, draft diffing and push-back-to-review flow
- Editorial workflow: implemented with assignments, approvals, comments, review queue and refresh-ready scope
- Campaigns and webhooks: implemented with UI, retry metadata and actionable event payloads
- Ops and release hardening: CI workflows, health commands, smoke coverage and runbooks added

Remaining work is now mostly:

- deeper real-world AI visibility connectors beyond the current provider mix
- richer business attribution inputs
- premium hosted builder UX depth
- broader monitoring and production observability polish

## Goal

Move the product from a solid AI SEO writing/publishing tool to a true SEO/AEO operating system that:

- understands brand context
- generates brand-safe content
- optimizes before publish
- measures SEO and AI visibility
- detects refresh opportunities
- supports team review and approval
- can run both external CMS publishing and first-party hosted blogs
- scales across multiple sites and campaigns

## Outcome Target

By the end of this 90-day plan, the product should support this workflow:

1. A team connects an external site or creates a first-party hosted site, then imports brand knowledge.
2. The system generates content with citations, scoring, and a publish-readiness checklist.
3. The system publishes either to an external CMS or to the hosted blog it manages directly.
4. The system tracks search and AI visibility for the site.
5. The system detects gaps and refresh opportunities.
6. The team reviews, approves, publishes, and monitors outcomes from one place.

## Core Product Positioning

The target position is:

"A brand-safe SEO and AI visibility operating system that can create, host, publish, measure, decide, and refresh."

This is stronger than "AI writer with autopublish".

## Execution Principles

- Keep the existing `keyword -> article -> review/approved -> published` workflow stable.
- Add new capabilities through additive schema and services first.
- Reuse current domain anchors:
  - `Site`
  - `SiteSetting`
  - `Article`
  - `ArticleAnalytic`
  - `SitePage`
  - `AgentEvent`
  - `GenerateArticleJob`
  - `SiteIndexService`
- Treat publishing as two explicit lanes:
  - external lane: WordPress, Webflow, Shopify, Ghost
  - hosted lane: first-party hosted blog managed by the platform
- Prioritize the hosted lane when a feature can create product differentiation there first.
- Each major feature must ship with:
  - schema
  - service layer
  - UI
  - tests
  - instrumentation
- Do not introduce auto-actions without a visible review path first.

## Workstreams

The plan is split into seven workstreams:

1. Brand Knowledge + Pre-Publish Optimizer
2. Hosted Experience & Infrastructure
3. AI Visibility / AEO
4. Refresh Autopilot
5. Editorial Workflow / Collaboration
6. Scale / Campaigns / Webhooks / API
7. Instrumentation / CI / Ops / Documentation

## Publishing Lanes

The roadmap now needs to support two different publishing models:

### External lane

Used for:

- WordPress
- Webflow
- Shopify
- Ghost

Primary needs:

- credential management
- API publishing reliability
- category and collection mapping
- publish/update/delete sync

### Hosted lane

Used for:

- first-party hosted blogs created and managed by the platform

Primary needs:

- domain and SSL lifecycle
- hosted theme and layout management
- static pages and public rendering
- first-party SEO technical control
- export and portability
- stronger product differentiation

## Priority Adjustment After Hosted Support

The original roadmap remains valid, but the new hosted lane changes priority.

What stays fully valid:

- Brand Knowledge
- article citations and scoring
- AI visibility
- refresh autopilot
- editorial workflow
- instrumentation and ops

What changes:

- hosted experience becomes a top-tier product stream, not a side feature
- external integrations matter, but no longer define the moat
- first-party hosting is now the best place to win on SEO, AI visibility, and publishing quality at the same time

---

## Phase 1

Timeline: 2026-03-29 to 2026-04-26

### Objective

Make every generated article more reliable, more brand-safe, and more reviewable before publish, while hardening the hosted publishing lane.

### Product Deliverables

- Brand Knowledge Base per site
- article citations and sources
- publish-readiness score
- pre-publish checklist
- Brand Kit page
- enriched article review page
- hosted blog foundation hardening
- hosted publishing lane aligned with the same review and approval model

### Technical Deliverables

- `brand_assets`
- `brand_rules`
- `article_citations`
- `article_scores`
- brand ingestion services
- article scoring service
- article citation persistence
- article review UI additions
- hosted publishing and hosted rendering hardening

### Recommended Data Model

#### `brand_assets`

- `id`
- `site_id`
- `type`
- `title`
- `source_url`
- `content`
- `status`
- `last_synced_at`
- `metadata`

Types should start with:

- `pillar_page`
- `offer`
- `faq`
- `proof`
- `case_study`
- `style_sample`
- `claim`
- `policy`

#### `brand_rules`

- `id`
- `site_id`
- `category`
- `label`
- `value`
- `priority`
- `is_active`

Categories should start with:

- `must_include`
- `must_avoid`
- `tone`
- `persona`
- `cta`
- `compliance`

#### `article_citations`

- `id`
- `article_id`
- `source_type`
- `title`
- `url`
- `domain`
- `excerpt`
- `metadata`

#### `article_scores`

- `id`
- `article_id`
- `readiness_score`
- `brand_fit_score`
- `seo_score`
- `citation_score`
- `internal_link_score`
- `fact_confidence_score`
- `warnings`
- `checklist`

### Backend Changes

Add services:

- `app/Services/Brand/BrandKnowledgeService.php`
- `app/Services/Brand/BrandAssetIngestionService.php`
- `app/Services/Content/ArticleScoringService.php`
- `app/Services/Content/ArticleCitationService.php`

Extend:

- `app/Jobs/GenerateArticleJob.php`
- `app/Services/Content/ArticleGenerator.php`

Expected behavior:

- load active brand assets for the site
- load active brand rules
- inject that context into generation
- persist sources used
- compute article score and checklist
- keep low-confidence articles in `review`

### Frontend Changes

Add:

- `resources/js/Pages/Sites/BrandKit.tsx`
- score and citation panels in `resources/js/Pages/Articles/Show.tsx`

Add components:

- `ArticleReadinessPanel`
- `ArticleCitationsPanel`
- `BrandAssetList`
- `BrandRulesEditor`

### API / Route Changes

Add routes and controllers for:

- Brand Kit page
- create/update/delete brand assets
- create/update/delete brand rules
- article score payload
- article citations payload

### Tests

Add tests for:

- brand asset ingestion
- scoring rules
- citation persistence
- generation with site brand context
- article review payload shape

### Phase 1 Exit Criteria

- all newly generated articles have a score
- all newly generated articles expose citations
- reviewers can see warnings and checklist before approval
- site-level brand context influences generation consistently
- hosted sites follow the same article generation and publishing workflow with no special-case regressions

---

## Hosted Experience & Infrastructure Track

Priority: High
Phases: Primarily Phase 1 and Phase 2

### Objective

Turn the hosted blog lane into a product advantage, not just a fallback publishing adapter.

### Why This Matters

The hosted lane is now the clearest opportunity to beat tools that only publish through external CMS integrations.

Because the platform controls:

- the publishing stack
- the public rendering
- the theme system
- the domain and SSL flow
- the SEO technical surface
- the export flow

it can deliver a more complete and more opinionated experience than an external plugin model alone.

### Product Deliverables

- hosted blog setup that feels production-ready
- hosted theme system with stronger defaults
- hosted page management beyond only basic setup
- hosted technical SEO controls
- hosted export reliability
- better visibility into DNS, SSL, and deploy state

### Current Hosted Foundation Already Present

The repository already includes:

- `Site::MODE_HOSTED`
- hosted integrations and publishing
- hosted public routes and rendering
- staging + custom domain + SSL flow
- hosted page editing
- hosted ZIP export

This track builds on those primitives instead of replacing them.

### Main Gaps To Close

- hosted pages are still limited in shape and scope
- hosted site customization is still fairly template-level
- technical SEO controls need to be more explicit and configurable
- content structure features like categories, tags, author presentation, and navigation are still limited
- media and asset management are still thin
- provisioning and domain observability can be improved

### Recommended Scope

#### Hosted content structure

Add:

- custom hosted pages beyond `home`, `about`, and `legal`
- configurable navigation and footer links
- article category and tag presentation
- optional author profile blocks
- optional call-to-action blocks on index and article pages

#### Hosted SEO technical layer

Add:

- canonical controls
- redirects
- Open Graph and social previews
- structured data / schema blocks
- breadcrumb support
- richer sitemap and feed controls
- noindex controls for hosted static pages when needed

#### Hosted media and assets

Add:

- brand/logo asset upload
- article/social image overrides
- basic hosted asset pipeline
- export-safe asset packaging

#### Hosted operations and reliability

Add:

- DNS and SSL health indicators
- provisioning retry flows
- better Ploi webhook diagnostics
- export status history
- basic hosted deployment audit log

### Possible Data Model Additions

Likely additions:

- `hosted_navigation_items`
- `hosted_redirects`
- `hosted_assets`
- `hosted_export_runs`
- `hosted_deploy_events`

### Backend Changes

Extend or add around:

- `app/Services/Hosted/HostedSiteService.php`
- `app/Services/Hosted/HostedExportService.php`
- `app/Services/Hosted/HostedSiteViewFactory.php`
- `app/Services/Hosted/HostedPageGenerator.php`
- `app/Http/Controllers/Web/HostedSiteController.php`
- `app/Http/Controllers/Hosted/HostedPublicController.php`

### Frontend Changes

Extend:

- `resources/js/Pages/Sites/Hosting.tsx`

Add or expand UI for:

- navigation management
- page inventory
- redirects
- SEO preview
- deployment / DNS / SSL state
- export history

### Tests

Add tests for:

- hosted custom pages
- navigation rendering
- redirects
- sitemap and schema correctness
- asset export packaging
- domain and SSL status transitions
- publish/update/delete behavior on hosted articles

### Hosted Track Exit Criteria

- hosted sites feel like a first-class product, not a hidden integration type
- hosted publishing is at least as reliable as external publishing
- hosted technical SEO controls are visible and operable
- export and public rendering are stable and test-covered

---

## Phase 2

Timeline: 2026-04-27 to 2026-05-24

### Objective

Add the missing product layer that matters most against the market: AI visibility / AEO tracking, while continuing hosted lane polish where it improves first-party differentiation.

### Product Deliverables

- AI Visibility dashboard
- tracked prompt sets per site
- per-engine visibility view
- cited source tracking
- competitor and gap detection
- action recommendations tied to content operations

### Technical Deliverables

- `ai_prompts`
- `ai_visibility_checks`
- `ai_visibility_mentions`
- `ai_visibility_sources`
- prompt generation
- scheduled checks
- scoring and recommendation services

### Recommended Data Model

#### `ai_prompts`

- `id`
- `site_id`
- `prompt`
- `topic`
- `intent`
- `priority`
- `locale`
- `country`
- `is_active`

#### `ai_visibility_checks`

- `id`
- `site_id`
- `ai_prompt_id`
- `engine`
- `status`
- `visibility_score`
- `appears`
- `rank_bucket`
- `checked_at`
- `raw_response`
- `metadata`

Start with engines:

- `ai_overviews`
- `chatgpt`
- `perplexity`
- `gemini`

#### `ai_visibility_mentions`

- `id`
- `ai_visibility_check_id`
- `domain`
- `url`
- `brand_name`
- `mention_type`
- `position`
- `is_our_brand`

#### `ai_visibility_sources`

- `id`
- `ai_visibility_check_id`
- `source_domain`
- `source_url`
- `source_title`
- `position`

### Backend Changes

Add services:

- `app/Services/AiVisibility/AiPromptService.php`
- `app/Services/AiVisibility/AiVisibilityRunner.php`
- `app/Services/AiVisibility/AiVisibilityScoringService.php`
- `app/Services/AiVisibility/AiVisibilityRecommendationService.php`

Add jobs / commands:

- `GenerateAiPromptSetJob`
- `AiVisibilityCheckJob`
- `ai-visibility:sync-prompts`
- `ai-visibility:check`

Prompt generation inputs should come from:

- site topics
- content plan
- article clusters
- analytics signals
- brand assets

### Frontend Changes

Add:

- `resources/js/Pages/Analytics/AiVisibility.tsx`

Dashboard blocks should include:

- visibility by engine
- prompt coverage
- most cited competitors
- most cited sources
- recommendations

### Tests

Add tests for:

- prompt generation
- check parsing
- scoring
- visibility recommendation generation
- dashboard payload shape

### Phase 2 Exit Criteria

- each site can track a defined set of prompts
- each site can see AI visibility by engine
- recommendation output can trigger content creation or refresh work

---

## Phase 3

Timeline: 2026-05-25 to 2026-06-27

### Objective

Turn the platform into an operational content system, not just a generation system.

### Product Deliverables

- refresh recommendation engine
- refresh execution flow
- editorial review queue
- comments and approvals
- campaign bulk operations
- outbound webhooks
- simple business-facing reporting

### Technical Deliverables

- `refresh_recommendations`
- `article_refresh_runs`
- `editorial_comments`
- `approval_requests`
- `article_assignments`
- `campaign_runs`
- `webhook_endpoints`
- `webhook_deliveries`

### Recommended Data Model

#### `refresh_recommendations`

- `id`
- `site_id`
- `article_id`
- `trigger_type`
- `severity`
- `reason`
- `recommended_actions`
- `status`
- `detected_at`
- `executed_at`

Start with trigger types:

- `position_drop`
- `traffic_drop`
- `ctr_drop`
- `ai_visibility_drop`
- `content_decay`
- `competitor_gap`

#### `article_refresh_runs`

- `id`
- `article_id`
- `refresh_recommendation_id`
- `old_score_snapshot`
- `new_score_snapshot`
- `status`
- `summary`
- `metadata`

#### `editorial_comments`

- `id`
- `article_id`
- `user_id`
- `body`
- `status`
- `resolved_at`

#### `approval_requests`

- `id`
- `article_id`
- `requested_by`
- `requested_to`
- `status`
- `decision_note`
- `decided_at`

#### `article_assignments`

- `id`
- `article_id`
- `user_id`
- `role`
- `assigned_at`

#### `campaign_runs`

- `id`
- `site_id`
- `name`
- `status`
- `input_type`
- `payload`
- `started_at`
- `completed_at`

#### `webhook_endpoints`

- `id`
- `team_id`
- `url`
- `events`
- `secret`
- `is_active`

#### `webhook_deliveries`

- `id`
- `webhook_endpoint_id`
- `event_name`
- `payload`
- `status`
- `response_code`
- `attempted_at`

### Backend Changes

Add services:

- `app/Services/Refresh/RefreshDetectionService.php`
- `app/Services/Refresh/RefreshPlannerService.php`
- `app/Services/Refresh/RefreshExecutionService.php`

Add jobs:

- `DetectRefreshCandidatesJob`
- `ExecuteArticleRefreshJob`

Add workflow endpoints and controllers for:

- review queue
- comments
- approval requests
- assignment
- campaigns
- webhooks

### Frontend Changes

Add:

- `resources/js/Pages/Articles/NeedsRefresh.tsx`
- `resources/js/Pages/Articles/ReviewQueue.tsx`
- `resources/js/Pages/Campaigns/Index.tsx`

Add features:

- comments panel
- assignment controls
- approval actions
- campaign creation and status view
- refresh recommendation panel

### Tests

Add tests for:

- refresh detection rules
- refresh execution
- workflow authorization
- comments and approvals
- campaign execution
- webhook delivery and retries

### Phase 3 Exit Criteria

- the system can detect refresh candidates automatically
- teams can operate review and approval in-app
- campaign runs work across multiple articles/keywords
- the system emits useful webhooks for automation

---

## Repository Mapping

These current areas should be used as the base:

- `app/Models/Site.php`
- `app/Models/SiteSetting.php`
- `app/Models/Article.php`
- `app/Models/SiteHosting.php`
- `app/Models/HostedPage.php`
- `app/Models/AgentEvent.php`
- `app/Jobs/GenerateArticleJob.php`
- `app/Jobs/AutopilotPublishJob.php`
- `app/Jobs/GenerateHostedSiteExportJob.php`
- `app/Services/Content/ArticleGenerator.php`
- `app/Services/Crawler/SiteIndexService.php`
- `app/Services/Hosted/HostedSiteService.php`
- `app/Services/Hosted/HostedExportService.php`
- `app/Services/Hosted/HostedSiteViewFactory.php`
- `resources/js/Pages/Articles/Show.tsx`
- `resources/js/Pages/Analytics/Index.tsx`
- `resources/js/Pages/Sites/Hosting.tsx`
- `resources/js/Pages/Sites/Show.tsx`

These areas will likely need cleanup after feature rollout:

- legacy runtime references tied to `brand_voices`
- duplicated site brand fields if superseded by Brand Knowledge
- any hard-coded workflow assumptions in dashboard and article UI
- any hosted-specific assumptions scattered outside a dedicated hosted domain model

## Sprint Order

Recommended sprint order:

### Sprint 1

- add `brand_assets`
- add `brand_rules`
- ship Brand Kit page MVP
- add ingestion tests
- identify hosted lane gaps and hard requirements

### Sprint 2

- harden hosted dashboard and hosted domain flows
- define hosted SEO technical controls
- add tests around hosted publish/render/export paths

### Sprint 3

- add `article_citations`
- add `article_scores`
- ship score/checklist panel
- ship citations panel

### Sprint 4

- integrate brand context fully into generation
- add scoring thresholds
- improve instrumentation
- extend hosted lane where brand context should affect first-party rendering

### Sprint 5

- add AI visibility schema
- add prompt generation
- add first check runner
- persist results

### Sprint 6

- ship AI visibility dashboard
- ship AI recommendations
- add alerting

### Sprint 7

- add refresh schema
- detect refresh candidates
- ship refresh review screen

### Sprint 8

- add editorial comments, assignments, approvals
- ship review queue
- enforce workflow permissions

### Sprint 9

- add campaigns
- add webhooks
- extend API
- finalize docs and ops

## KPI Targets

By the end of the plan, aim for:

- >90% successful generate-to-review runs without technical intervention
- <15 minutes from brief to review-ready article
- >60% of generated articles approved without heavy rewrite
- visible AI visibility tracking on every active site
- at least one refresh recommendation loop running automatically
- measurable reduction in manual editorial friction

## Non-Goals For This 90-Day Window

Do not prioritize these ahead of the plan above:

- many new CMS integrations
- major design rewrite
- a full CRM layer
- broad "AI marketing suite" expansion
- speculative features without score, workflow, or analytics impact
- expanding the external plugin lane faster than the hosted lane without a clear revenue reason

## Final Definition of Success

The project reaches "top-tier" territory when it no longer just generates and publishes content.

It needs to:

- understand brand context
- generate with evidence
- optimize before publish
- run a first-party hosted blog exceptionally well
- measure SEO and AI visibility
- decide what to refresh next
- support team operations cleanly

That is the standard this plan is designed to reach.
