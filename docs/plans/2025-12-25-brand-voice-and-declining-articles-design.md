# Design : Brand Voice par Site + Articles en Déclin

**Date :** 2025-12-25
**Statut :** Approuvé

## Contexte

Deux features backend existent mais manquent d'UI :
1. Brand Voice - table séparée `brand_voices` liée à `Team`, jamais exposée
2. Articles Needing Attention - calculé dans `AnalyticsSyncService`, jamais affiché

Décision : simplifier la brand voice (1 par site au lieu de plusieurs par team) et exposer les articles en déclin.

## 1. Brand Voice sur Site

### Migration base de données

**Ajouter sur `sites` :**
- `tone` : string nullable (Professionnel, Décontracté, Expert, Amical, Neutre)
- `writing_style` : text nullable
- `vocabulary` : JSON nullable (`{ "use": [...], "avoid": [...] }`)
- `brand_examples` : JSON nullable (array de strings)

**Supprimer :**
- Table `brand_voices`
- Colonne `brand_voice_id` sur `articles`

### Modification du code

**`App\Models\Site` :**
```php
public function toBrandVoiceContext(): string
{
    // Reprendre la logique de BrandVoice::toPromptContext()
}
```

**`App\Services\Content\ArticleGenerator` :**
- Remplacer `$article->brandVoice->toPromptContext()` par `$article->site->toBrandVoiceContext()`

### UI - Page d'édition du site

Nouvelle section "Voix de marque" dans `resources/js/Pages/Sites/Edit.tsx` :

| Champ | Type | Description |
|-------|------|-------------|
| Ton | Select | Professionnel, Décontracté, Expert, Amical, Neutre |
| Style d'écriture | Textarea | Instructions libres pour le style |
| Vocabulaire à utiliser | Tags input | Mots/expressions à privilégier |
| Vocabulaire à éviter | Tags input | Mots/expressions interdits |
| Exemples de contenu | Textarea | 2-3 extraits représentatifs |

### UI - Onboarding

Nouvelle étape après "Description du business" :

- Titre : "Personnalisez votre style"
- Ton : 4-5 options visuelles avec description courte
- Style : textarea optionnel
- Bouton "Passer cette étape" visible

### Nettoyage

- Supprimer le lien "Voix de marque" dans `Settings/Index.tsx`
- Supprimer `settings.brand-voices` dans `routes/web.php`
- Supprimer `SettingsController::brandVoices()`
- Supprimer `App\Models\BrandVoice` (après migration des données)

## 2. Articles en Déclin

### Source de données

API existante : `GET /api/sites/{site}/analytics/dashboard`

Retourne `needs_attention` : array d'articles avec :
- `article` : objet Article
- `position_change` : float (ex: 8.5 = a perdu 8.5 positions)
- `current_position` : float

Logique : articles publiés dont la position moyenne a chuté de plus de 5 sur les 30 derniers jours.

### UI - Dashboard du site

Nouvelle card dans la sidebar de `Sites/Show.tsx` :

**Position :** après la card "Connexions"

**Contenu :**
```
┌─────────────────────────────────┐
│ ⚠️ Articles à surveiller    (3) │
├─────────────────────────────────┤
│ Comment optimiser son SEO       │
│ Position 18  ↓ 8.5              │
├─────────────────────────────────┤
│ Guide du débutant React         │
│ Position 24  ↓ 6.2              │
├─────────────────────────────────┤
│ [Voir les analytics →]          │
└─────────────────────────────────┘
```

**État vide :** "Tous vos articles performent bien 🎉"

### UI - Page Analytics

Nouvelle section dans la page Analytics du site :

**Position :** après les graphiques globaux

**Tableau :**
| Titre | Position actuelle | Variation | Action |
|-------|-------------------|-----------|--------|
| Article X | 18 | ↓ 8.5 | Voir |
| Article Y | 24 | ↓ 6.2 | Voir |

Le bouton "Voir" redirige vers `articles.show`.

## Hors scope

- Page d'édition d'articles (`Articles/Edit.tsx`)
- Synchronisation des modifications avec le CMS

Ces features sont notées dans `TODO.md` pour implémentation future.

## Fichiers impactés

### À créer
- Migration pour modifier `sites` et supprimer `brand_voices`

### À modifier
- `app/Models/Site.php` - ajouter `toBrandVoiceContext()`
- `app/Services/Content/ArticleGenerator.php` - utiliser site au lieu de brandVoice
- `app/Http/Controllers/Web/SiteController.php` - gérer les nouveaux champs
- `resources/js/Pages/Sites/Edit.tsx` - section brand voice
- `resources/js/Pages/Sites/Show.tsx` - card articles en déclin
- `resources/js/Pages/Analytics/Index.tsx` - section articles en déclin
- Onboarding (étape brand voice)
- `routes/web.php` - supprimer route settings.brand-voices
- `app/Http/Controllers/Web/SettingsController.php` - supprimer brandVoices()
- `resources/js/Pages/Settings/Index.tsx` - supprimer lien brand voices

### À supprimer
- `app/Models/BrandVoice.php`
- `database/migrations/2025_12_20_130545_create_brand_voices_table.php` (après migration)
