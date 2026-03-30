import AppLayout from '@/Layouts/AppLayout';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Article, PageProps, PaginatedData, Site } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CheckCircle2, ClipboardCheck, ListTodo, Search, ShieldCheck, UserRound, UserSearch } from 'lucide-react';

interface ReviewQueueProps extends PageProps {
    articles: PaginatedData<Article>;
    scope: 'all' | 'assigned' | 'pending' | 'blocked' | 'refresh_ready' | string;
    sites: Site[];
    filters: {
        site_id?: number | string;
        search?: string;
    };
    stats: {
        all: number;
        assigned: number;
        pending: number;
        requested_by_me: number;
        unassigned: number;
        refresh_ready: number;
        ready: number;
        blocked: number;
    };
}

const scopes = [
    { key: 'all', label: 'All', icon: ListTodo },
    { key: 'assigned', label: 'Assigned to me', icon: UserRound },
    { key: 'pending', label: 'Waiting on me', icon: ClipboardCheck },
    { key: 'requested_by_me', label: 'Requested by me', icon: ShieldCheck },
    { key: 'unassigned', label: 'Unassigned', icon: UserSearch },
    { key: 'refresh_ready', label: 'Refresh ready', icon: ArrowRight },
    { key: 'ready', label: 'Ready', icon: CheckCircle2 },
    { key: 'blocked', label: 'Blocked', icon: AlertTriangle },
] as const;

export default function ReviewQueue({ articles, scope, sites, filters, stats }: ReviewQueueProps) {
    const applyScope = (nextScope: string) => {
        router.get(route('articles.review-queue'), { ...filters, scope: nextScope }, { preserveState: true });
    };

    const updateFilters = (nextFilters: Record<string, string | number | undefined>) => {
        router.get(route('articles.review-queue'), {
            scope,
            ...filters,
            ...nextFilters,
        }, { preserveState: true });
    };

    return (
        <AppLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Review Queue</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Editorial approval flow, assignments, and blocked drafts in one place.
                        </p>
                    </div>
                    <Button as="link" href={route('articles.index')} variant="secondary">
                        Back to articles
                    </Button>
                </div>
            }
        >
            <Head title="Review Queue" />

            <div className="mb-6 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                <select
                    value={filters.site_id || ''}
                    onChange={(event) => updateFilters({ site_id: event.target.value || undefined })}
                    className="rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                >
                    <option value="">All sites</option>
                    {sites.map((site) => (
                        <option key={site.id} value={site.id}>
                            {site.name}
                        </option>
                    ))}
                </select>

                <div className="relative">
                    <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="search"
                        placeholder="Search title or keyword..."
                        value={filters.search || ''}
                        onChange={(event) => updateFilters({ search: event.target.value || undefined })}
                        className="w-full rounded-xl border border-surface-200 bg-white py-3 pl-11 pr-4 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                {scopes.map((item) => {
                    const Icon = item.icon;
                    const value = stats[item.key];

                    return (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => applyScope(item.key)}
                            className={`rounded-2xl border p-5 text-left transition ${
                                scope === item.key
                                    ? 'border-primary-500 bg-primary-50/70 dark:border-primary-400 dark:bg-primary-500/10'
                                    : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900/50'
                            }`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{item.label}</p>
                                    <p className="mt-2 text-2xl font-bold text-surface-900 dark:text-white">{value}</p>
                                </div>
                                <div className="rounded-xl bg-surface-100 p-2.5 text-surface-700 dark:bg-surface-800 dark:text-surface-300">
                                    <Icon className="h-5 w-5" />
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>

            <div className="mt-6 space-y-4">
                {articles.data.length === 0 ? (
                    <Card>
                        <div className="flex flex-col items-center justify-center gap-3 py-10 text-center">
                            <CheckCircle2 className="h-8 w-8 text-emerald-500" />
                            <div>
                                <p className="font-medium text-surface-900 dark:text-white">Nothing in this queue.</p>
                                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                    Try another scope or wait for new articles to reach review.
                                </p>
                            </div>
                        </div>
                    </Card>
                ) : (
                    articles.data.map((article) => {
                        const pendingRequest = article.approval_requests?.find((request) => request.status === 'pending');
                        const readiness = article.score?.readiness_score ?? null;
                        const assignees = article.assignments ?? [];
                        const refreshReady = article.latest_refresh_run?.status === 'review_ready';

                        return (
                            <Card key={article.id}>
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant={getStatusVariant(article.status)}>{article.status}</Badge>
                                            {readiness !== null && (
                                                <Badge variant={readiness >= 80 ? 'success' : readiness >= 60 ? 'warning' : 'danger'}>
                                                    Readiness {readiness}/100
                                                </Badge>
                                            )}
                                            {pendingRequest && (
                                                <Badge variant="secondary">
                                                    Pending approval: {pendingRequest.requested_to_user?.name ?? 'Unknown'}
                                                </Badge>
                                            )}
                                            {refreshReady && (
                                                <Badge variant="secondary">Refresh ready</Badge>
                                            )}
                                            {!pendingRequest && article.status === 'approved' && (
                                                <Badge variant="success">Ready to publish</Badge>
                                            )}
                                        </div>

                                        <div>
                                            <Link
                                                href={route('articles.show', { article: article.id })}
                                                className="text-lg font-semibold text-surface-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                            >
                                                {article.title}
                                            </Link>
                                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                                {article.site?.name} {article.keyword?.keyword ? `· ${article.keyword.keyword}` : ''}
                                            </p>
                                        </div>

                                        {article.score?.warnings?.length ? (
                                            <div className="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                                {article.score.warnings[0]}
                                            </div>
                                        ) : null}

                                        {assignees.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {assignees.map((assignment) => (
                                                    <Badge key={assignment.id} variant="secondary">
                                                        {assignment.role}: {assignment.user?.name ?? 'Unassigned'}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <Button as="link" href={route('articles.show', { article: article.id })} icon={ArrowRight}>
                                        Open article
                                    </Button>
                                </div>
                            </Card>
                        );
                    })
                )}
            </div>

            {articles.meta.last_page > 1 && (
                <div className="mt-6 flex items-center justify-between text-sm text-surface-500 dark:text-surface-400">
                    <span>
                        Page {articles.meta.current_page} of {articles.meta.last_page}
                    </span>
                    <div className="flex items-center gap-2">
                        {articles.links.prev && (
                            <Button as="link" href={articles.links.prev} variant="secondary">
                                Previous
                            </Button>
                        )}
                        {articles.links.next && (
                            <Button as="link" href={articles.links.next} variant="secondary">
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
