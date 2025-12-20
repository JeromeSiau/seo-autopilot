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

    const colors = {
        default: {
            primary: '#10b981',
            secondary: '#34d399',
            text: 'text-surface-900',
            dot: 'bg-primary-500',
        },
        white: {
            primary: '#ffffff',
            secondary: 'rgba(255,255,255,0.7)',
            text: 'text-white',
            dot: 'bg-white',
        },
        mono: {
            primary: '#1a1a1a',
            secondary: '#374151',
            text: 'text-surface-900',
            dot: 'bg-surface-900',
        },
    };

    const c = colors[variant];

    return (
        <div className={`flex items-center ${gap} ${className}`}>
            <svg
                width={iconSize}
                height={iconSize}
                viewBox="0 0 48 48"
                fill="none"
                className="flex-shrink-0"
            >
                {/* Mât */}
                <path
                    d="M22 8C22 8 22 26 22 28"
                    stroke={c.primary}
                    strokeWidth="2.5"
                    strokeLinecap="round"
                />
                {/* Voile courbée */}
                <path
                    d="M22 10C28 12 34 18 36 26C30 26 22 26 22 26V10Z"
                    fill={c.primary}
                />
                {/* Vague principale */}
                <path
                    d="M6 34C10 32 14 34 18 33C22 32 26 34 30 33C34 32 38 34 42 33"
                    stroke={c.primary}
                    strokeWidth="2"
                    strokeLinecap="round"
                />
                {/* Vague secondaire */}
                <path
                    d="M8 40C12 38 16 40 20 39C24 38 28 40 32 39C36 38 40 40 42 39"
                    stroke={c.secondary}
                    strokeWidth="1.5"
                    strokeLinecap="round"
                />
            </svg>

            {showText && (
                <span className={`font-display font-extrabold ${textSize} ${c.text} tracking-tight`}>
                    RankCruise
                    <span className={`inline-block w-1.5 h-1.5 ${c.dot} rounded-full ml-0.5 align-super`} />
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
