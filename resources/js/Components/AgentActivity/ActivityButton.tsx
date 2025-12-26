import { Activity } from 'lucide-react';
import { clsx } from 'clsx';
import { useTranslations } from '@/hooks/useTranslations';

interface ActivityButtonProps {
    activeAgents: number;
    hasNewEvents: boolean;
    onClick: () => void;
}

export function ActivityButton({ activeAgents, hasNewEvents, onClick }: ActivityButtonProps) {
    const { t } = useTranslations();

    return (
        <button
            onClick={onClick}
            className={clsx(
                'fixed bottom-6 right-6 flex items-center gap-2 px-4 py-3 rounded-full shadow-lg transition-all z-40',
                'hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-surface-900',
                activeAgents > 0
                    ? 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500'
                    : 'bg-surface-900 dark:bg-surface-800 text-white hover:bg-surface-800 dark:hover:bg-surface-700 focus:ring-surface-500'
            )}
            aria-label={activeAgents > 0 ? `${activeAgents} agent${activeAgents > 1 ? 's' : ''} actif${activeAgents > 1 ? 's' : ''}` : "Voir l'activité des agents"}
        >
            <Activity size={20} className={activeAgents > 0 ? 'animate-pulse' : ''} />

            {activeAgents > 0 ? (
                <span className="text-sm font-medium">
                    {activeAgents} agent{activeAgents > 1 ? 's' : ''} actif{activeAgents > 1 ? 's' : ''}
                </span>
            ) : (
                <span className="text-sm font-medium">{t?.activity?.title ?? 'Activity'}</span>
            )}

            {hasNewEvents && (
                <span className="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse" />
            )}
        </button>
    );
}
