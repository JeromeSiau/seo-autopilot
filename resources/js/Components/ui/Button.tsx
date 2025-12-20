import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { LucideIcon } from 'lucide-react';
import { ButtonHTMLAttributes, ReactNode } from 'react';

type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
type ButtonSize = 'sm' | 'md' | 'lg';

interface BaseButtonProps {
    variant?: ButtonVariant;
    size?: ButtonSize;
    icon?: LucideIcon;
    iconPosition?: 'left' | 'right';
    loading?: boolean;
    children: ReactNode;
    className?: string;
}

interface ButtonAsButton extends BaseButtonProps, Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> {
    as?: 'button';
    href?: never;
}

interface ButtonAsLink extends BaseButtonProps {
    as: 'link';
    href: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
}

type ButtonProps = ButtonAsButton | ButtonAsLink;

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 shadow-sm',
    secondary:
        'bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 shadow-sm',
    outline:
        'bg-transparent text-indigo-600 ring-1 ring-inset ring-indigo-600 hover:bg-indigo-50',
    ghost: 'bg-transparent text-gray-700 hover:bg-gray-100 hover:text-gray-900',
    danger: 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600 shadow-sm',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3 py-2 text-sm',
    lg: 'px-4 py-2.5 text-sm',
};

export function Button({
    variant = 'primary',
    size = 'md',
    icon: Icon,
    iconPosition = 'left',
    loading = false,
    children,
    className,
    ...props
}: ButtonProps) {
    const classes = clsx(
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
        variantClasses[variant],
        sizeClasses[size],
        className
    );

    const content = (
        <>
            {loading ? (
                <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                    <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                        fill="none"
                    />
                    <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
            ) : Icon && iconPosition === 'left' ? (
                <Icon className="h-4 w-4" />
            ) : null}
            {children}
            {Icon && iconPosition === 'right' && !loading && <Icon className="h-4 w-4" />}
        </>
    );

    if (props.as === 'link') {
        const { as, href, method, ...linkProps } = props;
        return (
            <Link href={href} method={method} className={classes} {...linkProps}>
                {content}
            </Link>
        );
    }

    const { as, ...buttonProps } = props;
    return (
        <button className={classes} disabled={loading} {...buttonProps}>
            {content}
        </button>
    );
}
