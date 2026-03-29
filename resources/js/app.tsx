import '../css/app.css';
import './bootstrap';
import './echo';

import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent as ReactComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from './Contexts/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
type PageModule = {
    default: ReactComponent;
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob<PageModule>('./Pages/**/*.tsx'),
        ).then((module) => module.default),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const initialTheme = (props.initialPage.props.theme as 'light' | 'dark' | 'system') || 'system';

        root.render(
            <ThemeProvider initialTheme={initialTheme}>
                <App {...props} />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#10b981',
    },
});
