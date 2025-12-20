import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plug, Plus, Settings, Trash2, CheckCircle, XCircle } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Integration, PageProps } from '@/types';
import { format } from 'date-fns';

// Icons for different integration types
const IntegrationIcons: Record<string, string> = {
    wordpress: '/images/integrations/wordpress.svg',
    webflow: '/images/integrations/webflow.svg',
    shopify: '/images/integrations/shopify.svg',
};

interface IntegrationsIndexProps extends PageProps {
    integrations: Integration[];
}

export default function IntegrationsIndex({ integrations }: IntegrationsIndexProps) {
    const handleDelete = (integration: Integration) => {
        if (confirm(`Are you sure you want to delete ${integration.name}?`)) {
            router.delete(route('integrations.destroy', integration.id));
        }
    };

    const handleToggle = (integration: Integration) => {
        router.patch(route('integrations.toggle', integration.id));
    };

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'wordpress':
                return 'WordPress';
            case 'webflow':
                return 'Webflow';
            case 'shopify':
                return 'Shopify';
            default:
                return type;
        }
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'wordpress':
                return 'bg-blue-100 text-blue-700';
            case 'webflow':
                return 'bg-indigo-100 text-indigo-700';
            case 'shopify':
                return 'bg-green-100 text-green-700';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Integrations</h1>
                    <Button as="link" href={route('integrations.create')} icon={Plus}>
                        Add Integration
                    </Button>
                </div>
            }
        >
            <Head title="Integrations" />

            {integrations.length === 0 ? (
                <EmptyState
                    icon={Plug}
                    title="No integrations yet"
                    description="Connect your CMS to publish articles directly from SEO Autopilot."
                    action={{
                        label: 'Add Integration',
                        href: route('integrations.create'),
                    }}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {integrations.map((integration) => (
                        <Card key={integration.id}>
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100">
                                        <Plug className="h-6 w-6 text-gray-600" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-gray-900">
                                            {integration.name}
                                        </h3>
                                        <div className="mt-1 flex items-center gap-2">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${getTypeColor(
                                                    integration.type
                                                )}`}
                                            >
                                                {getTypeLabel(integration.type)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Link
                                        href={route('integrations.edit', integration.id)}
                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    >
                                        <Settings className="h-4 w-4" />
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(integration)}
                                        className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div className="mt-4 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    {integration.is_active ? (
                                        <span className="flex items-center gap-1 text-sm text-green-600">
                                            <CheckCircle className="h-4 w-4" />
                                            Active
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-sm text-gray-400">
                                            <XCircle className="h-4 w-4" />
                                            Inactive
                                        </span>
                                    )}
                                </div>
                                <button
                                    onClick={() => handleToggle(integration)}
                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                        integration.is_active ? 'bg-indigo-600' : 'bg-gray-200'
                                    }`}
                                >
                                    <span
                                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                            integration.is_active ? 'translate-x-5' : 'translate-x-0'
                                        }`}
                                    />
                                </button>
                            </div>

                            {integration.site && (
                                <div className="mt-4 border-t border-gray-100 pt-4">
                                    <p className="text-xs text-gray-500">
                                        Connected to{' '}
                                        <span className="font-medium text-gray-700">
                                            {integration.site.domain}
                                        </span>
                                    </p>
                                </div>
                            )}

                            <div className="mt-2 text-xs text-gray-400">
                                Added {format(new Date(integration.created_at), 'MMM d, yyyy')}
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {/* Available Integrations */}
            <div className="mt-12">
                <h2 className="text-lg font-semibold text-gray-900">Available Integrations</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Connect these platforms to publish your content automatically.
                </p>

                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    <Card className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50">
                            <span className="text-2xl">📝</span>
                        </div>
                        <div>
                            <h3 className="font-semibold text-gray-900">WordPress</h3>
                            <p className="text-sm text-gray-500">REST API integration</p>
                        </div>
                    </Card>

                    <Card className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50">
                            <span className="text-2xl">🌐</span>
                        </div>
                        <div>
                            <h3 className="font-semibold text-gray-900">Webflow</h3>
                            <p className="text-sm text-gray-500">CMS API integration</p>
                        </div>
                    </Card>

                    <Card className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50">
                            <span className="text-2xl">🛒</span>
                        </div>
                        <div>
                            <h3 className="font-semibold text-gray-900">Shopify</h3>
                            <p className="text-sm text-gray-500">Blog API integration</p>
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
