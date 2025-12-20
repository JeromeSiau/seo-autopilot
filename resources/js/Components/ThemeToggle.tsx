import { Moon, Sun } from 'lucide-react';
import { useTheme } from '@/Contexts/ThemeContext';

interface ThemeToggleProps {
    className?: string;
}

export default function ThemeToggle({ className = '' }: ThemeToggleProps) {
    const { theme, toggleTheme } = useTheme();

    return (
        <button
            onClick={toggleTheme}
            className={`relative p-2 rounded-lg transition-all duration-300 ${
                theme === 'dark'
                    ? 'bg-surface-800 text-primary-400 hover:bg-surface-700'
                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200'
            } ${className}`}
            aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            <div className="relative w-5 h-5">
                <Sun
                    className={`absolute inset-0 w-5 h-5 transition-all duration-300 ${
                        theme === 'dark'
                            ? 'opacity-0 rotate-90 scale-0'
                            : 'opacity-100 rotate-0 scale-100'
                    }`}
                />
                <Moon
                    className={`absolute inset-0 w-5 h-5 transition-all duration-300 ${
                        theme === 'dark'
                            ? 'opacity-100 rotate-0 scale-100'
                            : 'opacity-0 -rotate-90 scale-0'
                    }`}
                />
            </div>
        </button>
    );
}
