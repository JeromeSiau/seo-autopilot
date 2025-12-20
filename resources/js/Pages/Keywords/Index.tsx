import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, TrendingUp, Eye, Clock, CheckCircle, Loader2, ChevronLeft, ChevronRight, Hash, Target, Zap, BarChart3 } from 'lucide-react';
import clsx from 'clsx';
import { Keyword, Site, PaginatedData, PageProps } from '@/types';
import { useTranslations } from '@/hooks/useTranslations';

interface KeywordsIndexProps extends PageProps {
    keywords: PaginatedData<Keyword>;
    sites: Site[];
    filters: {
        site_id?: number;
        status?: string;
        search?: string;
    };
    stats: {
        total: number;
        queued: number;
        generating: number;
        completed: number;
    };
}

const STAT_CARDS = [
    { key: 'total', labelKey: 'total', icon: Hash, color: 'primary' },
    { key: 'queued', labelKey: 'queued', icon: Clock, color: 'blue' },
    { key: 'generating', labelKey: 'generating', icon: Zap, color: 'amber' },
    { key: 'completed', labelKey: 'completed', icon: CheckCircle, color: 'primary' },
] as const;

const STATUS_CONFIG: Record<string, { labelKey: string; color: string; icon: typeof Clock; animate?: boolean }> = {
    pending: { labelKey: 'pending', color: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400', icon: Clock },
    queued: { labelKey: 'queued', color: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400', icon: Clock },
    generating: { labelKey: 'generating', color: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', icon: Loader2, animate: true },
    completed: { labelKey: 'completed', color: 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400', icon: CheckCircle },
};

export default function KeywordsIndex({ keywords, sites, filters, stats }: KeywordsIndexProps) {
    const { t } = useTranslations();

    const getDifficultyColor = (difficulty: number | null) => {
        if (!difficulty) return 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400';
        if (difficulty < 30) return 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400';
        if (difficulty < 60) return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
        return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400';
    };

    const getColorClasses = (color: string) => {
        const colors = {
            primary: { bg: 'bg-primary-50 dark:bg-primary-500/10', text: 'text-primary-600 dark:text-primary-400', iconBg: 'bg-primary-100 dark:bg-primary-500/15' },
            blue: { bg: 'bg-blue-50 dark:bg-blue-500/10', text: 'text-blue-600 dark:text-blue-400', iconBg: 'bg-blue-100 dark:bg-blue-500/15' },
            amber: { bg: 'bg-amber-50 dark:bg-amber-500/10', text: 'text-amber-600 dark:text-amber-400', iconBg: 'bg-amber-100 dark:bg-amber-500/15' },
        };
        return colors[color as keyof typeof colors] || colors.primary;
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">{t?.keywords?.title ?? 'Keywords'}</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {t?.keywords?.subtitle ?? 'Découverts automatiquement par l\'Autopilot'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Target className="h-5 w-5 text-primary-500 dark:text-primary-400" />
                        <span className="text-sm font-medium text-surface-700 dark:text-surface-300">
                            {stats.total} {t?.keywords?.tracked ?? 'keywords suivis'}
                        </span>
                    </div>
                </div>
            }
        >
            <Head title={t?.keywords?.title ?? 'Keywords'} />

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {STAT_CARDS.map((card) => {
                    const colors = getColorClasses(card.color);
                    const Icon = card.icon;
                    const value = stats[card.key];
                    return (
                        <div
                            key={card.key}
                            className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-md dark:hover:shadow-green-glow/20 transition-shadow"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{t?.keywords?.[card.labelKey] ?? card.labelKey}</p>
                                    <p className={clsx(
                                        'mt-2 font-display text-2xl font-bold',
                                        card.key === 'generating' ? 'text-amber-600 dark:text-amber-400' :
                                        card.key === 'queued' ? 'text-blue-600 dark:text-blue-400' :
                                        card.key === 'completed' ? 'text-primary-600 dark:text-primary-400' : 'text-surface-900 dark:text-white'
                                    )}>
                                        {value}
                                    </p>
                                </div>
                                <div className={clsx('rounded-xl p-2.5', colors.iconBg)}>
                                    <Icon className={clsx('h-5 w-5', colors.text)} />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Filters */}
            <div className="mt-6 flex flex-wrap gap-3">
                <select
                    value={filters.site_id || ''}
                    onChange={(e) =>
                        router.get(route('keywords.index'), {
                            ...filters,
                            site_id: e.target.value || undefined,
                        })
                    }
                    className={clsx(
                        'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">{t?.keywords?.allSites ?? 'Tous les sites'}</option>
                    {sites.map((site) => (
                        <option key={site.id} value={site.id}>
                            {site.name}
                        </option>
                    ))}
                </select>

                <select
                    value={filters.status || ''}
                    onChange={(e) =>
                        router.get(route('keywords.index'), {
                            ...filters,
                            status: e.target.value || undefined,
                        })
                    }
                    className={clsx(
                        'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">{t?.keywords?.allStatuses ?? 'Tous les statuts'}</option>
                    <option value="pending">{t?.status?.pending ?? 'En attente'}</option>
                    <option value="queued">{t?.status?.queued ?? 'En queue'}</option>
                    <option value="generating">{t?.status?.generating ?? 'En génération'}</option>
                    <option value="completed">{t?.status?.completed ?? 'Complété'}</option>
                </select>

                <div className="relative flex-1 min-w-[200px]">
                    <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="search"
                        placeholder={t?.keywords?.searchPlaceholder ?? 'Rechercher un keyword...'}
                        value={filters.search || ''}
                        onChange={(e) =>
                            router.get(
                                route('keywords.index'),
                                { ...filters, search: e.target.value || undefined },
                                { preserveState: true }
                            )
                        }
                        className={clsx(
                            'w-full rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 pl-10 pr-4 py-2.5 text-sm text-surface-900 dark:text-white',
                            'placeholder:text-surface-400',
                            'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                            'transition-colors'
                        )}
                    />
                </div>
            </div>

            {/* Keywords Table / Empty State */}
            <div className="mt-6">
                {keywords.data.length === 0 ? (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <Search className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            {t?.keywords?.noKeywords ?? 'Aucun keyword trouvé'}
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto">
                            {t?.keywords?.noKeywordsDescription ?? 'Les keywords seront découverts automatiquement par l\'Autopilot une fois vos sites configurés.'}
                        </p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-surface-100 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-800/50">
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tableKeyword ?? 'Keyword'}
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tableVolume ?? 'Volume'}
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tableDifficulty ?? 'Difficulté'}
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tablePosition ?? 'Position'}
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tablePriority ?? 'Priorité'}
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            {t?.keywords?.tableStatus ?? 'Statut'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-surface-100 dark:divide-surface-800">
                                    {keywords.data.map((keyword) => {
                                        const statusConfig = STATUS_CONFIG[keyword.status as keyof typeof STATUS_CONFIG] || STATUS_CONFIG.pending;
                                        const StatusIcon = statusConfig.icon;
                                        return (
                                            <tr key={keyword.id} className="hover:bg-surface-50/50 dark:hover:bg-surface-800/50 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div>
                                                        <p className="font-medium text-surface-900 dark:text-white">{keyword.keyword}</p>
                                                        {keyword.site && (
                                                            <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">{keyword.site.domain}</p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {keyword.volume ? (
                                                        <span className="inline-flex items-center gap-1.5 text-sm text-surface-700 dark:text-surface-300">
                                                            <Eye className="h-3.5 w-3.5 text-surface-400" />
                                                            {keyword.volume.toLocaleString()}
                                                        </span>
                                                    ) : (
                                                        <span className="text-surface-300 dark:text-surface-600">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {keyword.difficulty !== null ? (
                                                        <span
                                                            className={clsx(
                                                                'inline-flex min-w-[2rem] justify-center rounded-lg px-2.5 py-1 text-xs font-semibold',
                                                                getDifficultyColor(keyword.difficulty)
                                                            )}
                                                        >
                                                            {keyword.difficulty}
                                                        </span>
                                                    ) : (
                                                        <span className="text-surface-300 dark:text-surface-600">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {keyword.position ? (
                                                        <span className="inline-flex items-center gap-1.5 text-sm text-surface-700 dark:text-surface-300">
                                                            <TrendingUp className="h-3.5 w-3.5 text-primary-500 dark:text-primary-400" />
                                                            {keyword.position.toFixed(1)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-surface-300 dark:text-surface-600">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="font-display text-sm font-bold text-primary-600 dark:text-primary-400">
                                                        {keyword.priority || keyword.score || '—'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className={clsx(
                                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium',
                                                        statusConfig.color
                                                    )}>
                                                        <StatusIcon className={clsx(
                                                            'h-3.5 w-3.5',
                                                            statusConfig.animate && 'animate-spin'
                                                        )} />
                                                        {t?.status?.[statusConfig.labelKey] ?? statusConfig.labelKey}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {keywords.meta.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-surface-100 dark:border-surface-800 px-6 py-4">
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{keywords.meta.from}</span>
                                    {' '}{t?.common?.to ?? 'à'}{' '}
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{keywords.meta.to}</span>
                                    {' '}{t?.common?.of ?? 'sur'}{' '}
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{keywords.meta.total}</span>
                                    {' '}{t?.common?.keywords ?? 'keywords'}
                                </p>
                                <div className="flex gap-2">
                                    {keywords.links.prev ? (
                                        <Link
                                            href={keywords.links.prev}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600 transition-colors"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                            {t?.common?.previous ?? 'Précédent'}
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 dark:border-surface-800 px-3 py-2 text-sm font-medium text-surface-300 dark:text-surface-600 cursor-not-allowed">
                                            <ChevronLeft className="h-4 w-4" />
                                            {t?.common?.previous ?? 'Précédent'}
                                        </span>
                                    )}
                                    {keywords.links.next ? (
                                        <Link
                                            href={keywords.links.next}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600 transition-colors"
                                        >
                                            {t?.common?.next ?? 'Suivant'}
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 dark:border-surface-800 px-3 py-2 text-sm font-medium text-surface-300 dark:text-surface-600 cursor-not-allowed">
                                            {t?.common?.next ?? 'Suivant'}
                                            <ChevronRight className="h-4 w-4" />
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
