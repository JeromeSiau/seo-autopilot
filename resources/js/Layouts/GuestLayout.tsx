import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import Logo from '@/Components/Logo';
import ThemeToggle from '@/Components/ThemeToggle';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useTranslations } from '@/hooks/useTranslations';

export default function Guest({ children }: PropsWithChildren) {
    const { t } = useTranslations();

    return (
        <div className="min-h-screen bg-surface-50 dark:bg-surface-900 transition-colors duration-300">
            {/* Header with theme and language controls */}
            <div className="absolute top-0 right-0 p-4 flex items-center gap-3">
                <LanguageSwitcher />
                <ThemeToggle />
            </div>

            <div className="flex min-h-screen flex-col items-center justify-center px-4 py-12">
                {/* Logo */}
                <Link href="/" className="mb-8">
                    <Logo size="lg" />
                </Link>

                {/* Card */}
                <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white dark:bg-surface-800 px-8 py-8 shadow-xl dark:shadow-card-dark ring-1 ring-surface-200/50 dark:ring-surface-700/50">
                    {children}
                </div>

                {/* Footer links */}
                <div className="mt-8 text-center text-sm text-surface-500 dark:text-surface-400">
                    <Link href="/" className="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                        ← {t?.auth?.backToHome ?? 'Back to home'}
                    </Link>
                </div>
            </div>
        </div>
    );
}
