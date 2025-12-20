import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Globe, Plus, ExternalLink, Settings, Trash2, CheckCircle, XCircle,
    Search, FileText, Play, Pause, AlertCircle
} from 'lucide-react';
import clsx from 'clsx';
import { Site, PaginatedData, PageProps } from '@/types';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { useTranslations } from '@/hooks/useTranslations';

interface SitesIndexProps extends PageProps {
    sites: PaginatedData<Site>;
}

export default function SitesIndex({ sites }: SitesIndexProps) {
    const { t } = useTranslations();

    const STATUS_CONFIG = {
        active: {
            label: t?.status?.active ?? 'Actif',
            color: 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400',
            icon: Play,
        },
        paused: {
            label: t?.status?.paused ?? 'Pause',
            color: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            icon: Pause,
        },
        not_configured: {
            label: t?.status?.notConfigured ?? 'Non configuré',
            color: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
            icon: AlertCircle,
        },
        error: {
            label: t?.status?.error ?? 'Erreur',
            color: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            icon: AlertCircle,
        },
    };
    const handleDelete = (e: React.MouseEvent, site: Site) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm((t?.sites?.confirmDelete ?? 'Êtes-vous sûr de vouloir supprimer {domain} ?').replace('{domain}', site.domain))) {
            router.delete(route('sites.destroy', { site: site.id }));
        }
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">{t?.sites?.title ?? 'Sites'}</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {t?.sites?.subtitle ?? 'Gérez vos sites web connectés'}
                        </p>
                    </div>
                    <Link
                        href={route('onboarding.create')}
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5',
                            'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                            'shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5',
                            'transition-all'
                        )}
                    >
                        <Plus className="h-4 w-4" />
                        {t?.sites?.addSite ?? 'Ajouter un site'}
                    </Link>
                </div>
            }
        >
            <Head title="Sites" />

            {sites.data.length === 0 ? (
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                    <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                        <Globe className="h-7 w-7 text-surface-400" />
                    </div>
                    <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                        {t?.sites?.noSites ?? 'Aucun site configuré'}
                    </h3>
                    <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                        {t?.sites?.noSitesDescription ?? 'Ajoutez votre premier site web pour commencer à découvrir des mots-clés et générer du contenu.'}
                    </p>
                    <Link
                        href={route('onboarding.create')}
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                            'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                            'shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg',
                            'transition-all'
                        )}
                    >
                        <Plus className="h-4 w-4" />
                        {t?.sites?.addSite ?? 'Ajouter un site'}
                    </Link>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {sites.data.map((site) => {
                        const status = STATUS_CONFIG[site.autopilot_status as keyof typeof STATUS_CONFIG] || STATUS_CONFIG.not_configured;
                        const StatusIcon = status.icon;
                        return (
                            <div
                                key={site.id}
                                onClick={() => router.visit(route('sites.show', { site: site.id }))}
                                className="group bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-lg dark:hover:shadow-green-glow/20 hover:border-primary-200 dark:hover:border-primary-500/30 transition-all cursor-pointer"
                            >
                                {/* Header */}
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10 flex items-center justify-center">
                                            <span className="font-display font-bold text-primary-700 dark:text-primary-400 text-lg uppercase">
                                                {site.domain.charAt(0)}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 className="font-display font-semibold text-surface-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                                {site.name}
                                            </h3>
                                            <div className="flex items-center gap-1 mt-0.5 text-sm text-surface-500 dark:text-surface-400">
                                                {site.domain}
                                                <ExternalLink className="h-3 w-3" />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                router.visit(route('sites.edit', { site: site.id }));
                                            }}
                                            className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-white transition-colors"
                                        >
                                            <Settings className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={(e) => handleDelete(e, site)}
                                            className="rounded-lg p-2 text-surface-400 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                {/* Stats */}
                                <div className="mt-4 flex items-center gap-4">
                                    <div className="flex items-center gap-1.5 text-sm text-surface-600 dark:text-surface-300">
                                        <Search className="h-4 w-4 text-surface-400" />
                                        <span className="font-medium">{site.keywords_count || 0}</span>
                                        <span className="text-surface-400">{t?.common?.keywords ?? 'keywords'}</span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-sm text-surface-600 dark:text-surface-300">
                                        <FileText className="h-4 w-4 text-surface-400" />
                                        <span className="font-medium">{site.articles_count || 0}</span>
                                        <span className="text-surface-400">{t?.common?.articles ?? 'articles'}</span>
                                    </div>
                                </div>

                                {/* Tags */}
                                <div className="mt-4 flex items-center flex-wrap gap-2">
                                    {/* Language */}
                                    <span className="inline-flex items-center rounded-lg bg-surface-100 dark:bg-surface-800 px-2 py-1 text-xs font-medium text-surface-600 dark:text-surface-400">
                                        {site.language.toUpperCase()}
                                    </span>

                                    {/* GSC Status */}
                                    {site.gsc_connected ? (
                                        <span className="inline-flex items-center gap-1 rounded-lg bg-primary-50 dark:bg-primary-500/10 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-400">
                                            <CheckCircle className="h-3 w-3" />
                                            GSC
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-lg bg-surface-100 dark:bg-surface-800 px-2 py-1 text-xs font-medium text-surface-400">
                                            <XCircle className="h-3 w-3" />
                                            GSC
                                        </span>
                                    )}

                                    {/* GA4 Status */}
                                    {site.ga4_connected ? (
                                        <span className="inline-flex items-center gap-1 rounded-lg bg-primary-50 dark:bg-primary-500/10 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-400">
                                            <CheckCircle className="h-3 w-3" />
                                            GA4
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-lg bg-surface-100 dark:bg-surface-800 px-2 py-1 text-xs font-medium text-surface-400">
                                            <XCircle className="h-3 w-3" />
                                            GA4
                                        </span>
                                    )}
                                </div>

                                {/* Footer */}
                                <div className="mt-4 pt-4 border-t border-surface-100 dark:border-surface-800 flex items-center justify-between">
                                    <span className={clsx(
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium',
                                        status.color
                                    )}>
                                        <StatusIcon className="h-3.5 w-3.5" />
                                        {status.label}
                                    </span>
                                    <span className="text-xs text-surface-400">
                                        {t?.sites?.addedOn ?? 'Ajouté le'} {format(new Date(site.created_at), 'd MMM yyyy', { locale: fr })}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}
