interface LogoProps {
    className?: string;
    showText?: boolean;
    size?: 'sm' | 'md' | 'lg';
    variant?: 'default' | 'white' | 'mono';
}

const sizes = {
    sm: { icon: 24, text: 'text-lg', gap: 'gap-2' },
    md: { icon: 32, text: 'text-xl', gap: 'gap-2.5' },
    lg: { icon: 44, text: 'text-2xl', gap: 'gap-3' },
};

export default function Logo({
    className = '',
    showText = true,
    size = 'md',
    variant = 'default'
}: LogoProps) {
    const { icon: iconSize, text: textSize, gap } = sizes[size];

    // For default variant, we show green icon + dark text in light mode, green icon + white text in dark mode
    // For white variant, everything is white (for use on colored backgrounds)
    // For mono variant, everything is dark (for print, etc.)

    return (
        <div className={`flex items-center ${gap} ${className}`}>
            <svg
                width={iconSize}
                height={iconSize}
                viewBox="0 0 48 48"
                fill="none"
                className="flex-shrink-0"
            >
                {variant === 'white' ? (
                    <>
                        <path d="M22 8C22 8 22 26 22 28" stroke="#ffffff" strokeWidth="2.5" strokeLinecap="round" />
                        <path d="M22 10C28 12 34 18 36 26C30 26 22 26 22 26V10Z" fill="#ffffff" />
                        <path d="M6 34C10 32 14 34 18 33C22 32 26 34 30 33C34 32 38 34 42 33" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" />
                        <path d="M8 40C12 38 16 40 20 39C24 38 28 40 32 39C36 38 40 40 42 39" stroke="rgba(255,255,255,0.7)" strokeWidth="1.5" strokeLinecap="round" />
                    </>
                ) : variant === 'mono' ? (
                    <>
                        <path d="M22 8C22 8 22 26 22 28" stroke="#1a1a1a" strokeWidth="2.5" strokeLinecap="round" />
                        <path d="M22 10C28 12 34 18 36 26C30 26 22 26 22 26V10Z" fill="#1a1a1a" />
                        <path d="M6 34C10 32 14 34 18 33C22 32 26 34 30 33C34 32 38 34 42 33" stroke="#1a1a1a" strokeWidth="2" strokeLinecap="round" />
                        <path d="M8 40C12 38 16 40 20 39C24 38 28 40 32 39C36 38 40 40 42 39" stroke="#374151" strokeWidth="1.5" strokeLinecap="round" />
                    </>
                ) : (
                    <>
                        {/* Default: green icon */}
                        <path d="M22 8C22 8 22 26 22 28" stroke="#10b981" strokeWidth="2.5" strokeLinecap="round" />
                        <path d="M22 10C28 12 34 18 36 26C30 26 22 26 22 26V10Z" fill="#10b981" />
                        <path d="M6 34C10 32 14 34 18 33C22 32 26 34 30 33C34 32 38 34 42 33" stroke="#10b981" strokeWidth="2" strokeLinecap="round" />
                        <path d="M8 40C12 38 16 40 20 39C24 38 28 40 32 39C36 38 40 40 42 39" stroke="#34d399" strokeWidth="1.5" strokeLinecap="round" />
                    </>
                )}
            </svg>

            {showText && (
                <span className={`font-display font-extrabold ${textSize} tracking-tight ${
                    variant === 'white' ? 'text-white' :
                    variant === 'mono' ? 'text-surface-900' :
                    'text-surface-900 dark:text-white'
                }`}>
                    RankCruise
                    <span className={`inline-block w-1.5 h-1.5 rounded-full ml-0.5 align-super ${
                        variant === 'white' ? 'bg-white' :
                        variant === 'mono' ? 'bg-surface-900' :
                        'bg-primary-500'
                    }`} />
                </span>
            )}
        </div>
    );
}

// Icon-only version for favicons and compact displays
export function LogoIcon({
    size = 32,
    variant = 'default',
    className = ''
}: {
    size?: number;
    variant?: 'default' | 'white' | 'mono';
    className?: string;
}) {
    const colors = {
        default: { primary: '#10b981', secondary: '#34d399' },
        white: { primary: '#ffffff', secondary: 'rgba(255,255,255,0.7)' },
        mono: { primary: '#1a1a1a', secondary: '#374151' },
    };

    const c = colors[variant];

    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 48 48"
            fill="none"
            className={className}
        >
            <path
                d="M22 8C22 8 22 26 22 28"
                stroke={c.primary}
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <path
                d="M22 10C28 12 34 18 36 26C30 26 22 26 22 26V10Z"
                fill={c.primary}
            />
            <path
                d="M6 34C10 32 14 34 18 33C22 32 26 34 30 33C34 32 38 34 42 33"
                stroke={c.primary}
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M8 40C12 38 16 40 20 39C24 38 28 40 32 39C36 38 40 40 42 39"
                stroke={c.secondary}
                strokeWidth="1.5"
                strokeLinecap="round"
            />
        </svg>
    );
}
