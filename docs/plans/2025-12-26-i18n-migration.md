# i18n Migration - Complete Translation Coverage

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate all hardcoded French/English text strings to the existing i18n system across all TSX files.

**Architecture:** Update JSON translation files (en/fr/es) with new keys organized by domain, then replace hardcoded strings in TSX files with `t?.domain.key` pattern using the existing `useTranslations()` hook.

**Tech Stack:** React, TypeScript, Inertia.js, Laravel (backend translation loading)

---

## Task 1: Update English Translation File (Base)

**Files:**
- Modify: `resources/lang/en/app.json`

**Step 1: Add new translation domains**

Add the following sections to `resources/lang/en/app.json`:

```json
{
  "profile": {
    "title": "Profile",
    "subtitle": "Manage your personal information and security preferences",
    "info": {
      "title": "Profile Information",
      "subtitle": "Update your name and email address"
    },
    "password": {
      "title": "Password",
      "subtitle": "Use a long, secure password",
      "current": "Current password",
      "new": "New password",
      "confirm": "Confirm password"
    },
    "delete": {
      "title": "Delete Account",
      "subtitle": "Permanently delete your account and all your data",
      "warning": "Once your account is deleted, all its resources and data will be permanently erased. Before deleting your account, please download any data you wish to keep.",
      "button": "Delete Account",
      "confirmTitle": "Delete your account?",
      "confirmWarning": "This action is irreversible. All your data will be permanently deleted.",
      "confirmPassword": "Confirm your password",
      "cancel": "Cancel",
      "confirmButton": "Delete permanently"
    },
    "saved": "Saved",
    "name": "Name",
    "email": "Email address",
    "emailNotVerified": "Your email address is not verified.",
    "resendLink": "Click here to resend the verification link.",
    "verificationSent": "A new verification link has been sent to your email address."
  },
  "integrations": {
    "title": "Integrations",
    "subtitle": "Connect your CMS to publish automatically",
    "add": "Add",
    "addIntegration": "Add an integration",
    "connectedTo": "Connected to",
    "available": "Available integrations",
    "confirmDelete": "Are you sure you want to delete {name}?",
    "site": "Site",
    "createSiteFirst": "You must create a site before adding an integration.",
    "createSite": "Create a site",
    "types": {
      "wordpress": {
        "name": "WordPress",
        "description": "Publishing via REST API"
      },
      "webflow": {
        "name": "Webflow",
        "description": "Webflow CMS integration"
      },
      "shopify": {
        "name": "Shopify",
        "description": "Shopify blog publishing"
      },
      "ghost": {
        "name": "Ghost",
        "description": "Open-source publishing platform"
      }
    }
  },
  "contentPlan": {
    "title": "Content Plan",
    "regenerate": "Regenerate",
    "regenerating": "Regenerating...",
    "confirmRegenerate": "This will regenerate your entire Content Plan. Continue?",
    "settings": "Settings",
    "tabs": {
      "keywords": "Keywords",
      "planned": "Planned",
      "generated": "Generated",
      "published": "Published"
    },
    "status": {
      "planned": "Planned",
      "generating": "Generating",
      "ready": "Ready",
      "published": "Published"
    },
    "progress": {
      "queuedTitle": "Preparing...",
      "queuedSubtitle": "Your Content Plan is queued. Analysis will start shortly.",
      "generatingTitle": "Creating your Content Plan",
      "generatingSubtitle": "We're analyzing your site and preparing your content calendar",
      "completed": "% completed",
      "backgroundNote": "You can close this page, generation continues in the background."
    },
    "tips": {
      "tip1": "We analyze Google data to find your best SEO opportunities",
      "tip2": "The more data your site has, the better the plan",
      "tip3": "Articles will be optimized for your target audience",
      "tip4": "Each topic is selected for its traffic potential",
      "tip5": "Our AI avoids topics you've already covered"
    }
  },
  "siteCard": {
    "status": {
      "active": "Active",
      "paused": "Paused",
      "setupRequired": "Setup required",
      "error": "Error"
    },
    "view": "View",
    "setup": "Setup",
    "perWeek": "per week",
    "thisWeek": "this week",
    "inReview": "in review",
    "setupIncomplete": "Setup incomplete",
    "continueSetup": "Continue setup"
  },
  "notifications": {
    "title": "Notifications",
    "loading": "Loading...",
    "empty": "No notifications"
  },
  "activity": {
    "title": "Activity",
    "empty": "No activity yet"
  }
}
```

