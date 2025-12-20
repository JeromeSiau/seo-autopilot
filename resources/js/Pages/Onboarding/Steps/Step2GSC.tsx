import { useState } from 'react';
import axios from 'axios';
import { Search, ArrowRight, ChevronLeft, TrendingUp, Target, BarChart3 } from 'lucide-react';
import clsx from 'clsx';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

const BENEFITS = [
    {
        icon: Target,
        title: 'Découverte automatique',
        description: 'Identifie les mots-clés performants',
    },
    {
        icon: TrendingUp,
        title: 'Opportunités cachées',
        description: 'Positions 5-30 à exploiter',
    },
    {
        icon: BarChart3,
        title: 'Suivi des performances',
        description: 'Analytics en temps réel',
    },
];

export default function Step2GSC({ siteId, onNext, onBack }: Props) {
    const [loading, setLoading] = useState(false);

    const handleConnect = async () => {
        setLoading(true);
        try {
            const response = await axios.post(route('onboarding.step2', siteId), { skip: false });
            if (response.data.redirect) {
                window.location.href = response.data.redirect;
            }
        } catch (error) {
            console.error(error);
            setLoading(false);
        }
    };

    const handleSkip = async () => {
        setLoading(true);
        try {
            await axios.post(route('onboarding.step2', siteId), { skip: true });
            onNext();
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-blue-200">
                    <Search className="h-7 w-7 text-blue-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900">
                    Google Search Console
                </h2>
                <p className="mt-2 text-surface-500">
                    Connectez votre Search Console pour découvrir automatiquement des opportunités
                </p>
            </div>

            {/* Benefits */}
            <div className="grid gap-3">
                {BENEFITS.map((benefit) => (
                    <div
                        key={benefit.title}
                        className="flex items-start gap-3 rounded-xl bg-surface-50 p-4"
                    >
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100">
                            <benefit.icon className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="font-medium text-surface-900">{benefit.title}</p>
                            <p className="text-sm text-surface-500">{benefit.description}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Connect Button */}
            <button
                onClick={handleConnect}
                disabled={loading}
                className={clsx(
                    'flex w-full items-center justify-center gap-3 rounded-xl px-6 py-3.5',
                    'bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold',
                    'shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30',
                    'hover:-translate-y-0.5 transition-all',
                    'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                )}
            >
                {loading ? (
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                ) : (
                    <>
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Connecter Google Search Console
                    </>
                )}
            </button>

            {/* Skip Option */}
            <button
                onClick={handleSkip}
                disabled={loading}
                className="flex w-full items-center justify-center gap-2 py-2 text-sm text-surface-500 hover:text-surface-700 transition-colors"
            >
                Passer cette étape
                <ArrowRight className="h-4 w-4" />
            </button>

            {/* Navigation */}
            <div className="pt-2">
                <button
                    onClick={onBack}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Retour
                </button>
            </div>
        </div>
    );
}
