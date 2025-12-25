# Brand Voice par Site + Articles en Déclin - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Simplifier la brand voice (1 par site) et afficher les articles en déclin sur le dashboard et la page analytics.

**Architecture:** Migration des champs brand voice de la table `brand_voices` vers `sites`. Suppression de la relation Article→BrandVoice. Ajout d'une card "Articles à surveiller" sur le dashboard et d'une section tableau sur la page Analytics.

**Tech Stack:** Laravel 11, Inertia.js, React, TypeScript, Tailwind CSS

---

## Task 1: Migration - Ajouter les champs brand voice sur sites

**Files:**
- Create: `database/migrations/2025_12_25_000001_add_brand_voice_to_sites_table.php`

**Step 1: Créer la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('tone')->nullable()->after('topics');
            $table->text('writing_style')->nullable()->after('tone');
            $table->json('vocabulary')->nullable()->after('writing_style');
            $table->json('brand_examples')->nullable()->after('vocabulary');
        });

        // Remove brand_voice_id from articles if it exists
        if (Schema::hasColumn('articles', 'brand_voice_id')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropForeign(['brand_voice_id']);
                $table->dropColumn('brand_voice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['tone', 'writing_style', 'vocabulary', 'brand_examples']);
        });
    }
};
```

**Step 2: Exécuter la migration**

Run: `php artisan migrate`
Expected: Migration successful

**Step 3: Commit**

```bash
git add database/migrations/2025_12_25_000001_add_brand_voice_to_sites_table.php
git commit -m "feat: add brand voice fields to sites table"
```

---

## Task 2: Mettre à jour le modèle Site

**Files:**
- Modify: `app/Models/Site.php`

**Step 1: Ajouter les champs fillable et la méthode toBrandVoiceContext**

Dans `app/Models/Site.php`, ajouter après `'onboarding_completed_at'` dans `$fillable` (ligne 33):

```php
        'tone',
        'writing_style',
        'vocabulary',
        'brand_examples',
```

Dans `$casts` (après ligne 51), ajouter:

```php
        'vocabulary' => 'array',
        'brand_examples' => 'array',
```

À la fin de la classe (avant la dernière `}`), ajouter:

```php
    public function toBrandVoiceContext(): string
    {
        if (!$this->tone && !$this->writing_style) {
            return 'Write in a professional, engaging tone.';
        }

        $context = '';

        if ($this->writing_style) {
            $context .= "Writing Style: {$this->writing_style}\n";
        }

        if ($this->tone) {
            $context .= "Tone: {$this->tone}\n";
        }

        if (!empty($this->vocabulary)) {
            $context .= "Vocabulary preferences:\n";
            if (!empty($this->vocabulary['use'])) {
                $context .= "- Words to use: " . implode(', ', $this->vocabulary['use']) . "\n";
            }
            if (!empty($this->vocabulary['avoid'])) {
                $context .= "- Words to avoid: " . implode(', ', $this->vocabulary['avoid']) . "\n";
            }
        }

        if (!empty($this->brand_examples)) {
            $context .= "\nExample excerpts from existing content:\n";
            foreach (array_slice($this->brand_examples, 0, 3) as $example) {
                $context .= "---\n{$example}\n";
            }
        }

        return $context ?: 'Write in a professional, engaging tone.';
    }
