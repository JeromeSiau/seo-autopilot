import { useEffect, useState, useCallback, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { PageProps, GeneratingArticle } from '@/types';
import { AgentEvent } from '@/Components/AgentActivity';

const MAX_EVENTS = 100;

export function useGlobalAgentActivity() {
    const { generatingArticles = [] } = usePage<PageProps>().props;
    const [events, setEvents] = useState<AgentEvent[]>([]);
    const [activeAgents, setActiveAgents] = useState<Map<number, string[]>>(new Map());
    const [isConnected, setIsConnected] = useState(false);
    const subscribedChannels = useRef<Set<number>>(new Set());

    // Subscribe to article channels
    useEffect(() => {
        if (!window.Echo || generatingArticles.length === 0) {
            return;
        }

        const articleIds = generatingArticles.map(a => a.id);

        // Subscribe to new channels
        for (const articleId of articleIds) {
            if (subscribedChannels.current.has(articleId)) continue;

            const channel = window.Echo.private(`article.${articleId}`);

            channel
                .listen('.agent.activity', (event: AgentEvent) => {
                    const eventWithArticle = { ...event, article_id: articleId };

                    setEvents(prev => {
                        const updated = [...prev, eventWithArticle];
                        return updated.length > MAX_EVENTS ? updated.slice(-MAX_EVENTS) : updated;
                    });

                    // Track active agents per article
                    if (event.event_type === 'started') {
                        setActiveAgents(prev => {
                            const updated = new Map(prev);
                            const current = updated.get(articleId) || [];
                            updated.set(articleId, [...new Set([...current, event.agent_type])]);
                            return updated;
                        });
                    } else if (event.event_type === 'completed' || event.event_type === 'error') {
                        setActiveAgents(prev => {
                            const updated = new Map(prev);
                            const current = updated.get(articleId) || [];
                            updated.set(articleId, current.filter(a => a !== event.agent_type));
                            return updated;
                        });
                    }
                })
                .subscribed(() => {
                    setIsConnected(true);
                });

            subscribedChannels.current.add(articleId);
        }

        // Cleanup function - unsubscribe from channels no longer needed
        return () => {
            for (const articleId of subscribedChannels.current) {
                if (!articleIds.includes(articleId)) {
                    window.Echo.leave(`article.${articleId}`);
                    subscribedChannels.current.delete(articleId);
                }
            }
        };
    }, [generatingArticles]);

    // Cleanup all channels on unmount
    useEffect(() => {
        return () => {
            for (const articleId of subscribedChannels.current) {
                window.Echo.leave(`article.${articleId}`);
            }
            subscribedChannels.current.clear();
        };
    }, []);

    const clearEvents = useCallback(() => {
        setEvents([]);
    }, []);

    const getEventsByArticle = useCallback((articleId: number) => {
        return events.filter(e => e.article_id === articleId);
    }, [events]);

    const totalActiveAgents = Array.from(activeAgents.values()).flat().length;

    return {
        events,
        generatingArticles,
        isConnected,
        activeAgents,
        totalActiveAgents,
        clearEvents,
        getEventsByArticle,
        hasNewEvents: events.length > 0,
        hasActiveGeneration: generatingArticles.length > 0,
    };
}
