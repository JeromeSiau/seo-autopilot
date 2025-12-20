import { Link } from '@inertiajs/react';
import { ArrowRight, Settings } from 'lucide-react';

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

interface Props {
    site: Site;
}

const STATUS_CONFIG = {
    active: { icon: '🟢', label: 'Actif', color: 'text-green-600' },
    paused: { icon: '🟡', label: 'En pause', color: 'text-yellow-600' },
    not_configured: { icon: '⚪', label: 'Non configuré', color: 'text-gray-400' },
    error: { icon: '🔴', label: 'Erreur', color: 'text-red-600' },
};

export default function SiteCard({ site }: Props) {
    const status = STATUS_CONFIG[site.autopilot_status];

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <span className="text-xl">{status.icon}</span>
                    <div>
                        <h3 className="font-semibold text-gray-900">{site.domain}</h3>
                        <p className="text-sm text-gray-500">{site.name}</p>
                    </div>
                </div>
                <Link
                    href={site.onboarding_complete ? route('sites.show', site.id) : route('onboarding.create')}
                    className="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                    {site.onboarding_complete ? 'Voir détails' : 'Configurer'}
                    <ArrowRight className="h-4 w-4" />
                </Link>
            </div>

            {site.onboarding_complete ? (
                <div className="mt-4 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p className="text-lg font-semibold text-gray-900">{site.articles_per_week}</p>
                        <p className="text-xs text-gray-500">articles/sem</p>
                    </div>
                    <div>
                        <p className="text-lg font-semibold text-gray-900">{site.articles_this_week}</p>
                        <p className="text-xs text-gray-500">cette semaine</p>
                    </div>
                    <div>
                        <p className={`text-lg font-semibold ${site.articles_in_review > 0 ? 'text-yellow-600' : 'text-gray-900'}`}>
                            {site.articles_in_review}
                        </p>
                        <p className="text-xs text-gray-500">en review</p>
                    </div>
                </div>
            ) : (
                <div className="mt-4 rounded-lg bg-gray-50 p-3 text-center text-sm text-gray-500">
                    Configuration incomplète - Cliquez pour terminer
                </div>
            )}

            <div className="mt-4 flex items-center justify-between border-t pt-4">
                <span className={`text-sm font-medium ${status.color}`}>
                    Autopilot: {status.label}
                </span>
                {site.onboarding_complete && (
                    <Link
                        href={route('sites.edit', site.id)}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <Settings className="h-4 w-4" />
                    </Link>
                )}
            </div>
        </div>
    );
}