```

**Step 2: Vérifier la syntaxe**

Run: `php -l app/Models/Site.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Models/Site.php
git commit -m "feat: add brand voice fields and toBrandVoiceContext method to Site model"
```

---

## Task 3: Mettre à jour ArticleGenerator

**Files:**
- Modify: `app/Services/Content/ArticleGenerator.php`

**Step 1: Remplacer l'usage de BrandVoice par Site**

Dans `app/Services/Content/ArticleGenerator.php`:

1. Supprimer la ligne 6: `use App\Models\BrandVoice;`

2. Modifier la signature de `generate()` (ligne 27):
   - Avant: `public function generate(Keyword $keyword, ?BrandVoice $brandVoice = null): GeneratedArticle`
   - Après: `public function generate(Keyword $keyword): GeneratedArticle`

3. Modifier l'appel à `writeContent()` (ligne 43):
   - Avant: `$content = $this->writeContent($outline, $research, $brandVoice);`
   - Après: `$content = $this->writeContent($outline, $research, $keyword->site);`

4. Modifier la signature de `writeContent()` (lignes 141-145):
   - Avant:
     ```php
     private function writeContent(
         ArticleOutline $outline,
         ResearchData $research,
         ?BrandVoice $brandVoice
     ): string {
     ```
   - Après:
     ```php
     private function writeContent(
         ArticleOutline $outline,
         ResearchData $research,
         \App\Models\Site $site
     ): string {
     ```

5. Modifier la ligne 148:
   - Avant: `$brandContext = $brandVoice?->toPromptContext() ?? 'Write in a professional, engaging tone.';`
   - Après: `$brandContext = $site->toBrandVoiceContext();`

**Step 2: Vérifier la syntaxe**

Run: `php -l app/Services/Content/ArticleGenerator.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Services/Content/ArticleGenerator.php
git commit -m "refactor: use Site brand voice instead of BrandVoice model in ArticleGenerator"
```

---

## Task 4: Mettre à jour GenerateArticleJob

**Files:**
- Modify: `app/Jobs/GenerateArticleJob.php`

**Step 1: Vérifier et mettre à jour l'appel à ArticleGenerator**

Rechercher l'appel à `$generator->generate()` et retirer le paramètre `$brandVoice` s'il est passé.

Run: `grep -n "generate(" app/Jobs/GenerateArticleJob.php`

Modifier l'appel pour ne passer que le keyword:
- Avant: `$generator->generate($keyword, $brandVoice)`
- Après: `$generator->generate($keyword)`

**Step 2: Vérifier la syntaxe**

Run: `php -l app/Jobs/GenerateArticleJob.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Jobs/GenerateArticleJob.php
git commit -m "refactor: simplify GenerateArticleJob to use site brand voice"
```

---

## Task 5: Mettre à jour SiteController pour les champs brand voice

**Files:**
- Modify: `app/Http/Controllers/Web/SiteController.php`

**Step 1: Ajouter les champs brand voice à la validation update**

Dans `app/Http/Controllers/Web/SiteController.php`, méthode `update()` (ligne 86), modifier la validation:

```php
    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'size:2'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'in:professional,casual,expert,friendly,neutral'],
            'writing_style' => ['nullable', 'string', 'max:1000'],
            'vocabulary' => ['nullable', 'array'],
            'vocabulary.use' => ['nullable', 'array'],
            'vocabulary.use.*' => ['string', 'max:100'],
            'vocabulary.avoid' => ['nullable', 'array'],
            'vocabulary.avoid.*' => ['string', 'max:100'],
            'brand_examples' => ['nullable', 'array', 'max:5'],
            'brand_examples.*' => ['string', 'max:2000'],
        ]);

        $site->update($validated);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Site updated successfully.');
    }
```

**Step 2: Vérifier la syntaxe**

Run: `php -l app/Http/Controllers/Web/SiteController.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Http/Controllers/Web/SiteController.php
git commit -m "feat: add brand voice fields validation to SiteController"
```

---

## Task 6: Mettre à jour SiteResource pour inclure les champs brand voice

**Files:**
- Modify: `app/Http/Resources/SiteResource.php`

**Step 1: Ajouter les champs brand voice au resource**

Rechercher le fichier et ajouter les champs:

Run: `grep -n "toArray" app/Http/Resources/SiteResource.php`

Ajouter dans le tableau retourné:
```php
            'tone' => $this->tone,
            'writing_style' => $this->writing_style,
            'vocabulary' => $this->vocabulary,
            'brand_examples' => $this->brand_examples,
```

**Step 2: Commit**

```bash
git add app/Http/Resources/SiteResource.php
git commit -m "feat: add brand voice fields to SiteResource"
```

---

## Task 7: Mettre à jour Sites/Edit.tsx - Section Brand Voice

**Files:**
- Modify: `resources/js/Pages/Sites/Edit.tsx`
- Modify: `resources/js/types/index.d.ts`

**Step 1: Mettre à jour les types TypeScript**

Dans `resources/js/types/index.d.ts`, ajouter au type `Site`:
```typescript
    tone?: string | null;
    writing_style?: string | null;
    vocabulary?: { use?: string[]; avoid?: string[] } | null;
    brand_examples?: string[] | null;
