import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from './Button';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: {
        label: string;
        href?: string;
        onClick?: () => void;
    };
    children?: ReactNode;
}

export function EmptyState({ icon: Icon, title, description, action, children }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-800 px-6 py-12 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-700">
                <Icon className="h-6 w-6 text-surface-400" />
            </div>
            <h3 className="mt-4 text-sm font-semibold text-surface-900 dark:text-white">{title}</h3>
            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{description}</p>
            {action && (
                <div className="mt-6">
                    {action.href ? (
                        <Button as="link" href={action.href}>
                            {action.label}
                        </Button>
                    ) : (
                        <Button onClick={action.onClick}>{action.label}</Button>
                    )}
                </div>
            )}
            {children && <div className="mt-6">{children}</div>}
        </div>
    );
}
