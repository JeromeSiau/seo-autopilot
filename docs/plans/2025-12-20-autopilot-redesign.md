# SEO Autopilot - Redesign Full Auto

**Date**: 2025-12-20
**Status**: Validated
**Objectif**: Transformer l'interface manuelle en système 100% automatisé

---

## 1. Vision

L'utilisateur connecte son site et le système tourne tout seul en arrière-plan :
- Découverte automatique de keywords
- Génération automatique d'articles
- Publication automatique selon les règles

**Philosophie** : L'utilisateur fait confiance au système. Pas de configuration complexe.

---

## 2. Wizard d'Onboarding

Flux séquentiel pour ajouter un nouveau site :

```
ÉTAPE 1: AJOUTER UN SITE
├─ Nom du site
├─ Domaine (ex: monsite.com)
└─ Langue du contenu

ÉTAPE 2: CONNECTER GOOGLE SEARCH CONSOLE (optionnel)
├─ Bouton OAuth "Connecter GSC"
├─ OU "Passer cette étape"
└─ Message: "Recommandé pour de meilleurs résultats"

ÉTAPE 3: DÉCRIRE VOTRE BUSINESS
├─ Textarea: "Décrivez votre activité en 2-3 phrases"
├─ Thématiques principales (tags)
└─ Public cible

ÉTAPE 4: CONFIGURER L'AUTOPILOT
├─ Articles par semaine: [slider, défaut selon plan]
├─ Jours de publication: [checkboxes, défaut tous]
└─ Mode: Auto-publish (défaut) OU Review avant publication

ÉTAPE 5: INTÉGRATION PUBLICATION (optionnel)
├─ WordPress / Webflow / Shopify
├─ OU "Configurer plus tard"
└─ Sans intégration = articles en téléchargement

ÉTAPE 6: LANCEMENT
├─ Résumé de la configuration
├─ Bouton "Activer l'Autopilot"
└─ L'analyse démarre immédiatement
```

### Alternative sans Google Search Console

Si GSC non connecté, le système utilise :
1. **Description business** → LLM génère stratégie keywords
2. **Crawl du site existant** → Extraction thématiques actuelles
3. **Combinaison des deux** → Stratégie complète

---

## 3. Configuration Simplifiée

**3 réglages uniquement :**

| Réglage | Options | Défaut |
|---------|---------|--------|
| Articles par semaine | Slider 1-max selon plan | Calculé selon plan |
| Jours de publication | Checkboxes Lun-Dim | Tous |
| Mode publication | Auto-publish / Review | Auto-publish |

### Défauts selon plan

| Plan | Articles/mois | Défaut/semaine | Max/semaine |
|------|---------------|----------------|-------------|
| Starter ($49) | 10 | 2 | 3 |
| Pro ($99) | 30 | 7 | 10 |
| Agency ($249) | 100 | 25 | 30 |

### Scoring Keywords (interne)

Le score est calculé automatiquement, non configurable :
```
score = (volume × 0.3) +
        ((100 - difficulty) × 0.3) +
        (quick_win_bonus × 0.25) +
        (relevance × 0.15)
```

Le système priorise automatiquement les meilleurs keywords.

---

## 4. Dashboard Global Multi-Sites

Vue d'ensemble de tous les sites avec drill-down.

### Header
- Logo + [+ Ajouter un site]
- Notifications (badge compteur)
- Menu utilisateur

### Stats Globales
- Sites actifs
- Keywords en queue
- Articles ce mois
- Articles publiés
- Barre usage mensuel

### Liste des Sites

Chaque site affiche :
- État (🟢 Actif, 🟡 Pause, ⚪ Non configuré, 🔴 Erreur)
- Config résumée (X articles/sem)
- Activité récente
- Actions requises

### Section "Actions Requises"
- Articles en review
- Échecs de publication
- Recommandations (ex: connecter GSC)

---

## 5. Vue Détaillée d'un Site

### En-tête
- Retour dashboard
- Nom du site + état autopilot
- Boutons: Configuration, Pause, Analytics

### Layout 2 colonnes

**Gauche : Configuration actuelle**
- Articles/semaine
- Jours de publication
- Mode publication
- Intégrations connectées
- Lien modifier