**Step 2: Add complements to existing domains**

Add these keys to existing sections in `resources/lang/en/app.json`:

In `dashboard`:
```json
"generationBanner": {
  "title": "Content Plan being created",
  "viewProgress": "View progress"
},
"usage": {
  "percentUsed": "{percent}% used",
  "remaining": "{count} remaining"
},
"trend": {
  "vsLastMonth": "vs last month"
}
```

In `sites`:
```json
"create": {
  "title": "Add New Site",
  "domain": "Domain",
  "domainHelp": "Enter your domain without http:// or www.",
  "name": "Site Name",
  "namePlaceholder": "My Website",
  "language": "Primary Language"
},
"show": {
  "settings": "Settings",
  "topKeywords": "Top 10 Keywords",
  "sortedByScore": "Sorted by score",
  "diff": "Diff:",
  "pos": "Pos:",
  "businessDescription": "Business description",
  "targetAudience": "Target audience",
  "autopilot": "Autopilot",
  "active": "Active",
  "connections": "Connections",
  "gsc": "Google Search Console",
  "ga4": "Google Analytics 4",
  "cmsIntegrations": "CMS Integrations",
  "articlesToWatch": "Articles to watch",
  "quickActions": "Quick actions",
  "language": "Language",
  "createdAt": "Created on",
  "configuredAt": "Configured on"
},
"edit": {
  "title": "Edit",
  "generalInfo": "General information",
  "siteName": "Site name",
  "contentLanguage": "Content language",
  "businessDescription": "Business description",
  "businessPlaceholder": "Describe your business in a few sentences...",
  "targetAudience": "Target audience",
  "audiencePlaceholder": "E.g.: entrepreneurs, athletes, developers...",
  "brandVoice": "Brand voice",
  "brandVoiceSubtitle": "Customize the tone and style of your generated articles.",
  "tone": "Tone",
  "writingStyle": "Writing style",
  "stylePlaceholder": "E.g.: Short, punchy sentences. Use concrete examples...",
  "vocabularyUse": "Vocabulary to use",
  "vocabularyUseHelp": "Words and expressions to favor in your articles.",
  "vocabularyAvoid": "Vocabulary to avoid",
  "vocabularyAvoidHelp": "Words and expressions not to use.",
  "addWord": "Add a word or expression...",
  "addWordAvoid": "Add a word to avoid...",
  "contentExamples": "Content examples",
  "contentExamplesHelp": "Paste representative excerpts of your writing style.",
  "examplePlaceholder": "Paste a representative text excerpt...",
  "addExample": "Add this example",
  "saving": "Saving..."
},
"tones": {
  "professional": { "name": "Professional", "description": "Formal and credible" },
  "casual": { "name": "Casual", "description": "Accessible and friendly" },
  "expert": { "name": "Expert", "description": "Technical and in-depth" },
  "friendly": { "name": "Friendly", "description": "Warm and engaging" },
  "neutral": { "name": "Neutral", "description": "Objective and factual" }
},
"languages": {
  "fr": "Français",
  "en": "English",
  "es": "Español",
  "de": "Deutsch",
  "it": "Italiano",
  "pt": "Português",
  "nl": "Dutch"
}
```

In `articles`:
```json
"show": {
  "content": "Content",
  "copy": "Copy",
  "copied": "Copied!",
  "edit": "Edit",
  "noContent": "No content available.",
  "seoMeta": "SEO Meta",
  "metaTitle": "Meta Title",
  "metaDescription": "Meta Description",
  "noDescription": "No description",
  "slug": "Slug",
  "actions": "Actions",
  "approveForPublishing": "Approve for Publishing",
  "selectIntegration": "Select Integration",
  "choose": "Choose...",
  "publishArticle": "Publish Article",
  "noIntegrations": "No integrations configured.",
  "addOne": "Add one",
  "toPublish": "to publish this article.",
  "targetKeyword": "Target Keyword",
  "volume": "Volume",
  "difficulty": "Difficulty",
  "characters": "characters"
}
```

In `onboarding`:
```json
"wizard": {
  "title": "Site configuration",
  "exit": "Exit"
},
"steps": {
  "site": { "name": "Site", "description": "Basic info" },
  "searchConsole": { "name": "Search Console", "description": "GSC connection" },
  "business": { "name": "Business", "description": "Your activity" },
  "config": { "name": "Configuration", "description": "Publishing rhythm" },
  "publication": { "name": "Publication", "description": "CMS integration" },
  "launch": { "name": "Launch", "description": "Activate autopilot" }
}
```

