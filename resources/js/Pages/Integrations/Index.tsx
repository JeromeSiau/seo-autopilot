import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plug, Plus, Settings, Trash2, CheckCircle, XCircle, Globe, ExternalLink } from 'lucide-react';
import clsx from 'clsx';
import { Integration, PageProps } from '@/types';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

interface IntegrationsIndexProps extends PageProps {
    integrations: Integration[];
}

const PLATFORM_CONFIG = {
    wordpress: {
        name: 'WordPress',
        description: 'Publication via REST API',
        color: 'bg-[#21759b]',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 19.5c-5.247 0-9.5-4.253-9.5-9.5S6.753 2.5 12 2.5s9.5 4.253 9.5 9.5-4.253 9.5-9.5 9.5z"/>
            </svg>
        ),
    },
    webflow: {
        name: 'Webflow',
        description: 'Intégration CMS',
        color: 'bg-[#4353ff]',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.803 9.124L13.716 15.214 11.366 10.864 13.716 6.514 17.803 9.124Z"/>
            </svg>
        ),
    },
    shopify: {
        name: 'Shopify',
        description: 'Blog API',
        color: 'bg-[#96bf48]',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15.337 3.415L12.982 1.383 10.9 3.424 8.691 3.424 7.17 5.87 10.744 12.205 7.51 5.57 11.232 16.233Z"/>
            </svg>
        ),
    },
};

const AVAILABLE_PLATFORMS = [
    { id: 'wordpress', name: 'WordPress', description: 'Publication via REST API', emoji: '📝', bgColor: 'bg-blue-50 dark:bg-blue-900/20' },
    { id: 'webflow', name: 'Webflow', description: 'Intégration CMS Webflow', emoji: '🌐', bgColor: 'bg-indigo-50 dark:bg-indigo-900/20' },
    { id: 'shopify', name: 'Shopify', description: 'Blog API Shopify', emoji: '🛒', bgColor: 'bg-green-50 dark:bg-green-900/20' },
];

export default function IntegrationsIndex({ integrations }: IntegrationsIndexProps) {
    const handleDelete = (e: React.MouseEvent, integration: Integration) => {
        e.preventDefault();
        if (confirm(`Êtes-vous sûr de vouloir supprimer ${integration.name} ?`)) {
            router.delete(route('integrations.destroy', { integration: integration.id }));
        }
    };

    const handleToggle = (e: React.MouseEvent, integration: Integration) => {
        e.preventDefault();
        router.patch(route('integrations.toggle', { integration: integration.id }));
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Intégrations</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Connectez vos CMS pour publier automatiquement
                        </p>
                    </div>
                    <Link
                        href={route('integrations.create')}
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5',
                            'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                            'shadow-green hover:shadow-green-lg dark:shadow-green-glow dark:hover:shadow-green-glow-lg hover:-translate-y-0.5',
                            'transition-all'
                        )}
                    >
                        <Plus className="h-4 w-4" />
                        Ajouter
                    </Link>
                </div>
            }
        >
            <Head title="Intégrations" />

            {integrations.length === 0 ? (
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                    <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                        <Plug className="h-7 w-7 text-surface-400" />
                    </div>
                    <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                        Aucune intégration
                    </h3>
                    <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                        Connectez votre CMS pour publier vos articles directement depuis RankCruise.
                    </p>
                    <Link
                        href={route('integrations.create')}
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                            'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                            'shadow-green hover:shadow-green-lg dark:shadow-green-glow dark:hover:shadow-green-glow-lg',
                            'transition-all'
                        )}
                    >
                        <Plus className="h-4 w-4" />
                        Ajouter une intégration
                    </Link>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {integrations.map((integration) => {
                        const platform = PLATFORM_CONFIG[integration.type as keyof typeof PLATFORM_CONFIG];
                        return (
                            <div
                                key={integration.id}
                                className="group bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-lg hover:border-primary-200 dark:hover:border-primary-800 transition-all"
                            >
                                {/* Header */}
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className={clsx(
                                            'flex h-12 w-12 items-center justify-center rounded-xl text-white',
                                            platform?.color || 'bg-surface-200 dark:bg-surface-800'
                                        )}>
                                            {platform?.icon || <Plug className="h-6 w-6" />}
                                        </div>
                                        <div>
                                            <h3 className="font-display font-semibold text-surface-900 dark:text-white">
                                                {integration.name}
                                            </h3>
                                            <span className="inline-flex items-center rounded-lg bg-surface-100 dark:bg-surface-800 px-2 py-0.5 text-xs font-medium text-surface-600 dark:text-surface-400 mt-1">
                                                {platform?.name || integration.type}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link
                                            href={route('integrations.edit', { integration: integration.id })}
                                            className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300 transition-colors"
                                        >
                                            <Settings className="h-4 w-4" />
                                        </Link>
                                        <button
                                            onClick={(e) => handleDelete(e, integration)}
                                            className="rounded-lg p-2 text-surface-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                {/* Status & Toggle */}
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {integration.is_active ? (
                                            <span className="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 dark:text-primary-400">
                                                <CheckCircle className="h-4 w-4" />
                                                Actif
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 text-sm text-surface-400">
                                                <XCircle className="h-4 w-4" />
                                                Inactif
                                            </span>
                                        )}
                                    </div>
                                    <button
                                        onClick={(e) => handleToggle(e, integration)}
                                        className={clsx(
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent',
                                            'transition-colors duration-200 ease-in-out',
                                            'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:ring-offset-2 dark:focus:ring-offset-surface-900',
                                            integration.is_active ? 'bg-primary-500' : 'bg-surface-200 dark:bg-surface-700'
                                        )}
                                    >
                                        <span
                                            className={clsx(
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0',
                                                'transition duration-200 ease-in-out',
                                                integration.is_active ? 'translate-x-5' : 'translate-x-0'
                                            )}
                                        />
                                    </button>
                                </div>

                                {/* Connected Site */}
                                {integration.site && (
                                    <div className="mt-4 pt-4 border-t border-surface-100 dark:border-surface-800">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Globe className="h-4 w-4 text-surface-400" />
                                            <span className="text-surface-500 dark:text-surface-400">Connecté à</span>
                                            <span className="font-medium text-surface-700 dark:text-surface-300">{integration.site.domain}</span>
                                        </div>
                                    </div>
                                )}

                                {/* Date */}
                                <div className="mt-3 text-xs text-surface-400">
                                    Ajouté le {format(new Date(integration.created_at), 'd MMM yyyy', { locale: fr })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Available Integrations */}
            <div className="mt-12">
                <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Intégrations disponibles</h2>
                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    Connectez ces plateformes pour publier automatiquement votre contenu.
                </p>

                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    {AVAILABLE_PLATFORMS.map((platform) => (
                        <Link
                            key={platform.id}
                            href={route('integrations.create', { type: platform.id })}
                            className="flex items-center gap-4 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-md hover:border-primary-200 dark:hover:border-primary-800 transition-all"
                        >
                            <div className={clsx(
                                'flex h-12 w-12 items-center justify-center rounded-xl',
                                platform.bgColor
                            )}>
                                <span className="text-2xl">{platform.emoji}</span>
                            </div>
                            <div className="flex-1">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">{platform.name}</h3>
                                <p className="text-sm text-surface-500 dark:text-surface-400">{platform.description}</p>
                            </div>
                            <ExternalLink className="h-4 w-4 text-surface-400" />
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
