import { usePage, router } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import clsx from 'clsx';
import { PageProps } from '@/types';

const LOCALES = [
    { code: 'en', label: 'EN', flag: '🇬🇧' },
    { code: 'fr', label: 'FR', flag: '🇫🇷' },
    { code: 'es', label: 'ES', flag: '🇪🇸' },
] as const;

interface LanguageSwitcherProps {
    className?: string;
}

export default function LanguageSwitcher({ className = '' }: LanguageSwitcherProps) {
    const { locale } = usePage<PageProps>().props;

    const handleChange = (newLocale: string) => {
        router.post(route('preferences.update'), { locale: newLocale }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const currentLocale = LOCALES.find(l => l.code === locale) || LOCALES[0];

    return (
        <div className={clsx('relative', className)}>
            <select
                value={locale}
                onChange={(e) => handleChange(e.target.value)}
                className={clsx(
                    'appearance-none cursor-pointer',
                    'pl-8 pr-3 py-2 rounded-lg',
                    'text-sm font-medium',
                    'bg-surface-100 dark:bg-surface-800',
                    'text-surface-700 dark:text-surface-300',
                    'border-0',
                    'focus:outline-none focus:ring-2 focus:ring-primary-500/20',
                    'hover:bg-surface-200 dark:hover:bg-surface-700',
                    'transition-colors'
                )}
            >
                {LOCALES.map((l) => (
                    <option key={l.code} value={l.code}>
                        {l.flag} {l.label}
                    </option>
                ))}
            </select>
            <Globe className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500 dark:text-surface-400 pointer-events-none" />
        </div>
    );
}
