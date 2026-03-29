import axios from 'axios';
import IntegrationForm from '@/Components/Integration/IntegrationForm';
import { SiteMode } from '@/types';
import { ChevronLeft, ChevronRight, Globe, Server } from 'lucide-react';
import { useState } from 'react';
import clsx from 'clsx';

interface Props {
    siteId: number;
    mode: SiteMode;
    stagingDomain?: string | null;
    onNext: () => void;
    onBack: () => void;
}

export default function Step5Integration({ siteId, mode, stagingDomain, onNext, onBack }: Props) {
    const [loading, setLoading] = useState(false);

    if (mode === 'hosted') {
        const handleProvision = async () => {
            if (stagingDomain) {
                onNext();
                return;
            }

            setLoading(true);

            try {
                await axios.post(route('sites.hosting.provision-staging', { site: siteId }));
                onNext();
            } finally {
                setLoading(false);
            }
        };

        return (
            <div className="space-y-6">
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10">
                        <Server className="h-7 w-7 text-primary-600" />
                    </div>
                    <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                        Provisionner le blog heberge
                    </h2>
                    <p className="mt-2 text-surface-500 dark:text-surface-400">
                        On cree le domaine de staging maintenant, puis vous pourrez connecter le domaine client depuis l'espace hosting.
                    </p>
                </div>

                <div className="rounded-2xl border border-surface-200 bg-surface-50 p-5 dark:border-surface-700 dark:bg-surface-800/50">
                    <div className="flex items-start gap-4">
                        <div className="rounded-xl bg-white p-3 dark:bg-surface-900">
                            <Globe className="h-5 w-5 text-primary-600" />
                        </div>
                        <div>
                            <p className="font-semibold text-surface-900 dark:text-white">Etat actuel</p>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                {stagingDomain
                                    ? `Staging deja provisionne: ${stagingDomain}`
                                    : 'Aucun staging n est encore provisionne pour ce site.'}
                            </p>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={handleProvision}
                    disabled={loading}
                    className={clsx(
                        'flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3',
                        'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold',
                        'shadow-green hover:shadow-green-lg hover:-translate-y-0.5',
                        'transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                    )}
                >
                    {loading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <>
                            {stagingDomain ? 'Continuer vers le lancement' : 'Provisionner le staging'}
                            <ChevronRight className="h-5 w-5" />
                        </>
                    )}
                </button>

                <button
                    type="button"
                    onClick={onBack}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Retour
                </button>
            </div>
        );
    }

    return (
        <IntegrationForm
            siteId={siteId}
            onSuccess={onNext}
            onSkip={onNext}
            onBack={onBack}
            showSkip={true}
            showBack={true}
        />
    );
}
