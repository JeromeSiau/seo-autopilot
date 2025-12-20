import clsx from 'clsx';
import { ReactNode } from 'react';

interface TableProps {
    children: ReactNode;
    className?: string;
}

export function Table({ children, className }: TableProps) {
    return (
        <div className={clsx('overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5', className)}>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">{children}</table>
            </div>
        </div>
    );
}

export function TableHead({ children }: { children: ReactNode }) {
    return <thead className="bg-gray-50">{children}</thead>;
}

export function TableBody({ children }: { children: ReactNode }) {
    return <tbody className="divide-y divide-gray-200 bg-white">{children}</tbody>;
}

interface TableRowProps {
    children: ReactNode;
    className?: string;
    onClick?: () => void;
}

export function TableRow({ children, className, onClick }: TableRowProps) {
    return (
        <tr
            className={clsx(onClick && 'cursor-pointer hover:bg-gray-50', className)}
            onClick={onClick}
        >
            {children}
        </tr>
    );
}

interface TableHeaderProps {
    children: ReactNode;
    className?: string;
    align?: 'left' | 'center' | 'right';
}

export function TableHeader({ children, className, align = 'left' }: TableHeaderProps) {
    return (
        <th
            scope="col"
            className={clsx(
                'px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500',
                {
                    'text-left': align === 'left',
                    'text-center': align === 'center',
                    'text-right': align === 'right',
                },
                className
            )}
        >
            {children}
        </th>
    );
}

interface TableCellProps {
    children: ReactNode;
    className?: string;
    align?: 'left' | 'center' | 'right';
}

export function TableCell({ children, className, align = 'left' }: TableCellProps) {
    return (
        <td
            className={clsx(
                'whitespace-nowrap px-4 py-3 text-sm text-gray-900',
                {
                    'text-left': align === 'left',
                    'text-center': align === 'center',
                    'text-right': align === 'right',
                },
                className
            )}
        >
            {children}
        </td>
    );
}
