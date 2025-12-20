import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Plug } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Site, PageProps } from '@/types';
import { FormEvent, useState } from 'react';

interface IntegrationsCreateProps extends PageProps {
    sites: Site[];
}

const integrationTypes = [
    {
        type: 'wordpress',
        name: 'WordPress',
        description: 'Connect via REST API with application password',
        fields: ['url', 'username', 'password'],
    },
    {
        type: 'webflow',
        name: 'Webflow',
        description: 'Connect via Webflow API token',
        fields: ['api_token', 'collection_id'],
    },
    {
        type: 'shopify',
        name: 'Shopify',
        description: 'Connect via Shopify Admin API',
        fields: ['shop_domain', 'api_token'],
    },
];

export default function IntegrationsCreate({ sites }: IntegrationsCreateProps) {
    const [selectedType, setSelectedType] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        site_id: '',
        type: '',
        name: '',
        credentials: {} as Record<string, string>,
    });

    const handleTypeSelect = (type: string) => {
        setSelectedType(type);
        setData('type', type);
    };

    const handleCredentialChange = (field: string, value: string) => {
        setData('credentials', { ...data.credentials, [field]: value });
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('integrations.store'));
    };

    const selectedIntegration = integrationTypes.find((i) => i.type === selectedType);

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('integrations.index')}
                        className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">Add Integration</h1>
                </div>
            }
        >
            <Head title="Add Integration" />

            <div className="mx-auto max-w-2xl">
                {/* Step 1: Select Type */}
                {!selectedType && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-gray-900">
                            Select Integration Type
                        </h2>
                        <div className="grid gap-4">
                            {integrationTypes.map((integration) => (
                                <button
                                    key={integration.type}
                                    onClick={() => handleTypeSelect(integration.type)}
                                    className="flex items-center gap-4 rounded-xl bg-white p-6 text-left shadow-sm ring-1 ring-gray-900/5 transition hover:shadow-md"
                                >
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50">
                                        <Plug className="h-6 w-6 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-gray-900">
                                            {integration.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            {integration.description}
                                        </p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Step 2: Configure */}
                {selectedType && selectedIntegration && (
                    <Card>
                        <div className="mb-6 flex items-center gap-4">
                            <button
                                onClick={() => setSelectedType(null)}
                                className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                <ArrowLeft className="h-5 w-5" />
                            </button>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Configure {selectedIntegration.name}
                                </h2>
                                <p className="text-sm text-gray-500">
                                    {selectedIntegration.description}
                                </p>
                            </div>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <label
                                    htmlFor="site_id"
                                    className="block text-sm font-medium text-gray-700"
                                >
                                    Site
                                </label>
                                <select
                                    id="site_id"
                                    value={data.site_id}
                                    onChange={(e) => setData('site_id', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a site...</option>
                                    {sites.map((site) => (
                                        <option key={site.id} value={site.id}>
                                            {site.name} ({site.domain})
                                        </option>
                                    ))}
                                </select>
                                {errors.site_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.site_id}</p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="name"
                                    className="block text-sm font-medium text-gray-700"
                                >
                                    Integration Name
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={`My ${selectedIntegration.name}`}
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            {/* WordPress Fields */}
                            {selectedType === 'wordpress' && (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            WordPress URL
                                        </label>
                                        <input
                                            type="url"
                                            value={data.credentials.url || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('url', e.target.value)
                                            }
                                            placeholder="https://yoursite.com"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Username
                                        </label>
                                        <input
                                            type="text"
                                            value={data.credentials.username || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('username', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Application Password
                                        </label>
                                        <input
                                            type="password"
                                            value={data.credentials.password || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('password', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">
                                            Generate an application password in WordPress → Users →
                                            Profile → Application Passwords
                                        </p>
                                    </div>
                                </>
                            )}

                            {/* Webflow Fields */}
                            {selectedType === 'webflow' && (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            API Token
                                        </label>
                                        <input
                                            type="password"
                                            value={data.credentials.api_token || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('api_token', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Collection ID
                                        </label>
                                        <input
                                            type="text"
                                            value={data.credentials.collection_id || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('collection_id', e.target.value)
                                            }
                                            placeholder="The CMS collection to publish to"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                </>
                            )}

                            {/* Shopify Fields */}
                            {selectedType === 'shopify' && (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Shop Domain
                                        </label>
                                        <input
                                            type="text"
                                            value={data.credentials.shop_domain || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('shop_domain', e.target.value)
                                            }
                                            placeholder="yourstore.myshopify.com"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Admin API Token
                                        </label>
                                        <input
                                            type="password"
                                            value={data.credentials.api_token || ''}
                                            onChange={(e) =>
                                                handleCredentialChange('api_token', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                </>
                            )}

                            <div className="flex justify-end gap-3 pt-4">
                                <Button
                                    as="link"
                                    href={route('integrations.index')}
                                    variant="secondary"
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" loading={processing}>
                                    Add Integration
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
