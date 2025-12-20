import { usePage } from '@inertiajs/react';

interface PageProps {
    locale: 'en' | 'fr' | 'es';
    translations: {
        app: Record<string, any>;
    };
}

export function useTranslations() {
    const { translations, locale } = usePage<PageProps>().props;
    return {
        t: translations.app,
        locale,
    };
}
