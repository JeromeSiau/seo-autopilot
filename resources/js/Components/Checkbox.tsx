import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-700 text-primary-600 shadow-sm focus:ring-primary-500 dark:focus:ring-primary-400 transition-colors ' +
                className
            }
        />
    );
}
