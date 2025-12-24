import { Search, BarChart3, CheckCircle, Link2, PenTool, Sparkles, AlertCircle } from 'lucide-react';
import { clsx } from 'clsx';

const AGENT_CONFIG: Record<string, { icon: typeof Search; color: string; label: string }> = {
    research: { icon: Search, color: 'text-blue-500 bg-blue-50', label: 'Research' },
    competitor: { icon: BarChart3, color: 'text-purple-500 bg-purple-50', label: 'Competitor' },
    fact_checker: { icon: CheckCircle, color: 'text-orange-500 bg-orange-50', label: 'Fact Check' },
    internal_linking: { icon: Link2, color: 'text-green-500 bg-green-50', label: 'Linking' },
    writing: { icon: PenTool, color: 'text-indigo-500 bg-indigo-50', label: 'Writing' },
    outline: { icon: Sparkles, color: 'text-cyan-500 bg-cyan-50', label: 'Outline' },
    polish: { icon: Sparkles, color: 'text-pink-500 bg-pink-50', label: 'Polish' },
};

interface AgentBadgeProps {
    agentType: string;
    size?: 'sm' | 'md';
    showLabel?: boolean;
}

export function AgentBadge({ agentType, size = 'md', showLabel = true }: AgentBadgeProps) {
    const config = AGENT_CONFIG[agentType] || {
        icon: AlertCircle,
        color: 'text-gray-500 bg-gray-50',
        label: agentType
    };

    const Icon = config.icon;
    const iconSize = size === 'sm' ? 14 : 18;

    return (
        <span className={clsx(
            'inline-flex items-center gap-1.5 rounded-full font-medium',
            config.color,
            size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'
        )}>
            <Icon size={iconSize} />
            {showLabel && config.label}
        </span>
    );
}
