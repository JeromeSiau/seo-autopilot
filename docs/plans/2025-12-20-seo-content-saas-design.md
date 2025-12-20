# SEO Content SaaS - Design Document

**Date**: 2025-12-20
**Status**: Validated
**Stack**: Laravel 12 + Inertia.js + React + MySQL

---

## 1. Vision & Positionnement

### Proposition de Valeur
Une plateforme SaaS de création de contenu SEO automatisée qui se différencie par :
1. **Qualité de contenu supérieure** - Orchestration multi-LLM pour un contenu naturel et optimisé
2. **Analytics/ROI tracking** - Mesure précise de l'impact de chaque article via Search Console + GA4

### Concurrence
- **Outrank.so** (~$99/mois) - Focus quantité/automation + backlinks (network effect)
- **Notre différenciation** - Focus qualité mesurable, pas de backlinks (pas de network effect au départ)

### Public Cible
Tous segments au même pricing (~$99) :
- Agences SEO/Marketing
- E-commerce / Shopify owners
- SaaS B2B
- PME / Solopreneurs
- Équipes content internes

---

## 2. Architecture Technique

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
│                   React + Inertia.js                            │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐       │
│  │ Dashboard │ │  Content  │ │ Analytics │ │ Settings  │       │
│  │           │ │  Planner  │ │           │ │           │       │
│  └───────────┘ └───────────┘ └───────────┘ └───────────┘       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LARAVEL 12 BACKEND                          │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐               │
│  │   Auth &    │ │   Content   │ │  Analytics  │               │
│  │   Billing   │ │   Engine    │ │   Engine    │               │
│  └─────────────┘ └─────────────┘ └─────────────┘               │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐               │
│  │  Publisher  │ │  LLM Orch.  │ │   Keyword   │               │
│  │   Service   │ │   Service   │ │   Service   │               │
│  └─────────────┘ └─────────────┘ └─────────────┘               │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│    MySQL     │    │    Redis     │    │  Queue Jobs  │
│   (données)  │    │   (cache)    │    │  (Horizon)   │
└──────────────┘    └──────────────┘    └──────────────┘
```

### Choix Techniques
- **MySQL** : Base de données existante sur le serveur
- **Redis** : Cache Search Console, sessions, rate limiting LLM
- **Laravel Horizon** : Gestion des jobs asynchrones (génération articles)
- **Services découplés** : Modules Laravel indépendants, testables

---

## 3. Modèle de Données

```sql
-- Teams (multi-tenant)
teams:
  - id, name, owner_id
  - plan (enum: starter, pro, agency)
  - articles_limit, stripe_id

-- Users
users:
  - id, name, email, password
  - team_id (fk)

-- Sites (multi-sites pour Agency)
sites:
  - id, team_id (fk)
  - domain, language
  - gsc_token, ga4_token

-- Integrations (WordPress, Webflow, Shopify)
integrations:
  - id, team_id (fk)
  - type (enum), credentials (encrypted JSON)

-- Brand Voice (style personnalisé)
brand_voices:
  - id, team_id (fk)
  - tone, vocabulary (JSON), writing_style
  - analyzed_from (URL source)

-- Keywords
keywords:
  - id, site_id (fk)
  - keyword, volume, difficulty
  - status, cluster_id
  - source (enum: search_console, ai_generated, manual)

-- Articles
articles:
  - id, site_id (fk), keyword_id (fk)
  - title, content (TEXT), meta_desc
  - status (enum: draft, generating, ready, published, failed)
  - published_at, published_url
  - llm_used, cost

-- Analytics (données historiques)
analytics:
  - id, article_id (fk), date
  - impressions, clicks, position, ctr
```

---

## 4. LLM Orchestration Pipeline

### Modèles Utilisés (Pricing 2025)

| Étape | Modèle | Coût/1M tokens (in/out) |
|-------|--------|-------------------------|
| Recherche & Analyse | Gemini 2.5 Flash-Lite | $0.10 / $0.40 |
| Structure SEO | GPT-5 | $1.25 / $10.00 |
| Rédaction créative | Claude Sonnet 4.5 | $3.00 / $15.00 |
| Polish & Meta | GPT-5 nano | $0.05 / $0.40 |
| Images (×3) | FLUX.2 Pro | $0.03/image |

### Pipeline de Génération

```
ÉTAPE 1: RECHERCHE & ANALYSE [Gemini 2.5 Flash-Lite]
├─ Analyse SERP top 10 résultats
├─ Extraction points clés concurrence
├─ Identification des gaps
└─ Output: research_data JSON

ÉTAPE 2: STRUCTURE SEO [GPT-5]
├─ Génération du plan (H1, H2, H3)
├─ Optimisation headers pour keyword
├─ Définition sections + longueur cible
└─ Output: article_outline JSON

ÉTAPE 3: RÉDACTION CRÉATIVE [Claude Sonnet 4.5]
├─ Rédaction section par section (parallélisable)
├─ Application du brand_voice client
├─ Ton naturel, exemples concrets
└─ Output: article_sections[]

ÉTAPE 4: GÉNÉRATION IMAGES [FLUX.2 Pro]
├─ Hero image
├─ 2 illustrations contextuelles
└─ Output: image_urls[]

