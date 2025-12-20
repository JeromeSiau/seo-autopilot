import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
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

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50">
                            <Globe className="h-6 w-6 text-indigo-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">{site.name}</h1>
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
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                                <Key className="h-5 w-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{site.keywords_count || 0}</p>
                                <p className="text-sm text-gray-500">Keywords</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                                <FileText className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{site.articles_count || 0}</p>
                                <p className="text-sm text-gray-500">Articles</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                                <Plug className="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-gray-900">{site.integrations_count || 0}</p>
                                <p className="text-sm text-gray-500">Intégrations</p>
                            </div>
                        </Card>
                    </div>

                    {/* Top Keywords */}
                    <Card>
                        <div className="flex items-center justify-between border-b border-gray-100 pb-4">
                            <h2 className="font-semibold text-gray-900">Top Keywords</h2>
                            <Link
                                href={route('keywords.index', { site_id: site.id })}
                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-500"
                            >
                                Voir tous
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                        {site.keywords && site.keywords.length > 0 ? (
                            <div className="divide-y divide-gray-50">
                                {site.keywords.map((keyword) => (
                                    <div key={keyword.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium text-gray-900">{keyword.keyword}</p>
                                            <div className="flex items-center gap-3 text-xs text-gray-500">
                                                {keyword.volume && <span>{keyword.volume.toLocaleString()} vol.</span>}
                                                {keyword.position && <span>Position: {keyword.position}</span>}
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
                            <p className="py-8 text-center text-sm text-gray-500">
                                Aucun keyword pour le moment. L'autopilot en découvrira bientôt !
                            </p>
                        )}
                    </Card>

                    {/* Business Info */}
                    {site.business_description && (
                        <Card>
                            <h2 className="mb-4 font-semibold text-gray-900">Description du business</h2>
                            <p className="text-gray-600">{site.business_description}</p>
                            {site.target_audience && (
                                <div className="mt-4 flex items-start gap-2">
                                    <Users className="mt-0.5 h-4 w-4 text-gray-400" />
                                    <div>
                                        <p className="text-xs font-medium text-gray-500">Audience cible</p>
                                        <p className="text-sm text-gray-600">{site.target_audience}</p>
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
                        <h2 className="mb-4 font-semibold text-gray-900">Autopilot</h2>
                        {site.settings?.autopilot_enabled ? (
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <div className="h-3 w-3 animate-pulse rounded-full bg-green-500" />
                                    <span className="font-medium text-green-600">Actif</span>
                                </div>
                                <div className="space-y-2 text-sm text-gray-600">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-gray-400" />
                                        <span>{site.settings.articles_per_week} articles/semaine</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-gray-400" />
                                        <span>
                                            {site.settings.publish_days
                                                .map((d) => dayLabels[d] || d)
                                                .join(', ')}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Target className="h-4 w-4 text-gray-400" />
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
                                <p className="mb-4 text-sm text-gray-500">
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
                        <h2 className="mb-4 font-semibold text-gray-900">Connexions</h2>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Google Search Console</span>
                                {site.gsc_connected ? (
                                    <span className="flex items-center gap-1 text-sm text-green-600">
                                        <CheckCircle className="h-4 w-4" />
                                        Connecté
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-sm text-gray-400">
                                        <XCircle className="h-4 w-4" />
                                        Non connecté
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Google Analytics 4</span>
                                {site.ga4_connected ? (
                                    <span className="flex items-center gap-1 text-sm text-green-600">
                                        <CheckCircle className="h-4 w-4" />
                                        Connecté
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-sm text-gray-400">
                                        <XCircle className="h-4 w-4" />
                                        Non connecté
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Intégrations CMS</span>
                                <span className="text-sm text-gray-600">{site.integrations_count || 0}</span>
                            </div>
                        </div>
                        <div className="mt-4 border-t border-gray-100 pt-4">
                            <Link
                                href={route('integrations.index', { site_id: site.id })}
                                className="flex items-center justify-center gap-1 text-sm text-indigo-600 hover:text-indigo-500"
                            >
                                Gérer les intégrations
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </Card>

                    {/* Quick Links */}
                    <Card>
                        <h2 className="mb-4 font-semibold text-gray-900">Actions rapides</h2>
                        <div className="space-y-2">
                            <Link
                                href={route('keywords.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            >
                                <Key className="h-4 w-4" />
                                Voir les keywords
                            </Link>
                            <Link
                                href={route('articles.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            >
                                <FileText className="h-4 w-4" />
                                Voir les articles
                            </Link>
                            <Link
                                href={route('analytics.index', { site_id: site.id })}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            >
                                <Target className="h-4 w-4" />
                                Voir les analytics
                            </Link>
                        </div>
                    </Card>

                    {/* Metadata */}
                    <Card className="text-sm text-gray-500">
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
