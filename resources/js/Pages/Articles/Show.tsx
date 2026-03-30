import AppLayout from '@/Layouts/AppLayout';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Article, Integration, PageProps, TeamMember } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import { format } from 'date-fns';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    ClipboardCheck,
    Copy,
    Download,
    Edit,
    ExternalLink,
    FileText,
    Link2,
    MessageSquare,
    Clock,
    Send,
    ShieldCheck,
    Sparkles,
    Trash2,
    UserRound,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface ArticleShowProps extends PageProps {
    article: Article;
    integrations: Integration[];
    teamMembers: TeamMember[];
    currentUserRole: 'owner' | 'admin' | 'member' | null;
}

export default function ArticleShow({ article, integrations, teamMembers, currentUserRole, auth }: ArticleShowProps) {
    const { t } = useTranslations();
    const [copied, setCopied] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [selectedIntegration, setSelectedIntegration] = useState<number | null>(null);
    const hostedAuthors = article.site?.hosted_authors ?? [];
    const hostedCategories = article.site?.hosted_categories ?? [];
    const hostedTags = article.site?.hosted_tags ?? [];
    const isHostedArticle = article.site?.mode === 'hosted';

    const commentForm = useForm({
        body: '',
    });

    const assignmentForm = useForm<{
        user_id: number | '';
        role: 'writer' | 'reviewer' | 'approver';
    }>({
        user_id: teamMembers[0]?.id ?? '',
        role: 'reviewer',
    });

    const approvalForm = useForm<{
        requested_to: number | '';
        decision_note: string;
    }>({
        requested_to: teamMembers.find((member) => member.id !== auth.user.id)?.id ?? '',
        decision_note: '',
    });

    const hostedMetadataForm = useForm<{
        hosted_author_id: number | '';
        hosted_category_id: number | '';
        hosted_tag_ids: number[];
    }>({
        hosted_author_id: article.hosted_author?.id ?? '',
        hosted_category_id: article.hosted_category?.id ?? '',
        hosted_tag_ids: article.hosted_tags?.map((tag) => tag.id) ?? [],
    });

    const sanitizedContent = article.content
        ? DOMPurify.sanitize(article.content, {
              ALLOWED_TAGS: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'ol', 'li', 'strong', 'em', 'code', 'pre', 'blockquote', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br', 'hr', 'span', 'div'],
              ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel'],
          })
        : '';

    const canApprove = article.permissions?.approve ?? false;
    const canPublish = article.permissions?.publish ?? false;
    const canComment = article.permissions?.comment ?? false;
    const canAssign = article.permissions?.assign ?? false;
    const canRequestApproval = article.permissions?.request_approval ?? false;
    const canUpdateArticle = article.permissions?.update ?? false;
    const pendingApprovalRequest = article.approval_requests?.find((request) => request.status === 'pending');
    const activeRefreshRecommendations = article.refresh_recommendations?.filter((recommendation) => recommendation.status !== 'dismissed') ?? [];

    const subScores = article.score
        ? [
              { label: 'SEO', value: article.score.seo_score },
              { label: 'Brand', value: article.score.brand_fit_score },
              { label: 'Sources', value: article.score.citation_score },
              { label: 'Links', value: article.score.internal_link_score },
              { label: 'Facts', value: article.score.fact_confidence_score },
          ]
        : [];

    const handleCopyContent = async () => {
        if (!article.content) {
            return;
        }

        await navigator.clipboard.writeText(article.content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handlePublish = () => {
        if (!selectedIntegration) {
            return;
        }

        setPublishing(true);
        router.post(
            route('articles.publish', { article: article.id }),
            { integration_id: selectedIntegration },
            {
                onFinish: () => setPublishing(false),
            },
        );
    };

    const handleApprove = () => {
        router.post(route('articles.approve', { article: article.id }));
    };

    const handleCommentSubmit = () => {
        commentForm.post(route('articles.comments.store', { article: article.id }), {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        });
    };

    const handleResolveComment = (commentId: number) => {
        router.patch(route('articles.comments.resolve', { article: article.id, editorialComment: commentId }), {}, { preserveScroll: true });
    };

    const handleAssignmentSubmit = () => {
        assignmentForm.post(route('articles.assignments.store', { article: article.id }), {
            preserveScroll: true,
        });
    };

    const handleRemoveAssignment = (assignmentId: number) => {
        router.delete(route('articles.assignments.destroy', { article: article.id, articleAssignment: assignmentId }), {
            preserveScroll: true,
        });
    };

    const handleRequestApproval = () => {
        approvalForm.post(route('articles.approval-requests.store', { article: article.id }), {
            preserveScroll: true,
            onSuccess: () => approvalForm.reset('decision_note'),
        });
    };

    const handleApproveRequest = (approvalRequestId: number) => {
        router.post(
            route('articles.approval-requests.approve', {
                article: article.id,
                approvalRequest: approvalRequestId,
            }),
            {},
            { preserveScroll: true },
        );
    };

    const handleRejectRequest = (approvalRequestId: number) => {
        router.post(
            route('articles.approval-requests.reject', {
                article: article.id,
                approvalRequest: approvalRequestId,
            }),
            {},
            { preserveScroll: true },
        );
    };

    const handleRefreshAction = (recommendationId: number, action: 'accept' | 'dismiss' | 'execute' | 'apply') => {
        router.post(route(`refresh-recommendations.${action}`, { refreshRecommendation: recommendationId }), {}, { preserveScroll: true });
    };

    const handleHostedTagToggle = (tagId: number) => {
        hostedMetadataForm.setData(
            'hosted_tag_ids',
            hostedMetadataForm.data.hosted_tag_ids.includes(tagId)
                ? hostedMetadataForm.data.hosted_tag_ids.filter((value) => value !== tagId)
                : [...hostedMetadataForm.data.hosted_tag_ids, tagId],
        );
    };

    const handleHostedMetadataSave = () => {
        hostedMetadataForm.patch(route('articles.hosted-metadata.update', { article: article.id }), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('articles.index')}
                            className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">{article.title}</h1>
                            <div className="mt-1 flex items-center gap-3 text-sm text-gray-500">
                                <span className="flex items-center gap-1">
                                    <FileText className="h-4 w-4" />
                                    {article.word_count.toLocaleString()} words
                                </span>
                                <span className="flex items-center gap-1">
                                    <Clock className="h-4 w-4" />
                                    {format(new Date(article.created_at), 'MMM d, yyyy')}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant={getStatusVariant(article.status)} size="md">
                            {article.status}
                        </Badge>
                        {article.published_url && (
                            <a
                                href={article.published_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1 text-sm text-indigo-600 hover:underline"
                            >
                                View Live <ExternalLink className="h-4 w-4" />
                            </a>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={article.title} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <div className="flex items-center justify-between border-b border-gray-200 pb-4">
                            <h2 className="text-lg font-semibold text-gray-900">{t?.articles?.show?.content ?? 'Content'}</h2>
                            <div className="flex gap-2">
                                <Button variant="secondary" size="sm" icon={copied ? Check : Copy} onClick={handleCopyContent}>
                                    {copied ? (t?.articles?.show?.copied ?? 'Copied!') : (t?.articles?.show?.copy ?? 'Copy')}
                                </Button>
                                {article.permissions?.update && (
                                    <Button as="link" href={route('articles.edit', { article: article.id })} variant="secondary" size="sm" icon={Edit}>
                                        {t?.articles?.show?.edit ?? 'Edit'}
                                    </Button>
                                )}
                                <Button as="link" href={route('articles.export-html', { article: article.id })} variant="secondary" size="sm" icon={Download}>
                                    Download HTML
                                </Button>
                            </div>
                        </div>
                        <div className="mt-4">
                            {sanitizedContent ? (
                                <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: sanitizedContent }} />
                            ) : (
                                <p className="text-gray-500">{t?.articles?.show?.noContent ?? 'No content available.'}</p>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Editorial Comments" />
                        <div className="mt-4 space-y-4">
                            {canComment && (
                                <div className="space-y-3 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                    <textarea
                                        rows={4}
                                        value={commentForm.data.body}
                                        onChange={(event) => commentForm.setData('body', event.target.value)}
                                        className="w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        placeholder="Leave an editorial note, request changes, or capture a decision."
                                    />
                                    {commentForm.errors.body && <p className="text-sm text-red-600 dark:text-red-400">{commentForm.errors.body}</p>}
                                    <div className="flex justify-end">
                                        <Button onClick={handleCommentSubmit} loading={commentForm.processing} icon={MessageSquare}>
                                            Add comment
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {article.editorial_comments && article.editorial_comments.length > 0 ? (
                                article.editorial_comments.map((comment) => {
                                    const canResolveComment = comment.user?.id === auth.user.id || canApprove;

                                    return (
                                        <div
                                            key={comment.id}
                                            className={`rounded-xl border p-4 ${
                                                comment.resolved_at
                                                    ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/10'
                                                    : 'border-surface-200 dark:border-surface-800'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-medium text-surface-900 dark:text-white">{comment.user?.name ?? 'Unknown user'}</p>
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                        {format(new Date(comment.created_at), 'MMM d, yyyy HH:mm')}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {comment.resolved_at && <Badge variant="success">Resolved</Badge>}
                                                    {canResolveComment && (
                                                        <Button variant="secondary" size="sm" onClick={() => handleResolveComment(comment.id)}>
                                                            {comment.resolved_at ? 'Reopen' : 'Resolve'}
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="mt-3 whitespace-pre-wrap text-sm text-surface-700 dark:text-surface-300">{comment.body}</p>
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-sm text-surface-500 dark:text-surface-400">No editorial comments yet.</p>
                            )}
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    {article.score && (
                        <Card>
                            <CardHeader title="Review Readiness" />
                            <div className="mt-4 space-y-4">
                                <div className="flex items-end justify-between gap-4">
                                    <div>
                                        <p className="text-4xl font-bold text-surface-900 dark:text-white">{article.score.readiness_score}/100</p>
                                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">Overall publish readiness score</p>
                                    </div>
                                    <Badge variant={article.score.readiness_score >= 80 ? 'success' : article.score.readiness_score >= 60 ? 'warning' : 'danger'}>
                                        {article.score.readiness_score >= 80 ? 'Strong' : article.score.readiness_score >= 60 ? 'Needs review' : 'At risk'}
                                    </Badge>
                                </div>

                                <div className="space-y-2">
                                    {subScores.map((item) => (
                                        <div key={item.label} className="flex items-center justify-between text-sm">
                                            <span className="text-surface-600 dark:text-surface-400">{item.label}</span>
                                            <span className="font-medium text-surface-900 dark:text-white">{item.value}/100</span>
                                        </div>
                                    ))}
                                </div>

                                {article.score.warnings.length > 0 && (
                                    <div className="rounded-lg bg-amber-50 p-4 dark:bg-amber-500/10">
                                        <div className="mb-2 flex items-center gap-2 text-amber-700 dark:text-amber-300">
                                            <AlertTriangle className="h-4 w-4" />
                                            <span className="text-sm font-medium">Warnings</span>
                                        </div>
                                        <div className="space-y-2">
                                            {article.score.warnings.map((warning, index) => (
                                                <p key={index} className="text-sm text-amber-800 dark:text-amber-200">
                                                    {warning}
                                                </p>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-surface-900 dark:text-white">Checklist</p>
                                    {article.score.checklist.map((item, index) => (
                                        <div key={index} className="flex items-start gap-2 text-sm">
                                            <span className={item.done ? 'text-green-600 dark:text-green-400' : 'text-surface-400'}>
                                                <Check className="mt-0.5 h-4 w-4" />
                                            </span>
                                            <span className={item.done ? 'text-surface-700 dark:text-surface-300' : 'text-surface-500 dark:text-surface-400'}>{item.label}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </Card>
                    )}

                    {article.business_attribution && (
                        <Card>
                            <CardHeader title="Business Attribution" />
                            <div className="mt-4 space-y-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <MetricPill label="Traffic value" value={formatCurrency(article.business_attribution.totals.traffic_value)} />
                                    <MetricPill
                                        label="Estimated conversions"
                                        value={`${article.business_attribution.totals.estimated_conversions}`}
                                        caption={article.business_attribution.totals.conversion_source === 'tracked' ? 'GA4 tracked' : 'Modeled fallback'}
                                    />
                                    <MetricPill
                                        label="ROI"
                                        value={article.business_attribution.roi !== null && article.business_attribution.roi !== undefined ? `${article.business_attribution.roi.toFixed(0)}%` : '—'}
                                        caption={article.business_attribution.performance_label.replace(/_/g, ' ')}
                                    />
                                    <MetricPill label="Sessions" value={`${article.business_attribution.totals.sessions}`} />
                                </div>

                                <div className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                    <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                        Last {article.business_attribution.lookback_days} days vs previous window
                                    </p>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <MetricPill label="Clicks delta" value={formatSignedNumber(article.business_attribution.deltas.clicks.absolute)} />
                                        <MetricPill label="Sessions delta" value={formatSignedNumber(article.business_attribution.deltas.sessions.absolute)} />
                                        <MetricPill label="Conversion delta" value={formatSignedFloat(article.business_attribution.deltas.estimated_conversions.absolute)} />
                                        <MetricPill label="Value delta" value={formatCurrency(article.business_attribution.deltas.traffic_value.absolute)} />
                                    </div>
                                </div>
                            </div>
                        </Card>
                    )}

                    {isHostedArticle && (
                        <Card>
                            <CardHeader title="Hosted metadata" />
                            <div className="mt-4 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Author</label>
                                    <select
                                        value={hostedMetadataForm.data.hosted_author_id}
                                        onChange={(event) => hostedMetadataForm.setData('hosted_author_id', Number(event.target.value) || '')}
                                        disabled={!canUpdateArticle}
                                        className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                        <option value="">No author</option>
                                        {hostedAuthors.map((author) => (
                                            <option key={author.id} value={author.id}>
                                                {author.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Category</label>
                                    <select
                                        value={hostedMetadataForm.data.hosted_category_id}
                                        onChange={(event) => hostedMetadataForm.setData('hosted_category_id', Number(event.target.value) || '')}
                                        disabled={!canUpdateArticle}
                                        className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                        <option value="">No category</option>
                                        {hostedCategories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <p className="text-sm font-medium text-surface-700 dark:text-surface-300">Tags</p>
                                    {hostedTags.length > 0 ? (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {hostedTags.map((tag) => {
                                                const checked = hostedMetadataForm.data.hosted_tag_ids.includes(tag.id);

                                                return (
                                                    <label
                                                        key={tag.id}
                                                        className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-2 text-sm ${
                                                            checked
                                                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-500/10 dark:text-primary-200'
                                                                : 'border-surface-200 text-surface-600 dark:border-surface-700 dark:text-surface-300'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            onChange={() => handleHostedTagToggle(tag.id)}
                                                            disabled={!canUpdateArticle}
                                                            className="sr-only"
                                                        />
                                                        {tag.name}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">No hosted tags defined on the site yet.</p>
                                    )}
                                </div>

                                <div className="rounded-xl bg-surface-50 p-3 text-sm text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                    These values feed hosted author/category/tag archives, schema markup and exported pages.
                                </div>

                                {canUpdateArticle && (
                                    <div className="flex justify-end">
                                        <Button onClick={handleHostedMetadataSave} loading={hostedMetadataForm.processing}>
                                            Save hosted metadata
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </Card>
                    )}

                    <Card>
                        <CardHeader title="Workflow" />
                        <div className="mt-4 space-y-4">
                            <div className="rounded-xl bg-surface-50 p-3 text-sm text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                Your role: <span className="font-medium">{currentUserRole ?? 'member'}</span>
                            </div>

                            {canAssign && (
                                <div className="space-y-3 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <select
                                            value={assignmentForm.data.role}
                                            onChange={(event) => assignmentForm.setData('role', event.target.value as 'writer' | 'reviewer' | 'approver')}
                                            className="rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        >
                                            <option value="writer">Writer</option>
                                            <option value="reviewer">Reviewer</option>
                                            <option value="approver">Approver</option>
                                        </select>
                                        <select
                                            value={assignmentForm.data.user_id}
                                            onChange={(event) => assignmentForm.setData('user_id', Number(event.target.value) || '')}
                                            className="rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        >
                                            <option value="">Select teammate</option>
                                            {teamMembers.map((member) => (
                                                <option key={member.id} value={member.id}>
                                                    {member.name} ({member.role})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    {(assignmentForm.errors.user_id || assignmentForm.errors.role) && (
                                        <p className="text-sm text-red-600 dark:text-red-400">{assignmentForm.errors.user_id ?? assignmentForm.errors.role}</p>
                                    )}
                                    <div className="flex justify-end">
                                        <Button onClick={handleAssignmentSubmit} loading={assignmentForm.processing} icon={UserRound}>
                                            Save assignment
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {article.assignments && article.assignments.length > 0 ? (
                                article.assignments.map((assignment) => (
                                    <div key={assignment.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-surface-900 dark:text-white">
                                                    {assignment.user?.name ?? 'Unassigned'}
                                                </p>
                                                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                                    {assignment.role}
                                                </p>
                                            </div>
                                            {canAssign && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveAssignment(assignment.id)}
                                                    className="rounded-lg p-2 text-surface-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-surface-500 dark:text-surface-400">No assignments yet.</p>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Refresh Autopilot" />
                        <div className="mt-4 space-y-4">
                            {activeRefreshRecommendations.length === 0 ? (
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    No refresh recommendations are open on this article yet.
                                </p>
                            ) : (
                                activeRefreshRecommendations.map((recommendation) => (
                                    <div key={recommendation.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Sparkles className="h-4 w-4 text-amber-500" />
                                                    <p className="font-medium text-surface-900 dark:text-white">
                                                        {recommendation.trigger_type.replace(/_/g, ' ')}
                                                    </p>
                                                    <Badge variant={recommendation.severity === 'high' ? 'danger' : recommendation.severity === 'medium' ? 'warning' : 'secondary'}>
                                                        {recommendation.severity}
                                                    </Badge>
                                                </div>
                                                <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">{recommendation.reason}</p>
                                            </div>
                                        </div>

                                        {recommendation.recommended_actions.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {recommendation.recommended_actions.map((action, index) => (
                                                    <span key={index} className="rounded-full bg-surface-50 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                        {action}
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-2">
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
                                            {article.latest_refresh_run && (
                                                <Button variant="secondary" size="sm" onClick={() => handleRefreshAction(recommendation.id, 'apply')}>
                                                    Push to review
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}

                            {article.latest_refresh_run && (
                                <div className="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                                    <p className="font-medium text-surface-900 dark:text-white">Latest refresh draft</p>
                                    <p className="mt-1 text-sm text-surface-600 dark:text-surface-400 whitespace-pre-wrap">
                                        {article.latest_refresh_run.summary}
                                    </p>
                                    {article.latest_refresh_run.new_score_snapshot?.readiness_score !== undefined && (
                                        <p className="mt-3 text-sm text-surface-700 dark:text-surface-300">
                                            Estimated readiness after refresh: {article.latest_refresh_run.new_score_snapshot.readiness_score}/100
                                        </p>
                                    )}
                                    {article.latest_refresh_run.metadata?.diff?.sections_added && article.latest_refresh_run.metadata.diff.sections_added.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {article.latest_refresh_run.metadata.diff.sections_added.map((section) => (
                                                <span key={section} className="rounded-full bg-white/80 px-3 py-1 text-xs text-surface-600 dark:bg-surface-900/60 dark:text-surface-300">
                                                    {section}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    {article.latest_refresh_run.metadata?.draft_meta_title && (
                                        <div className="mt-3 rounded-lg bg-white/80 p-3 text-sm dark:bg-surface-900/60">
                                            <p className="font-medium text-surface-900 dark:text-white">Draft meta title</p>
                                            <p className="mt-1 text-surface-600 dark:text-surface-400">
                                                {article.latest_refresh_run.metadata.draft_meta_title}
                                            </p>
                                        </div>
                                    )}
                                    {article.latest_refresh_run.metadata?.business_case && (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            <MetricPill label="Value now" value={formatCurrency(article.latest_refresh_run.metadata.business_case.traffic_value)} />
                                            <MetricPill label="Value delta" value={formatCurrency(article.latest_refresh_run.metadata.business_case.traffic_value_delta)} />
                                            <MetricPill label="Est. conversions" value={`${article.latest_refresh_run.metadata.business_case.estimated_conversions ?? '—'}`} />
                                            <MetricPill
                                                label="ROI now"
                                                value={article.latest_refresh_run.metadata.business_case.roi !== null && article.latest_refresh_run.metadata.business_case.roi !== undefined
                                                    ? `${article.latest_refresh_run.metadata.business_case.roi.toFixed(0)}%`
                                                    : '—'}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Approval Flow" />
                        <div className="mt-4 space-y-4">
                            {canRequestApproval && !pendingApprovalRequest && (
                                <div className="space-y-3 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                    <select
                                        value={approvalForm.data.requested_to}
                                        onChange={(event) => approvalForm.setData('requested_to', Number(event.target.value) || '')}
                                        className="w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                        <option value="">Select approver</option>
                                        {teamMembers
                                            .filter((member) => member.id !== auth.user.id)
                                            .map((member) => (
                                                <option key={member.id} value={member.id}>
                                                    {member.name} ({member.role})
                                                </option>
                                            ))}
                                    </select>
                                    <textarea
                                        rows={3}
                                        value={approvalForm.data.decision_note}
                                        onChange={(event) => approvalForm.setData('decision_note', event.target.value)}
                                        className="w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        placeholder="Optional note for the approver"
                                    />
                                    {(approvalForm.errors.requested_to || approvalForm.errors.decision_note) && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
                                            {approvalForm.errors.requested_to ?? approvalForm.errors.decision_note}
                                        </p>
                                    )}
                                    <div className="flex justify-end">
                                        <Button onClick={handleRequestApproval} loading={approvalForm.processing} icon={ClipboardCheck}>
                                            Request approval
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {pendingApprovalRequest && (
                                <div className="rounded-xl border border-primary-200 bg-primary-50/70 p-4 dark:border-primary-500/20 dark:bg-primary-500/10">
                                    <p className="font-medium text-surface-900 dark:text-white">Pending approval</p>
                                    <p className="mt-1 text-sm text-surface-600 dark:text-surface-400">
                                        Waiting on {pendingApprovalRequest.requested_to_user?.name ?? 'Unknown approver'}
                                    </p>
                                </div>
                            )}

                            {article.approval_requests && article.approval_requests.length > 0 ? (
                                article.approval_requests.map((request) => {
                                    const canDecide = request.status === 'pending' && (request.requested_to === auth.user.id || canApprove);

                                    return (
                                        <div key={request.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium text-surface-900 dark:text-white">
                                                            {request.requested_by_user?.name ?? 'Unknown'} {'->'} {request.requested_to_user?.name ?? 'Unknown'}
                                                        </p>
                                                        <Badge variant={request.status === 'approved' ? 'success' : request.status === 'rejected' ? 'danger' : 'secondary'}>
                                                            {request.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                        Requested {format(new Date(request.created_at), 'MMM d, yyyy HH:mm')}
                                                    </p>
                                                </div>
                                            </div>

                                            {request.decision_note && (
                                                <p className="mt-3 text-sm text-surface-700 dark:text-surface-300">{request.decision_note}</p>
                                            )}

                                            {canDecide && (
                                                <div className="mt-4 flex flex-wrap gap-2">
                                                    <Button onClick={() => handleApproveRequest(request.id)} size="sm" icon={ShieldCheck}>
                                                        Approve
                                                    </Button>
                                                    <Button onClick={() => handleRejectRequest(request.id)} variant="secondary" size="sm" icon={XCircle}>
                                                        Reject
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-sm text-surface-500 dark:text-surface-400">No approval activity yet.</p>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Activity Timeline" />
                        <div className="mt-4 space-y-3">
                            {article.activity_timeline && article.activity_timeline.length > 0 ? (
                                article.activity_timeline.map((event, index) => (
                                    <div key={`${event.type}-${event.created_at}-${index}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Clock className="h-4 w-4 text-surface-400" />
                                                    <p className="font-medium text-surface-900 dark:text-white">{event.title}</p>
                                                </div>
                                                <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                    {event.actor?.name ? `${event.actor.name} · ` : ''}
                                                    {event.created_at ? format(new Date(event.created_at), 'MMM d, yyyy HH:mm') : 'Unknown time'}
                                                </p>
                                            </div>
                                            <Badge variant="secondary">{event.type}</Badge>
                                        </div>
                                        {event.body && (
                                            <p className="mt-3 whitespace-pre-wrap text-sm text-surface-700 dark:text-surface-300">{event.body}</p>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-surface-500 dark:text-surface-400">No workflow activity yet.</p>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title={t?.articles?.show?.seoMeta ?? 'SEO Meta'} />
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.metaTitle ?? 'Meta Title'}
                                </label>
                                <p className="mt-1 text-sm text-gray-900">{article.meta_title || article.title}</p>
                                <p className="mt-1 text-xs text-gray-400">{(article.meta_title || article.title).length}/60 {t?.articles?.show?.characters ?? 'characters'}</p>
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.metaDescription ?? 'Meta Description'}
                                </label>
                                <p className="mt-1 text-sm text-gray-900">
                                    {article.meta_description || article.excerpt || (t?.articles?.show?.noDescription ?? 'No description')}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">{(article.meta_description || article.excerpt || '').length}/160 {t?.articles?.show?.characters ?? 'characters'}</p>
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.slug ?? 'Slug'}
                                </label>
                                <p className="mt-1 text-sm font-mono text-gray-900">{article.slug}</p>
                            </div>
                        </div>
                    </Card>

                    {article.status !== 'published' && (
                        <Card>
                            <CardHeader title={t?.articles?.show?.actions ?? 'Actions'} />
                            <div className="mt-4 space-y-4">
                                {canApprove && (article.status === 'draft' || article.status === 'review') ? (
                                    <Button onClick={handleApprove} variant="secondary" className="w-full">
                                        {t?.articles?.show?.approveForPublishing ?? 'Approve for Publishing'}
                                    </Button>
                                ) : null}

                                {canPublish && article.status === 'approved' && integrations.length > 0 && (
                                    <>
                                        <div>
                                            <label className="text-sm font-medium text-gray-700">
                                                {t?.articles?.show?.selectIntegration ?? 'Select Integration'}
                                            </label>
                                            <select
                                                value={selectedIntegration || ''}
                                                onChange={(e) => setSelectedIntegration(Number(e.target.value) || null)}
                                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">{t?.articles?.show?.choose ?? 'Choose...'}</option>
                                                {integrations.map((integration) => (
                                                    <option key={integration.id} value={integration.id}>
                                                        {integration.name} ({integration.type})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <Button onClick={handlePublish} loading={publishing} disabled={!selectedIntegration} icon={Send} className="w-full">
                                            {t?.articles?.show?.publishArticle ?? 'Publish Article'}
                                        </Button>
                                    </>
                                )}

                                {canPublish && article.status === 'approved' && integrations.length === 0 && (
                                    <div className="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-700">
                                        <p>
                                            {t?.articles?.show?.noIntegrations ?? 'No integrations configured.'}{' '}
                                            <Link href={route('integrations.create')} className="font-medium underline">
                                                {t?.articles?.show?.addOne ?? 'Add one'}
                                            </Link>{' '}
                                            {t?.articles?.show?.toPublish ?? 'to publish this article.'}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </Card>
                    )}

                    {article.citations && article.citations.length > 0 && (
                        <Card>
                            <CardHeader title="References" />
                            <div className="mt-4 space-y-3">
                                {article.citations.map((citation) => (
                                    <div key={citation.id} className="rounded-lg border border-surface-200 p-3 dark:border-surface-800">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge variant="secondary">{citation.source_type}</Badge>
                                            {citation.domain && <span className="text-xs text-surface-500 dark:text-surface-400">{citation.domain}</span>}
                                        </div>
                                        <p className="text-sm font-medium text-surface-900 dark:text-white">{citation.title}</p>
                                        {citation.excerpt && <p className="mt-1 text-sm text-surface-600 dark:text-surface-400">{citation.excerpt}</p>}
                                        {citation.url && (
                                            <a
                                                href={citation.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-2 inline-flex items-center gap-1 text-sm text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                Open source
                                                <Link2 className="h-3.5 w-3.5" />
                                            </a>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}

                    {article.keyword && (
                        <Card>
                            <CardHeader title={t?.articles?.show?.targetKeyword ?? 'Target Keyword'} />
                            <div className="mt-4">
                                <p className="text-lg font-medium text-indigo-600">"{article.keyword.keyword}"</p>
                                <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                    {article.keyword.volume && (
                                        <div>
                                            <p className="text-gray-500">{t?.articles?.show?.volume ?? 'Volume'}</p>
                                            <p className="font-medium">{article.keyword.volume.toLocaleString()}</p>
                                        </div>
                                    )}
                                    {article.keyword.difficulty !== null && (
                                        <div>
                                            <p className="text-gray-500">{t?.articles?.show?.difficulty ?? 'Difficulty'}</p>
                                            <p className="font-medium">{article.keyword.difficulty}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function MetricPill({
    label,
    value,
    caption,
}: {
    label: string;
    value: string;
    caption?: string;
}) {
    return (
        <div className="rounded-xl bg-surface-50 p-3 dark:bg-surface-800">
            <p className="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400">{label}</p>
            <p className="mt-2 text-lg font-semibold text-surface-900 dark:text-white">{value}</p>
            {caption && <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{caption}</p>}
        </div>
    );
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

function formatSignedNumber(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(0)}`;
}

function formatSignedFloat(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(1)}`;
}
