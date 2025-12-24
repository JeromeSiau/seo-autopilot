import { useEffect, useState, useCallback } from 'react';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: 'started' | 'progress' | 'completed' | 'error';
    message: string;
    reasoning: string | null;
    metadata: Record<string, unknown> | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface UseAgentActivityOptions {
    articleId: number;
    enabled?: boolean;
}

export function useAgentActivity({ articleId, enabled = true }: UseAgentActivityOptions) {
    const [events, setEvents] = useState<AgentEvent[]>([]);
    const [isConnected, setIsConnected] = useState(false);
    const [activeAgents, setActiveAgents] = useState<string[]>([]);

    useEffect(() => {
        if (!enabled || !articleId || !window.Echo) {
            return;
        }

        const channel = window.Echo.private(`article.${articleId}`);

        channel
            .listen('.agent.activity', (event: AgentEvent) => {
                setEvents(prev => [...prev, event]);

                // Track active agents
                if (event.event_type === 'started') {
                    setActiveAgents(prev => [...new Set([...prev, event.agent_type])]);
                } else if (event.event_type === 'completed' || event.event_type === 'error') {
                    setActiveAgents(prev => prev.filter(a => a !== event.agent_type));
                }
            })
            .subscribed(() => {
                setIsConnected(true);
            })
            .error(() => {
                setIsConnected(false);
            });

        return () => {
            channel.stopListening('.agent.activity');
            window.Echo.leave(`article.${articleId}`);
        };
    }, [articleId, enabled]);

    const clearEvents = useCallback(() => {
        setEvents([]);
    }, []);

    const getEventsByAgent = useCallback((agentType: string) => {
        return events.filter(e => e.agent_type === agentType);
    }, [events]);

    const getLatestEventByAgent = useCallback((agentType: string) => {
        const agentEvents = events.filter(e => e.agent_type === agentType);
        return agentEvents[agentEvents.length - 1] || null;
    }, [events]);

    return {
        events,
        isConnected,
        activeAgents,
        clearEvents,
        getEventsByAgent,
        getLatestEventByAgent,
        hasNewEvents: events.length > 0,
    };
}
