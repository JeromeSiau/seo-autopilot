# SEO Autopilot - Landing Page & Dashboard Redesign

**Date**: 2025-12-20
**Status**: Approved

---

## Objectifs

Deux chantiers majeurs :
1. Création d'une landing page multi-langue
2. Refonte graphique du dashboard et des pages liées

## Positionnement & Différenciation

| Axe | Description |
|-----|-------------|
| **Qualité du contenu** | Articles générés plus naturels, moins "IA" |
| **Simplicité extrême** | UX sans friction, interface intuitive |
| **International multi-langue** | Support natif FR, EN, ES (extensible) |

## Style Visuel

**Direction : "Clean & Confident"**
- Minimaliste moderne avec aperçus produit
- Fond clair, accents colorés différenciants
- Typographie sans-serif moderne, titres bold
- Beaucoup d'espace, sections aérées
- Animations subtiles au scroll
- Mood : Professionnel, confiant, sérieux

*Note : Détails visuels (couleurs exactes, typo, spacing) délégués au skill frontend-design*

---

## Chantier 1 : Landing Page

### Structure (11 sections)

#### 1. Hero
- Headline axé sur : qualité du contenu + simplicité + autopilot
- Sous-titre renforçant le message
- CTA principal : "Get Started" / "Commencer"
- Visuel : screenshot ou illustration du dashboard

#### 2. Social Proof Bar
- Nombre d'utilisateurs ou "Trusted by X companies"
- Logos clients si disponibles (sinon stats)

#### 3. Problème → Solution
- Pain points : SEO manuel = long, complexe, répétitif
- Solution : Automatisation intelligente, contenu de qualité

#### 4. Features Clés (3-4)
- Génération d'articles IA de qualité
- Multi-langue natif
- Intégrations (WordPress, GSC, etc.)
- Autopilot : planning automatique

#### 5. Comment ça marche (3 étapes)
1. Connecter son site
2. Configurer les préférences
3. L'autopilot publie pour vous

#### 6. Preview Produit
- Screenshots du dashboard en action
- Possiblement mini-démo interactive

#### 7. Témoignages
- 2-3 citations clients (à fournir ou placeholder)

#### 8. Pricing
| Plan | Prix | Articles/mois | Features |
|------|------|---------------|----------|
| Starter | $49 | 10 | 1 site, Analytics de base |
| Pro | $99 | 30 | 3 sites, Full analytics, Toutes intégrations |
| Agency | $249 | 100 | Sites illimités, Multi-users, White-label reports |

#### 9. FAQ
- 5-6 questions fréquentes

#### 10. CTA Final
- Relance avec headline + bouton

#### 11. Footer
- Navigation, légal, language switcher, réseaux sociaux

---

## Chantier 2 : Dashboard Redesign

### Niveau de refonte
**Modéré** : Améliorer la hiérarchie visuelle, ajouter des visualisations, moderniser les composants tout en gardant la structure générale.

### Problèmes actuels
- Look générique "template Laravel"
- Trop simple, sous-utilisation de l'espace
- Manque de visualisations (graphiques, tendances)

### Améliorations transverses (toutes pages)

| Aspect | Amélioration |
|--------|--------------|
| **Header de page** | Unifié : Titre + description + actions principales |
| **Cards** | Hover states, bordures subtiles, icônes enrichies |
| **Tables** | Tri, filtres inline, actions rapides, pagination améliorée |
| **Empty states** | Illustrations + CTA clairs |
| **Loading states** | Skeletons au lieu de spinners |
| **Notifications** | Toasts pour feedback après actions |
| **Navigation** | Sidebar conservée mais raffinée, badges notifications |

### Pages spécifiques

#### 1. Dashboard (page principale)
- Stats cards avec sparklines/mini-graphiques pour tendances
- Usage card avec graphique donut ou barre visuelle
- Sites grid avec cards plus riches
- Section "Actions requises" plus visible
- Ajout : Graphique d'évolution (30 derniers jours)

#### 2. Keywords
- Barre de filtres horizontale avec chips actifs
- Table avec indicateur visuel de potentiel/priorité
- Bulk actions plus accessibles
- Stats header (total, en attente, générés)

#### 3. Articles
- Vue kanban optionnelle (Draft → Review → Approved → Published)
- Cards articles avec preview titre, statut coloré, date
- Filtres par statut (tabs ou chips)
- Stats header par statut

#### 4. Sites
- Cards sites avec métriques clés visibles
- Indicateur santé visuel (configuré, actif, erreur)
- Quick actions accessibles

#### 5. Analytics
- Line charts pour trafic/impressions
- Sélecteur de période (7j, 30j, 90j, custom)
- Métriques avec variation vs période précédente (↑ +12%)
- Filtre/comparaison entre sites

#### 6. Integrations
- Cards avec logo, statut connecté, dernière sync
- Groupement par catégorie (CMS, Analytics, Publishing)
- Setup wizard modal step-by-step
- Health check indicator

#### 7. Onboarding Wizard
- Progress bar avec étapes numérotées
- Validation temps réel sur les champs
- Illustrations pour guider l'utilisateur
- Option skip pour étapes optionnelles

#### 8. Settings
- Navigation tabs ou sidebar secondaire
- Sections clairement séparées (Account, Billing, Team, API Keys, Notifications)
- Billing : usage vs limite, historique, upgrade CTA
- Team : liste membres avec rôles, invitations pending

---

## Multi-langue

### URLs
- Pattern : `/{locale}/` — ex: `/en/`, `/fr/`, `/es/`
- Appliqué à landing ET dashboard

### Comportement
- Détection automatique de la langue navigateur
- Redirect vers langue appropriée
- Switcher visible dans header + footer (landing) et header (dashboard)
- Préférence utilisateur stockée en DB (override détection)

### Langues au lancement
- English (EN) — langue par défaut
- Français (FR)
- Español (ES)

### SEO (landing)
- Balises `hreflang`
- Meta lang
- Sitemap par langue

### Structure fichiers
```
resources/lang/
├── en/
│   ├── landing.json
│   ├── dashboard.json
│   ├── keywords.json
│   ├── articles.json
│   └── ...
├── fr/
│   └── ...
└── es/
    └── ...
```

---

## Ordre d'implémentation

| Priorité | Chantier | Raison |
|----------|----------|--------|
| 1 | Landing Page | Vitrine produit, acquisition utilisateurs |
| 2 | Composants UI partagés | Design system unifié |
| 3 | Dashboard principal | Page la plus utilisée |
| 4 | Onboarding Wizard | Critique pour la conversion |
| 5 | Pages secondaires | Keywords, Articles, Sites, Analytics, Integrations |
| 6 | Settings | Moins prioritaire |

---

## Notes techniques

- Framework : React 18 + TypeScript + Tailwind CSS + Inertia.js
- Build : Vite 7
- Icons : Lucide React
- Charts : Recharts (déjà installé)
- Dates : date-fns avec locales FR/EN/ES

## Prochaine étape

Implémentation via skill **frontend-design** pour garantir une qualité design production-grade.
