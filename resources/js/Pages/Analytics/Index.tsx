import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart3,
    TrendingUp,
    MousePointer,
    Eye,
    Target,
    Info,
} from 'lucide-react';
import clsx from 'clsx';
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { Site, AnalyticsData, PageProps } from '@/types';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

interface AnalyticsIndexProps extends PageProps {
    sites: Site[];
    selectedSite: Site | null;
    analyticsData: AnalyticsData[];
    summary: {
        total_clicks: number;
        total_impressions: number;
        avg_position: number;
        avg_ctr: number;
        clicks_change: number;
        impressions_change: number;
    };
    topPages: Array<{
        page: string;
        clicks: number;
        impressions: number;
        position: number;
        ctr: number;
    }>;
    topQueries: Array<{
        query: string;
        clicks: number;
        impressions: number;
        position: number;
        ctr: number;
    }>;
    dateRange: string;
    connectedSitesCount: number;
    totalSitesCount: number;
}

const STAT_CARDS = [
    { key: 'clicks', label: 'Total Clics', icon: MousePointer, color: 'primary' },
    { key: 'impressions', label: 'Impressions', icon: Eye, color: 'blue' },
    { key: 'ctr', label: 'CTR Moyen', icon: TrendingUp, color: 'primary' },
    { key: 'position', label: 'Position Moyenne', icon: Target, color: 'purple' },
] as const;

