import clsx from 'clsx';
import { ReactNode } from 'react';

interface CardProps {
    children: ReactNode;
    className?: string;
    padding?: 'none' | 'sm' | 'md' | 'lg';
}

export function Card({ children, className, padding = 'md' }: CardProps) {
    return (
        <div
            className={clsx(
                'rounded-xl bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl shadow-sm dark:shadow-card-dark ring-1 ring-surface-200 dark:ring-surface-800',
                {
                    'p-0': padding === 'none',
                    'p-4': padding === 'sm',
                    'p-6': padding === 'md',
                    'p-8': padding === 'lg',
                },
                className
            )}
        >
            {children}
        </div>
    );
}

interface CardHeaderProps {
    title: string;
    description?: string;
    action?: ReactNode;
}

export function CardHeader({ title, description, action }: CardHeaderProps) {
    return (
        <div className="flex items-center justify-between">
            <div>
                <h3 className="text-base font-semibold text-surface-900 dark:text-white">{title}</h3>
                {description && <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{description}</p>}
            </div>
            {action && <div>{action}</div>}
        </div>
    );
}
