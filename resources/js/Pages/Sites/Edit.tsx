import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import clsx from 'clsx';
import { Site, PageProps } from '@/types';
import { FormEvent } from 'react';

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

export default function SiteEdit({ site }: SiteEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: site.name,
        language: site.language,
        business_description: site.business_description || '',
        target_audience: site.target_audience || '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(route('sites.update', { site: site.id }));
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

            <div className="mx-auto max-w-2xl">
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Site Name */}
                        <div>
                            <label
                                htmlFor="name"
                                className="block text-sm font-medium text-surface-700 dark:text-surface-300"
                            >
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
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm',
                                    'placeholder:text-surface-400'
                                )}
                            />
                            {errors.name && (
                                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                            )}
                        </div>

                        {/* Language */}
                        <div>
                            <label
                                htmlFor="language"
                                className="block text-sm font-medium text-surface-700 dark:text-surface-300"
                            >
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
                                    <option key={lang.code} value={lang.code}>
                                        {lang.label}
                                    </option>
                                ))}
                            </select>
                            {errors.language && (
                                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.language}</p>
                            )}
                        </div>

                        {/* Business Description */}
                        <div>
                            <label
                                htmlFor="business_description"
                                className="block text-sm font-medium text-surface-700 dark:text-surface-300"
                            >
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
                            {errors.business_description && (
                                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.business_description}</p>
                            )}
                        </div>

                        {/* Target Audience */}
                        <div>
                            <label
                                htmlFor="target_audience"
                                className="block text-sm font-medium text-surface-700 dark:text-surface-300"
                            >
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
                            {errors.target_audience && (
                                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.target_audience}</p>
                            )}
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
