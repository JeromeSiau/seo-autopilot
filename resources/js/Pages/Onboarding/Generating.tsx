import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import ProgressSteps from '@/Components/ContentPlan/ProgressSteps';
import Celebration from '@/Components/ContentPlan/Celebration';
import CalendarReveal from '@/Components/ContentPlan/CalendarReveal';
import { useTranslations } from '@/hooks/useTranslations';

interface Site {
    id: number;
    name: string;
    domain: string;
}

interface Step {
    name: string;
    status: 'pending' | 'running' | 'completed';
    icon?: string;
}

interface GenerationStatus {
    status: 'not_started' | 'pending' | 'running' | 'completed' | 'failed';
    current_step: number;
    total_steps: number;
    steps: Step[];
    keywords_found: number;
    articles_planned: number;
    error_message?: string;
}

interface Props {
    site: Site;
}

type Phase = 'progress' | 'celebration' | 'calendar';

export default function Generating({ site }: Props) {
    const { t } = useTranslations();
    const [status, setStatus] = useState<GenerationStatus | null>(null);
    const [phase, setPhase] = useState<Phase>('progress');

    // Polling
    useEffect(() => {
        const fetchStatus = async () => {
            try {
                const res = await fetch(`/sites/${site.id}/generation-status`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) {
                    console.error('API error:', res.status);
                    return;
                }

                const data = await res.json();
                setStatus(data);

                if (data.status === 'completed' && phase === 'progress') {
                    // Start celebration sequence
                    setTimeout(() => setPhase('celebration'), 500);
                    setTimeout(() => setPhase('calendar'), 3500);
                }
            } catch (e) {
                console.error('Failed to fetch status', e);
            }
        };

        // Initial fetch
        fetchStatus();

        // Poll every 2 seconds while in progress
        const interval = setInterval(() => {
            if (phase === 'progress') {
                fetchStatus();
            }
        }, 2000);

        return () => clearInterval(interval);
    }, [site.id, phase]);

    return (
        <div className="min-h-screen bg-surface-50 dark:bg-surface-900 transition-colors">
            <Head title={t?.contentPlan?.progress?.generatingTitle ?? 'Creating your Content Plan'} />

            <div className="max-w-3xl mx-auto px-4 py-12">
                {phase === 'progress' && (
                    <ProgressSteps status={status} />
                )}

                {phase === 'celebration' && (
                    <Celebration articlesPlanned={status?.articles_planned || 0} />
                )}

                {phase === 'calendar' && (
                    <CalendarReveal
                        site={site}
                        articlesPlanned={status?.articles_planned || 0}
                    />
                )}

                {/* Error state */}
                {status?.status === 'failed' && (
                    <div className="mt-8 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl">
                        <p className="text-red-700 dark:text-red-400">
                            {t?.contentPlan?.progress?.errorOccurred ?? 'An error occurred:'} {status.error_message}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
