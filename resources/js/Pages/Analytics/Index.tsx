import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    BarChart3,
    TrendingUp,
    TrendingDown,
    MousePointer,
    Eye,
    Target,
    Info,
    Radar,
    RefreshCw,
    Sparkles,
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
import { Site, AnalyticsData, PageProps, AiVisibilityPayload, RefreshRecommendationListItem, BusinessSummary } from '@/types';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { useTranslations } from '@/hooks/useTranslations';
import { Button } from '@/Components/ui/Button';

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
    aiVisibility: AiVisibilityPayload;
    refreshRecommendations: RefreshRecommendationListItem[];
    businessSummary: BusinessSummary;
}

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
    aiVisibility,
    refreshRecommendations,
    businessSummary,
}: AnalyticsIndexProps) {
    const { t } = useTranslations();
    const [businessModel, setBusinessModel] = useState({
        modeled_conversion_rate: businessSummary.business_model.modeled_conversion_rate?.toString() ?? '',
        average_conversion_value: businessSummary.business_model.average_conversion_value?.toString() ?? '',
    });

    const STAT_CARDS = [
        { key: 'clicks', label: t?.analytics?.totalClicks ?? 'Total Clics', icon: MousePointer, color: 'primary' },
        { key: 'impressions', label: t?.analytics?.impressions ?? 'Impressions', icon: Eye, color: 'blue' },
        { key: 'ctr', label: t?.analytics?.averageCtr ?? 'CTR Moyen', icon: TrendingUp, color: 'primary' },
        { key: 'position', label: t?.analytics?.averagePosition ?? 'Position Moyenne', icon: Target, color: 'purple' },
    ] as const;
    const handleSiteChange = (siteId: string) => {
        router.get(route('analytics.index'), { site_id: siteId || undefined });
    };

    const handleDateRangeChange = (range: string) => {
        router.get(route('analytics.index'), {
            site_id: selectedSite?.id,
            range,
        });
    };

    const handleAiVisibilitySync = () => {
        if (!selectedSite) {
            return;
        }

        router.post(route('analytics.ai-visibility.sync', { site: selectedSite.id }), {}, { preserveScroll: true });
    };

    const handleRefreshDetect = () => {
        if (!selectedSite) {
            return;
        }

        router.post(route('analytics.refresh.detect', { site: selectedSite.id }), {}, { preserveScroll: true });
    };

    const handleRefreshAction = (recommendationId: number, action: 'accept' | 'dismiss' | 'execute') => {
        router.post(route(`refresh-recommendations.${action}`, { refreshRecommendation: recommendationId }), {}, { preserveScroll: true });
    };

    useEffect(() => {
        setBusinessModel({
            modeled_conversion_rate: businessSummary.business_model.modeled_conversion_rate?.toString() ?? '',
            average_conversion_value: businessSummary.business_model.average_conversion_value?.toString() ?? '',
        });
    }, [businessSummary.business_model.average_conversion_value, businessSummary.business_model.modeled_conversion_rate, selectedSite?.id]);

    const handleBusinessModelSave = () => {
        if (!selectedSite) {
            return;
        }

        router.patch(route('analytics.business-model.update', { site: selectedSite.id }), {
            modeled_conversion_rate: businessModel.modeled_conversion_rate === '' ? null : Number(businessModel.modeled_conversion_rate),
            average_conversion_value: businessModel.average_conversion_value === '' ? null : Number(businessModel.average_conversion_value),
        }, { preserveScroll: true });
    };

    const isAggregatedView = !selectedSite && connectedSitesCount > 0;
    const hasVisibilityData =
        aiVisibility.summary.total_prompts > 0 ||
        aiVisibility.top_prompts.length > 0 ||
        refreshRecommendations.length > 0;
    const hasData = Boolean(selectedSite?.gsc_connected || isAggregatedView || hasVisibilityData);

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
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">{t?.analytics?.title ?? 'Analytics'}</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {t?.analytics?.subtitle ?? 'Performances de recherche depuis Google Search Console'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {isAggregatedView && connectedSitesCount < totalSitesCount && (
                            <div className="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                <Info className="h-3.5 w-3.5" />
                                {connectedSitesCount}/{totalSitesCount} sites connectés
                            </div>
                        )}
                        {selectedSite && (
                            <>
                                <Link href={route('articles.needs-refresh', { site_id: selectedSite.id })}>
                                    <Button variant="secondary" size="sm" icon={RefreshCw}>
                                        Open refresh planner
                                    </Button>
                                </Link>
                                <Link href={route('analytics.ai-visibility.index', { site_id: selectedSite.id })}>
                                    <Button variant="secondary" size="sm" icon={Sparkles}>
                                        Open AI visibility
                                    </Button>
                                </Link>
                                <Button variant="secondary" size="sm" icon={Radar} onClick={handleAiVisibilitySync}>
                                    Sync AI visibility
                                </Button>
                                <Button variant="secondary" size="sm" icon={RefreshCw} onClick={handleRefreshDetect}>
                                    Detect refreshes
                                </Button>
                            </>
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
                            <option value="">{t?.articles?.allSites ?? 'Tous les sites'}</option>
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
                            <option value="7">{t?.analytics?.last7days ?? '7 derniers jours'}</option>
                            <option value="28">{t?.analytics?.last28days ?? '28 derniers jours'}</option>
                            <option value="90">{t?.analytics?.last3months ?? '90 derniers jours'}</option>
                        </select>
                    </div>
                </div>
            }
        >
            <Head title={t?.analytics?.title ?? 'Analytics'} />

            {!hasData ? (
                selectedSite && !selectedSite.gsc_connected ? (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <BarChart3 className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            {t?.analytics?.connectGsc ?? 'Connectez Google Search Console'}
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                            {t?.analytics?.connectGscDescription ?? `Connectez GSC pour voir les données de performance de ${selectedSite.domain}.`}
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
                            {t?.analytics?.connectGscButton ?? 'Connecter GSC'}
                        </Link>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <BarChart3 className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            {t?.analytics?.noSitesConnected ?? 'Aucun site connecté'}
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto mb-6">
                            {t?.analytics?.noSitesConnectedDescription ?? 'Connectez Google Search Console sur au moins un site pour voir les analytics.'}
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
                            {t?.analytics?.manageSites ?? 'Gérer les sites'}
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
                                                    {trend >= 0 ? '+' : ''}{trend}% {t?.analytics?.vsPreviousPeriod ?? 'vs période précédente'}
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

                    <div className="mt-6 grid gap-4 xl:grid-cols-5">
                        <BusinessMetricCard
                            label="Traffic value"
                            value={formatBusinessCurrency(businessSummary.totals.traffic_value)}
                            delta={businessSummary.deltas.traffic_value.percentage}
                            tone="emerald"
                        />
                        <BusinessMetricCard
                            label="Attributed revenue"
                            value={formatBusinessCurrency(businessSummary.totals.attributed_revenue)}
                            delta={businessSummary.deltas.attributed_revenue.percentage}
                            tone="rose"
                            caption={businessSummary.totals.conversion_source === 'tracked' ? 'Conversions monetized from GA4' : 'Modeled from site assumptions'}
                        />
                        <BusinessMetricCard
                            label="Total value"
                            value={formatBusinessCurrency(businessSummary.totals.total_value)}
                            delta={businessSummary.deltas.total_value.percentage}
                            tone="blue"
                            caption={businessSummary.totals.search_click_share !== null && businessSummary.totals.search_click_share !== undefined ? `${businessSummary.totals.search_click_share.toFixed(1)}% of tracked search clicks` : 'Search share not available yet'}
                        />
                        <BusinessMetricCard
                            label="Estimated conversions"
                            value={businessSummary.totals.estimated_conversions.toLocaleString()}
                            delta={businessSummary.deltas.estimated_conversions.percentage}
                            tone="indigo"
                            caption={businessSummary.totals.conversion_source === 'tracked' ? 'GA4 tracked' : 'Modeled fallback'}
                        />
                        <BusinessMetricCard
                            label="Blended ROI"
                            value={businessSummary.totals.roi !== null && businessSummary.totals.roi !== undefined ? `${businessSummary.totals.roi.toFixed(0)}%` : '—'}
                            tone="amber"
                            caption={businessSummary.totals.generation_cost > 0 ? `Net ${formatBusinessCurrency(businessSummary.totals.net_value)}` : 'No generation cost yet'}
                        />
                    </div>

                    {/* Chart */}
                    {analyticsData.length > 0 && (
                        <div className="mt-6 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="mb-6">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">{t?.analytics?.performanceOverTime ?? 'Performance dans le temps'}</h3>
                                <p className="text-sm text-surface-500 dark:text-surface-400">{t?.analytics?.clicksAndImpressions ?? 'Clics et impressions'}</p>
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
                                            name={t?.analytics?.clicks ?? 'Clics'}
                                        />
                                        <Area
                                            yAxisId="right"
                                            type="monotone"
                                            dataKey="impressions"
                                            stroke="#3b82f6"
                                            fillOpacity={1}
                                            fill="url(#colorImpressions)"
                                            strokeWidth={2}
                                            name={t?.analytics?.impressions ?? 'Impressions'}
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    )}

                    {/* Tables */}
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <TrendingUp className="h-5 w-5 text-emerald-500" />
                                        Business attribution
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Traffic value plus attributed revenue over the last {businessSummary.lookback_days} days, weighted by tracked or modeled conversions.
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 space-y-3">
                                {businessSummary.top_articles.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">
                                        No attributed article value yet.
                                    </p>
                                ) : (
                                    businessSummary.top_articles.map((article) => (
                                        <div key={article.article_id} className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium text-surface-900 dark:text-white">{article.title}</p>
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                        {article.performance_label.replace(/_/g, ' ')}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={route('articles.show', { article: article.article_id })}
                                                    className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    Open
                                                </Link>
                                            </div>
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <SmallMetric label="Traffic value" value={formatBusinessCurrency(article.traffic_value)} />
                                                <SmallMetric label="Revenue" value={formatBusinessCurrency(article.attributed_revenue)} />
                                                <SmallMetric label="Total value" value={formatBusinessCurrency(article.total_value)} />
                                            </div>
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <SmallMetric label="Conversions" value={article.estimated_conversions} />
                                                <SmallMetric label="Search share" value={article.search_click_share !== null && article.search_click_share !== undefined ? `${article.search_click_share.toFixed(1)}%` : '—'} />
                                                <SmallMetric
                                                    label="ROI"
                                                    value={article.roi !== null && article.roi !== undefined ? `${article.roi.toFixed(0)}%` : '—'}
                                                />
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <Target className="h-5 w-5 text-rose-500" />
                                        Business model
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Site-specific assumptions used when GA4 does not expose enough tracked conversions.
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="block">
                                        <span className="text-xs font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                            Modeled conversion rate
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.1"
                                            value={businessModel.modeled_conversion_rate}
                                            onChange={(event) => setBusinessModel((current) => ({ ...current, modeled_conversion_rate: event.target.value }))}
                                            className="mt-2 w-full rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                                        />
                                    </label>
                                    <label className="block">
                                        <span className="text-xs font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                            Average conversion value
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={businessModel.average_conversion_value}
                                            onChange={(event) => setBusinessModel((current) => ({ ...current, average_conversion_value: event.target.value }))}
                                            className="mt-2 w-full rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                                        />
                                    </label>
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-surface-200 dark:border-surface-800 px-4 py-3">
                                    <div className="text-sm text-surface-600 dark:text-surface-300">
                                        <p>
                                            Source: <span className="font-medium text-surface-900 dark:text-white">{businessSummary.business_model.source}</span>
                                        </p>
                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                            Search click capture: {businessSummary.search_capture.recent_click_share !== null && businessSummary.search_capture.recent_click_share !== undefined ? `${businessSummary.search_capture.recent_click_share.toFixed(1)}%` : 'not enough site analytics yet'}
                                        </p>
                                    </div>
                                    {selectedSite && (
                                        <Button variant="secondary" size="sm" onClick={handleBusinessModelSave}>
                                            Save assumptions
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <RefreshCw className="h-5 w-5 text-primary-500" />
                                        Refresh winners
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Articles with a recent refresh and improving business value.
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 space-y-3">
                                {businessSummary.refresh_winners.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">
                                        No refresh winners detected yet.
                                    </p>
                                ) : (
                                    businessSummary.refresh_winners.map((winner) => (
                                        <div key={winner.article_id} className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium text-surface-900 dark:text-white">{winner.title}</p>
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                        {winner.latest_refresh_at
                                                            ? `Last refresh ${format(new Date(winner.latest_refresh_at), 'd MMM yyyy', { locale: fr })}`
                                                            : 'Recent refresh'}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={route('articles.show', { article: winner.article_id })}
                                                    className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    Open
                                                </Link>
                                            </div>
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <SmallMetric label="Traffic delta" value={formatSignedCurrency(winner.traffic_value_delta)} />
                                                <SmallMetric label="Revenue delta" value={formatSignedCurrency(winner.attributed_revenue_delta)} />
                                                <SmallMetric label="Total delta" value={formatSignedCurrency(winner.total_value_delta)} />
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <Radar className="h-5 w-5 text-indigo-500" />
                                        AI Visibility
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Estimated coverage across AI answer engines.
                                    </p>
                                </div>
                                <div className="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 px-3 py-2 text-right">
                                    <p className="text-xs uppercase tracking-wide text-indigo-500">Average</p>
                                    <p className="text-xl font-semibold text-indigo-700 dark:text-indigo-300">
                                        {aiVisibility.summary.avg_visibility_score.toFixed(1)}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <div className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Prompts</p>
                                    <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">{aiVisibility.summary.total_prompts}</p>
                                </div>
                                <div className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Covered</p>
                                    <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">{aiVisibility.summary.covered_prompts}</p>
                                </div>
                                <div className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Checked</p>
                                    <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">{aiVisibility.summary.checked_prompts}</p>
                                </div>
                            </div>

                            {aiVisibility.engines.length > 0 && (
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    {aiVisibility.engines.map((engine) => (
                                        <div key={engine.engine} className="rounded-xl bg-surface-50/80 dark:bg-surface-800/70 p-4">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-medium capitalize text-surface-900 dark:text-white">
                                                    {engine.engine.replace('_', ' ')}
                                                </p>
                                                <span className="text-sm font-semibold text-surface-700 dark:text-surface-300">
                                                    {engine.avg_visibility_score.toFixed(1)}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-xs text-surface-500 dark:text-surface-400">
                                                {engine.covered_prompts}/{engine.total_prompts} prompts surfaced
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <Sparkles className="h-5 w-5 text-amber-500" />
                                        AI Opportunity Queue
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Highest-value prompt gaps and reinforcement opportunities.
                                    </p>
                                </div>
                            </div>

                            {aiVisibility.recommendations.length === 0 ? (
                                <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">
                                    No AI visibility recommendations yet. Run a sync on a selected site to populate this panel.
                                </p>
                            ) : (
                                <div className="mt-4 space-y-3">
                                    {aiVisibility.recommendations.map((recommendation, index) => (
                                        <div key={`${recommendation.prompt_id}-${index}`} className="rounded-xl border border-surface-200 dark:border-surface-800 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">{recommendation.title}</p>
                                                <span className="rounded-full bg-surface-100 dark:bg-surface-800 px-2.5 py-1 text-xs text-surface-600 dark:text-surface-300">
                                                    {recommendation.type.replace('_', ' ')}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{recommendation.reason}</p>
                                            {recommendation.article_id && (
                                                <Link
                                                    href={route('articles.show', { article: recommendation.article_id })}
                                                    className="mt-3 inline-flex text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    Open related article
                                                </Link>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Top Pages */}
                        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                            <div className="border-b border-surface-100 dark:border-surface-800 px-6 py-4">
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">{t?.analytics?.topPages ?? 'Top Pages'}</h3>
                            </div>
                            {isAggregatedView ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    {t?.analytics?.selectSiteForPages ?? 'Sélectionnez un site pour voir les top pages.'}
                                </div>
                            ) : topPages.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    {t?.analytics?.noData ?? 'Aucune donnée disponible.'}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="bg-surface-50/50 dark:bg-surface-800/50">
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.page ?? 'Page'}
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.clicks ?? 'Clics'}
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.position ?? 'Pos'}
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
                                <h3 className="font-display font-semibold text-surface-900 dark:text-white">{t?.analytics?.topQueries ?? 'Top Requêtes'}</h3>
                            </div>
                            {isAggregatedView ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    {t?.analytics?.selectSiteForQueries ?? 'Sélectionnez un site pour voir les top requêtes.'}
                                </div>
                            ) : topQueries.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    {t?.analytics?.noData ?? 'Aucune donnée disponible.'}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="bg-surface-50/50 dark:bg-surface-800/50">
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.query ?? 'Requête'}
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.clicks ?? 'Clics'}
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                                    {t?.analytics?.position ?? 'Pos'}
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

                    {/* Declining Articles */}
                    {selectedSite && (
                        <div className="mt-6 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                            <div className="border-b border-surface-100 dark:border-surface-800 px-6 py-4 flex items-center justify-between">
                                <div>
                                    <h3 className="font-display font-semibold text-surface-900 dark:text-white flex items-center gap-2">
                                        <TrendingDown className="h-5 w-5 text-amber-500" />
                                        Articles nécessitant une attention
                                    </h3>
                                    <p className="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
                                        Refresh candidates detected from analytics, AI visibility, and content decay signals.
                                    </p>
                                </div>
                            </div>
                            {refreshRecommendations.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                    No refresh recommendations yet.
                                </div>
                            ) : (
                                <div className="divide-y divide-surface-100 dark:divide-surface-800">
                                    {refreshRecommendations.map((recommendation) => (
                                        <div key={recommendation.id} className="px-6 py-5">
                                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                                <div className="max-w-3xl">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium text-surface-900 dark:text-white">
                                                            {recommendation.article_title ?? 'Untitled article'}
                                                        </p>
                                                        <span className={clsx(
                                                            'rounded-full px-2.5 py-1 text-xs font-medium',
                                                            recommendation.severity === 'high'
                                                                ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                                : recommendation.severity === 'medium'
                                                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                                                    : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                                        )}>
                                                            {recommendation.severity}
                                                        </span>
                                                        <span className="rounded-full bg-surface-100 dark:bg-surface-800 px-2.5 py-1 text-xs text-surface-600 dark:text-surface-300">
                                                            {recommendation.trigger_type.replace(/_/g, ' ')}
                                                        </span>
                                                    </div>
                                                    <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{recommendation.reason}</p>
                                                    {recommendation.recommended_actions.length > 0 && (
                                                        <div className="mt-3 flex flex-wrap gap-2">
                                                            {recommendation.recommended_actions.map((action, index) => (
                                                                <span key={index} className="rounded-full bg-surface-50 dark:bg-surface-800 px-3 py-1 text-xs text-surface-600 dark:text-surface-300">
                                                                    {action}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {recommendation.status === 'open' && (
                                                        <Button variant="secondary" size="sm" onClick={() => handleRefreshAction(recommendation.id, 'accept')}>
                                                            Accept
                                                        </Button>
                                                    )}
                                                    {recommendation.status !== 'executed' && recommendation.status !== 'dismissed' && (
                                                        <Button size="sm" onClick={() => handleRefreshAction(recommendation.id, 'execute')}>
                                                            Generate draft
                                                        </Button>
                                                    )}
                                                    {recommendation.status !== 'dismissed' && (
                                                        <Button variant="ghost" size="sm" onClick={() => handleRefreshAction(recommendation.id, 'dismiss')}>
                                                            Dismiss
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}
        </AppLayout>
    );
}

function BusinessMetricCard({
    label,
    value,
    delta,
    tone,
    caption,
}: {
    label: string;
    value: string;
    delta?: number | null;
    tone: 'emerald' | 'indigo' | 'amber' | 'slate' | 'rose' | 'blue';
    caption?: string;
}) {
    const tones = {
        emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        slate: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200',
        rose: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
        blue: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
    } as const;

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5">
            <div className={clsx('inline-flex rounded-xl px-3 py-1.5 text-xs font-semibold uppercase tracking-wide', tones[tone])}>{label}</div>
            <p className="mt-4 font-display text-3xl font-bold text-surface-900 dark:text-white">{value}</p>
            {delta !== null && delta !== undefined && (
                <p className={clsx('mt-2 text-xs font-medium', delta >= 0 ? 'text-emerald-600' : 'text-red-600')}>
                    {delta >= 0 ? '+' : ''}
                    {delta.toFixed(1)}% vs previous window
                </p>
            )}
            {caption && <p className="mt-2 text-xs text-surface-500 dark:text-surface-400">{caption}</p>}
        </div>
    );
}

function SmallMetric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl bg-surface-50/80 dark:bg-surface-800/70 p-3">
            <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">{label}</p>
            <p className="mt-2 text-lg font-semibold text-surface-900 dark:text-white">{value}</p>
        </div>
    );
}

function formatBusinessCurrency(value: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function formatSignedCurrency(value: number): string {
    const sign = value > 0 ? '+' : '';
    return `${sign}${formatBusinessCurrency(value)}`;
}

function formatSignedNumber(value: number): string {
    const sign = value > 0 ? '+' : '';
    return `${sign}${value.toFixed(1)}`;
}
