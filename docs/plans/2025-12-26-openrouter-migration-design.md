# Migration OpenRouter - Design Document

**Date:** 2025-12-26
**Projet:** RankCruise
**Objectif:** Unifier tous les LLMs via OpenRouter pour simplifier la facturation et réduire les coûts

---

## Vue d'ensemble

### Avant
- PHP: 3 providers séparés (OpenAI, Anthropic, Google)
- JS Agents: OpenAI hardcodé
- Images: Replicate (FLUX)
- Embeddings: Voyage AI

### Après
- PHP: OpenRouter (unifié pour tous les LLMs)
- JS Agents: OpenRouter (unifié)
- Images: Replicate (FLUX) - inchangé
- Embeddings: Voyage AI - inchangé

---

## Mapping des modèles

### Pipeline Article (PHP)

| Étape | Avant | Après (OpenRouter) | Prix Input/Output |
|-------|-------|-------------------|-------------------|
| Research | `gemini-2.5-flash-lite` | `google/gemini-2.5-flash` | $0.15 / $0.60 |
| Outline | `gpt-4o` | `deepseek/deepseek-v3.2` | $0.26 / $0.38 |
| Write Section | `claude-sonnet-4-5` | `anthropic/claude-sonnet-4-5` | $3.00 / $15.00 |
| Polish | `gpt-4o-mini` | `deepseek/deepseek-v3.2` | $0.26 / $0.38 |

### Agents Node.js

| Agent | Avant | Après (OpenRouter) |
|-------|-------|-------------------|
| research-agent | `gpt-4o` | `deepseek/deepseek-v3.2` |
| competitor-agent | `gpt-4o` | `deepseek/deepseek-v3.2` |
| fact-checker-agent | `gpt-4o` | `openai/gpt-4o-mini` |
| internal-linking-agent | `gpt-4o` | `openai/gpt-4o-mini` |
| site-indexer | `gpt-4o` | `deepseek/deepseek-v3.2` |

---

## Fichiers à créer

### `app/Services/LLM/Providers/OpenRouterProvider.php`

Nouveau provider implémentant `LLMProviderInterface`:
- Base URL: `https://openrouter.ai/api/v1`
- Compatible OpenAI SDK
- Headers requis: `HTTP-Referer`, `X-Title`
- Support du calcul de coûts pour tracking admin

---

## Fichiers à modifier

### `app/Services/LLM/LLMManager.php`

Mise à jour de `getStepConfig()`:

```php
private function getStepConfig(string $step): array
{
    return match($step) {
        'research'      => ['provider' => 'openrouter', 'model' => 'google/gemini-2.5-flash'],
        'outline'       => ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-v3.2'],
        'write_section' => ['provider' => 'openrouter', 'model' => 'anthropic/claude-sonnet-4-5'],
        'polish'        => ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-v3.2'],
        default         => ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-v3.2'],
    };
}
```

### `config/services.php`

Ajouter configuration OpenRouter:

```php
'openrouter' => [
    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => 'https://openrouter.ai/api/v1',
],
```

### `agents/shared/llm.js`

Refactor pour utiliser OpenRouter:

```javascript
const openrouter = new OpenAI({
  baseURL: 'https://openrouter.ai/api/v1',
  apiKey: config.openrouterApiKey,
  defaultHeaders: {
    'HTTP-Referer': 'https://rankcruise.io',
    'X-Title': 'RankCruise SEO Pipeline'
  }
});

const DEFAULT_MODEL = 'deepseek/deepseek-v3.2';
```

### `agents/shared/config.js`

Ajouter `openrouterApiKey` depuis `.env`.

### `resources/js/Pages/Articles/Show.tsx`

Supprimer l'affichage du coût de génération (lignes 79-82):

```tsx
// SUPPRIMER:
<span className="flex items-center gap-1">
    <DollarSign className="h-4 w-4" />
    ${article.generation_cost.toFixed(3)}
</span>
```

### `.env` / `.env.example`

```env
# Ajouter
OPENROUTER_API_KEY=sk-or-...

# Supprimer (après migration validée)
# OPENAI_API_KEY=
# ANTHROPIC_API_KEY=
# GOOGLE_AI_API_KEY=
```

---

## Fichiers à supprimer (après validation)

- `app/Services/LLM/Providers/OpenAIProvider.php`
- `app/Services/LLM/Providers/AnthropicProvider.php`
- `app/Services/LLM/Providers/GoogleProvider.php`

---

## Plan d'implémentation

1. Créer `OpenRouterProvider.php`
2. Mettre à jour `LLMManager.php` (step config)
3. Ajouter config dans `config/services.php`
4. Refactorer `agents/shared/llm.js` pour OpenRouter
5. Mettre à jour `agents/shared/config.js`
6. Supprimer affichage coût dans `Articles/Show.tsx`
7. Ajouter `OPENROUTER_API_KEY` au `.env`
8. Tester le pipeline complet
9. Supprimer anciens providers (cleanup)

---

## Notes techniques

### OpenRouter Routing

Par défaut, OpenRouter route automatiquement vers le provider le plus rapide/disponible. Options disponibles:

```javascript
{
  "provider": {
    "order": ["anthropic", "bedrock"],
    "allow_fallbacks": true
  }
}
```

### Coûts

Les coûts restent trackés côté backend pour l'admin uniquement. Non affichés aux users (modèle SaaS par abonnement).

### Providers conservés

- **Voyage AI**: Embeddings (pas sur OpenRouter)
- **Replicate**: FLUX 1.1 Pro pour images (pas sur OpenRouter)
