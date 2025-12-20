import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, Plus, Play, Sparkles, TrendingUp, Eye, Filter } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '@/Components/ui/Table';
import { Keyword, Site, PaginatedData, PageProps } from '@/types';
import { useState } from 'react';

interface KeywordsIndexProps extends PageProps {
    keywords: PaginatedData<Keyword>;
    sites: Site[];
    filters: {
        site_id?: number;
        status?: string;
        search?: string;
    };
}

export default function KeywordsIndex({ keywords, sites, filters }: KeywordsIndexProps) {
    const [selectedKeywords, setSelectedKeywords] = useState<number[]>([]);

    const handleSelectAll = () => {
        if (selectedKeywords.length === keywords.data.length) {
            setSelectedKeywords([]);
        } else {
            setSelectedKeywords(keywords.data.map((k) => k.id));
        }
    };

    const handleSelectKeyword = (id: number) => {
        setSelectedKeywords((prev) =>
            prev.includes(id) ? prev.filter((k) => k !== id) : [...prev, id]
        );
    };

    const handleGenerateArticles = () => {
        if (selectedKeywords.length === 0) return;
        router.post(route('keywords.generate-bulk'), { keyword_ids: selectedKeywords });
    };

    const handleDiscoverKeywords = (siteId?: number) => {
        router.post(route('keywords.discover'), { site_id: siteId });
    };

    const getDifficultyColor = (difficulty: number | null) => {
        if (!difficulty) return 'bg-gray-100 text-gray-600';
        if (difficulty < 30) return 'bg-green-100 text-green-700';
        if (difficulty < 60) return 'bg-yellow-100 text-yellow-700';
        return 'bg-red-100 text-red-700';
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Keywords</h1>
                    <div className="flex gap-3">
                        {selectedKeywords.length > 0 && (
                            <Button
                                onClick={handleGenerateArticles}
                                variant="secondary"
                                icon={Sparkles}
                            >
                                Generate ({selectedKeywords.length})
                            </Button>
                        )}
                        <Button
                            onClick={() => handleDiscoverKeywords()}
                            variant="secondary"
                            icon={Search}
                        >
                            Discover
                        </Button>
                        <Button as="link" href={route('keywords.create')} icon={Plus}>
                            Add Keyword
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title="Keywords" />

            {/* Filters */}
            <div className="mb-6 flex flex-wrap gap-4">
                <select
                    value={filters.site_id || ''}
                    onChange={(e) =>
                        router.get(route('keywords.index'), {
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
                        router.get(route('keywords.index'), {
                            ...filters,
                            status: e.target.value || undefined,
                        })
                    }
                    className="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>

                <input
                    type="search"
                    placeholder="Search keywords..."
                    value={filters.search || ''}
                    onChange={(e) =>
                        router.get(
                            route('keywords.index'),
                            { ...filters, search: e.target.value || undefined },
                            { preserveState: true }
                        )
                    }
                    className="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            {keywords.data.length === 0 ? (
                <EmptyState
                    icon={Search}
                    title="No keywords yet"
                    description="Add keywords manually or discover them from Google Search Console."
                    action={{
                        label: 'Discover Keywords',
                        onClick: () => handleDiscoverKeywords(),
                    }}
                />
            ) : (
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableHeader className="w-12">
                                <input
                                    type="checkbox"
                                    checked={selectedKeywords.length === keywords.data.length}
                                    onChange={handleSelectAll}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                            </TableHeader>
                            <TableHeader>Keyword</TableHeader>
                            <TableHeader align="center">Volume</TableHeader>
                            <TableHeader align="center">Difficulty</TableHeader>
                            <TableHeader align="center">Position</TableHeader>
                            <TableHeader align="center">Score</TableHeader>
                            <TableHeader align="center">Status</TableHeader>
                            <TableHeader align="right">Actions</TableHeader>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {keywords.data.map((keyword) => (
                            <TableRow key={keyword.id}>
                                <TableCell>
                                    <input
                                        type="checkbox"
                                        checked={selectedKeywords.includes(keyword.id)}
                                        onChange={() => handleSelectKeyword(keyword.id)}
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </TableCell>
                                <TableCell>
                                    <div>
                                        <p className="font-medium text-gray-900">{keyword.keyword}</p>
                                        {keyword.site && (
                                            <p className="text-xs text-gray-500">{keyword.site.domain}</p>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell align="center">
                                    {keyword.volume ? (
                                        <span className="flex items-center justify-center gap-1 text-gray-600">
                                            <Eye className="h-3 w-3" />
                                            {keyword.volume.toLocaleString()}
                                        </span>
                                    ) : (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </TableCell>
                                <TableCell align="center">
                                    {keyword.difficulty !== null ? (
                                        <span
                                            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${getDifficultyColor(
                                                keyword.difficulty
                                            )}`}
                                        >
                                            {keyword.difficulty}
                                        </span>
                                    ) : (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </TableCell>
                                <TableCell align="center">
                                    {keyword.position ? (
                                        <span className="flex items-center justify-center gap-1 text-gray-600">
                                            <TrendingUp className="h-3 w-3" />
                                            {keyword.position.toFixed(1)}
                                        </span>
                                    ) : (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </TableCell>
                                <TableCell align="center">
                                    <span className="text-sm font-semibold text-indigo-600">
                                        {keyword.score}
                                    </span>
                                </TableCell>
                                <TableCell align="center">
                                    <Badge variant={getStatusVariant(keyword.status)}>
                                        {keyword.status}
                                    </Badge>
                                </TableCell>
                                <TableCell align="right">
                                    {keyword.status === 'pending' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            icon={Play}
                                            onClick={() =>
                                                router.post(route('keywords.generate', keyword.id))
                                            }
                                        >
                                            Generate
                                        </Button>
                                    )}
                                    {keyword.article && (
                                        <Button
                                            as="link"
                                            href={route('articles.show', keyword.article.id)}
                                            size="sm"
                                            variant="ghost"
                                        >
                                            View Article
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {/* Pagination */}
            {keywords.meta.last_page > 1 && (
                <div className="mt-6 flex items-center justify-between">
                    <p className="text-sm text-gray-500">
                        Showing {keywords.meta.from} to {keywords.meta.to} of {keywords.meta.total} keywords
                    </p>
                    <div className="flex gap-2">
                        {keywords.links.prev && (
                            <Button
                                as="link"
                                href={keywords.links.prev}
                                variant="secondary"
                                size="sm"
                            >
                                Previous
                            </Button>
                        )}
                        {keywords.links.next && (
                            <Button
                                as="link"
                                href={keywords.links.next}
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
