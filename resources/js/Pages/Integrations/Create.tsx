import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Site, PageProps } from '@/types';
import IntegrationForm from '@/Components/Integration/IntegrationForm';
import { useTranslations } from '@/hooks/useTranslations';

interface IntegrationsCreateProps extends PageProps {
    sites: Site[];
    selectedSiteId?: number;
}

export default function IntegrationsCreate({ sites, selectedSiteId }: IntegrationsCreateProps) {
    const { t } = useTranslations();
    // Use the selected site from query params or the first site
    const siteId = selectedSiteId || sites[0]?.id;

    if (!siteId) {
        return (
            <AppLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('integrations.index')}
                            className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300 transition-colors"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                            {t?.integrations?.addIntegration ?? 'Add an integration'}
                        </h1>
                    </div>
                }
            >
                <Head title={t?.integrations?.addIntegration ?? 'Add an integration'} />
                <div className="mx-auto max-w-lg text-center py-12">
                    <p className="text-surface-500 dark:text-surface-400">
                        {t?.integrations?.createSiteFirst ?? 'You must create a site before adding an integration.'}
                    </p>
                    <Link
                        href={route('sites.create')}
                        className="mt-4 inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        {t?.integrations?.createSite ?? 'Create a site'}
                    </Link>
                </div>
            </AppLayout>
        );
    }

    const handleSuccess = () => {
        // Check if onboarding not completed for this site
        const site = sites.find((s) => s.id === siteId);
        if (site && !site.onboarding_completed_at) {
            router.visit(route('onboarding.resume', siteId));
        } else {
            router.visit(route('integrations.index'));
        }
    };

    const handleBack = () => {
        router.visit(route('integrations.index'));
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('integrations.index')}
                        className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300 transition-colors"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                        {t?.integrations?.addIntegration ?? 'Add an integration'}
                    </h1>
                </div>
            }
        >
            <Head title={t?.integrations?.addIntegration ?? 'Add an integration'} />

            <div className="mx-auto max-w-lg">
                {/* Site selector if multiple sites */}
                {sites.length > 1 && (
                    <div className="mb-6">
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                            {t?.integrations?.site ?? 'Site'}
                        </label>
                        <select
                            value={siteId}
                            onChange={(e) =>
                                router.visit(route('integrations.create', { site_id: e.target.value }))
                            }
                            className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                        >
                            {sites.map((site) => (
                                <option key={site.id} value={site.id}>
                                    {site.name} ({site.domain})
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <IntegrationForm
                    siteId={siteId}
                    onSuccess={handleSuccess}
                    onBack={handleBack}
                    showSkip={false}
                    showBack={true}
                />
            </div>
        </AppLayout>
    );
}