export default function AnalyticsIndex({
    sites,
    selectedSite,
    analyticsData = [],
    summary,
    topPages = [],
    topQueries = [],
    dateRange,
    connectedSitesCount,
    totalSitesCount,
}: AnalyticsIndexProps) {
    const handleSiteChange = (siteId: string) => {
        router.get(route('analytics.index'), { site_id: siteId || undefined });
    };

    const handleDateRangeChange = (range: string) => {
        router.get(route('analytics.index'), {
            site_id: selectedSite?.id,
            range,
        });
    };

    const isAggregatedView = !selectedSite && connectedSitesCount > 0;
    const hasData = selectedSite?.gsc_connected || isAggregatedView;

    const getColorClasses = (color: string) => {
        const colors = {
            primary: { text: 'text-primary-600', iconBg: 'bg-primary-100 dark:bg-primary-900/30' },
            blue: { text: 'text-blue-600', iconBg: 'bg-blue-100 dark:bg-blue-900/30' },
            purple: { text: 'text-purple-600', iconBg: 'bg-purple-100 dark:bg-purple-900/30' },
        };
        return colors[color as keyof typeof colors] || colors.primary;
    };

    const getStatValue = (key: string) => {
        switch (key) {
            case 'clicks':
                return summary.total_clicks.toLocaleString();
            case 'impressions':
                return summary.total_impressions.toLocaleString();
            case 'ctr':
                return `${summary.avg_ctr.toFixed(1)}%`;
            case 'position':
                return summary.avg_position.toFixed(1);
            default:
                return '—';
        }
    };

    const getTrend = (key: string) => {
        if (key === 'clicks' && summary.clicks_change !== 0) {
            return summary.clicks_change;
        }
        if (key === 'impressions' && summary.impressions_change !== 0) {
            return summary.impressions_change;
        }
        return null;
    };

    return (
        <AppLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Analytics</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Performances de recherche depuis Google Search Console
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {isAggregatedView && connectedSitesCount < totalSitesCount && (
                            <div className="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                <Info className="h-3.5 w-3.5" />
                                {connectedSitesCount}/{totalSitesCount} sites connectés
                            </div>
                        )}
                        <select
                            value={selectedSite?.id || ''}
                            onChange={(e) => handleSiteChange(e.target.value)}
                            className={clsx(
                                'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
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
                            value={dateRange}
                            onChange={(e) => handleDateRangeChange(e.target.value)}
                            className={clsx(
                                'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
                                'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                                'transition-colors'
                            )}
                        >
                            <option value="7">7 derniers jours</option>
                            <option value="28">28 derniers jours</option>
                            <option value="90">90 derniers jours</option>
                        </select>
                    </div>
                </div>
            }
        >
            <Head title="Analytics" />

            {!hasData ? (
                selectedSite && !selectedSite.gsc_connected ? (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <BarChart3 className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            Connectez Google Search Console
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                            Connectez GSC pour voir les données de performance de {selectedSite.domain}.
                        </p>
                        <Link
                            href={route('sites.edit', { site: selectedSite.id })}
                            className={clsx(
                                'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                                'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                                'shadow-green dark:shadow-green-glow hover:shadow-green-lg',
                                'transition-all'
                            )}
                        >
                            Connecter GSC
                        </Link>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <BarChart3 className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            Aucun site connecté
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                            Connectez Google Search Console sur au moins un site pour voir les analytics.
                        </p>
                        <Link
                            href={route('sites.index')}
                            className={clsx(
                                'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                                'bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold',
                                'shadow-green dark:shadow-green-glow hover:shadow-green-lg',
                                'transition-all'
                            )}
                        >
                            Gérer les sites
                        </Link>
                    </div>
                )
            ) : (
                <>
                    {/* Stats Grid */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {STAT_CARDS.map((card) => {
                            const colors = getColorClasses(card.color);
                            const Icon = card.icon;
                            const value = getStatValue(card.key);
                            const trend = getTrend(card.key);
                            return (
                                <div
                                    key={card.key}
                                    className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-md transition-shadow"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{card.label}</p>
                                            <p className="mt-2 font-display text-2xl font-bold text-surface-900 dark:text-white">
                                                {value}
                                            </p>
                                            {trend !== null && (
                                                <p className={clsx(
                                                    'mt-1 flex items-center gap-1 text-xs font-medium',
                                                    trend >= 0 ? 'text-primary-600' : 'text-red-600'
                                                )}>
                                                    <TrendingUp className={clsx('h-3 w-3', trend < 0 && 'rotate-180')} />
                                                    {trend >= 0 ? '+' : ''}{trend}% vs période précédente
                                                </p>
                                            )}
                                        </div>
                                        <div className={clsx('rounded-xl p-2.5', colors.iconBg)}>
                                            <Icon className={clsx('h-5 w-5', colors.text)} />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Chart */}
                    {analyticsData.length > 0 && (
                        <div className="mt-6 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="mb-6">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">Performance dans le temps</h3>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Clics et impressions</p>
                            </div>
                            <div className="h-80">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={analyticsData}>
                                        <defs>
                                            <linearGradient id="colorClicks" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#10b981" stopOpacity={0.15} />
                                                <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                            </linearGradient>
                                            <linearGradient id="colorImpressions" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.15} />
                                                <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" className="dark:stroke-surface-800" />
                                        <XAxis
                                            dataKey="date"
                                            tickFormatter={(value) => format(new Date(value), 'd MMM', { locale: fr })}
                                            tick={{ fontSize: 12, fill: '#78716c' }}
                                            stroke="#e7e5e4"
                                            className="dark:stroke-surface-700"
                                        />
                                        <YAxis yAxisId="left" tick={{ fontSize: 12, fill: '#78716c' }} stroke="#e7e5e4" className="dark:stroke-surface-700" />
                                        <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 12, fill: '#78716c' }} stroke="#e7e5e4" className="dark:stroke-surface-700" />
                                        <Tooltip
                                            contentStyle={{
                                                backgroundColor: 'white',
                                                border: '1px solid #e7e5e4',
                                                borderRadius: '12px',
                                                boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                            }}
                                            wrapperClassName="dark:[&_.recharts-tooltip-wrapper]:opacity-100"
                                            content={(props) => {
                                                if (!props.active || !props.payload) return null;
                                                return (
                                                    <div className="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl p-3 shadow-lg">
                                                        <p className="text-sm font-medium text-surface-900 dark:text-white mb-2">
                                                            {props.label && format(new Date(props.label as string), 'd MMMM yyyy', { locale: fr })}
                                                        </p>
                                                        {props.payload.map((entry: any, index: number) => (
                                                            <p key={index} className="text-sm text-surface-700 dark:text-surface-300">
                                                                <span className="font-medium">{entry.name}:</span> {entry.value}
                                                            </p>
                                                        ))}
                                                    </div>
                                                );
                                            }}
                                        />
                                        <Legend />
                                        <Area
                                            yAxisId="left"
                                            type="monotone"
                                            dataKey="clicks"
                                            stroke="#10b981"
                                            fillOpacity={1}
                                            fill="url(#colorClicks)"
                                            strokeWidth={2}
                                            name="Clics"
                                        />
                                        <Area
                                            yAxisId="right"
                                            type="monotone"
                                            dataKey="impressions"
                                            stroke="#3b82f6"
                                            fillOpacity={1}
                                            fill="url(#colorImpressions)"
                                            strokeWidth={2}
                                            name="Impressions"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    )}

                    {/* Tables */}
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        {/* Top Pages */}
                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                            <div className="border-b border-surface-100 dark:border-surface-800 px-6 py-4">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">Top Pages</h3>
                            </div>
                            {isAggregatedView ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    Sélectionnez un site pour voir les top pages.
                                </div>
                            ) : topPages.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    Aucune donnée disponible.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="bg-surface-50/50 dark:bg-surface-800/50">
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Page
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Clics
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Pos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-surface-100 dark:divide-surface-800">
                                            {topPages.slice(0, 10).map((page, index) => (
                                                <tr key={index} className="hover:bg-surface-50/50 dark:hover:bg-surface-800/50 transition-colors">
                                                    <td className="max-w-xs truncate px-4 py-3 text-sm text-surface-900 dark:text-white">
                                                        {page.page}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-surface-700 dark:text-surface-300">
                                                        {page.clicks.toLocaleString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-surface-500 dark:text-surface-400">
                                                        {page.position.toFixed(1)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Top Queries */}
                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                            <div className="border-b border-surface-100 dark:border-surface-800 px-6 py-4">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">Top Requêtes</h3>
                            </div>
                            {isAggregatedView ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    Sélectionnez un site pour voir les top requêtes.
                                </div>
                            ) : topQueries.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    Aucune donnée disponible.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="bg-surface-50/50 dark:bg-surface-800/50">
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Requête
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Clics
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    Pos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-surface-100 dark:divide-surface-800">
                                            {topQueries.slice(0, 10).map((query, index) => (
                                                <tr key={index} className="hover:bg-surface-50/50 dark:hover:bg-surface-800/50 transition-colors">
                                                    <td className="max-w-xs truncate px-4 py-3 text-sm text-surface-900 dark:text-white">
                                                        {query.query}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-surface-700 dark:text-surface-300">
                                                        {query.clicks.toLocaleString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-surface-500 dark:text-surface-400">
                                                        {query.position.toFixed(1)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
