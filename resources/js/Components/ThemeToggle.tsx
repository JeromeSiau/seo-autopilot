import { Moon, Sun } from 'lucide-react';
import { useTheme } from '@/Contexts/ThemeContext';

interface ThemeToggleProps {
    className?: string;
}

export default function ThemeToggle({ className = '' }: ThemeToggleProps) {
    const { resolvedTheme, toggleTheme } = useTheme();

    return (
        <button
            onClick={toggleTheme}
            className={`relative h-9 w-9 flex items-center justify-center rounded-lg transition-colors text-surface-500 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-700 dark:hover:text-surface-200 ${className}`}
            aria-label={resolvedTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            <div className="relative w-5 h-5">
                <Sun
                    className={`absolute inset-0 w-5 h-5 transition-all duration-300 ${
                        resolvedTheme === 'dark'
                            ? 'opacity-0 rotate-90 scale-0'
                            : 'opacity-100 rotate-0 scale-100'
                    }`}
                />
                <Moon
                    className={`absolute inset-0 w-5 h-5 transition-all duration-300 ${
                        resolvedTheme === 'dark'
                            ? 'opacity-100 rotate-0 scale-100'
                            : 'opacity-0 -rotate-90 scale-0'
                    }`}
                />
            </div>
        </button>
    );
}
