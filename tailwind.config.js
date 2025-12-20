import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                // Primary: Emerald Green - fresh, modern
                primary: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                    950: '#022c22',
                },
                // Surface: Warm neutrals
                surface: {
                    50: '#fafaf8',
                    100: '#f5f4f0',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    800: '#1f2937',
                    900: '#1a1a1a',
                    950: '#0a0a0a',
                },
            },
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Bricolage Grotesque', 'Georgia', 'serif'],
            },
            fontSize: {
                'display-lg': ['4.5rem', { lineHeight: '1', letterSpacing: '-0.02em' }],
                'display-md': ['3.75rem', { lineHeight: '1.1', letterSpacing: '-0.02em' }],
                'display-sm': ['2.75rem', { lineHeight: '1.15', letterSpacing: '-0.01em' }],
            },
            boxShadow: {
                'green': '0 4px 14px rgba(16, 185, 129, 0.35)',
                'green-lg': '0 6px 20px rgba(16, 185, 129, 0.4)',
            },
            animation: {
                'fade-in': 'fadeIn 0.6s ease-out forwards',
                'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },

    plugins: [forms],
};
