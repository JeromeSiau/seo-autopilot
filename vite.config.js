import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }

                    if (id.includes('/react/') || id.includes('/react-dom/')) {
                        return 'vendor';
                    }

                    if (id.includes('/@inertiajs/react/')) {
                        return 'inertia';
                    }

                    if (
                        id.includes('/lucide-react/')
                        || id.includes('/@headlessui/react/')
                        || id.includes('/clsx/')
                    ) {
                        return 'ui';
                    }

                    return undefined;
                },
            },
        },
    },
});