```

**Step 2: Mettre à jour Sites/Edit.tsx**

Remplacer le contenu de `resources/js/Pages/Sites/Edit.tsx`:

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import clsx from 'clsx';
import { Site, PageProps } from '@/types';
import { FormEvent, useState } from 'react';

interface SiteEditProps extends PageProps {
    site: Site;
}

const LANGUAGES = [
    { code: 'fr', label: 'Français' },
    { code: 'en', label: 'English' },
    { code: 'es', label: 'Español' },
    { code: 'de', label: 'Deutsch' },
    { code: 'it', label: 'Italiano' },
    { code: 'pt', label: 'Português' },
];

const TONES = [
    { value: 'professional', label: 'Professionnel', description: 'Formel et crédible' },
    { value: 'casual', label: 'Décontracté', description: 'Accessible et convivial' },
    { value: 'expert', label: 'Expert', description: 'Technique et approfondi' },
    { value: 'friendly', label: 'Amical', description: 'Chaleureux et engageant' },
    { value: 'neutral', label: 'Neutre', description: 'Objectif et factuel' },
];

function TagInput({
    value,
    onChange,
    placeholder
}: {
    value: string[];
    onChange: (tags: string[]) => void;
    placeholder: string;
}) {
    const [input, setInput] = useState('');

    const addTag = () => {
        const tag = input.trim();
        if (tag && !value.includes(tag)) {
            onChange([...value, tag]);
            setInput('');
        }
    };

    const removeTag = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <input
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addTag())}
                    placeholder={placeholder}
                    className={clsx(
                        'flex-1 rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                        'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                        'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                        'placeholder:text-surface-400'
                    )}
                />
                <button
                    type="button"
                    onClick={addTag}
                    className="rounded-xl bg-surface-100 dark:bg-surface-800 px-3 py-2 text-surface-600 dark:text-surface-400 hover:bg-surface-200 dark:hover:bg-surface-700"
                >
                    <Plus className="h-4 w-4" />
                </button>
            </div>
            {value.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {value.map((tag, index) => (
                        <span
                            key={index}
                            className="inline-flex items-center gap-1 rounded-lg bg-primary-50 dark:bg-primary-500/15 px-2.5 py-1 text-sm text-primary-700 dark:text-primary-400"
                        >
                            {tag}
                            <button type="button" onClick={() => removeTag(index)} className="hover:text-primary-900 dark:hover:text-primary-200">
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function SiteEdit({ site }: SiteEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: site.name,
        language: site.language,
        business_description: site.business_description || '',
        target_audience: site.target_audience || '',
        tone: site.tone || '',
        writing_style: site.writing_style || '',
        vocabulary: site.vocabulary || { use: [], avoid: [] },
        brand_examples: site.brand_examples || [],
    });

    const [exampleInput, setExampleInput] = useState('');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(route('sites.update', { site: site.id }));
    };

    const addExample = () => {
        const example = exampleInput.trim();
        if (example && data.brand_examples.length < 5) {
            setData('brand_examples', [...data.brand_examples, example]);
            setExampleInput('');
        }
    };

    const removeExample = (index: number) => {
        setData('brand_examples', data.brand_examples.filter((_, i) => i !== index));
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('sites.show', { site: site.id })}
                        className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-white transition-colors"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                            Modifier {site.name}
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {site.domain}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Modifier ${site.name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* General Settings */}
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-6">
                        Informations générales
                    </h2>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Site Name */}
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                Nom du site
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                    'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                )}
                            />
                            {errors.name && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>}
                        </div>

                        {/* Language */}
                        <div>
                            <label htmlFor="language" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                Langue du contenu
                            </label>
                            <select
                                id="language"
                                value={data.language}
                                onChange={(e) => setData('language', e.target.value)}
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                    'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                )}
                            >
                                {LANGUAGES.map((lang) => (
                                    <option key={lang.code} value={lang.code}>{lang.label}</option>
                                ))}
                            </select>
                        </div>

                        {/* Business Description */}
                        <div>
                            <label htmlFor="business_description" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                Description de l'activité
                            </label>
                            <textarea
                                id="business_description"
                                rows={3}
                                value={data.business_description}
                                onChange={(e) => setData('business_description', e.target.value)}
                                placeholder="Décrivez votre activité en quelques phrases..."
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                    'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                                    'placeholder:text-surface-400'
                                )}
                            />
                        </div>

                        {/* Target Audience */}
                        <div>
                            <label htmlFor="target_audience" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                Audience cible
                            </label>
                            <input
                                type="text"
                                id="target_audience"
                                value={data.target_audience}
                                onChange={(e) => setData('target_audience', e.target.value)}
                                placeholder="Ex: entrepreneurs, sportifs, développeurs..."
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                    'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                                    'placeholder:text-surface-400'
                                )}
                            />
                        </div>

                        {/* Divider */}
                        <div className="border-t border-surface-100 dark:border-surface-800 pt-6">
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-4">
                                Voix de marque
                            </h2>
                            <p className="text-sm text-surface-500 dark:text-surface-400 mb-6">
                                Personnalisez le ton et le style de vos articles générés.
                            </p>
                        </div>

                        {/* Tone */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">
                                Ton
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                {TONES.map((tone) => (
                                    <button
                                        key={tone.value}
                                        type="button"
                                        onClick={() => setData('tone', data.tone === tone.value ? '' : tone.value)}
                                        className={clsx(
                                            'rounded-xl border p-3 text-left transition-all',
                                            data.tone === tone.value
                                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/15'
                                                : 'border-surface-200 dark:border-surface-700 hover:border-surface-300 dark:hover:border-surface-600'
                                        )}
                                    >
                                        <p className={clsx(
                                            'font-medium text-sm',
                                            data.tone === tone.value ? 'text-primary-700 dark:text-primary-400' : 'text-surface-900 dark:text-white'
                                        )}>
                                            {tone.label}
                                        </p>
                                        <p className="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                                            {tone.description}
                                        </p>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Writing Style */}
                        <div>
                            <label htmlFor="writing_style" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                Style d'écriture
                            </label>
                            <textarea
                                id="writing_style"
                                rows={3}
                                value={data.writing_style}
                                onChange={(e) => setData('writing_style', e.target.value)}
                                placeholder="Ex: Phrases courtes et percutantes. Utiliser des exemples concrets. Éviter le jargon technique..."
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                    'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                                    'placeholder:text-surface-400'
                                )}
                            />
                        </div>

                        {/* Vocabulary Use */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Vocabulaire à utiliser
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                Mots et expressions à privilégier dans vos articles.
                            </p>
                            <TagInput
                                value={data.vocabulary.use || []}
                                onChange={(tags) => setData('vocabulary', { ...data.vocabulary, use: tags })}
                                placeholder="Ajouter un mot ou expression..."
                            />
                        </div>

                        {/* Vocabulary Avoid */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Vocabulaire à éviter
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                Mots et expressions à ne pas utiliser.
                            </p>
                            <TagInput
                                value={data.vocabulary.avoid || []}
                                onChange={(tags) => setData('vocabulary', { ...data.vocabulary, avoid: tags })}
                                placeholder="Ajouter un mot à éviter..."
                            />
                        </div>

                        {/* Brand Examples */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Exemples de contenu ({data.brand_examples.length}/5)
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                Collez des extraits représentatifs de votre style d'écriture.
                            </p>
                            <div className="space-y-3">
                                {data.brand_examples.map((example, index) => (
                                    <div key={index} className="relative">
                                        <div className="rounded-xl bg-surface-50 dark:bg-surface-800 p-3 pr-10 text-sm text-surface-700 dark:text-surface-300">
                                            {example.substring(0, 200)}{example.length > 200 ? '...' : ''}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeExample(index)}
                                            className="absolute top-2 right-2 rounded-lg p-1 text-surface-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/15"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                                {data.brand_examples.length < 5 && (
                                    <div className="space-y-2">
                                        <textarea
                                            rows={3}
                                            value={exampleInput}
                                            onChange={(e) => setExampleInput(e.target.value)}
                                            placeholder="Collez un extrait de texte représentatif de votre style..."
                                            className={clsx(
                                                'block w-full rounded-xl border-surface-300 dark:border-surface-700 shadow-sm',
                                                'bg-white dark:bg-surface-800 text-surface-900 dark:text-white',
                                                'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                                                'placeholder:text-surface-400'
                                            )}
                                        />
                                        <button
                                            type="button"
                                            onClick={addExample}
                                            disabled={!exampleInput.trim()}
                                            className={clsx(
                                                'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium',
                                                'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300',
                                                'hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-50'
                                            )}
                                        >
                                            <Plus className="h-4 w-4" />
                                            Ajouter cet exemple
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-surface-100 dark:border-surface-800">
                            <Link
                                href={route('sites.show', { site: site.id })}
                                className={clsx(
                                    'inline-flex items-center rounded-xl px-4 py-2.5',
                                    'text-sm font-semibold text-surface-700 dark:text-surface-300',
                                    'border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800',
                                    'hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors'
                                )}
                            >
                                Annuler
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className={clsx(
                                    'inline-flex items-center rounded-xl px-4 py-2.5',
                                    'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                                    'shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5',
                                    'transition-all disabled:opacity-50 disabled:cursor-not-allowed'
                                )}
                            >
                                {processing ? 'Enregistrement...' : 'Enregistrer'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
```

