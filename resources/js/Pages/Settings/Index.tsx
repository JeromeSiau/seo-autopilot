import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Users, Key, Bell, Palette, ChevronRight, Zap, Sparkles } from 'lucide-react';
import clsx from 'clsx';
import { PageProps, Team } from '@/types';

interface SettingsIndexProps extends PageProps {
    team: Team;
}

const settingsLinks = [
    {
        name: 'Facturation',
        description: 'Gérez votre abonnement et vos moyens de paiement',
        href: 'settings.billing',
        icon: CreditCard,
        color: 'primary',
    },
    {
        name: 'Équipe',
        description: 'Invitez des membres et gérez les permissions',
        href: 'settings.team',
        icon: Users,
        color: 'blue',
    },
    {
        name: 'Clés API',
        description: 'Gérez les clés API pour les services externes',
        href: 'settings.api-keys',
        icon: Key,
        color: 'purple',
    },
    {
        name: 'Voix de marque',
        description: 'Configurez le ton et le style de votre contenu',
        href: 'settings.brand-voices',
        icon: Palette,
        color: 'amber',
    },
    {
        name: 'Notifications',
        description: 'Configurez les notifications email et webhook',
        href: 'settings.notifications',
        icon: Bell,
        color: 'blue',
    },
];

const PLAN_CONFIG = {
    free: { label: 'Gratuit', color: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300' },
    starter: { label: 'Starter', color: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400' },
    pro: { label: 'Pro', color: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' },
    enterprise: { label: 'Enterprise', color: 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400' },
};

export default function SettingsIndex({ team }: SettingsIndexProps) {
    const usagePercentage = Math.round((team.articles_generated_count / team.articles_limit) * 100);
    const planConfig = PLAN_CONFIG[team.plan] || PLAN_CONFIG.free;

    const getColorClasses = (color: string) => {
        const colors: Record<string, { iconBg: string; icon: string }> = {
            primary: { iconBg: 'bg-primary-100 dark:bg-primary-500/15', icon: 'text-primary-600 dark:text-primary-400' },
            blue: { iconBg: 'bg-blue-100 dark:bg-blue-500/15', icon: 'text-blue-600 dark:text-blue-400' },
            purple: { iconBg: 'bg-purple-100 dark:bg-purple-500/15', icon: 'text-purple-600 dark:text-purple-400' },
            amber: { iconBg: 'bg-amber-100 dark:bg-amber-500/15', icon: 'text-amber-600 dark:text-amber-400' },
        };
        return colors[color] || colors.primary;
    };

    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Paramètres</h1>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Gérez votre compte et vos préférences
                    </p>
                </div>
            }
        >
            <Head title="Paramètres" />

            {/* Current Plan Card */}
            <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6 mb-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                Plan actuel
                            </h2>
                            <span className={clsx(
                                'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold',
                                planConfig.color
                            )}>
                                <Sparkles className="h-3 w-3" />
                                {planConfig.label}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            {team.articles_limit - team.articles_generated_count} articles restants ce mois-ci
                        </p>
                    </div>
                    <div className="flex flex-col items-start sm:items-end gap-3">
                        <div className="text-right">
                            <p className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                                {team.articles_generated_count}
                                <span className="text-lg font-normal text-surface-400"> / {team.articles_limit}</span>
                            </p>
                            <p className="text-xs text-surface-500 dark:text-surface-400">articles ce mois</p>
                        </div>
                        <Link
                            href={route('settings.billing')}
                            className={clsx(
                                'inline-flex items-center gap-1.5 rounded-lg px-4 py-2',
                                'text-sm font-semibold',
                                'bg-gradient-to-r from-primary-500 to-primary-600 text-white',
                                'shadow-green dark:shadow-green-glow hover:shadow-green-lg hover:-translate-y-0.5',
                                'transition-all'
                            )}
                        >
                            <Zap className="h-4 w-4" />
                            Upgrader
                        </Link>
                    </div>
                </div>
                <div className="mt-5">
                    <div className="flex justify-between text-xs text-surface-500 dark:text-surface-400 mb-2">
                        <span>{usagePercentage}% utilisé</span>
                        <span>{team.articles_limit - team.articles_generated_count} restants</span>
                    </div>
                    <div className="h-2.5 w-full rounded-full bg-surface-100 dark:bg-surface-800">
                        <div
                            className={clsx(
                                'h-2.5 rounded-full transition-all',
                                usagePercentage >= 90 ? 'bg-red-500' :
                                usagePercentage >= 70 ? 'bg-amber-500' : 'bg-primary-500'
                            )}
                            style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                        />
                    </div>
                </div>
            </div>

            {/* Settings Links */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {settingsLinks.map((item) => {
                    const colors = getColorClasses(item.color);
                    return (
                        <Link
                            key={item.name}
                            href={route(item.href)}
                            className="group bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-lg hover:border-primary-200 dark:hover:border-primary-800 transition-all"
                        >
                            <div className="flex items-start gap-4">
                                <div className={clsx(
                                    'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl transition-colors',
                                    colors.iconBg,
                                    'group-hover:scale-105'
                                )}>
                                    <item.icon className={clsx('h-6 w-6', colors.icon)} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display font-semibold text-surface-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                            {item.name}
                                        </h3>
                                        <ChevronRight className="h-4 w-4 text-surface-300 dark:text-surface-600 group-hover:text-primary-500 dark:group-hover:text-primary-400 group-hover:translate-x-0.5 transition-all" />
                                    </div>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400 line-clamp-2">
                                        {item.description}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}