ÉTAPE 5: POLISH & META [GPT-5 nano]
├─ Meta title + meta description
├─ Suggestions liens internes
├─ Score SEO rapide
└─ Output: article_final + seo_metadata
```

### Coût par Article

| Composant | Coût estimé |
|-----------|-------------|
| LLM (texte) | ~$0.06 |
| Images (×3) | ~$0.09 |
| **Total** | **~$0.15** |

**30 articles/mois = ~$4.50 de coût**

---

## 5. Keyword Discovery Engine

### Sources de Données

1. **Search Console Mining**
   - Requêtes positions 5-30 (quick wins)
   - Impressions sans clics (opportunités)

2. **Topic Clustering IA**
   - User décrit son business
   - LLM génère stratégie complète (pillar + clusters)

3. **DataForSEO API**
   - Volume de recherche
   - Difficulty score
   - CPC, tendances

### Scoring Formula

```
score = (volume × 0.3) +
        ((100 - difficulty) × 0.3) +
        (quick_win_bonus × 0.25) +  // +20 si position 5-30
        (relevance × 0.15)          // LLM évalue 0-100
```

### Content Calendar

- Auto-génération plan 30 jours
- Priorisation par score
- Interface drag & drop
- Clustering visuel par topic

---

## 6. Publishing Integrations

### Plateformes Supportées

| Plateforme | Auth Method | Features |
|------------|-------------|----------|
| WordPress | App Password / JWT | Categories, Featured img, Yoast/RankMath |
| Webflow | API Token | CMS Collections, Rich text, Images |
| Shopify | Admin API Token | Blog posts, Metafields SEO |

### Flux de Publication

1. Article status = `ready`
2. User clique "Publish" ou auto-schedule
3. Job async :
   - Upload images
   - Crée post (draft/published)
   - Récupère URL publiée
   - Met à jour `articles.published_url`
4. Retry automatique (3×, exponential backoff)
5. Notification si échec permanent

---

## 7. Analytics & ROI Tracking

### Data Collection

| Source | Données |
|--------|---------|
| Search Console API | Position, Impressions, Clics, CTR, Requêtes/page |
| GA4 API | Sessions, Temps/page, Bounce rate, Conversions |

### Sync Schedule
- Job quotidien par site
- Historique 30 derniers jours
- Stockage dans table `analytics`

### Dashboard Features

- **KPIs** : Articles publiés, Impressions, Clics, Position moyenne
- **Graphique** : Évolution trafic organique
- **Top Performers** : Articles avec meilleurs résultats
- **Need Attention** : Articles en perte de position
- **ROI Calculator** : Coût génération vs valeur trafic (CPC × clics)
- **Alertes** : Notification perte >5 positions
- **Export** : CSV/PDF pour rapports clients

---

## 8. Pricing Structure

| Plan | Prix | Articles/mois | Features |
|------|------|---------------|----------|
| Starter | $49 | 10 | 1 site, Analytics de base |
| Pro | $99 | 30 | 3 sites, Full analytics, Toutes intégrations |
| Agency | $249 | 100 | Sites illimités, Multi-users, White-label reports |

### Économie Unitaire (Plan Pro)

| | Montant |
|--|---------|
| Prix | $99 |
| Coût articles (30×$0.15) | $4.50 |
| Coût DataForSEO | ~$2 |
| Coût infra (mutualisé) | ~$3 |
| **Marge brute** | **~$89.50 (90%)** |

---

## 9. Features Additionnelles

### Multi-langue
- Support dès le MVP
- L'utilisateur choisit la langue par site
- Les LLMs modernes gèrent nativement

### Brand Voice Engine
- Analyse automatique du contenu existant
- Extraction du ton, vocabulaire, style
- Application à chaque génération

### Onboarding Flow

1. Signup / Paiement
2. Connexion Search Console (OAuth)
3. Description business (formulaire simple)
4. Analyse auto → génération 50-100 keywords
5. Content Calendar auto-rempli
6. Configuration intégration publication
7. Lancement première génération

---

## 10. Stack Technique Détaillé

### Backend
- Laravel 12
- Laravel Horizon (queues)
- Laravel Sanctum (API auth)
- Laravel Cashier (Stripe billing)

### Frontend
- React 18+
- Inertia.js
- Tailwind CSS
- Recharts (graphiques)
- React Beautiful DnD (calendar)

### Infrastructure
- MySQL 8
- Redis
- Laravel Forge / Ploi (deployment)
- S3 / Cloudflare R2 (stockage images)

### APIs Externes
- OpenAI (GPT-5, GPT-5 nano)
- Anthropic (Claude Sonnet 4.5)
- Google (Gemini 2.5 Flash-Lite)
- Black Forest Labs (FLUX.2 Pro)
- Google Search Console API
- Google Analytics 4 API
- DataForSEO API
- Stripe (paiements)

---

## 11. Prochaines Étapes

1. **Setup projet** : Laravel 12 + Inertia + React
2. **Auth & Billing** : Sanctum + Cashier + Stripe
3. **Modèle de données** : Migrations + Models
4. **LLM Service** : Orchestration multi-modèles
5. **Keyword Engine** : Search Console + DataForSEO + Clustering
6. **Content Generator** : Pipeline complet
7. **Publisher Service** : WordPress first
8. **Analytics Engine** : Search Console sync
9. **Dashboard UI** : React components
10. **Testing & Launch**

---

*Document validé le 2025-12-20*
