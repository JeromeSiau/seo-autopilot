import { Link } from '@inertiajs/react';
import { ArrowRight, Settings, Play, Pause, AlertCircle, Circle } from 'lucide-react';
import clsx from 'clsx';

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
    active: {
        label: 'Active',
        color: 'text-primary-600',
        bg: 'bg-primary-50',
        border: 'border-primary-200',
        icon: Play,
        iconColor: 'text-primary-500',
    },
    paused: {
        label: 'Paused',
        color: 'text-amber-600',
        bg: 'bg-amber-50',
        border: 'border-amber-200',
        icon: Pause,
        iconColor: 'text-amber-500',
    },
    not_configured: {
        label: 'Setup required',
        color: 'text-surface-500',
        bg: 'bg-surface-100',
        border: 'border-surface-200',
        icon: Circle,
        iconColor: 'text-surface-400',
    },
    error: {
        label: 'Error',
        color: 'text-red-600',
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: AlertCircle,
        iconColor: 'text-red-500',
    },
};

export default function SiteCard({ site }: Props) {
    const status = STATUS_CONFIG[site.autopilot_status];
    const StatusIcon = status.icon;

    return (
        <div className="bg-white rounded-2xl border border-surface-200 p-5 hover:shadow-lg hover:border-primary-200 transition-all group">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                        <span className="font-display font-bold text-primary-700 uppercase">
                            {site.domain.charAt(0)}
                        </span>
                    </div>
                    <div>
                        <h3 className="font-display font-semibold text-surface-900">{site.domain}</h3>
                        <p className="text-sm text-surface-500">{site.name}</p>
                    </div>
                </div>
                <Link
                    href={site.onboarding_complete ? route('sites.show', { site: site.id }) : route('onboarding.resume', { site: site.id })}
                    className="flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    {site.onboarding_complete ? 'View' : 'Setup'}
                    <ArrowRight className="h-4 w-4" />
                </Link>
            </div>

            {/* Stats or Setup prompt */}
            {site.onboarding_complete ? (
                <div className="mt-5 grid grid-cols-3 gap-3">
                    <div className="rounded-xl bg-surface-50 p-3 text-center">
                        <p className="font-display text-lg font-bold text-surface-900">{site.articles_per_week}</p>
                        <p className="text-xs text-surface-500">per week</p>
                    </div>
                    <div className="rounded-xl bg-surface-50 p-3 text-center">
                        <p className="font-display text-lg font-bold text-surface-900">{site.articles_this_week}</p>
                        <p className="text-xs text-surface-500">this week</p>
                    </div>
                    <div className="rounded-xl bg-surface-50 p-3 text-center">
                        <p className={clsx(
                            'font-display text-lg font-bold',
                            site.articles_in_review > 0 ? 'text-amber-600' : 'text-surface-900'
                        )}>
                            {site.articles_in_review}
                        </p>
                        <p className="text-xs text-surface-500">in review</p>
                    </div>
                </div>
            ) : (
                <div className="mt-5 rounded-xl bg-surface-50 p-4 text-center">
                    <p className="text-sm text-surface-500">Setup incomplete</p>
                    <Link
                        href={route('onboarding.resume', { site: site.id })}
                        className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700"
                    >
                        Continue setup
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            )}

            {/* Footer */}
            <div className="mt-4 flex items-center justify-between pt-4 border-t border-surface-100">
                <div className={clsx(
                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                    status.bg,
                    status.color
                )}>
                    <StatusIcon className={clsx('h-3 w-3', status.iconColor)} />
                    {status.label}
                </div>
                {site.onboarding_complete && (
                    <Link
                        href={route('sites.edit', { site: site.id })}
                        className="p-1.5 rounded-lg text-surface-400 hover:text-surface-600 hover:bg-surface-100 transition-all"
                    >
                        <Settings className="h-4 w-4" />
                    </Link>
                )}
            </div>
        </div>
    );
}
