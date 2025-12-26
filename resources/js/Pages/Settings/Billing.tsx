import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Check, Zap, Building2, Rocket } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import clsx from 'clsx';

interface Plan {
    id: number;
    slug: string;
    name: string;
    price: number;
    articles_per_month: number;
    sites_limit: number;
    features: string[];
}

interface Props {
    team: {
        id: number;
        name: string;
        is_trial: boolean;
        trial_ends_at: string | null;
        plan: Plan | null;
        subscribed: boolean;
    };
    plans: Plan[];
}

const planIcons: Record<string, typeof Zap> = {
    starter: Zap,
    pro: Rocket,
    agency: Building2,
};

export default function Billing({ team, plans }: Props) {
    const [loading, setLoading] = useState<number | null>(null);

    const handleCheckout = (planId: number) => {
        setLoading(planId);
        router.post(route('billing.checkout'), { plan_id: planId });
    };

    const handlePortal = () => {
        router.post(route('billing.portal'));
    };

    const trialDaysLeft = team.trial_ends_at
        ? Math.max(0, Math.ceil((new Date(team.trial_ends_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24)))
        : 0;

    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                        Facturation
                    </h1>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Gérez votre abonnement et vos moyens de paiement
                    </p>
                </div>
            }
        >
            <Head title="Facturation" />

            <div className="mx-auto max-w-5xl">
                {/* Current Status */}
                <div className="rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-6">
                    <h2 className="text-lg font-semibold text-surface-900 dark:text-white">
                        Votre abonnement
                    </h2>

                    {team.is_trial ? (
                        <div className="mt-4">
                            <div className="flex items-center gap-3">
                                <span className="rounded-full bg-amber-100 dark:bg-amber-500/20 px-3 py-1 text-sm font-medium text-amber-700 dark:text-amber-400">
                                    Période d'essai
                                </span>
                                {trialDaysLeft > 0 ? (
                                    <span className="text-sm text-surface-500">
                                        {trialDaysLeft} jour{trialDaysLeft > 1 ? 's' : ''} restant{trialDaysLeft > 1 ? 's' : ''}
                                    </span>
                                ) : (
                                    <span className="text-sm text-red-500 font-medium">
                                        Expirée
                                    </span>
                                )}
                            </div>
                            <p className="mt-2 text-surface-600 dark:text-surface-400">
                                2 articles gratuits pour découvrir la plateforme
                            </p>
                        </div>
                    ) : team.plan ? (
                        <div className="mt-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className="rounded-full bg-primary-100 dark:bg-primary-500/20 px-3 py-1 text-sm font-medium text-primary-700 dark:text-primary-400">
                                        Plan {team.plan.name}
                                    </span>
                                    <span className="text-2xl font-bold text-surface-900 dark:text-white">
                                        {team.plan.price}€/mois
                                    </span>
                                </div>
                                <button
                                    onClick={handlePortal}
                                    className="rounded-lg border border-surface-300 dark:border-surface-600 px-4 py-2 text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors"
                                >
                                    Gérer l'abonnement
                                </button>
                            </div>
                            <p className="mt-2 text-surface-600 dark:text-surface-400">
                                {team.plan.articles_per_month} articles/mois •{' '}
                                {team.plan.sites_limit === -1 ? 'Sites illimités' : `${team.plan.sites_limit} site${team.plan.sites_limit > 1 ? 's' : ''}`}
                            </p>
                        </div>
                    ) : null}
                </div>

                {/* Plans */}
                <div className="mt-8">
                    <h2 className="text-lg font-semibold text-surface-900 dark:text-white">
                        {team.plan ? 'Changer de plan' : 'Choisir un plan'}
                    </h2>

                    <div className="mt-6 grid gap-6 md:grid-cols-3">
                        {plans.map((plan) => {
                            const Icon = planIcons[plan.slug] || Zap;
                            const isCurrent = team.plan?.id === plan.id;
                            const isPopular = plan.slug === 'pro';

                            return (
                                <div
                                    key={plan.id}
                                    className={clsx(
                                        'relative rounded-2xl border p-6 transition-all',
                                        isCurrent
                                            ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-500/10 ring-2 ring-primary-500/20'
                                            : 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600'
                                    )}
                                >
                                    {isPopular && !isCurrent && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <span className="rounded-full bg-primary-500 px-3 py-1 text-xs font-semibold text-white">
                                                Populaire
                                            </span>
                                        </div>
                                    )}

                                    <div className="flex items-center gap-3">
                                        <div className={clsx(
                                            'flex h-10 w-10 items-center justify-center rounded-xl',
                                            isCurrent ? 'bg-primary-100 dark:bg-primary-500/20' : 'bg-surface-100 dark:bg-surface-700'
                                        )}>
                                            <Icon className={clsx(
                                                'h-5 w-5',
                                                isCurrent ? 'text-primary-600' : 'text-surface-500'
                                            )} />
                                        </div>
                                        <h3 className="text-lg font-semibold text-surface-900 dark:text-white">
                                            {plan.name}
                                        </h3>
                                    </div>

                                    <div className="mt-4">
                                        <span className="text-4xl font-bold text-surface-900 dark:text-white">
                                            {plan.price}€
                                        </span>
                                        <span className="text-surface-500">/mois</span>
                                    </div>

                                    <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                                        {plan.articles_per_month} articles/mois •{' '}
                                        {plan.sites_limit === -1 ? 'Sites illimités' : `${plan.sites_limit} site${plan.sites_limit > 1 ? 's' : ''}`}
                                    </p>

                                    <ul className="mt-6 space-y-3">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm">
                                                <Check className="h-5 w-5 flex-shrink-0 text-primary-500" />
                                                <span className="text-surface-600 dark:text-surface-400">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <button
                                        onClick={() => handleCheckout(plan.id)}
                                        disabled={isCurrent || loading !== null}
                                        className={clsx(
                                            'mt-6 w-full rounded-xl py-3 text-sm font-semibold transition-all',
                                            isCurrent
                                                ? 'bg-surface-100 dark:bg-surface-700 text-surface-400 cursor-not-allowed'
                                                : 'bg-primary-500 text-white hover:bg-primary-600 shadow-green hover:shadow-green-lg'
                                        )}
                                    >
                                        {loading === plan.id ? (
                                            <span className="flex items-center justify-center gap-2">
                                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                                Redirection...
                                            </span>
                                        ) : isCurrent ? (
                                            'Plan actuel'
                                        ) : team.plan ? (
                                            plan.price > team.plan.price ? 'Upgrader' : 'Downgrader'
                                        ) : (
                                            'Choisir ce plan'
                                        )}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* FAQ */}
                <div className="mt-12 rounded-2xl bg-surface-50 dark:bg-surface-800/50 p-6">
                    <h3 className="font-semibold text-surface-900 dark:text-white">
                        Questions fréquentes
                    </h3>
                    <div className="mt-4 space-y-4 text-sm text-surface-600 dark:text-surface-400">
                        <p>
                            <strong>Puis-je changer de plan ?</strong><br />
                            Oui, vous pouvez upgrader à tout moment. Le changement prend effet immédiatement avec un prorata.
                            Les downgrades prennent effet à la fin de votre période de facturation.
                        </p>
                        <p>
                            <strong>Que se passe-t-il si je dépasse mon quota ?</strong><br />
                            Vous ne pourrez plus générer d'articles jusqu'au renouvellement de votre quota le mois suivant.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
