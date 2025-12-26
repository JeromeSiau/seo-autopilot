import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Plus, AlertTriangle, ArrowRight, Globe, Search, FileText, CheckCircle, TrendingUp, Zap, Sparkles, Loader2 } from 'lucide-react';
import SiteCard from '@/Components/Dashboard/SiteCard';
import { PageProps } from '@/types';
import clsx from 'clsx';
import { useTranslations } from '@/hooks/useTranslations';
import { trans } from '@/utils/i18n';

interface Site {
    id: number;
    domain: string;
    name: string;
    autopilot_status: 'active' | 'paused' | 'not_configured' | 'error';
    articles_per_week: number;
    articles_in_review: number;
    articles_this_week: number;
    onboarding_complete: boolean;
    is_generating: boolean;
}

interface Action {
    type: 'review' | 'failed' | 'recommendation';
    site_id: number;
    site_domain: string;
    count?: number;
    message: string;
    action_url: string;
}

interface Stats {
    total_sites: number;
    active_sites: number;
    total_keywords_queued: number;
    articles_this_month: number;
    articles_published_this_month: number;
    articles_used: number;
    articles_limit: number;
}

interface DashboardProps extends PageProps {
    stats: Stats;
    sites: Site[];
    actionsRequired: Action[];
    unreadNotifications: number;
}

interface StatCardProps {
    title: string;
    value: string | number;
    icon: React.ElementType;
    trend?: { value: number; positive: boolean };
    color: 'primary' | 'blue' | 'purple' | 'amber';
    trendLabel?: string;
}