**Step 3: Verify JSON is valid**

Run: `node -e "require('./resources/lang/en/app.json')"`
Expected: No output (valid JSON)

**Step 4: Commit**

```bash
git add resources/lang/en/app.json
git commit -m "i18n: add missing translation keys to en/app.json"
```

---

## Task 2: Update French Translation File

**Files:**
- Modify: `resources/lang/fr/app.json`

**Step 1: Add French translations for new domains**

Add the following sections to `resources/lang/fr/app.json`:

```json
{
  "profile": {
    "title": "Profil",
    "subtitle": "Gérez vos informations personnelles et vos préférences de sécurité",
    "info": {
      "title": "Informations du profil",
      "subtitle": "Mettez à jour votre nom et votre adresse email"
    },
    "password": {
      "title": "Mot de passe",
      "subtitle": "Assurez-vous d'utiliser un mot de passe long et sécurisé",
      "current": "Mot de passe actuel",
      "new": "Nouveau mot de passe",
      "confirm": "Confirmer le mot de passe"
    },
    "delete": {
      "title": "Supprimer le compte",
      "subtitle": "Supprimez définitivement votre compte et toutes vos données",
      "warning": "Une fois votre compte supprimé, toutes ses ressources et données seront définitivement effacées. Avant de supprimer votre compte, veuillez télécharger toutes les données que vous souhaitez conserver.",
      "button": "Supprimer le compte",
      "confirmTitle": "Supprimer votre compte ?",
      "confirmWarning": "Cette action est irréversible. Toutes vos données seront définitivement supprimées.",
      "confirmPassword": "Confirmez votre mot de passe",
      "cancel": "Annuler",
      "confirmButton": "Supprimer définitivement"
    },
    "saved": "Enregistré",
    "name": "Nom",
    "email": "Adresse email",
    "emailNotVerified": "Votre adresse email n'est pas vérifiée.",
    "resendLink": "Cliquez ici pour renvoyer le lien de vérification.",
    "verificationSent": "Un nouveau lien de vérification a été envoyé à votre adresse email."
  },
  "integrations": {
    "title": "Intégrations",
    "subtitle": "Connectez vos CMS pour publier automatiquement",
    "add": "Ajouter",
    "addIntegration": "Ajouter une intégration",
    "connectedTo": "Connecté à",
    "available": "Intégrations disponibles",
    "confirmDelete": "Êtes-vous sûr de vouloir supprimer {name} ?",
    "site": "Site",
    "createSiteFirst": "Vous devez d'abord créer un site avant d'ajouter une intégration.",
    "createSite": "Créer un site",
    "types": {
      "wordpress": {
        "name": "WordPress",
        "description": "Publication via REST API"
      },
      "webflow": {
        "name": "Webflow",
        "description": "Intégration CMS Webflow"
      },
      "shopify": {
        "name": "Shopify",
        "description": "Publication sur le blog Shopify"
      },
      "ghost": {
        "name": "Ghost",
        "description": "Plateforme de publication open-source"
      }
    }
  },
  "contentPlan": {
    "title": "Content Plan",
    "regenerate": "Régénérer",
    "regenerating": "Régénération...",
    "confirmRegenerate": "Cela va régénérer tout votre Content Plan. Continuer ?",
    "settings": "Paramètres",
    "tabs": {
      "keywords": "Keywords",
      "planned": "Planifiés",
      "generated": "Générés",
      "published": "Publiés"
    },
    "status": {
      "planned": "Planifié",
      "generating": "En génération",
      "ready": "Prêt",
      "published": "Publié"
    },
    "progress": {
      "queuedTitle": "Préparation en cours...",
      "queuedSubtitle": "Votre Content Plan est en file d'attente. L'analyse démarrera dans quelques instants.",
      "generatingTitle": "Création de votre Content Plan",
      "generatingSubtitle": "Nous analysons votre site et préparons votre calendrier de contenu",
      "completed": "% complété",
      "backgroundNote": "Vous pouvez fermer cette page, la génération continue en arrière-plan."
    },
    "tips": {
      "tip1": "Nous analysons les données de Google pour trouver vos meilleures opportunités SEO",
      "tip2": "Plus votre site a de données, meilleur sera le plan",
      "tip3": "Les articles seront optimisés pour votre audience cible",
      "tip4": "Chaque sujet est sélectionné pour son potentiel de trafic",
      "tip5": "Notre IA évite les sujets que vous avez déjà traités"
    }
  },
  "siteCard": {
    "status": {
      "active": "Actif",
      "paused": "En pause",
      "setupRequired": "Configuration requise",
      "error": "Erreur"
    },
    "view": "Voir",
    "setup": "Configurer",
    "perWeek": "par semaine",
    "thisWeek": "cette semaine",
    "inReview": "en révision",
    "setupIncomplete": "Configuration incomplète",
    "continueSetup": "Continuer la configuration"
  },
  "notifications": {
    "title": "Notifications",
    "loading": "Chargement...",
    "empty": "Aucune notification"
  },
  "activity": {
    "title": "Activité",
    "empty": "Aucune activité pour le moment"
  }
}
```

