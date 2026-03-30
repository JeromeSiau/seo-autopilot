import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/Button';
import { PageProps, Site } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowLeft, ExternalLink, RefreshCw, Sparkles, TrendingUp } from 'lucide-react';
import clsx from 'clsx';

interface RefreshPlannerItem {
    id: number;
    site_id: number;
    site_name?: string | null;
    article_id: number;
    article_title?: string | null;
    article_status?: string | null;
    trigger_type: string;
    severity: 'low' | 'medium' | 'high';
    reason: string;
    recommended_actions: string[];
    metrics_snapshot?: Record<string, unknown>;
    business_attribution?: {
        recent?: {
            clicks?: number | null;
            sessions?: number | null;
            estimated_conversions?: number | null;
            traffic_value?: number | null;
            conversion_source?: string | null;
        } | null;
        previous?: {
            clicks?: number | null;
            sessions?: number | null;
            estimated_conversions?: number | null;
            traffic_value?: number | null;
            conversion_source?: string | null;
        } | null;
        deltas?: {
            clicks?: { absolute?: number | null; percentage?: number | null } | null;
            sessions?: { absolute?: number | null; percentage?: number | null } | null;
            estimated_conversions?: { absolute?: number | null; percentage?: number | null } | null;
            traffic_value?: { absolute?: number | null; percentage?: number | null } | null;
        } | null;
    } | null;
    ai_visibility?: {
        recent_avg?: number | null;
        previous_avg?: number | null;
        delta?: number | null;
        appears_rate?: number | null;
        weakest_engine?: string | null;
        weakest_score?: number | null;
        matching_prompts: string[];
        competitor_domains: string[];
        largest_drop?: {
            topic?: string | null;
            engine?: string | null;
            current_score?: number | null;
            previous_score?: number | null;
            delta?: number | null;
        } | null;
    } | null;
    status: 'open' | 'accepted' | 'executed' | 'dismissed';
    detected_at?: string | null;
    executed_at?: string | null;
    next_action: 'accept_recommendation' | 'generate_refresh_draft' | 'review_draft' | 'dismissed' | 'review_recommendation';
    latest_run?: {
        id: number;
        status: string;
        summary: string;
        draft_meta_title?: string | null;
        draft_meta_description?: string | null;
        draft_content_excerpt?: string | null;
        diff?: {
            old_meta_title?: string | null;
            new_meta_title?: string | null;
            old_meta_description?: string | null;
            new_meta_description?: string | null;
            meta_title_changed?: boolean;
            meta_description_changed?: boolean;
            old_word_count?: number | null;
            new_word_count?: number | null;
            word_delta?: number | null;
            sections_added?: string[];
        } | null;
        focus_sections?: string[];
        business_case?: {
            traffic_value?: number | null;
            estimated_conversions?: number | null;
            conversion_source?: string | null;
            roi?: number | null;
            traffic_value_delta?: number | null;
            conversion_delta?: number | null;
            click_delta?: number | null;
            session_delta?: number | null;
            trigger_type?: string | null;
        } | null;
        old_readiness_score?: number | null;
        new_readiness_score?: number | null;
        readiness_delta?: number | null;
    } | null;
}

interface RefreshPlannerPayload {
    summary: {
        total: number;
        open: number;
        accepted: number;
        executed: number;
        dismissed: number;
    };
    items: RefreshPlannerItem[];
}

interface NeedsRefreshPageProps extends PageProps {
    sites: Site[];
    selectedSite: Site | null;
    selectedStatus: 'active' | 'all' | 'open' | 'accepted' | 'executed' | 'dismissed';
    refreshPlanner: RefreshPlannerPayload;
}

const FILTER_CLASS =
    'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors';

