import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Edit,
    Send,
    ExternalLink,
    Copy,
    Check,
    FileText,
    Clock,
} from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { Article, Integration, PageProps } from '@/types';
import { format } from 'date-fns';
import { useState } from 'react';
import DOMPurify from 'dompurify';
import { useTranslations } from '@/hooks/useTranslations';

interface ArticleShowProps extends PageProps {
    article: Article;
    integrations: Integration[];
}

export default function ArticleShow({ article, integrations }: ArticleShowProps) {
    const { t } = useTranslations();
    const [copied, setCopied] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [selectedIntegration, setSelectedIntegration] = useState<number | null>(null);

    const sanitizedContent = article.content
        ? DOMPurify.sanitize(article.content, {
              ALLOWED_TAGS: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'ol', 'li', 'strong', 'em', 'code', 'pre', 'blockquote', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br', 'hr', 'span', 'div'],
              ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel'],
          })
        : '';

    const handleCopyContent = async () => {
        if (article.content) {
            await navigator.clipboard.writeText(article.content);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const handlePublish = () => {
        if (!selectedIntegration) return;
        setPublishing(true);
        router.post(
            route('articles.publish', { article: article.id }),
            { integration_id: selectedIntegration },
            {
                onFinish: () => setPublishing(false),
            }
        );
    };

    const handleApprove = () => {
        router.post(route('articles.approve', { article: article.id }));
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
                {/* Main Content */}
                <div className="lg:col-span-2">
                    <Card>
                        <div className="flex items-center justify-between border-b border-gray-200 pb-4">
                            <h2 className="text-lg font-semibold text-gray-900">{t?.articles?.show?.content ?? 'Content'}</h2>
                            <div className="flex gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    icon={copied ? Check : Copy}
                                    onClick={handleCopyContent}
                                >
                                    {copied ? (t?.articles?.show?.copied ?? 'Copied!') : (t?.articles?.show?.copy ?? 'Copy')}
                                </Button>
                                <Button
                                    as="link"
                                    href={route('articles.edit', { article: article.id })}
                                    variant="secondary"
                                    size="sm"
                                    icon={Edit}
                                >
                                    {t?.articles?.show?.edit ?? 'Edit'}
                                </Button>
                            </div>
                        </div>
                        <div className="mt-4">
                            {sanitizedContent ? (
                                <div
                                    className="prose prose-sm max-w-none"
                                    dangerouslySetInnerHTML={{ __html: sanitizedContent }}
                                />
                            ) : (
                                <p className="text-gray-500">{t?.articles?.show?.noContent ?? 'No content available.'}</p>
                            )}
                        </div>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Meta Information */}
                    <Card>
                        <CardHeader title={t?.articles?.show?.seoMeta ?? 'SEO Meta'} />
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.metaTitle ?? 'Meta Title'}
                                </label>
                                <p className="mt-1 text-sm text-gray-900">
                                    {article.meta_title || article.title}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    {(article.meta_title || article.title).length}/60 {t?.articles?.show?.characters ?? 'characters'}
                                </p>
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.metaDescription ?? 'Meta Description'}
                                </label>
                                <p className="mt-1 text-sm text-gray-900">
                                    {article.meta_description || article.excerpt || (t?.articles?.show?.noDescription ?? 'No description')}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    {(article.meta_description || article.excerpt || '').length}/160 {t?.articles?.show?.characters ?? 'characters'}
                                </p>
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {t?.articles?.show?.slug ?? 'Slug'}
                                </label>
                                <p className="mt-1 text-sm font-mono text-gray-900">{article.slug}</p>
                            </div>
                        </div>
                    </Card>

                    {/* Actions */}
                    {article.status !== 'published' && (
                        <Card>
                            <CardHeader title={t?.articles?.show?.actions ?? 'Actions'} />
                            <div className="mt-4 space-y-4">
                                {article.status === 'draft' || article.status === 'review' ? (
                                    <Button
                                        onClick={handleApprove}
                                        variant="secondary"
                                        className="w-full"
                                    >
                                        {t?.articles?.show?.approveForPublishing ?? 'Approve for Publishing'}
                                    </Button>
                                ) : null}

                                {article.status === 'approved' && integrations.length > 0 && (
                                    <>
                                        <div>
                                            <label className="text-sm font-medium text-gray-700">
                                                {t?.articles?.show?.selectIntegration ?? 'Select Integration'}
                                            </label>
                                            <select
                                                value={selectedIntegration || ''}
                                                onChange={(e) =>
                                                    setSelectedIntegration(Number(e.target.value) || null)
                                                }
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
                                        <Button
                                            onClick={handlePublish}
                                            loading={publishing}
                                            disabled={!selectedIntegration}
                                            icon={Send}
                                            className="w-full"
                                        >
                                            {t?.articles?.show?.publishArticle ?? 'Publish Article'}
                                        </Button>
                                    </>
                                )}

                                {article.status === 'approved' && integrations.length === 0 && (
                                    <div className="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-700">
                                        <p>
                                            {t?.articles?.show?.noIntegrations ?? 'No integrations configured.'}{' '}
                                            <Link
                                                href={route('integrations.create')}
                                                className="font-medium underline"
                                            >
                                                {t?.articles?.show?.addOne ?? 'Add one'}
                                            </Link>{' '}
                                            {t?.articles?.show?.toPublish ?? 'to publish this article.'}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </Card>
                    )}

                    {/* Keyword Info */}
                    {article.keyword && (
                        <Card>
                            <CardHeader title={t?.articles?.show?.targetKeyword ?? 'Target Keyword'} />
                            <div className="mt-4">
                                <p className="text-lg font-medium text-indigo-600">
                                    "{article.keyword.keyword}"
                                </p>
                                <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                    {article.keyword.volume && (
                                        <div>
                                            <p className="text-gray-500">{t?.articles?.show?.volume ?? 'Volume'}</p>
                                            <p className="font-medium">
                                                {article.keyword.volume.toLocaleString()}
                                            </p>
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