**Droite : Timeline activité**
- Événements récents avec timestamps
- Articles publiés, générés, keywords découverts
- Alertes et erreurs

### Pipeline Visuel
```
KEYWORDS → ARTICLES → PUBLICATION
12 queue    2 en cours   1 programmé
```

### Section Review (si mode review actif)
- Liste articles en attente
- Actions: Prévisualiser, Approuver, Éditer, Rejeter

### Performance
- Graphique clics/impressions 7 jours
- Top articles

---

## 6. Moteur Autopilot (Backend)

### Scheduler Laravel

3 jobs principaux orchestrés par le scheduler.

### Job 1 : Keyword Discovery (1x/jour)

```
Pour chaque site autopilot actif:
  1. Si GSC connecté → Importer nouvelles données
  2. Si quota keywords < limite → Générer suggestions LLM
  3. Scorer chaque keyword
  4. Ajouter à la queue (priorité = score)
```

### Job 2 : Article Generator (toutes les heures)

```
Pour chaque site autopilot actif:
  1. Vérifier quota semaine
  2. Vérifier jour autorisé
  3. Prendre keyword prioritaire en queue
  4. Lancer pipeline LLM complet
  5. Si auto_publish → statut "ready_to_publish"
     Sinon → statut "review" + notification
```

### Job 3 : Publisher (toutes les heures)

```
Pour chaque article "ready_to_publish":
  1. Vérifier intégration configurée
  2. Publier via API
  3. Retry avec backoff si échec (max 3)
  4. Mettre à jour statut + URL
  5. Notification si échec permanent
```

### Mode Hybride Publication

- **Avec intégration** : Publication automatique
- **Sans intégration** : Articles en statut "ready", téléchargement manuel

---

## 7. Notifications

### In-App

Centre de notifications avec :
- Articles en review
- Articles publiés
- Échecs de publication
- Keywords découverts
- Actions requises

### Email Digest

Configurable par l'utilisateur :
- Fréquence : Quotidien / Hebdomadaire / Désactivé
- Alertes immédiates : Échecs, Quota 80%

### Contenu Email Hebdo

- Résumé : X générés, Y publiés, Z en review
- État de chaque site
- Lien vers dashboard

---

## 8. Modèle de Données

### Nouvelle table : `site_settings`

```sql
site_settings:
  - id
  - site_id (fk)
  - autopilot_enabled (boolean, default false)
  - articles_per_week (int)
  - publish_days (json: ["mon","tue","wed","thu","fri"])
  - auto_publish (boolean, default true)
  - created_at, updated_at
```

### Nouvelle table : `autopilot_logs`

```sql
autopilot_logs:
  - id
  - site_id (fk)
  - event_type (enum: keyword_discovered, article_generated,
                article_published, publish_failed, keywords_imported)
  - payload (json)
  - created_at
```

### Nouvelle table : `notifications`

```sql
notifications:
  - id
  - user_id (fk)
  - site_id (fk, nullable)
  - type (enum: review_needed, published, publish_failed,
          quota_warning, keywords_found)
  - title, message
  - action_url (nullable)
  - read_at (nullable)
  - created_at
```

### Modifications : `sites`

```sql
+ business_description (text, nullable)
+ target_audience (text, nullable)
+ topics (json, nullable)
+ last_crawled_at (datetime, nullable)
+ onboarding_completed_at (datetime, nullable)
```

### Modifications : `keywords`

```sql
+ queued_at (datetime, nullable)
+ processed_at (datetime, nullable)
+ priority (int)
```

### Modifications : `users`

```sql
+ notification_email_frequency (enum: daily, weekly, disabled)
+ notification_immediate_failures (boolean, default true)
+ notification_immediate_quota (boolean, default true)
```

---

## 9. Prochaines Étapes

1. **Migrations DB** : Nouvelles tables et colonnes
2. **Backend Autopilot** : Jobs Laravel + Scheduler
3. **API Endpoints** : Wizard, config, notifications
4. **Frontend Wizard** : Onboarding multi-étapes
5. **Frontend Dashboard** : Vue globale + drill-down
6. **Système Notifications** : In-app + emails
7. **Tests & Intégration**

---

*Document validé le 2025-12-20*
