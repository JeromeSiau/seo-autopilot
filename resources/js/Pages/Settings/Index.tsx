import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Settings, CreditCard, Users, Key, Bell, Palette } from 'lucide-react';
import { Card } from '@/Components/ui/Card';
import { PageProps, Team } from '@/types';

interface SettingsIndexProps extends PageProps {
    team: Team;
}

const settingsLinks = [
    {
        name: 'Billing',
        description: 'Manage your subscription and payment methods',
        href: 'settings.billing',
        icon: CreditCard,
    },
    {
        name: 'Team',
        description: 'Invite team members and manage permissions',
        href: 'settings.team',
        icon: Users,
    },
    {
        name: 'API Keys',
        description: 'Manage API keys for external services',
        href: 'settings.api-keys',
        icon: Key,
    },
    {
        name: 'Brand Voices',
        description: 'Configure your content tone and style',
        href: 'settings.brand-voices',
        icon: Palette,
    },
    {
        name: 'Notifications',
        description: 'Configure email and webhook notifications',
        href: 'settings.notifications',
        icon: Bell,
    },
];

export default function SettingsIndex({ team }: SettingsIndexProps) {
    return (
        <AppLayout
            header={
                <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
            }
        >
            <Head title="Settings" />

            {/* Current Plan */}
            <Card className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Current Plan</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            You're currently on the{' '}
                            <span className="font-medium capitalize text-indigo-600">{team.plan}</span>{' '}
                            plan.
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold text-gray-900">
                            {team.articles_generated_count} / {team.articles_limit}
                        </p>
                        <p className="text-sm text-gray-500">articles this month</p>
                    </div>
                </div>
                <div className="mt-4">
                    <div className="h-2 w-full rounded-full bg-gray-200">
                        <div
                            className="h-2 rounded-full bg-indigo-600 transition-all"
                            style={{
                                width: `${Math.min(
                                    (team.articles_generated_count / team.articles_limit) * 100,
                                    100
                                )}%`,
                            }}
                        />
                    </div>
                </div>
            </Card>

            {/* Settings Links */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {settingsLinks.map((item) => (
                    <Link
                        key={item.name}
                        href={route(item.href)}
                        className="group relative block rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 transition hover:shadow-md"
                    >
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 transition group-hover:bg-indigo-100">
                                <item.icon className="h-6 w-6 text-indigo-600" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900">{item.name}</h3>
                                <p className="mt-1 text-sm text-gray-500">{item.description}</p>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
