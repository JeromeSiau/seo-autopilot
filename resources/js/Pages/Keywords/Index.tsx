import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, TrendingUp, Eye, Clock, CheckCircle, Loader2, ChevronLeft, ChevronRight, Hash, Target, Zap, BarChart3 } from 'lucide-react';
import clsx from 'clsx';
import { Keyword, Site, PaginatedData, PageProps } from '@/types';

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
    { key: 'total', label: 'Total', icon: Hash, color: 'primary' },
    { key: 'queued', label: 'En queue', icon: Clock, color: 'blue' },
    { key: 'generating', label: 'En génération', icon: Zap, color: 'amber' },
    { key: 'completed', label: 'Complétés', icon: CheckCircle, color: 'primary' },
] as const;

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: typeof Clock; animate?: boolean }> = {
    pending: { label: 'En attente', color: 'bg-surface-100 text-surface-600', icon: Clock },
    queued: { label: 'En queue', color: 'bg-blue-50 text-blue-700', icon: Clock },
    generating: { label: 'En génération', color: 'bg-amber-50 text-amber-700', icon: Loader2, animate: true },
    completed: { label: 'Complété', color: 'bg-primary-50 text-primary-700', icon: CheckCircle },
};

export default function KeywordsIndex({ keywords, sites, filters, stats }: KeywordsIndexProps) {
    const getDifficultyColor = (difficulty: number | null) => {
        if (!difficulty) return 'bg-surface-100 text-surface-500';
        if (difficulty < 30) return 'bg-primary-50 text-primary-700';
        if (difficulty < 60) return 'bg-amber-50 text-amber-700';
        return 'bg-red-50 text-red-700';
    };

    const getColorClasses = (color: string) => {
        const colors = {
            primary: { bg: 'bg-primary-50', text: 'text-primary-600', iconBg: 'bg-primary-100' },
            blue: { bg: 'bg-blue-50', text: 'text-blue-600', iconBg: 'bg-blue-100' },
            amber: { bg: 'bg-amber-50', text: 'text-amber-600', iconBg: 'bg-amber-100' },
        };
        return colors[color as keyof typeof colors] || colors.primary;
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900">Keywords</h1>
                        <p className="mt-1 text-sm text-surface-500">
                            Découverts automatiquement par l'Autopilot
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Target className="h-5 w-5 text-primary-500" />
                        <span className="text-sm font-medium text-surface-700">
                            {stats.total} keywords suivis
                        </span>
                    </div>
                </div>
            }
        >
            <Head title="Keywords" />

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {STAT_CARDS.map((card) => {
                    const colors = getColorClasses(card.color);
                    const Icon = card.icon;
                    const value = stats[card.key];
                    return (
                        <div
                            key={card.key}
                            className="bg-white rounded-2xl border border-surface-200 p-5 hover:shadow-md transition-shadow"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm font-medium text-surface-500">{card.label}</p>
                                    <p className={clsx(
                                        'mt-2 font-display text-2xl font-bold',
                                        card.key === 'generating' ? 'text-amber-600' :
                                        card.key === 'queued' ? 'text-blue-600' :
                                        card.key === 'completed' ? 'text-primary-600' : 'text-surface-900'
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
                        'rounded-xl border border-surface-200 bg-white px-4 py-2.5 text-sm text-surface-700',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">Tous les sites</option>
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
                        'rounded-xl border border-surface-200 bg-white px-4 py-2.5 text-sm text-surface-700',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">Tous les statuts</option>
                    <option value="pending">En attente</option>
                    <option value="queued">En queue</option>
                    <option value="generating">En génération</option>
                    <option value="completed">Complété</option>
                </select>

                <div className="relative flex-1 min-w-[200px]">
                    <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="search"
                        placeholder="Rechercher un keyword..."
                        value={filters.search || ''}
                        onChange={(e) =>
                            router.get(
                                route('keywords.index'),
                                { ...filters, search: e.target.value || undefined },
                                { preserveState: true }
                            )
                        }
                        className={clsx(
                            'w-full rounded-xl border border-surface-200 bg-white pl-10 pr-4 py-2.5 text-sm text-surface-900',
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
                    <div className="bg-white rounded-2xl border border-surface-200 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mb-4">
                            <Search className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 mb-1">
                            Aucun keyword trouvé
                        </h3>
                        <p className="text-sm text-surface-500 max-w-sm mx-auto">
                            Les keywords seront découverts automatiquement par l'Autopilot une fois vos sites configurés.
                        </p>
                    </div>
                ) : (
                    <div className="bg-white rounded-2xl border border-surface-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-surface-100 bg-surface-50/50">
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Keyword
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Volume
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Difficulté
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Position
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Priorité
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500">
                                            Statut
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-surface-100">
                                    {keywords.data.map((keyword) => {
                                        const statusConfig = STATUS_CONFIG[keyword.status as keyof typeof STATUS_CONFIG] || STATUS_CONFIG.pending;
                                        const StatusIcon = statusConfig.icon;
                                        return (
                                            <tr key={keyword.id} className="hover:bg-surface-50/50 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div>
                                                        <p className="font-medium text-surface-900">{keyword.keyword}</p>
                                                        {keyword.site && (
                                                            <p className="mt-0.5 text-xs text-surface-500">{keyword.site.domain}</p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {keyword.volume ? (
                                                        <span className="inline-flex items-center gap-1.5 text-sm text-surface-700">
                                                            <Eye className="h-3.5 w-3.5 text-surface-400" />
                                                            {keyword.volume.toLocaleString()}
                                                        </span>
                                                    ) : (
                                                        <span className="text-surface-300">—</span>
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
                                                        <span className="text-surface-300">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {keyword.position ? (
                                                        <span className="inline-flex items-center gap-1.5 text-sm text-surface-700">
                                                            <TrendingUp className="h-3.5 w-3.5 text-primary-500" />
                                                            {keyword.position.toFixed(1)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-surface-300">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="font-display text-sm font-bold text-primary-600">
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
                                                        {statusConfig.label}
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
                            <div className="flex items-center justify-between border-t border-surface-100 px-6 py-4">
                                <p className="text-sm text-surface-500">
                                    <span className="font-medium text-surface-700">{keywords.meta.from}</span>
                                    {' '}à{' '}
                                    <span className="font-medium text-surface-700">{keywords.meta.to}</span>
                                    {' '}sur{' '}
                                    <span className="font-medium text-surface-700">{keywords.meta.total}</span>
                                    {' '}keywords
                                </p>
                                <div className="flex gap-2">
                                    {keywords.links.prev ? (
                                        <Link
                                            href={keywords.links.prev}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-50 hover:border-surface-300 transition-colors"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                            Précédent
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 px-3 py-2 text-sm font-medium text-surface-300 cursor-not-allowed">
                                            <ChevronLeft className="h-4 w-4" />
                                            Précédent
                                        </span>
                                    )}
                                    {keywords.links.next ? (
                                        <Link
                                            href={keywords.links.next}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-50 hover:border-surface-300 transition-colors"
                                        >
                                            Suivant
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 px-3 py-2 text-sm font-medium text-surface-300 cursor-not-allowed">
                                            Suivant
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
