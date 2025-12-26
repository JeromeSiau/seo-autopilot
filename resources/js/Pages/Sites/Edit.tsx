import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import clsx from 'clsx';
import { Site, PageProps } from '@/types';
import { FormEvent, useState } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface SiteEditProps extends PageProps {
    site: Site;
}

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
    const { t } = useTranslations();

    const LANGUAGES = [
        { code: 'fr', label: t?.sites?.languages?.fr ?? 'Français' },
        { code: 'en', label: t?.sites?.languages?.en ?? 'English' },
        { code: 'es', label: t?.sites?.languages?.es ?? 'Español' },
        { code: 'de', label: t?.sites?.languages?.de ?? 'Deutsch' },
        { code: 'it', label: t?.sites?.languages?.it ?? 'Italiano' },
        { code: 'pt', label: t?.sites?.languages?.pt ?? 'Português' },
    ];

    const TONES = [
        { value: 'professional', label: t?.sites?.tones?.professional?.name ?? 'Professional', description: t?.sites?.tones?.professional?.description ?? 'Formal and credible' },
        { value: 'casual', label: t?.sites?.tones?.casual?.name ?? 'Casual', description: t?.sites?.tones?.casual?.description ?? 'Accessible and friendly' },
        { value: 'expert', label: t?.sites?.tones?.expert?.name ?? 'Expert', description: t?.sites?.tones?.expert?.description ?? 'Technical and in-depth' },
        { value: 'friendly', label: t?.sites?.tones?.friendly?.name ?? 'Friendly', description: t?.sites?.tones?.friendly?.description ?? 'Warm and engaging' },
        { value: 'neutral', label: t?.sites?.tones?.neutral?.name ?? 'Neutral', description: t?.sites?.tones?.neutral?.description ?? 'Objective and factual' },
    ];

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
                            {t?.sites?.edit?.title ?? 'Edit'} {site.name}
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {site.domain}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`${t?.sites?.edit?.title ?? 'Edit'} ${site.name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* General Settings */}
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-6">
                        {t?.sites?.edit?.generalInfo ?? 'General information'}
                    </h2>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Site Name */}
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                {t?.sites?.edit?.siteName ?? 'Site name'}
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
                                {t?.sites?.edit?.contentLanguage ?? 'Content language'}
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
                                {t?.sites?.edit?.businessDescription ?? 'Business description'}
                            </label>
                            <textarea
                                id="business_description"
                                rows={3}
                                value={data.business_description}
                                onChange={(e) => setData('business_description', e.target.value)}
                                placeholder={t?.sites?.edit?.businessPlaceholder ?? 'Describe your business in a few sentences...'}
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
                                {t?.sites?.edit?.targetAudience ?? 'Target audience'}
                            </label>
                            <input
                                type="text"
                                id="target_audience"
                                value={data.target_audience}
                                onChange={(e) => setData('target_audience', e.target.value)}
                                placeholder={t?.sites?.edit?.audiencePlaceholder ?? 'E.g.: entrepreneurs, athletes, developers...'}
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
                                {t?.sites?.edit?.brandVoice ?? 'Brand voice'}
                            </h2>
                            <p className="text-sm text-surface-500 dark:text-surface-400 mb-6">
                                {t?.sites?.edit?.brandVoiceSubtitle ?? 'Customize the tone and style of your generated articles.'}
                            </p>
                        </div>

                        {/* Tone */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">
                                {t?.sites?.edit?.tone ?? 'Tone'}
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
                                {t?.sites?.edit?.writingStyle ?? 'Writing style'}
                            </label>
                            <textarea
                                id="writing_style"
                                rows={3}
                                value={data.writing_style}
                                onChange={(e) => setData('writing_style', e.target.value)}
                                placeholder={t?.sites?.edit?.stylePlaceholder ?? 'E.g.: Short, punchy sentences. Use concrete examples...'}
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
                                {t?.sites?.edit?.vocabularyUse ?? 'Vocabulary to use'}
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                {t?.sites?.edit?.vocabularyUseHelp ?? 'Words and expressions to favor in your articles.'}
                            </p>
                            <TagInput
                                value={data.vocabulary.use || []}
                                onChange={(tags) => setData('vocabulary', { ...data.vocabulary, use: tags })}
                                placeholder={t?.sites?.edit?.addWord ?? 'Add a word or expression...'}
                            />
                        </div>

                        {/* Vocabulary Avoid */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                {t?.sites?.edit?.vocabularyAvoid ?? 'Vocabulary to avoid'}
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                {t?.sites?.edit?.vocabularyAvoidHelp ?? 'Words and expressions not to use.'}
                            </p>
                            <TagInput
                                value={data.vocabulary.avoid || []}
                                onChange={(tags) => setData('vocabulary', { ...data.vocabulary, avoid: tags })}
                                placeholder={t?.sites?.edit?.addWordAvoid ?? 'Add a word to avoid...'}
                            />
                        </div>

                        {/* Brand Examples */}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                {t?.sites?.edit?.contentExamples ?? 'Content examples'} ({data.brand_examples.length}/5)
                            </label>
                            <p className="text-xs text-surface-500 dark:text-surface-400 mb-2">
                                {t?.sites?.edit?.contentExamplesHelp ?? 'Paste representative excerpts of your writing style.'}
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
                                            placeholder={t?.sites?.edit?.examplePlaceholder ?? 'Paste a representative text excerpt...'}
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
                                            {t?.sites?.edit?.addExample ?? 'Add this example'}
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
                                {t?.common?.cancel ?? 'Cancel'}
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
                                {processing ? (t?.sites?.edit?.saving ?? 'Saving...') : (t?.common?.save ?? 'Save')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
