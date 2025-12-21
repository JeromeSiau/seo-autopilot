import { useState, useRef, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { Globe, ChevronDown } from 'lucide-react';
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
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const handleChange = (newLocale: string) => {
        setIsOpen(false);
        router.post(route('preferences.update'), { locale: newLocale }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const currentLocale = LOCALES.find(l => l.code === locale) || LOCALES[0];

    return (
        <div className={clsx('relative', className)} ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className={clsx(
                    'flex items-center gap-1.5 h-9 px-2.5 rounded-lg',
                    'text-sm font-medium',
                    'text-surface-600 dark:text-surface-300',
                    'hover:bg-surface-100 dark:hover:bg-surface-800',
                    'transition-colors'
                )}
            >
                <Globe className="h-4 w-4 text-surface-400" />
                <span>{currentLocale.flag}</span>
                <span>{currentLocale.label}</span>
                <ChevronDown className={clsx(
                    'h-3 w-3 text-surface-400 transition-transform',
                    isOpen && 'rotate-180'
                )} />
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-1 py-1 w-32 rounded-lg bg-white dark:bg-surface-900 shadow-lg ring-1 ring-surface-200 dark:ring-surface-800 z-50">
                    {LOCALES.map((l) => (
                        <button
                            key={l.code}
                            onClick={() => handleChange(l.code)}
                            className={clsx(
                                'flex items-center gap-2 w-full px-3 py-2 text-sm',
                                l.code === locale
                                    ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400'
                                    : 'text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800'
                            )}
                        >
                            <span>{l.flag}</span>
                            <span>{l.label}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
