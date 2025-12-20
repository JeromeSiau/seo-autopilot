import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Plus, AlertTriangle, ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import SiteCard from '@/Components/Dashboard/SiteCard';
import { PageProps } from '@/types';

interface Site {
    id: number;
    domain: string;
    name: string;
    autopilot_status: 'active' | 'paused' | 'not_configured' | 'error';
    articles_per_week: number;
    articles_in_review: number;
    articles_this_week: number;
    onboarding_complete: boolean;
}

interface Action {
    type: 'review' | 'failed' | 'recommendation';
    site_id: number;
    site_domain: string;
    count?: number;
    message: string;
    action_url: string;
}

interface Stats {
    total_sites: number;
    active_sites: number;
    total_keywords_queued: number;
    articles_this_month: number;
    articles_published_this_month: number;
    articles_used: number;
    articles_limit: number;
}

interface DashboardProps extends PageProps {
    stats: Stats;
    sites: Site[];
    actionsRequired: Action[];
    unreadNotifications: number;
}

export default function Dashboard({ stats, sites, actionsRequired }: DashboardProps) {
    const usagePercentage = stats.articles_limit > 0
        ? Math.round((stats.articles_used / stats.articles_limit) * 100)
        : 0;

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <Button as="link" href={route('onboarding.create')} icon={Plus}>
                        Ajouter un site
                    </Button>
                </div>
            }
        >
            <Head title="Dashboard" />

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard title="Sites actifs" value={`${stats.active_sites}/${stats.total_sites}`} color="blue" />
                <StatCard title="Keywords en queue" value={stats.total_keywords_queued} color="purple" />
                <StatCard title="Articles ce mois" value={stats.articles_this_month} color="green" />
                <StatCard title="Publiés ce mois" value={stats.articles_published_this_month} color="indigo" />
            </div>

            {/* Usage Bar */}
            <Card className="mt-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-500">Usage mensuel</p>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">
                            {stats.articles_used} / {stats.articles_limit} articles
                        </p>
                    </div>
                    <Button as="link" href={route('settings.billing')} variant="outline" size="sm">
                        Upgrade
                    </Button>
                </div>
                <div className="mt-4">
                    <div className="h-2 w-full rounded-full bg-gray-200">
                        <div
                            className={`h-2 rounded-full transition-all ${
                                usagePercentage >= 90 ? 'bg-red-500' :
                                usagePercentage >= 70 ? 'bg-yellow-500' : 'bg-indigo-600'
                            }`}
                            style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                        />
                    </div>
                </div>
            </Card>

            {/* Actions Required */}
            {actionsRequired.length > 0 && (
                <Card className="mt-6 border-yellow-200 bg-yellow-50">
                    <h3 className="flex items-center gap-2 font-semibold text-yellow-800">
                        <AlertTriangle className="h-5 w-5" />
                        Actions requises
                    </h3>
                    <div className="mt-3 space-y-2">
                        {actionsRequired.map((action, i) => (
                            <Link
                                key={i}
                                href={action.action_url}
                                className="flex items-center justify-between rounded-lg bg-white p-3 hover:bg-yellow-100"
                            >
                                <div>
                                    <p className="font-medium text-gray-900">{action.message}</p>
                                    <p className="text-sm text-gray-500">{action.site_domain}</p>
                                </div>
                                <ArrowRight className="h-4 w-4 text-gray-400" />
                            </Link>
                        ))}
                    </div>
                </Card>
            )}

            {/* Sites Grid */}
            <div className="mt-6">
                <h2 className="mb-4 text-lg font-semibold text-gray-900">Mes sites</h2>
                {sites.length === 0 ? (
                    <Card className="text-center py-12">
                        <p className="text-gray-500">Aucun site configuré</p>
                        <Button as="link" href={route('onboarding.create')} className="mt-4" icon={Plus}>
                            Ajouter votre premier site
                        </Button>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {sites.map((site) => (
                            <SiteCard key={site.id} site={site} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
