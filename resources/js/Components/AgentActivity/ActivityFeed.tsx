import { useRef, useEffect } from 'react';
import { ActivityItem } from './ActivityItem';
import { AgentBadge } from './AgentBadge';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: string;
    message: string;
    reasoning: string | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface ActivityFeedProps {
    events: AgentEvent[];
    groupByAgent?: boolean;
}

export function ActivityFeed({ events, groupByAgent = true }: ActivityFeedProps) {
    const feedRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom on new events
    useEffect(() => {
        if (feedRef.current) {
            feedRef.current.scrollTop = feedRef.current.scrollHeight;
        }
    }, [events]);

    if (events.length === 0) {
        return (
            <div className="text-center py-8 text-gray-500">
                <p>Aucune activité pour le moment</p>
            </div>
        );
    }

    if (!groupByAgent) {
        return (
            <div ref={feedRef} className="space-y-1 max-h-96 overflow-y-auto">
                {events.map(event => (
                    <ActivityItem key={event.id} event={event} />
                ))}
            </div>
        );
    }

    // Group events by agent
    const groupedEvents = events.reduce((acc, event) => {
        if (!acc[event.agent_type]) {
            acc[event.agent_type] = [];
        }
        acc[event.agent_type].push(event);
        return acc;
    }, {} as Record<string, AgentEvent[]>);

    const agentOrder = ['research', 'competitor', 'outline', 'writing', 'fact_checker', 'polish', 'internal_linking'];
    const sortedAgents = Object.keys(groupedEvents).sort(
        (a, b) => agentOrder.indexOf(a) - agentOrder.indexOf(b)
    );

    return (
        <div ref={feedRef} className="space-y-4 max-h-96 overflow-y-auto">
            {sortedAgents.map(agentType => {
                const agentEvents = groupedEvents[agentType];
                const lastEvent = agentEvents[agentEvents.length - 1];
                const isActive = lastEvent.event_type !== 'completed' && lastEvent.event_type !== 'error';

                return (
                    <div key={agentType} className="border rounded-lg overflow-hidden">
                        <div className="flex items-center justify-between px-3 py-2 bg-gray-50 border-b">
                            <AgentBadge agentType={agentType} size="sm" />
                            {isActive && (
                                <span className="flex items-center gap-1 text-xs text-blue-600">
                                    <span className="w-2 h-2 bg-blue-500 rounded-full animate-pulse" />
                                    En cours
                                </span>
                            )}
                        </div>
                        <div className="divide-y divide-gray-100">
                            {agentEvents.map(event => (
                                <ActivityItem key={event.id} event={event} />
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
