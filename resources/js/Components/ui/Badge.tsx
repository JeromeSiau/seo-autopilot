import clsx from 'clsx';
import { ReactNode } from 'react';

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' | 'primary';

interface BadgeProps {
    children: ReactNode;
    variant?: BadgeVariant;
    size?: 'sm' | 'md';
}

const variantClasses: Record<BadgeVariant, string> = {
    default: 'bg-surface-100 text-surface-700 dark:bg-surface-700 dark:text-surface-200',
    success: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    secondary: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    primary: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
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