**Step 2: Add complements to existing French domains**

Same structure as English but with French translations. Add to `dashboard`:
```json
"generationBanner": {
  "title": "Content Plan en cours de création",
  "viewProgress": "Voir la progression"
},
"usage": {
  "percentUsed": "{percent}% utilisé",
  "remaining": "{count} restants"
},
"trend": {
  "vsLastMonth": "vs mois dernier"
}
```

Add to `sites`:
```json
"create": {
  "title": "Ajouter un nouveau site",
  "domain": "Domaine",
  "domainHelp": "Entrez votre domaine sans http:// ou www.",
  "name": "Nom du site",
  "namePlaceholder": "Mon site web",
  "language": "Langue principale"
},
"show": {
  "settings": "Paramètres",
  "topKeywords": "Top 10 Keywords",
  "sortedByScore": "Triés par score",
  "diff": "Diff:",
  "pos": "Pos:",
  "businessDescription": "Description du business",
  "targetAudience": "Audience cible",
  "autopilot": "Autopilot",
  "active": "Actif",
  "connections": "Connexions",
  "gsc": "Google Search Console",
  "ga4": "Google Analytics 4",
  "cmsIntegrations": "Intégrations CMS",
  "articlesToWatch": "Articles à surveiller",
  "quickActions": "Actions rapides",
  "language": "Langue",
  "createdAt": "Créé le",
  "configuredAt": "Configuré le"
},
"edit": {
  "title": "Modifier",
  "generalInfo": "Informations générales",
  "siteName": "Nom du site",
  "contentLanguage": "Langue du contenu",
  "businessDescription": "Description de l'activité",
  "businessPlaceholder": "Décrivez votre activité en quelques phrases...",
  "targetAudience": "Audience cible",
  "audiencePlaceholder": "Ex: entrepreneurs, sportifs, développeurs...",
  "brandVoice": "Voix de marque",
  "brandVoiceSubtitle": "Personnalisez le ton et le style de vos articles générés.",
  "tone": "Ton",
  "writingStyle": "Style d'écriture",
  "stylePlaceholder": "Ex: Phrases courtes et percutantes. Utiliser des exemples concrets...",
  "vocabularyUse": "Vocabulaire à utiliser",
  "vocabularyUseHelp": "Mots et expressions à privilégier dans vos articles.",
  "vocabularyAvoid": "Vocabulaire à éviter",
  "vocabularyAvoidHelp": "Mots et expressions à ne pas utiliser.",
  "addWord": "Ajouter un mot ou expression...",
  "addWordAvoid": "Ajouter un mot à éviter...",
  "contentExamples": "Exemples de contenu",
  "contentExamplesHelp": "Collez des extraits représentatifs de votre style d'écriture.",
  "examplePlaceholder": "Collez un extrait de texte représentatif de votre style...",
  "addExample": "Ajouter cet exemple",
  "saving": "Enregistrement..."
},
"tones": {
  "professional": { "name": "Professionnel", "description": "Formel et crédible" },
  "casual": { "name": "Décontracté", "description": "Accessible et convivial" },
  "expert": { "name": "Expert", "description": "Technique et approfondi" },
  "friendly": { "name": "Amical", "description": "Chaleureux et engageant" },
  "neutral": { "name": "Neutre", "description": "Objectif et factuel" }
},
"languages": {
  "fr": "Français",
  "en": "English",
  "es": "Español",
  "de": "Deutsch",
  "it": "Italiano",
  "pt": "Português",
  "nl": "Néerlandais"
}
```

