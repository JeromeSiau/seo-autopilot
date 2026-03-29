import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Rocket, Check, ChevronLeft, Sparkles } from 'lucide-react';
import clsx from 'clsx';
import { SiteMode } from '@/types';

interface Props {
    siteId: number;
    mode: SiteMode;
    onBack: () => void;
}

export default function Step6Launch({ siteId, mode, onBack }: Props) {
    const [loading, setLoading] = useState(false);
    const launchItems = mode === 'hosted'
        ? [
            'Génération du plan éditorial initial',
            'Découverte de mots-clés pertinents',
            'Création automatique d articles SEO',
            'Publication sur votre blog heberge',
        ]
        : [
            'Analyse de votre site et Search Console',
            'Découverte de mots-clés pertinents',
            'Génération automatique d articles SEO',
            'Publication selon votre planning',
        ];

    const handleLaunch = () => {
        setLoading(true);
        router.post(route('onboarding.complete', siteId));
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="relative mx-auto flex h-16 w-16 items-center justify-center">
                    <div className="absolute inset-0 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 animate-pulse" />
                    <div className="relative flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-600">
                        <Rocket className="h-7 w-7 text-white" />
                    </div>
                </div>
                <h2 className="mt-5 font-display text-2xl font-bold text-surface-900 dark:text-white">
                    Prêt à lancer !
                </h2>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    {mode === 'hosted'
                        ? 'Votre blog heberge et votre autopilot sont prêts a démarrer.'
                        : 'Votre autopilot est configuré et prêt à démarrer'}
                </p>
            </div>

            {/* What happens next */}
            <div className="rounded-2xl bg-gradient-to-br from-primary-50 to-primary-100/50 dark:from-primary-500/15 dark:to-primary-500/5 p-6 border border-primary-100 dark:border-primary-500/20">
                <div className="flex items-center gap-2 mb-4">
                    <Sparkles className="h-5 w-5 text-primary-600" />
                    <h3 className="font-display font-semibold text-primary-900 dark:text-primary-400">
                        Ce qui va se passer
                    </h3>
                </div>
                <ul className="space-y-3">
                    {launchItems.map((item, i) => (
                        <li key={i} className="flex items-center gap-3">
                            <div className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary-500">
                                <Check className="h-3.5 w-3.5 text-white" />
                            </div>
                            <span className="text-sm text-primary-800 dark:text-primary-300">{item}</span>
                        </li>
                    ))}
                </ul>
            </div>

            {/* Launch Button */}
            <button
                onClick={handleLaunch}
                disabled={loading}
                className={clsx(
                    'group relative flex w-full items-center justify-center gap-3 rounded-xl px-6 py-4',
                    'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold text-lg',
                    'shadow-green hover:shadow-green-lg',
                    'transition-all disabled:opacity-50 disabled:cursor-not-allowed',
                    !loading && 'hover:-translate-y-0.5'
                )}
            >
                {loading ? (
                    <>
                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                        <span>Activation en cours...</span>
                    </>
                ) : (
                    <>
                        <Rocket className="h-5 w-5 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                        <span>Activer l'Autopilot</span>
                    </>
                )}
            </button>

            {/* Navigation */}
            <div className="pt-2">
                <button
                    onClick={onBack}
                    disabled={loading}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors disabled:opacity-50"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Retour
                </button>
            </div>
        </div>
    );
}