export default function NeedsRefreshPage({
    sites,
    selectedSite,
    selectedStatus,
    refreshPlanner,
}: NeedsRefreshPageProps) {
    const handleSiteChange = (siteId: string) => {
        router.get(route('articles.needs-refresh'), {
            site_id: siteId || undefined,
            status: selectedStatus,
        });
    };

    const handleStatusChange = (status: string) => {
        router.get(route('articles.needs-refresh'), {
            site_id: selectedSite?.id,
            status,
        });
    };

    const handleRefreshAction = (recommendationId: number, action: 'accept' | 'dismiss' | 'execute' | 'apply') => {
        router.post(route(`refresh-recommendations.${action}`, { refreshRecommendation: recommendationId }), {}, { preserveScroll: true });
    };

    return (
        <AppLayout
            header={
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="space-y-3">
                        <Link
                            href={route('articles.index')}
                            className="inline-flex items-center gap-2 text-sm font-medium text-surface-500 transition-colors hover:text-surface-900 dark:text-surface-400 dark:hover:text-white"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to articles
                        </Link>
                        <div>
                            <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Needs Refresh</h1>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Review declining articles, generate refresh drafts, and move them back into editorial flow.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <select value={selectedSite?.id || ''} onChange={(event) => handleSiteChange(event.target.value)} className={FILTER_CLASS}>
                            <option value="">All sites</option>
                            {sites.map((site) => (
                                <option key={site.id} value={site.id}>
                                    {site.name}
                                </option>
                            ))}
                        </select>

                        <select value={selectedStatus} onChange={(event) => handleStatusChange(event.target.value)} className={FILTER_CLASS}>
                            <option value="active">Active queue</option>
                            <option value="open">Open</option>
                            <option value="accepted">Accepted</option>
                            <option value="executed">Executed</option>
                            <option value="dismissed">Dismissed</option>
                            <option value="all">All statuses</option>
                        </select>
                    </div>
                </div>
            }
        >
            <Head title="Needs Refresh" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Queue total" value={String(refreshPlanner.summary.total)} tone="slate" />
                    <SummaryCard label="Open" value={String(refreshPlanner.summary.open)} tone="amber" />
                    <SummaryCard label="Accepted" value={String(refreshPlanner.summary.accepted)} tone="indigo" />
                    <SummaryCard label="Drafted" value={String(refreshPlanner.summary.executed)} tone="emerald" />
                </div>

                {refreshPlanner.items.length === 0 ? (
                    <div className="rounded-2xl border border-surface-200 bg-white p-12 text-center dark:border-surface-800 dark:bg-surface-900/50">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                            <RefreshCw className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="mt-4 font-display text-lg font-semibold text-surface-900 dark:text-white">No refresh items in this view</h3>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Adjust the filters or run refresh detection from analytics to populate the queue.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {refreshPlanner.items.map((item) => (
                            <div key={item.id} className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                                <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                    <div className="max-w-3xl">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                                {item.article_title ?? 'Untitled article'}
                                            </p>
                                            <span className={clsx(
                                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                                item.severity === 'high'
                                                    ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                    : item.severity === 'medium'
                                                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                                        : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                            )}>
                                                {item.severity}
                                            </span>
                                            <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                {item.trigger_type.replace(/_/g, ' ')}
                                            </span>
                                            <span className="rounded-full bg-primary-50 px-2.5 py-1 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                                {item.status}
                                            </span>
                                            {item.site_name && !selectedSite && (
                                                <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    {item.site_name}
                                                </span>
                                            )}
                                        </div>

                                        <p className="mt-3 text-sm text-surface-600 dark:text-surface-400">{item.reason}</p>

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {item.recommended_actions.map((action, index) => (
                                                <span key={index} className="rounded-full bg-surface-50 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    {action}
                                                </span>
                                            ))}
                                        </div>

                                        {item.ai_visibility && (
                                            <div className="mt-4 rounded-2xl border border-surface-200 bg-surface-50/80 p-4 dark:border-surface-800 dark:bg-surface-800/50">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                        AI visibility
                                                    </span>
                                                    {typeof item.ai_visibility.delta === 'number' && (
                                                        <span className={clsx(
                                                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                                                            item.ai_visibility.delta <= -10
                                                                ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                                : item.ai_visibility.delta >= 6
                                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                                    : 'bg-surface-100 text-surface-700 dark:bg-surface-900 dark:text-surface-300'
                                                        )}>
                                                            {item.ai_visibility.delta > 0 ? '+' : ''}
                                                            {item.ai_visibility.delta.toFixed(1)}
                                                        </span>
                                                    )}
                                                    {item.ai_visibility.weakest_engine && (
                                                        <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-900 dark:text-surface-300">
                                                            Weakest {item.ai_visibility.weakest_engine.replace(/_/g, ' ')}
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                    <MetricCard label="Current AI score" value={item.ai_visibility.recent_avg ?? '—'} />
                                                    <MetricCard label="Previous AI score" value={item.ai_visibility.previous_avg ?? '—'} />
                                                    <MetricCard
                                                        label="Appears rate"
                                                        value={item.ai_visibility.appears_rate !== null && item.ai_visibility.appears_rate !== undefined
                                                            ? `${Math.round(item.ai_visibility.appears_rate * 100)}%`
                                                            : '—'}
                                                    />
                                                </div>

                                                {item.ai_visibility.matching_prompts.length > 0 && (
                                                    <div className="mt-3">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Prompt cluster</p>
                                                        <div className="mt-2 flex flex-wrap gap-2">
                                                            {item.ai_visibility.matching_prompts.map((prompt) => (
                                                                <span key={prompt} className="rounded-full bg-white px-3 py-1 text-xs text-surface-600 dark:bg-surface-900 dark:text-surface-300">
                                                                    {prompt}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {item.ai_visibility.competitor_domains.length > 0 && (
                                                    <div className="mt-3">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Competing domains</p>
                                                        <div className="mt-2 flex flex-wrap gap-2">
                                                            {item.ai_visibility.competitor_domains.map((domain) => (
                                                                <span key={domain} className="rounded-full bg-white px-3 py-1 text-xs text-surface-600 dark:bg-surface-900 dark:text-surface-300">
                                                                    {domain}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {item.status === 'open' && (
                                                <Button variant="secondary" size="sm" onClick={() => handleRefreshAction(item.id, 'accept')}>
                                                    Accept
                                                </Button>
                                            )}
                                            {item.status !== 'executed' && item.status !== 'dismissed' && (
                                                <Button size="sm" icon={Sparkles} onClick={() => handleRefreshAction(item.id, 'execute')}>
                                                    Generate draft
                                                </Button>
                                            )}
                                            {item.status !== 'dismissed' && (
                                                <Button variant="ghost" size="sm" onClick={() => handleRefreshAction(item.id, 'dismiss')}>
                                                    Dismiss
                                                </Button>
                                            )}
                                            {item.latest_run && (
                                                <Button variant="secondary" size="sm" onClick={() => handleRefreshAction(item.id, 'apply')}>
                                                    Push to review
                                                </Button>
                                            )}
                                            <Link
                                                href={route('articles.show', { article: item.article_id })}
                                                className="inline-flex items-center gap-1 rounded-xl border border-surface-200 px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-50 dark:border-surface-700 dark:text-surface-200 dark:hover:bg-surface-800"
                                            >
                                                Open article
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    </div>

                                    <div className="w-full max-w-xl rounded-2xl border border-surface-200 bg-surface-50/70 p-5 dark:border-surface-800 dark:bg-surface-800/40">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">Planner state</p>
                                                <p className="mt-1 text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                                    {item.next_action.replace(/_/g, ' ')}
                                                </p>
                                            </div>
                                            {item.latest_run?.readiness_delta !== null && item.latest_run?.readiness_delta !== undefined && (
                                                <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                    <TrendingUp className="h-3.5 w-3.5" />
                                                    {item.latest_run.readiness_delta >= 0 ? '+' : ''}
                                                    {item.latest_run.readiness_delta} readiness
                                                </div>
                                            )}
                                        </div>

                                        {item.latest_run ? (
                                            <div className="mt-4 space-y-3">
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <MetricCard label="Old readiness" value={item.latest_run.old_readiness_score ?? '—'} />
                                                    <MetricCard label="Draft readiness" value={item.latest_run.new_readiness_score ?? '—'} />
                                                </div>

                                                {item.business_attribution && (
                                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                        <MetricCard
                                                            label="Clicks delta"
                                                            value={formatSignedValue(item.business_attribution.deltas?.clicks?.absolute)}
                                                        />
                                                        <MetricCard
                                                            label="Sessions delta"
                                                            value={formatSignedValue(item.business_attribution.deltas?.sessions?.absolute)}
                                                        />
                                                        <MetricCard
                                                            label="Conversion delta"
                                                            value={formatSignedFloat(item.business_attribution.deltas?.estimated_conversions?.absolute)}
                                                        />
                                                        <MetricCard
                                                            label="Value delta"
                                                            value={formatCurrency(item.business_attribution.deltas?.traffic_value?.absolute)}
                                                        />
                                                    </div>
                                                )}

                                                {item.latest_run.draft_meta_title && (
                                                    <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Draft meta title</p>
                                                        <p className="mt-2 text-sm font-medium text-surface-900 dark:text-white">
                                                            {item.latest_run.draft_meta_title}
                                                        </p>
                                                        {item.latest_run.draft_meta_description && (
                                                            <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                                                                {item.latest_run.draft_meta_description}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}

                                                {item.latest_run.diff && (
                                                    <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Planned diff</p>
                                                        <div className="mt-3 space-y-3 text-sm text-surface-700 dark:text-surface-300">
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                <div>
                                                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Meta title</p>
                                                                    <p className="mt-1">{item.latest_run.diff.old_meta_title ?? '—'}</p>
                                                                </div>
                                                                <div>
                                                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Draft meta title</p>
                                                                    <p className="mt-1">{item.latest_run.diff.new_meta_title ?? '—'}</p>
                                                                </div>
                                                            </div>

                                                            <div className="grid gap-3 sm:grid-cols-3">
                                                                <MetricCard label="Current words" value={item.latest_run.diff.old_word_count ?? '—'} />
                                                                <MetricCard label="Draft words" value={item.latest_run.diff.new_word_count ?? '—'} />
                                                                <MetricCard label="Word delta" value={formatSignedValue(item.latest_run.diff.word_delta)} />
                                                            </div>

                                                            {item.latest_run.diff.sections_added && item.latest_run.diff.sections_added.length > 0 && (
                                                                <div>
                                                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Sections added</p>
                                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                                        {item.latest_run.diff.sections_added.map((section) => (
                                                                            <span key={section} className="rounded-full bg-surface-100 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                                                {section}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {item.latest_run.summary && (
                                                    <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Planner summary</p>
                                                        <p className="mt-2 whitespace-pre-wrap text-sm text-surface-700 dark:text-surface-300">
                                                            {item.latest_run.summary}
                                                        </p>
                                                    </div>
                                                )}

                                                {item.latest_run.draft_content_excerpt && (
                                                    <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Draft excerpt</p>
                                                        <p className="mt-2 text-sm text-surface-700 dark:text-surface-300">
                                                            {item.latest_run.draft_content_excerpt}
                                                        </p>
                                                    </div>
                                                )}

                                                {item.latest_run.business_case && (
                                                    <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
                                                        <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">Business case</p>
                                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                            <MetricCard label="Value now" value={formatCurrency(item.latest_run.business_case.traffic_value)} />
                                                            <MetricCard label="ROI now" value={item.latest_run.business_case.roi !== null && item.latest_run.business_case.roi !== undefined ? `${item.latest_run.business_case.roi.toFixed(0)}%` : '—'} />
                                                            <MetricCard label="Value trend" value={formatCurrency(item.latest_run.business_case.traffic_value_delta)} />
                                                            <MetricCard label="Est. conversions" value={item.latest_run.business_case.estimated_conversions ?? '—'} />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">
                                                No refresh draft exists yet for this recommendation.
                                            </p>
                                        )}

                                        <p className="mt-4 text-xs text-surface-500 dark:text-surface-400">
                                            Detected {item.detected_at ? format(new Date(item.detected_at), 'd MMM yyyy', { locale: fr }) : 'recently'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone: 'slate' | 'amber' | 'indigo' | 'emerald';
}) {
    const tones = {
        slate: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200',
        amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    } as const;

    return (
        <div className="rounded-2xl border border-surface-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900/50">
            <div className={clsx('inline-flex rounded-xl px-3 py-1.5 text-xs font-semibold uppercase tracking-wide', tones[tone])}>
                {label}
            </div>
            <p className="mt-4 font-display text-3xl font-bold text-surface-900 dark:text-white">{value}</p>
        </div>
    );
}

function MetricCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl bg-white p-4 dark:bg-surface-900/70">
            <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">{value}</p>
        </div>
    );
}

function formatSignedValue(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value}`;
}

function formatSignedFloat(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(1)}`;
}

function formatCurrency(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value)}`;
}