**Step 3: Vérifier le build TypeScript**

Run: `npm run typecheck`
Expected: No TypeScript errors

**Step 4: Commit**

```bash
git add resources/js/Pages/Sites/Edit.tsx resources/js/types/index.d.ts
git commit -m "feat: add brand voice section to site edit page"
```

---

## Task 8: Ajouter la card "Articles à surveiller" sur Sites/Show.tsx

**Files:**
- Modify: `resources/js/Pages/Sites/Show.tsx`

**Step 1: Ajouter le fetch des articles en déclin**

Dans `resources/js/Pages/Sites/Show.tsx`, ajouter après les imports existants:

```tsx
import { useEffect, useState } from 'react';
import { AlertTriangle, TrendingDown } from 'lucide-react';
```

Ajouter le state et le fetch après la ligne `export default function SiteShow`:

```tsx
    const [decliningArticles, setDecliningArticles] = useState<Array<{
        article: { id: number; title: string };
        position_change: number;
        current_position: number;
    }>>([]);
    const [loadingDeclining, setLoadingDeclining] = useState(true);

    useEffect(() => {
        if (site.gsc_connected) {
            fetch(route('api.analytics.dashboard', { site: site.id }))
                .then(res => res.json())
                .then(data => {
                    setDecliningArticles(data.data?.needs_attention || []);
                    setLoadingDeclining(false);
                })
                .catch(() => setLoadingDeclining(false));
        } else {
            setLoadingDeclining(false);
        }
    }, [site.id, site.gsc_connected]);
```