Add to `articles`:
```json
"show": {
  "content": "Contenu",
  "copy": "Copier",
  "copied": "Copié !",
  "edit": "Modifier",
  "noContent": "Aucun contenu disponible.",
  "seoMeta": "SEO Meta",
  "metaTitle": "Meta Title",
  "metaDescription": "Meta Description",
  "noDescription": "Aucune description",
  "slug": "Slug",
  "actions": "Actions",
  "approveForPublishing": "Approuver pour publication",
  "selectIntegration": "Sélectionner l'intégration",
  "choose": "Choisir...",
  "publishArticle": "Publier l'article",
  "noIntegrations": "Aucune intégration configurée.",
  "addOne": "Ajoutez-en une",
  "toPublish": "pour publier cet article.",
  "targetKeyword": "Mot-clé cible",
  "volume": "Volume",
  "difficulty": "Difficulté",
  "characters": "caractères"
}
```

Add to `onboarding`:
```json
"wizard": {
  "title": "Configuration du site",
  "exit": "Quitter"
},
"steps": {
  "site": { "name": "Site", "description": "Infos de base" },
  "searchConsole": { "name": "Search Console", "description": "Connexion GSC" },
  "business": { "name": "Business", "description": "Votre activité" },
  "config": { "name": "Configuration", "description": "Rythme de publication" },
  "publication": { "name": "Publication", "description": "Intégration CMS" },
  "launch": { "name": "Lancement", "description": "Activer l'autopilot" }
}
```

**Step 3: Verify JSON is valid**

Run: `node -e "require('./resources/lang/fr/app.json')"`
Expected: No output (valid JSON)

**Step 4: Commit**

```bash
git add resources/lang/fr/app.json
git commit -m "i18n: add missing translation keys to fr/app.json"
```

---

## Task 3: Update Spanish Translation File

**Files:**
- Modify: `resources/lang/es/app.json`

**Step 1: Add Spanish translations**

Same structure as English/French with Spanish translations. Key examples:

```json
{
  "profile": {
    "title": "Perfil",
    "subtitle": "Gestiona tu información personal y preferencias de seguridad",
    "saved": "Guardado"
  },
  "integrations": {
    "title": "Integraciones",
    "subtitle": "Conecta tus CMS para publicar automáticamente"
  },
  "contentPlan": {
    "title": "Content Plan",
    "regenerate": "Regenerar"
  },
  "siteCard": {
    "status": {
      "active": "Activo",
      "paused": "Pausado"
    }
  },
  "notifications": {
    "title": "Notificaciones",
    "empty": "Sin notificaciones"
  },
  "activity": {
    "title": "Actividad",
    "empty": "Sin actividad por el momento"
  }
}
```

**Step 2: Verify and commit**

Run: `node -e "require('./resources/lang/es/app.json')"`

```bash
git add resources/lang/es/app.json
git commit -m "i18n: add missing translation keys to es/app.json"
```

---

## Task 4: Migrate Dashboard.tsx

**Files:**
- Modify: `resources/js/Pages/Dashboard.tsx`

**Step 1: Replace hardcoded strings**

Replace line 94 `{trend.positive ? '+' : ''}{trend.value}% vs last month`:
```tsx
{trend.positive ? '+' : ''}{trend.value}% {t?.dashboard?.trend?.vsLastMonth ?? 'vs last month'}
```

Replace lines 145-148 (generation banner):
```tsx
<p className="font-medium text-surface-900 dark:text-white flex items-center gap-2">
    <Sparkles className="h-4 w-4 text-primary-500" />
    {t?.dashboard?.generationBanner?.title ?? 'Content Plan being created'}
</p>
```

Replace line 156:
```tsx
{t?.dashboard?.generationBanner?.viewProgress ?? 'View progress'}
```

Replace lines 220-221:
```tsx
<span>{usagePercentage}% {t?.dashboard?.usage?.percentUsed ? '' : 'used'}</span>
<span>{stats.articles_limit - stats.articles_used} {t?.dashboard?.usage?.remaining ?? 'remaining'}</span>
```

**Step 2: Verify build**

Run: `npm run build`
Expected: Build succeeds without errors

**Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard.tsx
git commit -m "i18n: migrate Dashboard.tsx to use translations"
```

---

## Task 5: Migrate Profile Pages

**Files:**
- Modify: `resources/js/Pages/Profile/Edit.tsx`
- Modify: `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx`
- Modify: `resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx`
- Modify: `resources/js/Pages/Profile/Partials/DeleteUserForm.tsx`

**Step 1: Add useTranslations hook to each file**

Add import and hook:
```tsx
import { useTranslations } from '@/hooks/useTranslations';
// Inside component:
const { t } = useTranslations();
```

**Step 2: Replace all hardcoded strings**

Example for Edit.tsx:
```tsx
// "Profil" -> {t?.profile?.title ?? 'Profile'}
// "Gérez vos informations..." -> {t?.profile?.subtitle ?? 'Manage your personal information...'}
```

**Step 3: Verify and commit**

Run: `npm run build`

```bash
git add resources/js/Pages/Profile/
git commit -m "i18n: migrate Profile pages to use translations"
```

---

## Task 6: Migrate Sites Pages

**Files:**
- Modify: `resources/js/Pages/Sites/Index.tsx`
- Modify: `resources/js/Pages/Sites/Show.tsx`
- Modify: `resources/js/Pages/Sites/Edit.tsx`
- Modify: `resources/js/Pages/Sites/Create.tsx`
- Modify: `resources/js/Pages/Sites/ContentPlan.tsx`

**Step 1: Add useTranslations to each file (if not present)**

**Step 2: Replace hardcoded strings using t?.sites.xxx pattern**

**Step 3: Verify and commit**

```bash
git add resources/js/Pages/Sites/
git commit -m "i18n: migrate Sites pages to use translations"
```

---

## Task 7: Migrate Articles Pages

**Files:**
- Modify: `resources/js/Pages/Articles/Index.tsx`
- Modify: `resources/js/Pages/Articles/Show.tsx`

**Step 1-3: Same pattern as above**

```bash
git add resources/js/Pages/Articles/
git commit -m "i18n: migrate Articles pages to use translations"
```

---

## Task 8: Migrate Keywords Page

**Files:**
- Modify: `resources/js/Pages/Keywords/Index.tsx`

```bash
git add resources/js/Pages/Keywords/
git commit -m "i18n: migrate Keywords page to use translations"
```

---

## Task 9: Migrate Integrations Pages

**Files:**
- Modify: `resources/js/Pages/Integrations/Index.tsx`
- Modify: `resources/js/Pages/Integrations/Create.tsx`
- Modify: `resources/js/Pages/Integrations/Edit.tsx`

```bash
git add resources/js/Pages/Integrations/
git commit -m "i18n: migrate Integrations pages to use translations"
```

---

## Task 10: Migrate Onboarding Pages

**Files:**
- Modify: `resources/js/Pages/Onboarding/Wizard.tsx`
- Modify: `resources/js/Pages/Onboarding/Generating.tsx`

```bash
git add resources/js/Pages/Onboarding/
git commit -m "i18n: migrate Onboarding pages to use translations"
```

---

## Task 11: Migrate Components

**Files:**
- Modify: `resources/js/Components/Dashboard/SiteCard.tsx`
- Modify: `resources/js/Components/ContentPlan/ProgressSteps.tsx`
- Modify: `resources/js/Components/AgentActivity/ActivityFeed.tsx`
- Modify: `resources/js/Components/AgentActivity/ActivityButton.tsx`
- Modify: `resources/js/Components/Notifications/NotificationDropdown.tsx`
- Modify: `resources/js/Components/Integration/IntegrationForm.tsx`

```bash
git add resources/js/Components/
git commit -m "i18n: migrate Components to use translations"
```

---

## Task 12: Migrate Layouts

**Files:**
- Modify: `resources/js/Layouts/AppLayout.tsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx`

```bash
git add resources/js/Layouts/
git commit -m "i18n: migrate Layouts to use translations"
```

---

## Task 13: Final Verification

**Step 1: Run full build**

Run: `npm run build`
Expected: Build succeeds

**Step 2: Run type check**

Run: `npm run typecheck` (if available) or `npx tsc --noEmit`
Expected: No type errors

**Step 3: Manual testing**

- Test language switcher works
- Verify all pages display correctly in EN, FR, ES
- Check no hardcoded strings remain visible

**Step 4: Final commit**

```bash
git add -A
git commit -m "i18n: complete translation migration"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Update EN translations | 1 file |
| 2 | Update FR translations | 1 file |
| 3 | Update ES translations | 1 file |
| 4 | Migrate Dashboard | 1 file |
| 5 | Migrate Profile | 4 files |
| 6 | Migrate Sites | 5 files |
| 7 | Migrate Articles | 2 files |
| 8 | Migrate Keywords | 1 file |
| 9 | Migrate Integrations | 3 files |
| 10 | Migrate Onboarding | 2 files |
| 11 | Migrate Components | 6 files |
| 12 | Migrate Layouts | 2 files |
| 13 | Final verification | - |
