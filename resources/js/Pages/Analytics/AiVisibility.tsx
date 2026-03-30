import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/Button';
import { AiVisibilityPayload, PageProps, RefreshRecommendationListItem, Site } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    AlertTriangle,
    ArrowLeft,
    ExternalLink,
    Radar,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import clsx from 'clsx';

interface AiVisibilityPageProps extends PageProps {
    sites: Site[];
    selectedSite: Site | null;
    aiVisibility: AiVisibilityPayload;
    refreshRecommendations: RefreshRecommendationListItem[];
}

const ENGINE_LABELS: Record<string, string> = {
    ai_overviews: 'AI Overviews',
    chatgpt: 'ChatGPT',
    perplexity: 'Perplexity',
    gemini: 'Gemini',
};

const INPUT_CLASS =
    'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors';

export default function AiVisibilityPage({
    sites,
    selectedSite,
    aiVisibility,
    refreshRecommendations,
}: AiVisibilityPageProps) {
    const handleSiteChange = (siteId: string) => {
        router.get(route('analytics.ai-visibility.index'), { site_id: siteId || undefined });
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

    const hasData =
        aiVisibility.summary.total_prompts > 0 ||
        aiVisibility.top_prompts.length > 0 ||
        aiVisibility.weakest_prompts.length > 0 ||
        aiVisibility.recommendations.length > 0;

    return (
        <AppLayout
            header={
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="space-y-3">
                        <Link
                            href={route('analytics.index', { site_id: selectedSite?.id })}
                            className="inline-flex items-center gap-2 text-sm font-medium text-surface-500 transition-colors hover:text-surface-900 dark:text-surface-400 dark:hover:text-white"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to analytics
                        </Link>
                        <div>
                            <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">AI Visibility</h1>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Track prompt coverage, competitor presence, and source strength across AI answer engines.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={selectedSite?.id || ''}
                            onChange={(event) => handleSiteChange(event.target.value)}
                            className={INPUT_CLASS}
                        >
                            <option value="">Select a site</option>
                            {sites.map((site) => (
                                <option key={site.id} value={site.id}>
                                    {site.name}
                                </option>
                            ))}
                        </select>

                        {selectedSite && (
                            <>
                                <Link href={route('articles.needs-refresh', { site_id: selectedSite.id })}>
                                    <Button variant="secondary" size="sm" icon={RefreshCw}>
                                        Open refresh planner
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
                    </div>
                </div>
            }
        >
            <Head title="AI Visibility" />

            {!selectedSite ? (
                <div className="rounded-2xl border border-surface-200 bg-white p-12 text-center dark:border-surface-800 dark:bg-surface-900/50">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                        <Radar className="h-7 w-7 text-surface-400" />
                    </div>
                    <h3 className="mt-4 font-display text-lg font-semibold text-surface-900 dark:text-white">Select a site</h3>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Choose a site to inspect prompt coverage, AI competitor presence, and refresh opportunities.
                    </p>
                </div>
            ) : !hasData ? (
                <div className="rounded-2xl border border-surface-200 bg-white p-12 text-center dark:border-surface-800 dark:bg-surface-900/50">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                        <Sparkles className="h-7 w-7 text-surface-400" />
                    </div>
                    <h3 className="mt-4 font-display text-lg font-semibold text-surface-900 dark:text-white">No AI visibility data yet</h3>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Run a sync to generate prompt checks and recommendations for {selectedSite.name}.
                    </p>
                </div>
            ) : (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                        <SummaryCard label="Average score" value={aiVisibility.summary.avg_visibility_score.toFixed(1)} tone="indigo" />
                        <SummaryCard label="Tracked prompts" value={String(aiVisibility.summary.total_prompts)} tone="emerald" />
                        <SummaryCard label="Covered prompts" value={String(aiVisibility.summary.covered_prompts)} tone="amber" />
                        <SummaryCard
                            label="Average delta"
                            value={formatSignedNumber(aiVisibility.summary.avg_visibility_delta)}
                            tone={aiVisibility.summary.avg_visibility_delta >= 0 ? 'emerald' : 'rose'}
                        />
                        <SummaryCard
                            label="At-risk prompts"
                            value={String(aiVisibility.summary.high_risk_prompts)}
                            tone={aiVisibility.summary.high_risk_prompts > 0 ? 'rose' : 'slate'}
                        />
                        <SummaryCard
                            label="Last checked"
                            value={aiVisibility.summary.last_checked_at ? format(new Date(aiVisibility.summary.last_checked_at), 'd MMM', { locale: fr }) : 'Never'}
                            tone="slate"
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1fr_1fr]">
                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-surface-100 p-2.5 text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                    <Target className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Saved prompt sets</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Durable prompt groups that define the AI visibility surface monitored for this site.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.prompt_sets.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No prompt sets synced yet.</p>
                                ) : (
                                    aiVisibility.prompt_sets.map((set) => (
                                        <div key={set.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-medium text-surface-900 dark:text-white">{set.name}</p>
                                                        {set.is_default && (
                                                            <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                                Default
                                                            </span>
                                                        )}
                                                    </div>
                                                    {set.description && (
                                                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{set.description}</p>
                                                    )}
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold text-surface-900 dark:text-white">{set.avg_visibility_score.toFixed(1)}</p>
                                                    <p className="text-xs text-surface-500 dark:text-surface-400">avg visibility</p>
                                                </div>
                                            </div>
                                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                                                <span>{set.prompt_count} prompts</span>
                                                <span>{set.covered_prompts} covered</span>
                                                {set.last_synced_at && <span>Synced {format(new Date(set.last_synced_at), 'd MMM HH:mm', { locale: fr })}</span>}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-amber-50 p-2.5 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                                    <ShieldCheck className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Alert history</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Persisted alerts stay open until coverage improves, so regressions don’t disappear between runs.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.alert_history.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No persisted alert history yet.</p>
                                ) : (
                                    aiVisibility.alert_history.map((alert) => (
                                        <div key={alert.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium text-surface-900 dark:text-white">{alert.title}</p>
                                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{alert.reason}</p>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <SeverityBadge severity={alert.severity} />
                                                    <span className={clsx(
                                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                                        alert.status === 'open'
                                                            ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                            : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
                                                    )}>
                                                        {alert.status}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-surface-500 dark:text-surface-400">
                                                {alert.engine && <span>{ENGINE_LABELS[alert.engine] ?? alert.engine}</span>}
                                                {alert.last_detected_at && <span>Last seen {format(new Date(alert.last_detected_at), 'd MMM HH:mm', { locale: fr })}</span>}
                                                {alert.resolved_at && <span>Resolved {format(new Date(alert.resolved_at), 'd MMM HH:mm', { locale: fr })}</span>}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1fr_1fr]">
                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-red-50 p-2.5 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                                    <AlertTriangle className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Alert queue</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Prompt regressions, competitor pressure, and thin-source gaps that need attention first.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.alerts.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No AI visibility alerts yet.</p>
                                ) : (
                                    aiVisibility.alerts.map((alert, index) => (
                                        <div key={`${alert.type}-${alert.prompt_id ?? index}-${alert.engine ?? 'all'}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">{alert.title}</p>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <SeverityBadge severity={alert.severity} />
                                                    {alert.engine && (
                                                        <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                            {ENGINE_LABELS[alert.engine] ?? alert.engine}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{alert.reason}</p>
                                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                                {typeof alert.visibility_delta === 'number' && (
                                                    <DeltaBadge delta={alert.visibility_delta} />
                                                )}
                                                {(alert.related_domains ?? []).slice(0, 3).map((domain) => (
                                                    <span
                                                        key={domain}
                                                        className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                                    >
                                                        {domain}
                                                    </span>
                                                ))}
                                            </div>
                                            {alert.article_id && (
                                                <Link
                                                    href={route('articles.show', { article: alert.article_id })}
                                                    className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    Open related article
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </Link>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-indigo-50 p-2.5 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <TrendingUp className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Biggest movers</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        The prompt and engine combinations with the largest visibility swing versus the previous run.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.movers.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No mover history yet.</p>
                                ) : (
                                    aiVisibility.movers.map((prompt) => (
                                        <div key={`${prompt.id}-${prompt.engine}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium text-surface-900 dark:text-white">{prompt.topic ?? prompt.prompt}</p>
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                        {ENGINE_LABELS[prompt.engine] ?? prompt.engine}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <DeltaBadge delta={prompt.visibility_delta ?? 0} />
                                                    <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs font-semibold text-surface-700 dark:bg-surface-800 dark:text-surface-200">
                                                        {prompt.visibility_score}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                                                {prompt.previous_visibility_score !== null && prompt.previous_visibility_score !== undefined && (
                                                    <span>
                                                        Prev {prompt.previous_visibility_score}
                                                    </span>
                                                )}
                                                {prompt.article_id && (
                                                    <Link href={route('articles.show', { article: prompt.article_id })} className="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                        {prompt.article_title ?? 'Open article'}
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Visibility trend</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Average prompt visibility and covered checks over the last runs.
                                    </p>
                                </div>
                            </div>

                            {aiVisibility.trend.length === 0 ? (
                                <p className="mt-6 text-sm text-surface-500 dark:text-surface-400">No trend data yet.</p>
                            ) : (
                                <div className="mt-6 h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart data={aiVisibility.trend}>
                                            <defs>
                                                <linearGradient id="aiVisibilityScore" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.18} />
                                                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#e7e5e4" className="dark:stroke-surface-800" />
                                            <XAxis
                                                dataKey="date"
                                                tickFormatter={(value) => format(new Date(value), 'd MMM', { locale: fr })}
                                                tick={{ fontSize: 12, fill: '#78716c' }}
                                                stroke="#e7e5e4"
                                            />
                                            <YAxis tick={{ fontSize: 12, fill: '#78716c' }} stroke="#e7e5e4" />
                                            <Tooltip
                                                content={({ active, payload, label }) => {
                                                    if (!active || !payload?.length || !label) {
                                                        return null;
                                                    }

                                                    return (
                                                        <div className="rounded-xl border border-surface-200 bg-white p-3 shadow-lg dark:border-surface-700 dark:bg-surface-800">
                                                            <p className="text-sm font-medium text-surface-900 dark:text-white">
                                                                {format(new Date(label as string), 'd MMMM yyyy', { locale: fr })}
                                                            </p>
                                                            {payload.map((entry, index) => (
                                                                <p key={index} className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                                                                    <span className="font-medium">{entry.name}:</span> {entry.value}
                                                                </p>
                                                            ))}
                                                        </div>
                                                    );
                                                }}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="avg_visibility_score"
                                                stroke="#6366f1"
                                                fill="url(#aiVisibilityScore)"
                                                strokeWidth={2.5}
                                                name="Average score"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </section>

                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Engine coverage</h2>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Coverage split by answer engine and prompt count.
                            </p>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.engines.map((engine) => (
                                    <div key={engine.engine} className="rounded-xl bg-surface-50/80 p-4 dark:bg-surface-800/70">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-sm font-medium text-surface-900 dark:text-white">{ENGINE_LABELS[engine.engine] ?? engine.engine}</p>
                                            <span className="text-sm font-semibold text-surface-700 dark:text-surface-200">
                                                {engine.avg_visibility_score.toFixed(1)}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-xs text-surface-500 dark:text-surface-400">
                                            {engine.covered_prompts}/{engine.total_prompts} prompts covered
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <h3 className="mt-6 text-sm font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                Intent mix
                            </h3>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                {aiVisibility.intents.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No intent breakdown yet.</p>
                                ) : (
                                    aiVisibility.intents.map((intent) => (
                                        <div key={intent.intent} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <p className="text-sm font-medium capitalize text-surface-900 dark:text-white">
                                                {intent.intent.replace('_', ' ')}
                                            </p>
                                            <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">
                                                {intent.avg_visibility_score.toFixed(1)}
                                            </p>
                                            <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                {intent.covered_prompts}/{intent.total_prompts} covered
                                            </p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-amber-50 p-2.5 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                                    <Sparkles className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Opportunity queue</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        The highest-value content actions inferred from weak prompt coverage.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.recommendations.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No recommendations yet.</p>
                                ) : (
                                    aiVisibility.recommendations.map((recommendation, index) => (
                                        <div key={`${recommendation.prompt_id}-${index}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">{recommendation.title}</p>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <SeverityBadge severity={recommendation.severity} />
                                                    <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                        {recommendation.type.replace('_', ' ')}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{recommendation.reason}</p>
                                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    {ENGINE_LABELS[recommendation.engine] ?? recommendation.engine}
                                                </span>
                                                <DeltaBadge delta={recommendation.visibility_delta} />
                                                <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    Score {recommendation.visibility_score}
                                                </span>
                                                {(recommendation.related_domains ?? []).slice(0, 2).map((domain) => (
                                                    <span
                                                        key={domain}
                                                        className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                                    >
                                                        {domain}
                                                    </span>
                                                ))}
                                            </div>
                                            {recommendation.article_id && (
                                                <Link
                                                    href={route('articles.show', { article: recommendation.article_id })}
                                                    className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    {recommendation.action_label ?? 'Open related article'}
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </Link>
                                            )}
                                            {!recommendation.article_id && (
                                                <p className="mt-3 text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                                    {recommendation.action_label ?? 'Plan article'}
                                                </p>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-red-50 p-2.5 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                                    <TrendingDown className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Refresh queue</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Articles already flagged by analytics, AI visibility, or decay signals.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {refreshRecommendations.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No refresh candidates yet.</p>
                                ) : (
                                    refreshRecommendations.map((recommendation) => (
                                        <div key={recommendation.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">
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
                                                <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    {recommendation.trigger_type.replace('_', ' ')}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{recommendation.reason}</p>
                                            {recommendation.article_id && (
                                                <Link
                                                    href={route('articles.show', { article: recommendation.article_id })}
                                                    className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    Review article
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </Link>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                        <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-indigo-50 p-2.5 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <Target className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Weakest prompts</h2>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        Prompt and engine combinations with the lowest current visibility score.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                {aiVisibility.weakest_prompts.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No weak prompts yet.</p>
                                ) : (
                                    aiVisibility.weakest_prompts.map((prompt) => (
                                        <div key={`${prompt.id}-${prompt.engine}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white">{prompt.topic ?? prompt.prompt}</p>
                                                <div className="flex items-center gap-2">
                                                    <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                        {ENGINE_LABELS[prompt.engine] ?? prompt.engine}
                                                    </span>
                                                    <DeltaBadge delta={prompt.visibility_delta ?? 0} />
                                                    <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300">
                                                        {prompt.visibility_score}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">{prompt.prompt}</p>
                                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                                                {prompt.source_type && (
                                                    <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                        {prompt.source_type.replace('_', ' ')}
                                                    </span>
                                                )}
                                                {prompt.article_id && (
                                                    <Link
                                                        href={route('articles.show', { article: prompt.article_id })}
                                                        className="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                    >
                                                        {prompt.article_title ?? 'Open article'}
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>

                        <div className="space-y-6">
                            <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-xl bg-primary-50 p-2.5 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Top competitors</h2>
                                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                            Domains most often surfacing alongside or instead of your coverage.
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-5 space-y-3">
                                    {aiVisibility.competitors.length === 0 ? (
                                        <p className="text-sm text-surface-500 dark:text-surface-400">No competitor mentions detected yet.</p>
                                    ) : (
                                        aiVisibility.competitors.map((competitor) => (
                                            <div key={competitor.domain} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                <div className="flex items-center justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-medium text-surface-900 dark:text-white">{competitor.brand_name}</p>
                                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{competitor.domain}</p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-sm font-semibold text-surface-900 dark:text-white">{competitor.mentions} mentions</p>
                                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                            Avg position {competitor.average_position.toFixed(1)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </section>

                            <section className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-xl bg-surface-100 p-2.5 text-surface-700 dark:bg-surface-800 dark:text-surface-200">
                                        <ExternalLink className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">Top cited sources</h2>
                                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                            Sources most frequently used in the latest prompt checks.
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-5 space-y-3">
                                    {aiVisibility.sources.length === 0 ? (
                                        <p className="text-sm text-surface-500 dark:text-surface-400">No sources detected yet.</p>
                                    ) : (
                                        aiVisibility.sources.map((source, index) => (
                                            <div key={`${source.source_url ?? source.source_title ?? 'source'}-${index}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium text-surface-900 dark:text-white">
                                                            {source.source_title ?? source.source_domain ?? 'Untitled source'}
                                                        </p>
                                                        <p className="mt-1 truncate text-xs text-surface-500 dark:text-surface-400">
                                                            {source.source_domain ?? source.source_url ?? 'No URL'}
                                                        </p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-sm font-semibold text-surface-900 dark:text-white">{source.mentions} mentions</p>
                                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                            Avg position {source.average_position.toFixed(1)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            )}
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
    tone: 'indigo' | 'emerald' | 'amber' | 'slate' | 'rose';
}) {
    const tones = {
        indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        slate: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200',
        rose: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
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

function SeverityBadge({ severity }: { severity: 'low' | 'medium' | 'high' }) {
    return (
        <span
            className={clsx(
                'rounded-full px-2.5 py-1 text-xs font-medium',
                severity === 'high'
                    ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                    : severity === 'medium'
                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            )}
        >
            {severity}
        </span>
    );
}

function DeltaBadge({ delta }: { delta: number }) {
    return (
        <span
            className={clsx(
                'rounded-full px-2.5 py-1 text-xs font-semibold',
                delta <= -6
                    ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                    : delta >= 6
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
            )}
        >
            {formatSignedNumber(delta)}
        </span>
    );
}

function formatSignedNumber(value: number) {
    if (value > 0) {
        return `+${value.toFixed(1)}`;
    }

    return value.toFixed(1);
}
