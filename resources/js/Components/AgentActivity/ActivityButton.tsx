import { Activity } from 'lucide-react';
import { clsx } from 'clsx';

interface ActivityButtonProps {
    activeAgents: number;
    hasNewEvents: boolean;
    onClick: () => void;
}

export function ActivityButton({ activeAgents, hasNewEvents, onClick }: ActivityButtonProps) {
    return (
        <button
            onClick={onClick}
            className={clsx(
                'fixed bottom-6 right-6 flex items-center gap-2 px-4 py-3 rounded-full shadow-lg transition-all',
                'hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2',
                activeAgents > 0
                    ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500'
                    : 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500'
            )}
            aria-label={activeAgents > 0 ? `${activeAgents} agent${activeAgents > 1 ? 's' : ''} actif${activeAgents > 1 ? 's' : ''}` : "Voir l'activité des agents"}
        >
            <Activity size={20} className={activeAgents > 0 ? 'animate-pulse' : ''} />

            {activeAgents > 0 ? (
                <span className="text-sm font-medium">
                    {activeAgents} agent{activeAgents > 1 ? 's' : ''} actif{activeAgents > 1 ? 's' : ''}
                </span>
            ) : (
                <span className="text-sm font-medium">Activité</span>
            )}

            {hasNewEvents && (
                <span className="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full" />
            )}
        </button>
    );
}
