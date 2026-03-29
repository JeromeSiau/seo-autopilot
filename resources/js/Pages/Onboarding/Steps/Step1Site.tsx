import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Globe, ChevronRight, Layers3, Newspaper } from 'lucide-react';
import clsx from 'clsx';
import { SiteMode } from '@/types';

interface Props {
    data: { domain: string; name: string; language: string; mode: SiteMode };
    setData: (data: { domain: string; name: string; language: string; mode: SiteMode }) => void;
    onNext: (siteId: number, mode: SiteMode, crawlWarning?: string | null) => void;
}

const LANGUAGES = [
    { code: 'fr', name: 'Français', flag: '🇫🇷' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
    { code: 'es', name: 'Español', flag: '🇪🇸' },
    { code: 'it', name: 'Italiano', flag: '🇮🇹' },
];

export default function Step1Site({ data, setData, onNext }: Props) {
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await axios.post(route('onboarding.step1'), data);
            onNext(response.data.site_id, response.data.mode, response.data.crawl_warning);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                const errs: Record<string, string> = {};
                Object.entries(error.response.data.errors).forEach(([key, val]) => {
                    errs[key] = Array.isArray(val) ? val[0] : String(val);
                });
                setErrors(errs);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6" data-testid="onboarding-step1-form">
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10">
                    <Globe className="h-7 w-7 text-primary-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                    Ajouter votre site
                </h2>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    Choisissez entre un site existant a connecter ou un blog heberge directement dans SEO Autopilot.
                </p>
            </div>

            <div>
                <label className="mb-3 block text-sm font-medium text-surface-700 dark:text-surface-300">
                    Mode de publication
                </label>
                <div className="grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={() => setData({ ...data, mode: 'external' })}
                        className={clsx(
                            'rounded-2xl border p-4 text-left transition-all',
                            data.mode === 'external'
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10 ring-2 ring-primary-500/20'
                                : 'border-surface-200 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:hover:border-surface-600 dark:hover:bg-surface-800'
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-surface-100 p-3 dark:bg-surface-800">
                                <Layers3 className="h-5 w-5 text-surface-700 dark:text-surface-300" />
                            </div>
                            <div>
                                <p className="font-semibold text-surface-900 dark:text-white">Site existant</p>
                                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                    Connexion WordPress, Ghost, Shopify ou Webflow.
                                </p>
                            </div>
                        </div>
                    </button>

                    <button
                        type="button"
                        onClick={() => setData({ ...data, mode: 'hosted' })}
                        className={clsx(
                            'rounded-2xl border p-4 text-left transition-all',
                            data.mode === 'hosted'
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10 ring-2 ring-primary-500/20'
                                : 'border-surface-200 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:hover:border-surface-600 dark:hover:bg-surface-800'
                        )}
                    >
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-surface-100 p-3 dark:bg-surface-800">
                                <Newspaper className="h-5 w-5 text-surface-700 dark:text-surface-300" />
                            </div>
                            <div>
                                <p className="font-semibold text-surface-900 dark:text-white">Blog heberge</p>
                                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                    SEO Autopilot genere, heberge et exporte votre mini-site.
                                </p>
                            </div>
                        </div>
                    </button>
                </div>
                {errors.mode && (
                    <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.mode}</p>
                )}
            </div>

            {/* Domain Field */}
            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                    {data.mode === 'hosted' ? 'Domaine principal souhaite' : 'Domaine'}
                </label>
                <input
                    type="text"
                    value={data.domain}
                    onChange={(e) => setData({ ...data, domain: e.target.value })}
                    placeholder={data.mode === 'hosted' ? 'blog.monsite.com' : 'monsite.com'}
                    data-testid="onboarding-domain-input"
                    className={clsx(
                        'block w-full rounded-xl border bg-white dark:bg-surface-800 px-4 py-3 text-surface-900 dark:text-white placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.domain ? 'border-red-300 dark:border-red-500/50' : 'border-surface-200 dark:border-surface-700'
                    )}
                />
                {errors.domain && (
                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.domain}</p>
                )}
                <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                    {data.mode === 'hosted'
                        ? 'Ce domaine sera utilise comme domaine public cible, puis relie ensuite au staging et au SSL.'
                        : 'Le sitemap et le crawl initial utiliseront ce domaine.'}
                </p>
            </div>

            {/* Name Field */}
            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                    Nom du site
                </label>
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData({ ...data, name: e.target.value })}
                    placeholder="Mon Super Site"
                    data-testid="onboarding-name-input"
                    className={clsx(
                        'block w-full rounded-xl border bg-white dark:bg-surface-800 px-4 py-3 text-surface-900 dark:text-white placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.name ? 'border-red-300 dark:border-red-500/50' : 'border-surface-200 dark:border-surface-700'
                    )}
                />
                {errors.name && (
                    <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                )}
            </div>

            {/* Language Selection */}
            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                    Langue du contenu
                </label>
                <div className="grid grid-cols-5 gap-2">
                    {LANGUAGES.map((lang) => (
                        <button
                            key={lang.code}
                            type="button"
                            onClick={() => setData({ ...data, language: lang.code })}
                            className={clsx(
                                'flex flex-col items-center gap-1 rounded-xl border p-3 transition-all',
                                data.language === lang.code
                                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10 ring-2 ring-primary-500/20'
                                    : 'border-surface-200 dark:border-surface-700 hover:border-surface-300 dark:hover:border-surface-600 hover:bg-surface-50 dark:hover:bg-surface-800'
                            )}
                        >
                            <span className="text-xl">{lang.flag}</span>
                            <span className={clsx(
                                'text-xs font-medium',
                                data.language === lang.code ? 'text-primary-700 dark:text-primary-400' : 'text-surface-600 dark:text-surface-400'
                            )}>
                                {lang.code.toUpperCase()}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            {/* Submit Button */}
            <div className="pt-2">
                <button
                    type="submit"
                    disabled={loading}
                    data-testid="onboarding-step1-submit"
                    className={clsx(
                        'flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3',
                        'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold',
                        'shadow-green hover:shadow-green-lg hover:-translate-y-0.5',
                        'transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                    )}
                >
                    {loading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <>
                            {data.mode === 'hosted' ? 'Configurer le blog heberge' : 'Continuer'}
                            <ChevronRight className="h-5 w-5" />
                        </>
                    )}
                </button>
            </div>
        </form>
    );
}
