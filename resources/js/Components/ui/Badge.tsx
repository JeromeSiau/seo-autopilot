import clsx from 'clsx';
import { ReactNode } from 'react';

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'secondary';

interface BadgeProps {
    children: ReactNode;
    variant?: BadgeVariant;
    size?: 'sm' | 'md';
}

const variantClasses: Record<BadgeVariant, string> = {
    default: 'bg-gray-100 text-gray-700',
    success: 'bg-green-100 text-green-700',
    warning: 'bg-yellow-100 text-yellow-700',
    danger: 'bg-red-100 text-red-700',
    info: 'bg-blue-100 text-blue-700',
    secondary: 'bg-purple-100 text-purple-700',
};

export function Badge({ children, variant = 'default', size = 'sm' }: BadgeProps) {
    return (
        <span
            className={clsx(
                'inline-flex items-center rounded-full font-medium',
                variantClasses[variant],
                {
                    'px-2 py-0.5 text-xs': size === 'sm',
                    'px-2.5 py-1 text-sm': size === 'md',
                }
            )}
        >
            {children}
        </span>
    );
}

// Helper function to get status badge variant
export function getStatusVariant(status: string): BadgeVariant {
    switch (status) {
        case 'published':
        case 'completed':
        case 'active':
            return 'success';
        case 'draft':
        case 'pending':
            return 'default';
        case 'review':
        case 'processing':
            return 'info';
        case 'approved':
            return 'secondary';
        case 'failed':
            return 'danger';
        default:
            return 'default';
    }
}
