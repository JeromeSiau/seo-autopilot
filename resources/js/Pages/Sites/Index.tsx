import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Globe, Plus, ExternalLink, Settings, Trash2, CheckCircle, XCircle } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Site, PaginatedData, PageProps } from '@/types';
import { format } from 'date-fns';

interface SitesIndexProps extends PageProps {
    sites: PaginatedData<Site>;
}

export default function SitesIndex({ sites }: SitesIndexProps) {
    const handleDelete = (site: Site) => {
        if (confirm(`Are you sure you want to delete ${site.domain}?`)) {
            router.delete(route('sites.destroy', site.id));
        }
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Sites</h1>
                    <Button as="link" href={route('sites.create')} icon={Plus}>
                        Add Site
                    </Button>
                </div>
            }
        >
            <Head title="Sites" />

            {sites.data.length === 0 ? (
                <EmptyState
                    icon={Globe}
                    title="No sites yet"
                    description="Add your first website to start discovering keywords and generating content."
                    action={{
                        label: 'Add Site',
                        href: route('sites.create'),
                    }}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {sites.data.map((site) => (
                        <Card key={site.id} className="relative">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                                        <Globe className="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-gray-900">{site.name}</h3>
                                        <a
                                            href={`https://${site.domain}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-1 text-sm text-gray-500 hover:text-indigo-600"
                                        >
                                            {site.domain}
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Link
                                        href={route('sites.edit', site.id)}
                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    >
                                        <Settings className="h-4 w-4" />
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(site)}
                                        className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div className="mt-4 flex items-center gap-4 text-sm text-gray-500">
                                <span>{site.keywords_count || 0} keywords</span>
                                <span>{site.articles_count || 0} articles</span>
                            </div>

                            <div className="mt-4 flex items-center gap-2">
                                <Badge variant={site.language === 'en' ? 'info' : 'secondary'}>
                                    {site.language.toUpperCase()}
                                </Badge>
                                <div className="flex items-center gap-1">
                                    {site.gsc_connected ? (
                                        <span className="flex items-center gap-1 text-xs text-green-600">
                                            <CheckCircle className="h-3 w-3" />
                                            GSC
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-xs text-gray-400">
                                            <XCircle className="h-3 w-3" />
                                            GSC
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-1">
                                    {site.ga4_connected ? (
                                        <span className="flex items-center gap-1 text-xs text-green-600">
                                            <CheckCircle className="h-3 w-3" />
                                            GA4
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-xs text-gray-400">
                                            <XCircle className="h-3 w-3" />
                                            GA4
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4 border-t border-gray-100 pt-4">
                                <p className="text-xs text-gray-400">
                                    Added {format(new Date(site.created_at), 'MMM d, yyyy')}
                                </p>
                            </div>

                            <Link
                                href={route('sites.show', site.id)}
                                className="absolute inset-0 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                <span className="sr-only">View {site.name}</span>
                            </Link>
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
