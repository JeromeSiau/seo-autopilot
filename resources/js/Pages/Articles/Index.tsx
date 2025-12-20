import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, ExternalLink, Edit, Trash2, Send } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '@/Components/ui/Table';
import { Article, Site, PaginatedData, PageProps } from '@/types';
import { format } from 'date-fns';

interface ArticlesIndexProps extends PageProps {
    articles: PaginatedData<Article>;
    sites: Site[];
    filters: {
        site_id?: number;
        status?: string;
        search?: string;
    };
}

export default function ArticlesIndex({ articles, sites, filters }: ArticlesIndexProps) {
    const handleDelete = (article: Article) => {
        if (confirm('Are you sure you want to delete this article?')) {
            router.delete(route('articles.destroy', article.id));
        }
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Articles</h1>
                    <Button as="link" href={route('articles.create')} icon={Plus}>
                        Generate Article
                    </Button>
                </div>
            }
        >
            <Head title="Articles" />

            {/* Filters */}
            <div className="mb-6 flex flex-wrap gap-4">
                <select
                    value={filters.site_id || ''}
                    onChange={(e) =>
                        router.get(route('articles.index'), {
                            ...filters,
                            site_id: e.target.value || undefined,
                        })
                    }
                    className="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Sites</option>
                    {sites.map((site) => (
                        <option key={site.id} value={site.id}>
                            {site.name}
                        </option>
                    ))}
                </select>

                <select
                    value={filters.status || ''}
                    onChange={(e) =>
                        router.get(route('articles.index'), {
                            ...filters,
                            status: e.target.value || undefined,
                        })
                    }
                    className="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="review">Review</option>
                    <option value="approved">Approved</option>
                    <option value="published">Published</option>
                    <option value="failed">Failed</option>
                </select>

                <input
                    type="search"
                    placeholder="Search articles..."
                    value={filters.search || ''}
                    onChange={(e) =>
                        router.get(
                            route('articles.index'),
                            { ...filters, search: e.target.value || undefined },
                            { preserveState: true }
                        )
                    }
                    className="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            {articles.data.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="No articles yet"
                    description="Generate your first AI-powered SEO article."
                    action={{
                        label: 'Generate Article',
                        href: route('articles.create'),
                    }}
                />
            ) : (
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableHeader>Title</TableHeader>
                            <TableHeader align="center">Words</TableHeader>
                            <TableHeader align="center">Status</TableHeader>
                            <TableHeader align="center">Cost</TableHeader>
                            <TableHeader>Created</TableHeader>
                            <TableHeader align="right">Actions</TableHeader>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {articles.data.map((article) => (
                            <TableRow key={article.id}>
                                <TableCell>
                                    <div className="max-w-md">
                                        <Link
                                            href={route('articles.show', article.id)}
                                            className="font-medium text-gray-900 hover:text-indigo-600"
                                        >
                                            {article.title}
                                        </Link>
                                        {article.site && (
                                            <p className="mt-0.5 text-xs text-gray-500">
                                                {article.site.domain}
                                            </p>
                                        )}
                                        {article.keyword && (
                                            <p className="mt-0.5 text-xs text-indigo-600">
                                                "{article.keyword.keyword}"
                                            </p>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell align="center">
                                    <span className="text-gray-600">{article.word_count.toLocaleString()}</span>
                                </TableCell>
                                <TableCell align="center">
                                    <div className="flex flex-col items-center gap-1">
                                        <Badge variant={getStatusVariant(article.status)}>
                                            {article.status}
                                        </Badge>
                                        {article.published_url && (
                                            <a
                                                href={article.published_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                                            >
                                                View <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell align="center">
                                    <span className="text-gray-600">
                                        ${article.generation_cost.toFixed(3)}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span className="text-gray-500">
                                        {format(new Date(article.created_at), 'MMM d, yyyy')}
                                    </span>
                                </TableCell>
                                <TableCell align="right">
                                    <div className="flex justify-end gap-1">
                                        {article.status === 'approved' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                icon={Send}
                                                onClick={() =>
                                                    router.post(route('articles.publish', article.id))
                                                }
                                            >
                                                Publish
                                            </Button>
                                        )}
                                        <Link
                                            href={route('articles.edit', article.id)}
                                            className="rounded p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                        >
                                            <Edit className="h-4 w-4" />
                                        </Link>
                                        <button
                                            onClick={() => handleDelete(article)}
                                            className="rounded p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {/* Pagination */}
            {articles.meta.last_page > 1 && (
                <div className="mt-6 flex items-center justify-between">
                    <p className="text-sm text-gray-500">
                        Showing {articles.meta.from} to {articles.meta.to} of {articles.meta.total} articles
                    </p>
                    <div className="flex gap-2">
                        {articles.links.prev && (
                            <Button
                                as="link"
                                href={articles.links.prev}
                                variant="secondary"
                                size="sm"
                            >
                                Previous
                            </Button>
                        )}
                        {articles.links.next && (
                            <Button
                                as="link"
                                href={articles.links.next}
                                variant="secondary"
                                size="sm"
                            >
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
