import { useState, useEffect } from 'react';
import { Check, Loader2, Circle, Globe, BarChart3, Sparkles, Search, TrendingUp, Calendar, X } from 'lucide-react';
import clsx from 'clsx';
import { useTranslations } from '@/hooks/useTranslations';

interface Step {
    name: string;
    status: 'pending' | 'running' | 'completed';
    icon?: string;
    started_at?: string;
    completed_at?: string;
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
    status: GenerationStatus | null;
}

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    globe: Globe,
    chart: BarChart3,
    sparkles: Sparkles,
    search: Search,
    trending: TrendingUp,
    calendar: Calendar,
};

export default function ProgressSteps({ status }: Props) {
    const { t } = useTranslations();
    const [tipIndex, setTipIndex] = useState(0);

    const TIPS = [
        t?.contentPlan?.tips?.tip1 ?? "We analyze Google data to find your best SEO opportunities",
        t?.contentPlan?.tips?.tip2 ?? "The more data your site has, the better the plan",
        t?.contentPlan?.tips?.tip3 ?? "Articles will be optimized for your target audience",
        t?.contentPlan?.tips?.tip4 ?? "Each topic is selected for its traffic potential",
        t?.contentPlan?.tips?.tip5 ?? "Our AI avoids topics you've already covered",
    ];

    useEffect(() => {
        const interval = setInterval(() => {
            setTipIndex((i) => (i + 1) % TIPS.length);
        }, 5000);
        return () => clearInterval(interval);
    }, []);

    if (!status) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
            </div>
        );
    }

    // Show waiting message when job is pending (in queue but not started)
    const isWaiting = status.status === 'pending' || status.status === 'not_started' || !status.steps?.length;

    if (isWaiting) {
        return (
            <div className="space-y-8">
                {/* Header */}
                <div className="text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-100 dark:bg-primary-500/20 mb-4">
                        <Loader2 className="h-8 w-8 text-primary-600 dark:text-primary-400 animate-spin" />
                    </div>
                    <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                        {t?.contentPlan?.progress?.queuedTitle ?? 'Preparing...'}
                    </h1>
                    <p className="mt-2 text-surface-500 dark:text-surface-400">
                        {t?.contentPlan?.progress?.queuedSubtitle ?? 'Your Content Plan is in the queue. Analysis will start shortly.'}
                    </p>
                </div>

                {/* Animated waiting indicator */}
                <div className="max-w-md mx-auto">
                    <div className="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                        <div className="h-full w-1/3 bg-primary-500/50 rounded-full animate-pulse" />
                    </div>
                    <p className="mt-4 text-center text-sm text-surface-500 dark:text-surface-400">
                        💡 {TIPS[tipIndex]}
                    </p>
                </div>
            </div>
        );
    }

    const progress = status.steps
        ? (status.steps.filter(s => s.status === 'completed').length / status.steps.length) * 100
        : 0;

    return (
        <div className="space-y-8">
            {/* Header */}
            <div className="text-center">
                <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-100 dark:bg-primary-500/20 mb-4">
                    <Sparkles className="h-8 w-8 text-primary-600 dark:text-primary-400" />
                </div>
                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                    {t?.contentPlan?.progress?.generatingTitle ?? 'Creating your Content Plan'}
                </h1>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    {t?.contentPlan?.progress?.generatingSubtitle ?? 'We are analyzing your site and preparing your content calendar'}
                </p>
            </div>

            {/* Steps */}
            <div className="max-w-md mx-auto space-y-3">
                {status.steps?.map((step, index) => {
                    const IconComponent = ICONS[step.icon || 'circle'] || Circle;

                    return (
                        <div
                            key={index}
                            className={clsx(
                                'flex items-center gap-4 p-4 rounded-xl transition-all',
                                step.status === 'completed' && 'bg-primary-50 dark:bg-primary-500/10',
                                step.status === 'running' && 'bg-primary-100 dark:bg-primary-500/20 ring-2 ring-primary-500',
                                step.status === 'pending' && 'bg-surface-50 dark:bg-surface-800/50'
                            )}
                        >
                            {/* Icon */}
                            <div className={clsx(
                                'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                step.status === 'completed' && 'bg-primary-500 text-white',
                                step.status === 'running' && 'bg-primary-500 text-white',
                                step.status === 'pending' && 'bg-surface-200 dark:bg-surface-700 text-surface-400'
                            )}>
                                {step.status === 'completed' ? (
                                    <Check className="h-5 w-5" />
                                ) : step.status === 'running' ? (
                                    <Loader2 className="h-5 w-5 animate-spin" />
                                ) : (
                                    <IconComponent className="h-5 w-5" />
                                )}
                            </div>

                            {/* Text */}
                            <span className={clsx(
                                'font-medium',
                                step.status === 'completed' && 'text-primary-700 dark:text-primary-400',
                                step.status === 'running' && 'text-primary-900 dark:text-primary-300',
                                step.status === 'pending' && 'text-surface-400 dark:text-surface-500'
                            )}>
                                {step.name}
                                {step.status === 'running' && '...'}
                            </span>
                        </div>
                    );
                })}
            </div>

            {/* Progress bar */}
            <div className="max-w-md mx-auto">
                <div className="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                    <div
                        className="h-full bg-primary-500 rounded-full transition-all duration-500"
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <p className="mt-2 text-center text-sm text-surface-500 dark:text-surface-400">
                    {Math.round(progress)}{t?.contentPlan?.progress?.completed ?? '% completed'}
                </p>
            </div>

            {/* Rotating tip */}
            <div className="max-w-md mx-auto text-center">
                <p className="text-sm text-surface-500 dark:text-surface-400 transition-opacity duration-300">
                    💡 {TIPS[tipIndex]}
                </p>
            </div>

            {/* Dismissible hint */}
            <div className="max-w-md mx-auto mt-6">
                <div className="flex items-center gap-3 p-3 rounded-xl bg-surface-100 dark:bg-surface-800/50 text-surface-600 dark:text-surface-400 text-sm">
                    <X className="h-4 w-4 flex-shrink-0" />
                    <span>
                        {t?.contentPlan?.progress?.backgroundNote ?? 'You can close this page, generation continues in the background.'}
                    </span>
                </div>
            </div>
        </div>
    );
}
