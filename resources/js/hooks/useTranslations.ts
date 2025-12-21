import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

export function useTranslations() {
    const { translations, locale } = usePage<PageProps>().props;
    return {
        t: translations?.app,
        locale,
    };
}