function StatCard({ title, value, icon: Icon, trend, color, trendLabel = 'vs last month' }: StatCardProps) {
    const colorStyles = {
        primary: {
            bg: 'bg-primary-50 dark:bg-primary-500/10',
            icon: 'text-primary-600 dark:text-primary-400',
            iconBg: 'bg-primary-100 dark:bg-primary-500/15',
        },
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-500/10',
            icon: 'text-blue-600 dark:text-blue-400',
            iconBg: 'bg-blue-100 dark:bg-blue-500/15',
        },
        purple: {
            bg: 'bg-purple-50 dark:bg-purple-500/10',
            icon: 'text-purple-600 dark:text-purple-400',
            iconBg: 'bg-purple-100 dark:bg-purple-500/15',
        },
        amber: {
            bg: 'bg-amber-50 dark:bg-amber-500/10',
            icon: 'text-amber-600 dark:text-amber-400',
            iconBg: 'bg-amber-100 dark:bg-amber-500/15',
        },
    };

    const styles = colorStyles[color];

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-md dark:hover:shadow-green-glow/20 dark:hover:border-surface-700 transition-all">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{title}</p>
                    <p className="mt-2 font-display text-2xl font-bold text-surface-900 dark:text-white">{value}</p>
                    {trend && (
                        <div className={clsx(
                            'mt-2 flex items-center gap-1 text-xs font-medium',
                            trend.positive ? 'text-primary-600 dark:text-primary-400' : 'text-red-600 dark:text-red-400'
                        )}>
                            <TrendingUp className={clsx('h-3 w-3', !trend.positive && 'rotate-180')} />
                            {trend.positive ? '+' : ''}{trend.value}% {trendLabel}
                        </div>
                    )}
                </div>
                <div className={clsx('rounded-xl p-2.5', styles.iconBg)}>
                    <Icon className={clsx('h-5 w-5', styles.icon)} />
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({ stats, sites, actionsRequired }: DashboardProps) {
    const { t } = useTranslations();
    const usagePercentage = stats.articles_limit > 0
        ? Math.round((stats.articles_used / stats.articles_limit) * 100)
        : 0;

    // Find sites with active generation
    const generatingSites = sites.filter(s => s.is_generating);

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">{t?.dashboard?.title ?? 'Dashboard'}</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{t?.dashboard?.subtitle ?? 'Overview of your SEO content performance'}</p>
                    </div>
                    <Link
                        href={route('onboarding.create')}
                        className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5 transition-all"
                    >
                        <Plus className="h-4 w-4" />
                        {t?.dashboard?.addSite ?? 'Add site'}
                    </Link>
                </div>
            }
        >
            <Head title={t?.dashboard?.title ?? 'Dashboard'} />

            {/* Generation in progress banner */}
            {generatingSites.length > 0 && (
                <div className="mb-6 rounded-2xl bg-gradient-to-r from-primary-500/10 to-primary-600/10 dark:from-primary-500/20 dark:to-primary-600/20 border border-primary-200 dark:border-primary-500/30 p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-primary-500/20 dark:bg-primary-500/30 flex items-center justify-center">
                                <Loader2 className="h-5 w-5 text-primary-600 dark:text-primary-400 animate-spin" />
                            </div>
                            <div>
                                <p className="font-medium text-surface-900 dark:text-white flex items-center gap-2">
                                    <Sparkles className="h-4 w-4 text-primary-500" />
                                    {t?.dashboard?.generationBanner?.title ?? 'Content Plan being created'}
                                </p>
                                <p className="text-sm text-surface-600 dark:text-surface-400">
                                    {generatingSites.map(s => s.domain).join(', ')}
                                </p>
                            </div>
                        </div>
                        <Link
                            href={route('onboarding.generating', { site: generatingSites[0].id })}
                            className="inline-flex items-center gap-2 rounded-xl bg-primary-500 hover:bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-green dark:shadow-green-glow transition-all"
                        >
                            {t?.dashboard?.generationBanner?.viewProgress ?? 'View progress'}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            )}

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard
                    title={t?.dashboard?.activeSites ?? 'Active sites'}
                    value={`${stats.active_sites}/${stats.total_sites}`}
                    icon={Globe}
                    color="primary"
                />
                <StatCard
                    title={t?.dashboard?.keywordsQueued ?? 'Keywords queued'}
                    value={stats.total_keywords_queued}
                    icon={Search}
                    color="purple"
                />
                <StatCard
                    title={t?.dashboard?.articlesThisMonth ?? 'Articles this month'}
                    value={stats.articles_this_month}
                    icon={FileText}
                    color="blue"
                />
                <StatCard
                    title={t?.dashboard?.published ?? 'Published'}
                    value={stats.articles_published_this_month}
                    icon={CheckCircle}
                    color="primary"
                />
            </div>

            {/* Usage Bar */}
            <div className="mt-6 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{t?.dashboard?.monthlyUsage ?? 'Monthly usage'}</p>
                        <p className="mt-1 font-display text-2xl font-bold text-surface-900 dark:text-white">
                            {stats.articles_used} <span className="text-surface-400 font-normal">/ {stats.articles_limit} {t?.common?.articles ?? 'articles'}</span>
                        </p>
                    </div>
                    <Link
                        href={route('settings.billing')}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-primary-300 dark:hover:border-primary-500 transition-all"
                    >
                        <Zap className="h-4 w-4 text-primary-500 dark:text-primary-400" />
                        {t?.settings?.upgrade ?? 'Upgrade'}
                    </Link>
                </div>
                <div className="mt-4">
                    <div className="h-2.5 w-full rounded-full bg-surface-100 dark:bg-surface-800">
                        <div
                            className={clsx(
                                'h-2.5 rounded-full transition-all',
                                usagePercentage >= 90 ? 'bg-red-500' :
                                usagePercentage >= 70 ? 'bg-amber-500' : 'bg-primary-500 dark:shadow-[0_0_10px_rgba(16,185,129,0.4)]'
                            )}
                            style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                        />
                    </div>
                    <div className="mt-2 flex justify-between text-xs text-surface-500 dark:text-surface-400">
                        <span>{trans(t?.dashboard?.usage?.percentUsed ?? '{percent}% used', { percent: usagePercentage })}</span>
                        <span>{trans(t?.dashboard?.usage?.remaining ?? '{count} remaining', { count: stats.articles_limit - stats.articles_used })}</span>
                    </div>
                </div>
            </div>

            {/* Actions Required */}
            {actionsRequired.length > 0 && (
                <div className="mt-6 bg-amber-50 dark:bg-amber-500/10 rounded-2xl border border-amber-200 dark:border-amber-500/20 p-6">
                    <h3 className="flex items-center gap-2 font-display font-semibold text-amber-800 dark:text-amber-400">
                        <AlertTriangle className="h-5 w-5" />
                        {t?.dashboard?.actionsRequired ?? 'Actions required'}
                    </h3>
                    <div className="mt-4 space-y-2">
                        {actionsRequired.map((action, i) => {
                            // Use <a> for external redirects (like Google OAuth), Link for internal navigation
                            const isExternalRedirect = action.type === 'recommendation';
                            const className = "flex items-center justify-between rounded-xl bg-white dark:bg-surface-900/50 p-4 border border-amber-100 dark:border-amber-500/15 hover:border-amber-300 dark:hover:border-amber-500/30 hover:shadow-sm transition-all";

                            const content = (
                                <>
                                    <div>
                                        <p className="font-medium text-surface-900 dark:text-white">{action.message}</p>
                                        <p className="text-sm text-surface-500 dark:text-surface-400">{action.site_domain}</p>
                                    </div>
                                    <ArrowRight className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                </>
                            );

                            return isExternalRedirect ? (
                                <a key={i} href={action.action_url} className={className}>
                                    {content}
                                </a>
                            ) : (
                                <Link key={i} href={action.action_url} className={className}>
                                    {content}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Sites Grid */}
            <div className="mt-8">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">{t?.dashboard?.mySites ?? 'My sites'}</h2>
                    {sites.length > 0 && (
                        <Link
                            href={route('sites.index')}
                            className="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 flex items-center gap-1"
                        >
                            {t?.dashboard?.viewAll ?? 'View all'}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    )}
                </div>
                {sites.length === 0 ? (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <Globe className="h-6 w-6 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">{t?.dashboard?.noSites ?? 'No sites configured'}</h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 mb-6">{t?.dashboard?.noSitesDescription ?? 'Get started by adding your first website'}</p>
                        <Link
                            href={route('onboarding.create')}
                            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg transition-all"
                        >
                            <Plus className="h-4 w-4" />
                            {t?.dashboard?.addFirstSite ?? 'Add your first site'}
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {sites.map((site) => (
                            <SiteCard key={site.id} site={site} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
