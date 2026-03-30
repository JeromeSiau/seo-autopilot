import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { NotificationSettings, PageProps, WebhookDelivery, WebhookEndpoint } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Bell, CheckCircle2, PlugZap, RotateCw, Save, Send, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface NotificationsPageProps extends PageProps {
    settings: NotificationSettings;
    webhookEndpoints: WebhookEndpoint[];
    recentWebhookDeliveries: WebhookDelivery[];
    availableWebhookEvents: string[];
    canManageWebhooks: boolean;
}

export default function Notifications({
    settings,
    webhookEndpoints,
    recentWebhookDeliveries,
    availableWebhookEvents,
    canManageWebhooks,
}: NotificationsPageProps) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const preferencesForm = useForm<NotificationSettings>({
        email_frequency: settings.email_frequency,
        immediate_failures: settings.immediate_failures,
        immediate_quota: settings.immediate_quota,
    });

    const endpointForm = useForm<{
        url: string;
        events: string[];
        secret: string;
        is_active: boolean;
    }>({
        url: '',
        events: ['article.published'],
        secret: '',
        is_active: true,
    });

    const toggleEvent = (eventName: string) => {
        if (endpointForm.data.events.includes(eventName)) {
            endpointForm.setData('events', endpointForm.data.events.filter((item) => item !== eventName));
            return;
        }

        endpointForm.setData('events', [...endpointForm.data.events, eventName]);
    };

    const submitPreferences = () => {
        preferencesForm.post(route('settings.notifications.update'));
    };

    const submitEndpoint = () => {
        if (editingId) {
            endpointForm.patch(route('settings.webhooks.update', { webhookEndpoint: editingId }), {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    endpointForm.reset('url', 'secret');
                    endpointForm.setData('events', ['article.published']);
                    endpointForm.setData('is_active', true);
                },
            });
            return;
        }

        endpointForm.post(route('settings.webhooks.store'), {
            preserveScroll: true,
            onSuccess: () => {
                endpointForm.reset('url', 'secret');
                endpointForm.setData('events', ['article.published']);
                endpointForm.setData('is_active', true);
            },
        });
    };

    const startEditing = (endpoint: WebhookEndpoint) => {
        setEditingId(endpoint.id);
        endpointForm.setData({
            url: endpoint.url,
            events: endpoint.events,
            secret: '',
            is_active: endpoint.is_active,
        });
    };

    const cancelEditing = () => {
        setEditingId(null);
        endpointForm.reset('url', 'secret');
        endpointForm.setData('events', ['article.published']);
        endpointForm.setData('is_active', true);
    };

    const testEndpoint = (id: number) => {
        router.post(route('settings.webhooks.test', { webhookEndpoint: id }), {}, { preserveScroll: true });
    };

    const deleteEndpoint = (id: number) => {
        router.delete(route('settings.webhooks.destroy', { webhookEndpoint: id }), { preserveScroll: true });
    };

    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Notifications</h1>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Configure email preferences and outgoing webhooks.
                    </p>
                </div>
            }
        >
            <Head title="Notifications" />

            <div className="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Email Preferences" description="Choose how operational notifications are delivered." />
                        <div className="mt-5 space-y-5">
                            <div>
                                <label className="text-sm font-medium text-surface-700 dark:text-surface-300">Digest frequency</label>
                                <select
                                    value={preferencesForm.data.email_frequency}
                                    onChange={(event) => preferencesForm.setData('email_frequency', event.target.value as NotificationSettings['email_frequency'])}
                                    className="mt-2 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                                    <option value="never">Never</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>

                            <label className="flex items-start gap-3 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                <input
                                    type="checkbox"
                                    checked={preferencesForm.data.immediate_failures}
                                    onChange={(event) => preferencesForm.setData('immediate_failures', event.target.checked)}
                                    className="mt-1 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                />
                                <div>
                                    <p className="text-sm font-medium text-surface-900 dark:text-white">Immediate failure alerts</p>
                                    <p className="text-sm text-surface-500 dark:text-surface-400">Send a notification when generation or publishing fails.</p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                <input
                                    type="checkbox"
                                    checked={preferencesForm.data.immediate_quota}
                                    onChange={(event) => preferencesForm.setData('immediate_quota', event.target.checked)}
                                    className="mt-1 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                />
                                <div>
                                    <p className="text-sm font-medium text-surface-900 dark:text-white">Quota alerts</p>
                                    <p className="text-sm text-surface-500 dark:text-surface-400">Warn as soon as your team approaches or hits article limits.</p>
                                </div>
                            </label>

                            <div className="flex justify-end">
                                <Button onClick={submitPreferences} loading={preferencesForm.processing} icon={Save}>
                                    Save preferences
                                </Button>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <CardHeader
                            title="Webhook Endpoints"
                            description="Push article, refresh and AI visibility events into your own systems."
                        />
                        <div className="mt-5 space-y-5">
                            {canManageWebhooks ? (
                                <div className="space-y-4 rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                    <div>
                                        <label className="text-sm font-medium text-surface-700 dark:text-surface-300">Destination URL</label>
                                        <input
                                            value={endpointForm.data.url}
                                            onChange={(event) => endpointForm.setData('url', event.target.value)}
                                            placeholder="https://example.com/webhooks/seo-autopilot"
                                            className="mt-2 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="text-sm font-medium text-surface-700 dark:text-surface-300">Secret</label>
                                        <input
                                            value={endpointForm.data.secret}
                                            onChange={(event) => endpointForm.setData('secret', event.target.value)}
                                            placeholder={editingId ? 'Leave empty to keep existing secret' : 'Optional shared secret'}
                                            className="mt-2 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <p className="text-sm font-medium text-surface-700 dark:text-surface-300">Events</p>
                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            {availableWebhookEvents.map((eventName) => (
                                                <label key={eventName} className="flex items-center gap-3 rounded-xl border border-surface-200 px-4 py-3 text-sm dark:border-surface-800">
                                                    <input
                                                        type="checkbox"
                                                        checked={endpointForm.data.events.includes(eventName)}
                                                        onChange={() => toggleEvent(eventName)}
                                                        className="rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                                    />
                                                    <span className="text-surface-700 dark:text-surface-300">{eventName}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                                        <input
                                            type="checkbox"
                                            checked={endpointForm.data.is_active}
                                            onChange={(event) => endpointForm.setData('is_active', event.target.checked)}
                                            className="rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                        />
                                        Endpoint active
                                    </label>

                                    <div className="flex flex-wrap justify-end gap-2">
                                        {editingId && (
                                            <Button variant="ghost" onClick={cancelEditing}>
                                                Cancel
                                            </Button>
                                        )}
                                        <Button onClick={submitEndpoint} loading={endpointForm.processing} icon={PlugZap}>
                                            {editingId ? 'Update endpoint' : 'Add endpoint'}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Only team owners and admins can manage webhooks.
                                </p>
                            )}

                            <div className="space-y-3">
                                {webhookEndpoints.length === 0 ? (
                                    <p className="text-sm text-surface-500 dark:text-surface-400">No webhook endpoints configured yet.</p>
                                ) : (
                                    webhookEndpoints.map((endpoint) => (
                                        <div key={endpoint.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium text-surface-900 dark:text-white">{endpoint.url}</p>
                                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${endpoint.is_active ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400'}`}>
                                                            {endpoint.is_active ? 'active' : 'paused'}
                                                        </span>
                                                        {endpoint.has_secret && (
                                                            <span className="rounded-full bg-surface-100 px-2.5 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                                signed
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        {endpoint.events.map((eventName) => (
                                                            <span key={eventName} className="rounded-full bg-surface-50 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                                {eventName}
                                                            </span>
                                                        ))}
                                                    </div>
                                                    {endpoint.last_error && (
                                                        <p className="mt-3 text-sm text-red-600 dark:text-red-400">{endpoint.last_error}</p>
                                                    )}
                                                </div>
                                                {canManageWebhooks && (
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button variant="secondary" size="sm" onClick={() => startEditing(endpoint)}>
                                                            Edit
                                                        </Button>
                                                        <Button variant="secondary" size="sm" icon={Send} onClick={() => testEndpoint(endpoint.id)}>
                                                            Test
                                                        </Button>
                                                        <Button variant="ghost" size="sm" icon={Trash2} onClick={() => deleteEndpoint(endpoint.id)}>
                                                            Delete
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Recent Deliveries" description="Latest outbound webhook attempts across your team." />
                        <div className="mt-5 space-y-3">
                            {recentWebhookDeliveries.length === 0 ? (
                                <p className="text-sm text-surface-500 dark:text-surface-400">No webhook deliveries yet.</p>
                            ) : (
                                recentWebhookDeliveries.map((delivery) => (
                                    <div key={delivery.id} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-surface-900 dark:text-white">{delivery.event_name}</p>
                                                {delivery.endpoint_url && (
                                                    <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">{delivery.endpoint_url}</p>
                                                )}
                                                <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                    {delivery.attempted_at ? new Date(delivery.attempted_at).toLocaleString() : 'Pending'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {delivery.status === 'success' ? (
                                                    <CheckCircle2 className="h-4 w-4 text-primary-500" />
                                                ) : delivery.status === 'retrying' ? (
                                                    <RotateCw className="h-4 w-4 text-amber-500" />
                                                ) : delivery.status === 'failed' ? (
                                                    <XCircle className="h-4 w-4 text-red-500" />
                                                ) : (
                                                    <Bell className="h-4 w-4 text-surface-400" />
                                                )}
                                                <span className="text-sm font-medium text-surface-700 dark:text-surface-300">{delivery.status}</span>
                                            </div>
                                        </div>
                                        <div className="mt-2 space-y-1 text-sm text-surface-500 dark:text-surface-400">
                                            {(delivery.attempt_number || delivery.max_attempts) && (
                                                <p>
                                                    Attempt {delivery.attempt_number ?? 1}
                                                    {delivery.max_attempts ? ` of ${delivery.max_attempts}` : ''}
                                                </p>
                                            )}
                                            {(delivery.response_code || delivery.error_message) && (
                                                <p>{delivery.response_code ? `HTTP ${delivery.response_code}` : delivery.error_message}</p>
                                            )}
                                            {delivery.next_retry_at && delivery.status === 'retrying' && (
                                                <p>Retry scheduled for {new Date(delivery.next_retry_at).toLocaleString()}</p>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
