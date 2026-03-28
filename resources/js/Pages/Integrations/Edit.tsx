import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Globe, Plug } from 'lucide-react';
import clsx from 'clsx';
import { Integration, PageProps } from '@/types';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface IntegrationsEditProps extends PageProps {
    integration: Integration & {
        credentials?: Record<string, string>;
        secret_fields?: Record<string, boolean>;
    };
}

export default function IntegrationsEdit({ integration }: IntegrationsEditProps) {
    const { t } = useTranslations();
    const secretFields = integration.secret_fields || {};

    const { data, setData, put, processing, errors } = useForm({
        name: integration.name,
        credentials: integration.credentials || {} as Record<string, string>,
    });

    const handleCredentialChange = (field: string, value: string) => {
        setData('credentials', { ...data.credentials, [field]: value });
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(route('integrations.update', { integration: integration.id }));
    };

    const inputClasses = clsx(
        'mt-1.5 block w-full rounded-xl border px-4 py-3 text-surface-900 dark:text-white',
        'bg-white dark:bg-surface-800 border-surface-300 dark:border-surface-700',
        'placeholder:text-surface-400',
        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
        'transition-colors'
    );

    const secretPlaceholder = (key: string, fallback: string) =>
        secretFields[`has_${key}`] ? 'Leave empty to keep current' : fallback;

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('integrations.index')}
                        className="rounded-lg p-2 text-surface-400 transition-colors hover:bg-surface-100 hover:text-surface-600 dark:hover:bg-surface-800 dark:hover:text-white"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                            {t?.integrations?.edit?.title ?? 'Edit integration'}
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {t?.integrations?.edit?.subtitle ?? 'Update connection settings'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`${t?.integrations?.edit?.title ?? 'Edit'} ${integration.name}`} />

            <div className="mx-auto max-w-2xl">
                <div className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50 dark:backdrop-blur-xl">
                    <div className="mb-6 flex items-center gap-4">
                        <div className={clsx(
                            'flex h-12 w-12 items-center justify-center rounded-xl text-white',
                            integration.type === 'wordpress' ? 'bg-[#21759b]' :
                            integration.type === 'webflow' ? 'bg-[#4353ff]' :
                            integration.type === 'shopify' ? 'bg-[#96bf48]' :
                            integration.type === 'ghost' ? 'bg-[#15171a]' :
                            'bg-surface-200 dark:bg-surface-700'
                        )}>
                            <Plug className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                {t?.integrations?.types?.[integration.type as keyof typeof t.integrations.types]?.name || integration.type}
                            </h2>
                            <p className="text-sm text-surface-500 dark:text-surface-400">
                                {t?.integrations?.types?.[integration.type as keyof typeof t.integrations.types]?.description}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {integration.site && (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                    {t?.integrations?.edit?.connectedSite ?? 'Connected site'}
                                </label>
                                <div className="mt-1.5 flex items-center gap-3 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800/50">
                                    <Globe className="h-5 w-5 text-surface-400" />
                                    <div>
                                        <p className="font-medium text-surface-900 dark:text-white">{integration.site.name}</p>
                                        <p className="text-sm text-surface-500 dark:text-surface-400">{integration.site.domain}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div>
                            <label
                                htmlFor="name"
                                className="block text-sm font-medium text-surface-700 dark:text-surface-300"
                            >
                                {t?.integrations?.edit?.integrationName ?? 'Integration name'}
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={`My ${t?.integrations?.types?.[integration.type as keyof typeof t.integrations.types]?.name || integration.type}`}
                                className={inputClasses}
                            />
                            {errors.name && (
                                <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                            )}
                        </div>

                        {integration.type === 'wordpress' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.wordpress?.url ?? 'WordPress URL'}
                                    </label>
                                    <input
                                        type="url"
                                        value={data.credentials.site_url || ''}
                                        onChange={(e) => handleCredentialChange('site_url', e.target.value)}
                                        placeholder={t?.integrations?.fields?.wordpress?.urlPlaceholder ?? 'https://yoursite.com'}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.wordpress?.username ?? 'Username'}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.username || ''}
                                        onChange={(e) => handleCredentialChange('username', e.target.value)}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.wordpress?.password ?? 'Application password'}
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.app_password || ''}
                                        onChange={(e) => handleCredentialChange('app_password', e.target.value)}
                                        placeholder={secretPlaceholder('app_password', 'Enter an application password')}
                                        className={inputClasses}
                                    />
                                    <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                                        {t?.integrations?.fields?.wordpress?.passwordHelp ?? 'Generate an application password in WordPress > Users > Profile > Application Passwords'}
                                    </p>
                                </div>
                            </>
                        )}

                        {integration.type === 'webflow' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        API Token
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.api_token || ''}
                                        onChange={(e) => handleCredentialChange('api_token', e.target.value)}
                                        placeholder={secretPlaceholder('api_token', 'Enter an API token')}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        Site ID
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.site_id || ''}
                                        onChange={(e) => handleCredentialChange('site_id', e.target.value)}
                                        placeholder="The Webflow site identifier"
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.webflow?.collectionId ?? 'Collection ID'}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.collection_id || ''}
                                        onChange={(e) => handleCredentialChange('collection_id', e.target.value)}
                                        placeholder={t?.integrations?.fields?.webflow?.collectionIdPlaceholder ?? 'The CMS collection to publish to'}
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        {integration.type === 'shopify' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.shopify?.shopDomain ?? 'Shop domain'}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.shop_domain || ''}
                                        onChange={(e) => handleCredentialChange('shop_domain', e.target.value)}
                                        placeholder={t?.integrations?.fields?.shopify?.shopDomainPlaceholder ?? 'yourshop.myshopify.com'}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        Admin API Token
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.access_token || ''}
                                        onChange={(e) => handleCredentialChange('access_token', e.target.value)}
                                        placeholder={secretPlaceholder('access_token', 'Enter an admin API token')}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        Blog ID
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.blog_id || ''}
                                        onChange={(e) => handleCredentialChange('blog_id', e.target.value)}
                                        placeholder="Optional blog identifier"
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        {integration.type === 'ghost' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.ghost?.url ?? 'Ghost URL'}
                                    </label>
                                    <input
                                        type="url"
                                        value={data.credentials.blog_url || ''}
                                        onChange={(e) => handleCredentialChange('blog_url', e.target.value)}
                                        placeholder={t?.integrations?.fields?.ghost?.urlPlaceholder ?? 'https://yoursite.ghost.io'}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.ghost?.adminApiKey ?? 'Admin API Key'}
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.admin_api_key || ''}
                                        onChange={(e) => handleCredentialChange('admin_api_key', e.target.value)}
                                        placeholder={secretPlaceholder('admin_api_key', 'Enter an admin API key')}
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        <div className="flex justify-end gap-3 border-t border-surface-100 pt-4 dark:border-surface-800">
                            <Link
                                href={route('integrations.index')}
                                className={clsx(
                                    'inline-flex items-center rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-semibold text-surface-700 transition-colors hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700'
                                )}
                            >
                                {t?.integrations?.edit?.cancel ?? 'Cancel'}
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className={clsx(
                                    'inline-flex items-center rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 px-4 py-2.5 text-sm font-semibold text-white',
                                    'shadow-green transition-all hover:-translate-y-0.5 hover:shadow-green-lg dark:shadow-green-glow',
                                    'disabled:cursor-not-allowed disabled:opacity-50'
                                )}
                            >
                                {processing ? (t?.integrations?.edit?.saving ?? 'Saving...') : (t?.integrations?.edit?.save ?? 'Save')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
