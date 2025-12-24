export interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: 'started' | 'progress' | 'completed' | 'error';
    message: string;
    reasoning: string | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

export type AgentType = 'research' | 'competitor' | 'fact_checker' | 'internal_linking' | 'writing' | 'outline' | 'polish';
export type EventType = 'started' | 'progress' | 'completed' | 'error';