**Step 2: Ajouter la card dans la sidebar**

Après la card "Connexions" (après ligne 290), ajouter:

```tsx
                    {/* Declining Articles */}
                    {site.gsc_connected && (
                        <Card>
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                    <AlertTriangle className="h-4 w-4 text-amber-500" />
                                    Articles à surveiller
                                </h2>
                                {decliningArticles.length > 0 && (
                                    <span className="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                                        {decliningArticles.length}
                                    </span>
                                )}
                            </div>
                            {loadingDeclining ? (
                                <div className="py-4 text-center text-sm text-surface-400">
                                    Chargement...
                                </div>
                            ) : decliningArticles.length === 0 ? (
                                <div className="py-4 text-center">
                                    <p className="text-sm text-surface-500 dark:text-surface-400">
                                        Tous vos articles performent bien 🎉
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {decliningArticles.slice(0, 5).map((item) => (
                                        <Link
                                            key={item.article.id}
                                            href={route('articles.show', { article: item.article.id })}
                                            className="block rounded-lg p-2 -mx-2 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                        >
                                            <p className="text-sm font-medium text-surface-900 dark:text-white truncate">
                                                {item.article.title}
                                            </p>
                                            <div className="flex items-center gap-3 mt-1 text-xs">
                                                <span className="text-surface-500 dark:text-surface-400">
                                                    Position {Math.round(item.current_position)}
                                                </span>
                                                <span className="flex items-center gap-0.5 text-red-600 dark:text-red-400">
                                                    <TrendingDown className="h-3 w-3" />
                                                    {item.position_change.toFixed(1)}
                                                </span>
                                            </div>
                                        </Link>
                                    ))}
                                    {decliningArticles.length > 5 && (
                                        <Link
                                            href={route('analytics.index', { site_id: site.id })}
                                            className="block text-center text-sm text-primary-600 dark:text-primary-400 hover:text-primary-500 py-2"
                                        >
                                            Voir les {decliningArticles.length - 5} autres →
                                        </Link>
                                    )}
                                </div>
                            )}
                        </Card>
                    )}
```

