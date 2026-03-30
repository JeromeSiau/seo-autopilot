import AppLayout from '@/Layouts/AppLayout';
import { CampaignRun, PageProps, Site } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BarChart3, ExternalLink, Layers3 } from 'lucide-react';
import clsx from 'clsx';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

interface CampaignsIndexProps extends PageProps {
    sites: Site[];
    selectedSite: Site | null;
    selectedStatus: 'all' | 'pending' | 'dispatched' | 'completed' | 'failed';
    campaigns: CampaignRun[];
    summary: {
        total: number;
        pending: number;
        dispatched: number;
        completed: number;
        failed: number;
    };
}

const FILTER_CLASS =
    'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors';

export default function CampaignsIndex({
    sites,
    selectedSite,
    selectedStatus,
    campaigns,
    summary,
}: CampaignsIndexProps) {
    const handleSiteChange = (siteId: string) => {
        router.get(route('campaigns.index'), {
            site_id: siteId || undefined,
            status: selectedStatus,
        });
    };

    const handleStatusChange = (status: string) => {
        router.get(route('campaigns.index'), {
            site_id: selectedSite?.id,
            status,
        });
    };

    return (
        <AppLayout
            header={
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="space-y-3">
                        <Link
                            href={route('keywords.index')}
                            className="inline-flex items-center gap-2 text-sm font-medium text-surface-500 transition-colors hover:text-surface-900 dark:text-surface-400 dark:hover:text-white"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to keywords
                        </Link>
                        <div>
                            <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Campaign Runs</h1>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Review bulk keyword generation runs across sites and track their dispatch outcome.
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
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
            }
        >
            <Head title="Campaign Runs" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Total runs" value={String(summary.total)} tone="slate" />
                    <SummaryCard label="Pending" value={String(summary.pending)} tone="amber" />
                    <SummaryCard label="Dispatched" value={String(summary.dispatched)} tone="indigo" />
                    <SummaryCard label="Failed" value={String(summary.failed)} tone="red" />
                </div>

                {campaigns.length === 0 ? (
                    <div className="rounded-2xl border border-surface-200 bg-white p-12 text-center dark:border-surface-800 dark:bg-surface-900/50">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800">
                            <BarChart3 className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="mt-4 font-display text-lg font-semibold text-surface-900 dark:text-white">No campaign runs in this view</h3>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Launch a bulk keyword generation from the keywords screen to populate campaign history.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {campaigns.map((campaign) => {
                            const keywordCount = typeof campaign.payload?.keyword_count === 'number'
                                ? campaign.payload.keyword_count
                                : campaign.processed_count;

                            return (
                            <div key={campaign.id} className="rounded-2xl border border-surface-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900/50">
                                <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-display text-lg font-semibold text-surface-900 dark:text-white">{campaign.name}</p>
                                            <span className={clsx(
                                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                                campaign.status === 'dispatched' || campaign.status === 'completed'
                                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                                    : campaign.status === 'failed'
                                                        ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                        : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                            )}>
                                                {campaign.status}
                                            </span>
                                            {campaign.site?.name && (
                                                <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    {campaign.site.name}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">
                                            {campaign.input_type} · {campaign.creator?.name ?? 'System'}
                                        </p>
                                        <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                                            {keywordCount} keywords in batch
                                        </p>
                                    </div>

                                    <div className="grid min-w-[320px] gap-3 sm:grid-cols-3">
                                        <MetricCard label="Processed" value={campaign.processed_count} />
                                        <MetricCard label="Queued" value={campaign.succeeded_count} />
                                        <MetricCard label="Failed" value={campaign.failed_count} />
                                    </div>
                                </div>

                                <div className="mt-5 grid gap-3 lg:grid-cols-[1fr_auto]">
                                    <div className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-center gap-2 text-sm font-medium text-surface-900 dark:text-white">
                                            <Layers3 className="h-4 w-4 text-primary-500" />
                                            Run timeline
                                        </div>
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            <TimelineItem
                                                label="Started"
                                                value={campaign.started_at ? format(new Date(campaign.started_at), 'd MMM yyyy HH:mm', { locale: fr }) : 'Not started'}
                                            />
                                            <TimelineItem
                                                label="Completed"
                                                value={campaign.completed_at ? format(new Date(campaign.completed_at), 'd MMM yyyy HH:mm', { locale: fr }) : 'Still running'}
                                            />
                                        </div>
                                    </div>

                                    {campaign.site && (
                                        <div className="flex items-end">
                                            <Link
                                                href={route('keywords.index', { site_id: campaign.site.id })}
                                                className="inline-flex items-center gap-1 rounded-xl border border-surface-200 px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-50 dark:border-surface-700 dark:text-surface-200 dark:hover:bg-surface-800"
                                            >
                                                Open keywords
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )})}
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
    tone: 'slate' | 'amber' | 'indigo' | 'red';
}) {
    const tones = {
        slate: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200',
        amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        red: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
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

function MetricCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl bg-surface-50/80 p-4 dark:bg-surface-800/70">
            <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-surface-900 dark:text-white">{value}</p>
        </div>
    );
}

function TimelineItem({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">{label}</p>
            <p className="mt-2 text-sm font-medium text-surface-900 dark:text-white">{value}</p>
        </div>
    );
}
