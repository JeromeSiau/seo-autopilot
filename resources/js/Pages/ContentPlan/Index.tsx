import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    ChevronRight,
    Sparkles,
    Clock,
    CheckCircle,
} from 'lucide-react';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { Site, PageProps } from '@/types';

interface SiteWithPlan extends Site {
    planned_count: number;
    generated_count: number;
    published_count: number;
    next_article_date: string | null;
}

interface ContentPlanIndexProps extends PageProps {
    sites: SiteWithPlan[];
}

export default function ContentPlanIndex({ sites }: ContentPlanIndexProps) {
    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15">
                        <Calendar className="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-surface-900 dark:text-white">Content Plans</h1>
                        <p className="text-sm text-surface-500 dark:text-surface-400">
                            Gérez vos calendriers de contenu
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Content Plans" />

            {sites.length === 0 ? (
                <Card className="text-center py-12">
                    <Calendar className="mx-auto h-12 w-12 text-surface-300 dark:text-surface-600" />
                    <h3 className="mt-4 text-lg font-medium text-surface-900 dark:text-white">
                        Aucun site configuré
                    </h3>
                    <p className="mt-2 text-sm text-surface-500 dark:text-surface-400">
                        Ajoutez un site pour commencer à planifier votre contenu
                    </p>
                    <Link
                        href={route('sites.create')}
                        className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors"
                    >
                        Ajouter un site
                    </Link>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {sites.map((site) => (
                        <Link
                            key={site.id}
                            href={route('sites.content-plan-page', { site: site.id })}
                            className="group"
                        >
                            <Card className="h-full transition-all hover:shadow-lg hover:border-primary-200 dark:hover:border-primary-500/30">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <h3 className="font-semibold text-surface-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                            {site.name}
                                        </h3>
                                        <p className="text-sm text-surface-500 dark:text-surface-400">
                                            {site.domain}
                                        </p>
                                    </div>
                                    <ChevronRight className="h-5 w-5 text-surface-300 dark:text-surface-600 group-hover:text-primary-500 transition-colors" />
                                </div>

                                <div className="mt-4 flex items-center gap-4">
                                    <div className="flex items-center gap-1.5 text-sm">
                                        <Clock className="h-4 w-4 text-blue-500" />
                                        <span className="text-surface-600 dark:text-surface-400">
                                            {site.planned_count} planifiés
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-sm">
                                        <Sparkles className="h-4 w-4 text-yellow-500" />
                                        <span className="text-surface-600 dark:text-surface-400">
                                            {site.generated_count} générés
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-sm">
                                        <CheckCircle className="h-4 w-4 text-green-500" />
                                        <span className="text-surface-600 dark:text-surface-400">
                                            {site.published_count} publiés
                                        </span>
                                    </div>
                                </div>

                                {site.next_article_date && (
                                    <div className="mt-4 pt-4 border-t border-surface-100 dark:border-surface-800">
                                        <span className="text-xs">
                                            <Badge variant="secondary">
                                                Prochain article : {new Date(site.next_article_date).toLocaleDateString('fr-FR')}
                                            </Badge>
                                        </span>
                                    </div>
                                )}
                            </Card>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
