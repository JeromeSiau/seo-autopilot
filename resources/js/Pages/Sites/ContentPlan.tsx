import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Globe,
    Calendar,
    ChevronLeft,
    RefreshCw,
    Settings,
    Sparkles,
} from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import ContentCalendar from '@/Components/ContentPlan/ContentCalendar';
import { Site, PageProps } from '@/types';

interface ContentPlanProps extends PageProps {
    site: Site;
    stats: {
        keywords_total: number;
        articles_planned: number;
        articles_generated: number;
        articles_published: number;
    };
    canRegenerate: boolean;
}

export default function ContentPlan({ site, stats, canRegenerate }: ContentPlanProps) {
    const [regenerating, setRegenerating] = useState(false);

    const handleRegenerate = async () => {
        if (!confirm('Cela va régénérer tout votre Content Plan. Continuer ?')) return;

        setRegenerating(true);
        try {
            const res = await fetch(`/sites/${site.id}/content-plan/regenerate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (res.ok) {
                window.location.href = `/onboarding/generating/${site.id}`;
            }
        } catch (e) {
            console.error('Regenerate failed', e);
        }
        setRegenerating(false);
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('sites.show', { site: site.id })}
                            className="flex h-10 w-10 items-center justify-center rounded-lg text-surface-400 hover:bg-surface-100 hover:text-surface-600 dark:hover:bg-surface-800 dark:hover:text-surface-300"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </Link>
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15">
                            <Calendar className="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-surface-900 dark:text-white">Content Plan</h1>
                            <p className="text-sm text-surface-500 dark:text-surface-400">{site.domain}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {canRegenerate && (
                            <Button
                                variant="secondary"
                                icon={RefreshCw}
                                onClick={handleRegenerate}
                                disabled={regenerating}
                            >
                                {regenerating ? 'Régénération...' : 'Régénérer'}
                            </Button>
                        )}
                        <Button
                            as="link"
                            href={route('sites.edit', { site: site.id })}
                            variant="secondary"
                            icon={Settings}
                        >
                            Paramètres
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`Content Plan - ${site.name}`} />

            <div className="space-y-6">
                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-4">
                    <Card className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-500/15">
                            <Sparkles className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.keywords_total}</p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">Keywords</p>
                        </div>
                    </Card>
                    <Card className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/15">
                            <Calendar className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.articles_planned}</p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">Planifiés</p>
                        </div>
                    </Card>
                    <Card className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-50 dark:bg-yellow-500/15">
                            <RefreshCw className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.articles_generated}</p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">Générés</p>
                        </div>
                    </Card>
                    <Card className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-500/15">
                            <Globe className="h-5 w-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-surface-900 dark:text-white">{stats.articles_published}</p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">Publiés</p>
                        </div>
                    </Card>
                </div>

                {/* Calendar */}
                <ContentCalendar siteId={site.id} />

                {/* Legend */}
                <Card className="flex flex-wrap gap-6">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                        <span className="text-sm text-surface-600 dark:text-surface-400">Planifié</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-yellow-500" />
                        <span className="text-sm text-surface-600 dark:text-surface-400">En génération</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-green-500" />
                        <span className="text-sm text-surface-600 dark:text-surface-400">Prêt</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-primary-500" />
                        <span className="text-sm text-surface-600 dark:text-surface-400">Publié</span>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
