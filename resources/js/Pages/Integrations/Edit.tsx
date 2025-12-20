import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Plug, Globe } from 'lucide-react';
import clsx from 'clsx';
import { Integration, PageProps } from '@/types';
import { FormEvent } from 'react';

interface IntegrationsEditProps extends PageProps {
    integration: Integration & {
        credentials?: Record<string, string>;
    };
}

const integrationTypes = [
    {
        type: 'wordpress',
        name: 'WordPress',
        description: 'Connexion via REST API avec mot de passe application',
        fields: ['url', 'username', 'password'],
    },
    {
        type: 'webflow',
        name: 'Webflow',
        description: 'Connexion via token API Webflow',
        fields: ['api_token', 'collection_id'],
    },
    {
        type: 'shopify',
        name: 'Shopify',
        description: 'Connexion via Shopify Admin API',
        fields: ['shop_domain', 'api_token'],
    },
];

export default function IntegrationsEdit({ integration }: IntegrationsEditProps) {
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

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('integrations.index')}
                        className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 hover:text-surface-600 transition-colors"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900">
                            Modifier l'intégration
                        </h1>
                        <p className="mt-1 text-sm text-surface-500">
                            Mettez à jour les paramètres de connexion
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Modifier ${integration.name}`} />

            <div className="mx-auto max-w-2xl">
                <div className="bg-white rounded-2xl border border-surface-200 p-6">
                    {/* Header */}
                    <div className="mb-6 flex items-center gap-4">
                        <div className={clsx(
                            'flex h-12 w-12 items-center justify-center rounded-xl text-white',
                            integration.type === 'wordpress' ? 'bg-[#21759b]' :
                            integration.type === 'webflow' ? 'bg-[#4353ff]' :
                            integration.type === 'shopify' ? 'bg-[#96bf48]' :
                            'bg-surface-200'
                        )}>
                            <Plug className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900">
                                {integrationType?.name || integration.type}
                            </h2>
                            <p className="text-sm text-surface-500">
                                {integrationType?.description}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Connected Site (read-only) */}
                        {integration.site && (
                            <div>
                                <label className="block text-sm font-medium text-surface-700">
                                    Site connecté
                                </label>
                                <div className="mt-1.5 flex items-center gap-3 rounded-xl bg-surface-50 border border-surface-200 px-4 py-3">
                                    <Globe className="h-5 w-5 text-surface-400" />
                                    <div>
                                        <p className="font-medium text-surface-900">{integration.site.name}</p>
                                        <p className="text-sm text-surface-500">{integration.site.domain}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Integration Name */}
                        <div>
                            <label
                                htmlFor="name"
                                className="block text-sm font-medium text-surface-700"
                            >
                                Nom de l'intégration
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={`Mon ${integrationType?.name}`}
                                className={clsx(
                                    'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                    'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                )}
                            />
                            {errors.name && (
                                <p className="mt-1.5 text-sm text-red-600">{errors.name}</p>
                            )}
                        </div>

                        {/* WordPress Fields */}
                        {integration.type === 'wordpress' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        URL WordPress
                                    </label>
                                    <input
                                        type="url"
                                        value={data.credentials.url || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('url', e.target.value)
                                        }
                                        placeholder="https://votresite.com"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        Nom d'utilisateur
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.username || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('username', e.target.value)
                                        }
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        Mot de passe application
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.password || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('password', e.target.value)
                                        }
                                        placeholder="Laisser vide pour conserver l'actuel"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                    <p className="mt-1.5 text-xs text-surface-500">
                                        Générez un mot de passe application dans WordPress → Utilisateurs →
                                        Profil → Mots de passe application
                                    </p>
                                </div>
                            </>
                        )}

                        {/* Webflow Fields */}
                        {integration.type === 'webflow' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        Token API
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.api_token || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('api_token', e.target.value)
                                        }
                                        placeholder="Laisser vide pour conserver l'actuel"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        ID de collection
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.collection_id || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('collection_id', e.target.value)
                                        }
                                        placeholder="La collection CMS pour publier"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                            </>
                        )}

                        {/* Shopify Fields */}
                        {integration.type === 'shopify' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        Domaine boutique
                                    </label>
                                    <input
                                        type="text"
                                        value={data.credentials.shop_domain || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('shop_domain', e.target.value)
                                        }
                                        placeholder="votreboutique.myshopify.com"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-surface-700">
                                        Token Admin API
                                    </label>
                                    <input
                                        type="password"
                                        value={data.credentials.api_token || ''}
                                        onChange={(e) =>
                                            handleCredentialChange('api_token', e.target.value)
                                        }
                                        placeholder="Laisser vide pour conserver l'actuel"
                                        className={clsx(
                                            'mt-1.5 block w-full rounded-xl border-surface-300 shadow-sm',
                                            'focus:border-primary-500 focus:ring-primary-500 sm:text-sm'
                                        )}
                                    />
                                </div>
                            </>
                        )}

                        {/* Submit Buttons */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-surface-100">
                            <Link
                                href={route('integrations.index')}
                                className={clsx(
                                    'inline-flex items-center rounded-xl px-4 py-2.5',
                                    'text-sm font-semibold text-surface-700',
                                    'border border-surface-300 bg-white',
                                    'hover:bg-surface-50 transition-colors'
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
                                    'shadow-green hover:shadow-green-lg hover:-translate-y-0.5',
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
