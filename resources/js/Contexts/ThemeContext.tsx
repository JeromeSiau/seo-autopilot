import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { router } from '@inertiajs/react';

type Theme = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';

interface ThemeContextType {
    theme: Theme;
    resolvedTheme: ResolvedTheme;
    toggleTheme: () => void;
    setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

function getSystemTheme(): ResolvedTheme {
    if (typeof window !== 'undefined') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return 'light';
}

function resolveTheme(theme: Theme): ResolvedTheme {
    if (theme === 'system') {
        return getSystemTheme();
    }
    return theme;
}

interface ThemeProviderProps {
    children: ReactNode;
    initialTheme?: Theme;
}

export function ThemeProvider({ children, initialTheme = 'system' }: ThemeProviderProps) {
    const [theme, setThemeState] = useState<Theme>(initialTheme);
    const [resolvedTheme, setResolvedTheme] = useState<ResolvedTheme>(() => resolveTheme(initialTheme));

    // Apply theme to document
    useEffect(() => {
        const resolved = resolveTheme(theme);
        setResolvedTheme(resolved);

        const root = window.document.documentElement;
        // Only modify classes if needed to prevent flash
        if (!root.classList.contains(resolved)) {
            root.classList.remove('light', 'dark');
            root.classList.add(resolved);
        }
    }, [theme]);

    // Listen for system theme changes when theme is set to 'system'
    useEffect(() => {
        if (theme !== 'system') return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = (e: MediaQueryListEvent) => {
            const resolved = e.matches ? 'dark' : 'light';
            setResolvedTheme(resolved);

            const root = window.document.documentElement;
            root.classList.remove('light', 'dark');
            root.classList.add(resolved);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, [theme]);

    const toggleTheme = () => {
        const newTheme: Theme = resolvedTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
    };

    const setTheme = (newTheme: Theme) => {
        setThemeState(newTheme);
        // Save to cookie for server-side rendering on next page load
        const resolved = resolveTheme(newTheme);
        document.cookie = `theme=${resolved}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
        router.post(route('preferences.update'), { theme: newTheme }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <ThemeContext.Provider value={{ theme, resolvedTheme, toggleTheme, setTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
