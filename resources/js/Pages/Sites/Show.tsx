import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Globe,
    Settings,
    ExternalLink,
    CheckCircle,
    XCircle,
    Key,
    FileText,
    Plug,
    ArrowRight,
    Calendar,
    Target,
    Users,
    AlertTriangle,
    TrendingDown,
} from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { Site, Keyword, PageProps } from '@/types';
import { format } from 'date-fns';

interface SiteShowProps extends PageProps {
    site: Site & {
        keywords_count: number;
        articles_count: number;
        integrations_count: number;
        keywords?: Keyword[];
        settings?: {
            articles_per_week: number;
            publish_days: string[];
            auto_publish: boolean;
            autopilot_enabled: boolean;
        };
    };
}

export default function SiteShow({ site }: SiteShowProps) {
    const dayLabels: Record<string, string> = {
        mon: 'Lun',
        tue: 'Mar',
        wed: 'Mer',
        thu: 'Jeu',
        fri: 'Ven',
        sat: 'Sam',
        sun: 'Dim',
    };

    const [decliningArticles, setDecliningArticles] = useState<Array<{
        article: { id: number; title: string };
        position_change: number;
        current_position: number;
    }>>([]);
    const [loadingDeclining, setLoadingDeclining] = useState(true);

    useEffect(() => {
        if (site.gsc_connected) {
            fetch(route('api.analytics.dashboard', { site: site.id }))
                .then(res => res.json())
                .then(data => {
                    setDecliningArticles(data.data?.needs_attention || []);
                    setLoadingDeclining(false);
                })
                .catch(() => setLoadingDeclining(false));
        } else {
            setLoadingDeclining(false);
        }
    }, [site.id, site.gsc_connected]);

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15">
                            <Globe className="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-surface-900 dark:text-white">{site.name}</h1>
                            <a
                                href={`https://${site.domain}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1 text-sm text-surface-500 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                            >
                                {site.domain}
                                <ExternalLink className="h-3 w-3" />
                            </a>
                        </div>
                    </div>
                    <Button as="link" href={route('sites.edit', { site: site.id })} variant="secondary" icon={Settings}>
                        Paramètres
                    </Button>
                </div>
            }
        >
            <Head title={site.name} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main content */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Stats */}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-500/15">
                                <Key className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-surface-900 dark:text-white">{site.keywords_count || 0}</p>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Keywords</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/15">
                                <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-surface-900 dark:text-white">{site.articles_count || 0}</p>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Articles</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-500/15">
                                <Plug className="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-surface-900 dark:text-white">{site.integrations_count || 0}</p>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Intégrations</p>
                            </div>
                        </Card>
                    </div>

                    {/* Top Keywords */}
                    <Card>
                        <div className="flex items-center justify-between border-b border-surface-100 dark:border-surface-800 pb-4">
                            <div>
                                <h2 className="font-semibold text-surface-900 dark:text-white">Top 10 Keywords</h2>
                                <p className="text-xs text-surface-500 dark:text-surface-400">Triés par score</p>
                            </div>
                            <Link
                                href={route('keywords.index', { site_id: site.id })}
                                className="flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300"
                            >
                                Voir tous ({site.keywords_count || 0})
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                        {site.keywords && site.keywords.length > 0 ? (
                            <div className="divide-y divide-surface-50 dark:divide-surface-800">
                                {site.keywords.map((keyword) => (
                                    <div key={keyword.id} className="flex items-center justify-between py-3">
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-surface-900 dark:text-white truncate">{keyword.keyword}</p>
                                            <div className="flex items-center gap-3 text-xs text-surface-500 dark:text-surface-400">
                                                <span className="font-medium text-primary-600 dark:text-primary-400">
                                                    Score: {Number(keyword.score).toFixed(0)}
                                                </span>
                                                {keyword.volume !== null && keyword.volume > 0 && (
                                                    <span>{keyword.volume.toLocaleString()} vol.</span>
                                                )}
                                                {keyword.difficulty !== null && (
                                                    <span>Diff: {Number(keyword.difficulty).toFixed(0)}</span>
                                                )}
                                                {keyword.position && <span>Pos: {keyword.position}</span>}
                                            </div>
                                        </div>
                                        <Badge
                                            variant={
                                                keyword.status === 'completed'
                                                    ? 'success'
                                                    : keyword.status === 'generating'
                                                    ? 'warning'
                                                    : 'secondary'
                                            }
                                        >
                                            {keyword.status}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                Aucun keyword pour le moment. L'autopilot en découvrira bientôt !
                            </p>
                        )}
                    </Card>

                    {/* Business Info */}
                    {site.business_description && (
                        <Card>
                            <h2 className="mb-4 font-semibold text-surface-900 dark:text-white">Description du business</h2>
                            <p className="text-surface-600 dark:text-surface-400">{site.business_description}</p>
                            {site.target_audience && (
                                <div className="mt-4 flex items-start gap-2">
                                    <Users className="mt-0.5 h-4 w-4 text-surface-400" />
                                    <div>
                                        <p className="text-xs font-medium text-surface-500 dark:text-surface-400">Audience cible</p>
                                        <p className="text-sm text-surface-600 dark:text-surface-400">{site.target_audience}</p>
                                    </div>
                                </div>
                            )}
                            {site.topics && site.topics.length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {site.topics.map((topic, i) => (
                                        <Badge key={i} variant="secondary">
                                            {topic}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </Card>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Autopilot Status */}
                    <Card>
                        <h2 className="mb-4 font-semibold text-surface-900 dark:text-white">Autopilot</h2>
                        {site.settings?.autopilot_enabled ? (
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <div className="h-3 w-3 animate-pulse rounded-full bg-green-500" />
                                    <span className="font-medium text-green-600 dark:text-green-400">Actif</span>
                                </div>
                                <div className="space-y-2 text-sm text-surface-600 dark:text-surface-400">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-surface-400" />
                                        <span>{site.settings.articles_per_week} articles/semaine</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-surface-400" />
                                        <span>
                                            {site.settings.publish_days
                                                .map((d) => dayLabels[d] || d)
                                                .join(', ')}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Target className="h-4 w-4 text-surface-400" />
                                        <span>
                                            {site.settings.auto_publish
                                                ? 'Publication automatique'
                                                : 'Validation manuelle'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center">
                                <p className="mb-4 text-sm text-surface-500 dark:text-surface-400">
                                    L'autopilot n'est pas encore configuré.
                                </p>
                                <Button
                                    as="link"
                                    href={route('onboarding.resume', { site: site.id })}
                                    size="sm"
                                >
                                    Configurer
                                </Button>
                            </div>
                        )}
                    </Card>

                    {/* Connections */}
                    <Card>
                        <h2 className="mb-4 font-semibold text-surface-900 dark:text-white">Connexions</h2>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-surface-600 dark:text-surface-400">Google Search Console</span>
                                {site.gsc_connected ? (
                                    <span className="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                        <CheckCircle className="h-4 w-4" />
                                        Connecté
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-sm text-surface-400">
                                        <XCircle className="h-4 w-4" />
                                        Non connecté
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-surface-600 dark:text-surface-400">Google Analytics 4</span>
                                {site.ga4_connected ? (
                                    <span className="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                        <CheckCircle className="h-4 w-4" />
                                        Connecté
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-sm text-surface-400">
                                        <XCircle className="h-4 w-4" />
                                        Non connecté
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-surface-600 dark:text-surface-400">Intégrations CMS</span>
                                <span className="text-sm text-surface-600 dark:text-surface-400">{site.integrations_count || 0}</span>
                            </div>
                        </div>
                        <div className="mt-4 border-t border-surface-100 dark:border-surface-800 pt-4">
                            <Link
                                href={route('integrations.index', { site_id: site.id })}
                                className="flex items-center justify-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300"
                            >
                                Gérer les intégrations
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </Card>

                    {/* Articles à surveiller */}
                    <Card>
                        <div className="mb-4 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-orange-500" />
                                <h2 className="font-semibold text-surface-900 dark:text-white">Articles à surveiller</h2>
                                {!loadingDeclining && decliningArticles.length > 0 && (
                                    <Badge variant="warning">{decliningArticles.length}</Badge>
                                )}
                            </div>
                        </div>
                        {loadingDeclining ? (
                            <div className="py-8 text-center">
                                <div className="inline-block h-6 w-6 animate-spin rounded-full border-2 border-surface-200 border-t-primary-600 dark:border-surface-700 dark:border-t-primary-400"></div>
                            </div>
                        ) : decliningArticles.length > 0 ? (
                            <>
                                <div className="space-y-3">
                                    {decliningArticles.slice(0, 5).map((item) => (
                                        <div
                                            key={item.article.id}
                                            className="rounded-lg border border-orange-100 bg-orange-50 dark:border-orange-900/20 dark:bg-orange-900/10 p-3"
                                        >
                                            <Link
                                                href={route('articles.show', { article: item.article.id })}
                                                className="font-medium text-surface-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 line-clamp-2 text-sm"
                                            >
                                                {item.article.title}
                                            </Link>
                                            <div className="mt-2 flex items-center gap-3 text-xs">
                                                <span className="text-surface-600 dark:text-surface-400">
                                                    Position actuelle: {item.current_position}
                                                </span>
                                                <span className="flex items-center gap-1 text-red-600 dark:text-red-400">
                                                    <TrendingDown className="h-3 w-3" />
                                                    {Math.abs(item.position_change)} places
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {decliningArticles.length > 5 && (
                                    <div className="mt-4 border-t border-surface-100 dark:border-surface-800 pt-4">
                                        <Link
                                            href={route('analytics.index', { site_id: site.id })}
                                            className="flex items-center justify-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300"
                                        >
                                            Voir les {decliningArticles.length - 5} autres →
                                        </Link>
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                Tous vos articles performent bien 🎉
                            </p>
                        )}
                    </Card>

                    {/* Quick Links */}
                    <Card>
                        <h2 className="mb-4 font-semibold text-surface-900 dark:text-white">Actions rapides</h2>
                        <div className="space-y-2">
                            <Link
                                href={route('sites.content-plan-page', { site: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800"
                            >
                                <Calendar className="h-4 w-4" />
                                Content Plan
                            </Link>
                            <Link
                                href={route('keywords.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800"
                            >
                                <Key className="h-4 w-4" />
                                Voir les keywords
                            </Link>
                            <Link
                                href={route('articles.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800"
                            >
                                <FileText className="h-4 w-4" />
                                Voir les articles
                            </Link>
                            <Link
                                href={route('analytics.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800"
                            >
                                <Target className="h-4 w-4" />
                                Voir les analytics
                            </Link>
                        </div>
                    </Card>

                    {/* Metadata */}
                    <Card className="text-sm text-surface-500 dark:text-surface-400">
                        <div className="flex items-center justify-between">
                            <span>Langue</span>
                            <Badge variant="secondary">{site.language.toUpperCase()}</Badge>
                        </div>
                        <div className="mt-2 flex items-center justify-between">
                            <span>Créé le</span>
                            <span>{format(new Date(site.created_at), 'dd/MM/yyyy')}</span>
                        </div>
                        {site.onboarding_completed_at && (
                            <div className="mt-2 flex items-center justify-between">
                                <span>Configuré le</span>
                                <span>{format(new Date(site.onboarding_completed_at), 'dd/MM/yyyy')}</span>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
