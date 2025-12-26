import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Plug, Globe } from 'lucide-react';
import clsx from 'clsx';
import { Integration, PageProps } from '@/types';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface IntegrationsEditProps extends PageProps {
    integration: Integration & {
        credentials?: Record<string, string>;
    };
}

const integrationTypes = [
    {
        type: 'wordpress',
        fields: ['url', 'username', 'password'],
    },
    {
        type: 'webflow',
        fields: ['api_token', 'collection_id'],
    },
    {
        type: 'shopify',
        fields: ['shop_domain', 'api_token'],
    },
    {
        type: 'ghost',
        fields: ['url', 'admin_api_key'],
    },
];

export default function IntegrationsEdit({ integration }: IntegrationsEditProps) {
    const { t } = useTranslations();
    const integrationType = integrationTypes.find((i) => i.type === integration.type);

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

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('integrations.index')}
                        className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-white transition-colors"
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
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    {/* Header */}
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
                        {/* Connected Site (read-only) */}
                        {integration.site && (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                    {t?.integrations?.edit?.connectedSite ?? 'Connected site'}
                                </label>
                                <div className="mt-1.5 flex items-center gap-3 rounded-xl bg-surface-50 dark:bg-surface-800/50 border border-surface-200 dark:border-surface-700 px-4 py-3">
                                    <Globe className="h-5 w-5 text-surface-400" />
                                    <div>
                                        <p className="font-medium text-surface-900 dark:text-white">{integration.site.name}</p>
                                        <p className="text-sm text-surface-500 dark:text-surface-400">{integration.site.domain}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Integration Name */}
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

                        {/* WordPress Fields */}
                        {integration.type === 'wordpress' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.wordpress?.url ?? 'WordPress URL'}
                                    </label>
                                    <input
                                        type="url"
                                        value={data.credentials.url || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('url', e.target.value)
                                        }
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
                                        onChange={(e) =>
                                            handleCredentialChange('username', e.target.value)
                                        }
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.wordpress?.password ?? 'Application password'}
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.password || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('password', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.wordpress?.passwordPlaceholder ?? 'Leave empty to keep current'}
                                        className={inputClasses}
                                    />
                                    <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                                        {t?.integrations?.fields?.wordpress?.passwordHelp ?? 'Generate an application password in WordPress > Users > Profile > Application Passwords'}
                                    </p>
                                </div>
                            </>
                        )}

                        {/* Webflow Fields */}
                        {integration.type === 'webflow' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.webflow?.apiToken ?? 'API Token'}
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.api_token || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('api_token', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.webflow?.apiTokenPlaceholder ?? 'Leave empty to keep current'}
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
                                        onChange={(e) =>
                                            handleCredentialChange('collection_id', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.webflow?.collectionIdPlaceholder ?? 'The CMS collection to publish to'}
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        {/* Shopify Fields */}
                        {integration.type === 'shopify' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.shopify?.shopDomain ?? 'Shop domain'}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.shop_domain || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('shop_domain', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.shopify?.shopDomainPlaceholder ?? 'yourshop.myshopify.com'}
                                        className={inputClasses}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.shopify?.apiToken ?? 'Admin API Token'}
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.api_token || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('api_token', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.shopify?.apiTokenPlaceholder ?? 'Leave empty to keep current'}
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        {/* Ghost Fields */}
                        {integration.type === 'ghost' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {t?.integrations?.fields?.ghost?.url ?? 'Ghost URL'}
                                    </label>
                                    <input
                                        type="url"
                                        value={data.credentials.url || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('url', e.target.value)
                                        }
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
                                        onChange={(e) =>
                                            handleCredentialChange('admin_api_key', e.target.value)
                                        }
                                        placeholder={t?.integrations?.fields?.ghost?.adminApiKeyPlaceholder ?? 'Leave empty to keep current'}
                                        className={inputClasses}
                                    />
                                </div>
                            </>
                        )}

                        {/* Submit Buttons */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-surface-100 dark:border-surface-800">
                            <Link
                                href={route('integrations.index')}
                                className={clsx(
                                    'inline-flex items-center rounded-xl px-4 py-2.5',
                                    'text-sm font-semibold text-surface-700 dark:text-surface-300',
                                    'border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800',
                                    'hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors'
                                )}
                            >
                                {t?.integrations?.edit?.cancel ?? 'Cancel'}
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className={clsx(
                                    'inline-flex items-center rounded-xl px-4 py-2.5',
                                    'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                                    'shadow-green dark:shadow-green-glow hover:shadow-green-lg hover:-translate-y-0.5',
                                    'transition-all disabled:opacity-50 disabled:cursor-not-allowed'
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