**Step 3: Vérifier le build**

Run: `npm run typecheck`
Expected: No TypeScript errors

**Step 4: Commit**

```bash
git add resources/js/Pages/Sites/Show.tsx
git commit -m "feat: add declining articles card to site dashboard"
```

---

## Task 9: Ajouter la section "Articles en déclin" sur Analytics/Index.tsx

**Files:**
- Modify: `resources/js/Pages/Analytics/Index.tsx`
- Modify: `app/Http/Controllers/Web/AnalyticsController.php`

**Step 1: Mettre à jour le controller pour inclure needs_attention**

Dans le controller Analytics, ajouter `needs_attention` aux données passées à la vue (utiliser le service existant).

**Step 2: Ajouter la section dans Analytics/Index.tsx**

Après les tableaux Top Pages et Top Queries (après ligne 444), avant les `</>` de fermeture:

```tsx
                    {/* Declining Articles */}
                    {selectedSite && (
                        <div className="mt-6 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                            <div className="border-b border-surface-100 dark:border-surface-800 px-6 py-4 flex items-center justify-between">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <TrendingDown className="h-5 w-5 text-amber-500" />
                                        Articles nécessitant une attention
                                    </h3>
                                    <p className="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
                                        Articles dont la position a chuté de plus de 5 places
                                    </p>
                                </div>
                            </div>
                            {/* Note: needs_attention data would come from props */}
                            <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                Données disponibles prochainement.
                            </div>
                        </div>
                    )}
```

**Step 3: Commit**

```bash
git add resources/js/Pages/Analytics/Index.tsx
git commit -m "feat: add placeholder for declining articles section in analytics"
```

---

## Task 10: Nettoyage - Supprimer les références à BrandVoice

**Files:**
- Delete: `app/Models/BrandVoice.php`
- Modify: `resources/js/Pages/Settings/Index.tsx`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Web/SettingsController.php`

**Step 1: Supprimer le lien dans Settings/Index.tsx**

Dans `resources/js/Pages/Settings/Index.tsx`, supprimer l'entrée brand voices du tableau `settingsLinks` (lignes 46-52).

**Step 2: Supprimer la route dans web.php**

Dans `routes/web.php`, supprimer la ligne:
```php
Route::get('/settings/brand-voices', [SettingsController::class, 'brandVoices'])->name('settings.brand-voices');
```

**Step 3: Supprimer la méthode dans SettingsController**

Dans `app/Http/Controllers/Web/SettingsController.php`, supprimer la méthode `brandVoices()` (lignes 56-63).

**Step 4: Supprimer le modèle BrandVoice**

Run: `rm app/Models/BrandVoice.php`

**Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove BrandVoice model and settings page"
```

---

## Task 11: Ajouter la brand voice à l'onboarding (optionnel)

**Files:**
- Create: `resources/js/Pages/Onboarding/Steps/Step3BrandVoice.tsx`
- Modify: `resources/js/Pages/Onboarding/Wizard.tsx`

**Step 1: Créer le composant Step3BrandVoice**

Créer un nouveau step simple avec sélection du ton uniquement (pour ne pas alourdir l'onboarding).

**Step 2: Insérer le step dans le wizard**

Modifier l'ordre des steps dans Wizard.tsx pour insérer après Step3Business.

**Step 3: Commit**

```bash
git add resources/js/Pages/Onboarding/Steps/Step3BrandVoice.tsx resources/js/Pages/Onboarding/Wizard.tsx
git commit -m "feat: add optional brand voice step to onboarding"
```

---

## Vérification finale

**Step 1: Exécuter les tests**

Run: `php artisan test`
Expected: All tests pass

**Step 2: Vérifier le build frontend**

Run: `npm run build`
Expected: Build successful

**Step 3: Tester manuellement**

1. Aller sur /sites/{id}/edit et vérifier la section brand voice
2. Remplir les champs et sauvegarder
3. Vérifier que les articles générés utilisent le nouveau style
4. Aller sur /sites/{id} et vérifier la card "Articles à surveiller"
5. Aller sur /analytics et vérifier la section articles en déclin
