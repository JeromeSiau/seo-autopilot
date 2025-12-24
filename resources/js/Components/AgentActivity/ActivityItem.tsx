import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { clsx } from 'clsx';

interface ActivityItemProps {
    event: {
        id: number;
        agent_type: string;
        event_type: string;
        message: string;
        reasoning: string | null;
        progress_current: number | null;
        progress_total: number | null;
        progress_percent: number | null;
        created_at: string;
    };
}

export function ActivityItem({ event }: ActivityItemProps) {
    const [expanded, setExpanded] = useState(false);

    const statusIcon = {
        started: '🚀',
        progress: '⏳',
        completed: '✅',
        error: '❌',
    }[event.event_type] || '•';

    const timeAgo = formatDistanceToNow(new Date(event.created_at), {
        addSuffix: true,
        locale: fr
    });

    return (
        <div className={clsx(
            'py-2 px-3 border-l-2',
            event.event_type === 'error' && 'border-red-500 bg-red-50',
            event.event_type === 'completed' && 'border-green-500',
            event.event_type === 'started' && 'border-blue-500',
            event.event_type === 'progress' && 'border-gray-300',
        )}>
            <div className="flex items-start gap-2">
                <span className="text-sm">{statusIcon}</span>
                <div className="flex-1 min-w-0">
                    <p className="text-sm text-gray-900">{event.message}</p>

                    {event.progress_percent !== null && (
                        <div className="mt-1">
                            <div className="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-blue-500 transition-all duration-300"
                                    style={{ width: `${event.progress_percent}%` }}
                                />
                            </div>
                            <span className="text-xs text-gray-500">
                                {event.progress_current}/{event.progress_total}
                            </span>
                        </div>
                    )}

                    {event.reasoning && (
                        <button
                            onClick={() => setExpanded(!expanded)}
                            className="flex items-center gap-1 mt-1 text-xs text-gray-500 hover:text-gray-700"
                        >
                            {expanded ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
                            Détails
                        </button>
                    )}

                    {expanded && event.reasoning && (
                        <p className="mt-1 text-xs text-gray-600 italic bg-gray-50 p-2 rounded">
                            {event.reasoning}
                        </p>
                    )}
                </div>
                <span className="text-xs text-gray-400 whitespace-nowrap">{timeAgo}</span>
            </div>
        </div>
    );
}
