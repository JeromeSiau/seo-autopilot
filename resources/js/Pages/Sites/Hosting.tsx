import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { HostedPage, HostedPageKind, HostedThemeSettings, PageProps, Site } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, Globe, Palette, Server, Shield } from 'lucide-react';

interface HostingPageProps extends PageProps {
    site: Site;
}

const TEMPLATE_OPTIONS = [
    { value: 'editorial', label: 'Editorial' },
    { value: 'magazine', label: 'Magazine' },
    { value: 'minimal', label: 'Minimal' },
] as const;

const DOMAIN_STATUS_LABELS: Record<string, string> = {
    none: 'Not configured',
    dns_pending: 'Waiting for DNS',
    tenant_pending: 'Tenant provisioning',
    ssl_pending: 'Waiting for SSL',
    active: 'Active',
    error: 'Error',
};

const SSL_STATUS_LABELS: Record<string, string> = {
    none: 'Not requested',
    pending: 'Pending',
    active: 'Active',
    error: 'Error',
};

const PAGE_TITLES: Record<HostedPageKind, string> = {
    home: 'Home',
    about: 'About',
    legal: 'Legal',
};

const SOCIAL_KEYS = ['x', 'linkedin', 'github'] as const;

function statusVariant(status: string): 'default' | 'success' | 'warning' | 'danger' {
    switch (status) {
        case 'active':
            return 'success';
        case 'pending':
        case 'dns_pending':
        case 'tenant_pending':
        case 'ssl_pending':
            return 'warning';
        case 'error':
            return 'danger';
        default:
            return 'default';
    }
}

function HostedPageFormCard({
    siteId,
    kind,
    page,
}: {
    siteId: number;
    kind: HostedPageKind;
    page?: HostedPage;
}) {
    const { data, setData, patch, processing } = useForm({
        title: page?.title ?? PAGE_TITLES[kind],
        body_html: page?.body_html ?? '',
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
        is_published: page?.is_published ?? true,
    });

    const submit = () => {
        patch(route('sites.hosting.pages.update', { site: siteId, kind }));
    };

    return (
        <Card>
            <CardHeader
                title={PAGE_TITLES[kind]}
                description={`Edit the ${PAGE_TITLES[kind].toLowerCase()} page rendered on the hosted blog.`}
            />

            <div className="mt-5 space-y-4">
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(event) => setData('title', event.target.value)}
                        className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body HTML</label>
                    <textarea
                        value={data.body_html}
                        onChange={(event) => setData('body_html', event.target.value)}
                        rows={8}
                        className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta title</label>
                        <input
                            type="text"
                            value={data.meta_title}
                            onChange={(event) => setData('meta_title', event.target.value)}
                            className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta description</label>
                        <input
                            type="text"
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </div>
                </div>

                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input
                        type="checkbox"
                        checked={data.is_published}
                        onChange={(event) => setData('is_published', event.target.checked)}
                        className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                    />
                    Publish this page
                </label>

                <div className="flex justify-end">
                    <Button onClick={submit} loading={processing}>
                        Save {PAGE_TITLES[kind]}
                    </Button>
                </div>
            </div>
        </Card>
    );
}

export default function Hosting({ site }: HostingPageProps) {
    const hosting = site.hosting;
    const themeSettings = (hosting?.theme_settings ?? {}) as HostedThemeSettings;
    const liveUrl = hosting?.canonical_domain ? `https://${hosting.canonical_domain}` : site.public_url ?? null;

    const { data: domainData, setData: setDomainData, post: saveDomain, processing: savingDomain } = useForm({
        custom_domain: hosting?.custom_domain ?? '',
    });

    const { data: themeData, setData: setThemeData, patch: saveTheme, processing: savingTheme } = useForm<{
        template_key: 'editorial' | 'magazine' | 'minimal';
        theme_settings: HostedThemeSettings;
    }>({
        template_key: hosting?.template_key ?? 'editorial',
        theme_settings: {
            brand_name: themeSettings.brand_name ?? site.name,
            hero_title: themeSettings.hero_title ?? site.name,
            hero_description: themeSettings.hero_description ?? site.business_description ?? '',
            accent_color: themeSettings.accent_color ?? '#0f766e',
            surface_color: themeSettings.surface_color ?? '#f8fafc',
            text_color: themeSettings.text_color ?? '#0f172a',
            heading_font: themeSettings.heading_font ?? 'Georgia, serif',
            body_font: themeSettings.body_font ?? 'system-ui, sans-serif',
            footer_text: themeSettings.footer_text ?? `Published by ${site.name}`,
            social_links: {
                x: themeSettings.social_links?.x ?? '',
                linkedin: themeSettings.social_links?.linkedin ?? '',
                github: themeSettings.social_links?.github ?? '',
            },
        },
    });

    const pagesByKind = new Map<HostedPageKind, HostedPage>(
        (site.hosted_pages ?? []).map((page) => [page.kind, page]),
    );

    const updateThemeField = <K extends keyof HostedThemeSettings>(key: K, value: HostedThemeSettings[K]) => {
        setThemeData('theme_settings', {
            ...themeData.theme_settings,
            [key]: value,
        });
    };

    const updateSocialField = (key: (typeof SOCIAL_KEYS)[number], value: string) => {
        setThemeData('theme_settings', {
            ...themeData.theme_settings,
            social_links: {
                ...(themeData.theme_settings.social_links ?? {}),
                [key]: value,
            },
        });
    };

    return (
        <AppLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('sites.show', { site: site.id })}
                            className="rounded-lg p-2 text-surface-400 transition-colors hover:bg-surface-100 hover:text-surface-600 dark:hover:bg-surface-800 dark:hover:text-white"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-surface-900 dark:text-white">Hosted blog</h1>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Manage staging, custom domain, theme and export for {site.name}.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {liveUrl && (
                            <Button as="link" href={liveUrl} variant="secondary" icon={ExternalLink}>
                                Open site
                            </Button>
                        )}
                        <Button
                            onClick={() => router.post(route('sites.export-site', { site: site.id }))}
                            variant="primary"
                            icon={Download}
                        >
                            Build ZIP export
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`Hosted blog - ${site.name}`} />

            <div className="space-y-6">
                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-blue-50 p-3 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
                                <Server className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Staging domain</p>
                                <p className="mt-1 font-semibold text-surface-900 dark:text-white">
                                    {hosting?.staging_domain ?? 'Not provisioned'}
                                </p>
                                <div className="mt-3">
                                    <Badge variant={statusVariant(hosting?.ssl_status ?? 'none')}>
                                        {SSL_STATUS_LABELS[hosting?.ssl_status ?? 'none']}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        {!hosting?.staging_domain && (
                            <div className="mt-5">
                                <Button
                                    onClick={() => router.post(route('sites.hosting.provision-staging', { site: site.id }))}
                                    className="w-full"
                                >
                                    Provision staging
                                </Button>
                            </div>
                        )}
                    </Card>

                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-emerald-50 p-3 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <Globe className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Custom domain</p>
                                <p className="mt-1 font-semibold text-surface-900 dark:text-white">
                                    {hosting?.custom_domain ?? 'Not connected'}
                                </p>
                                <div className="mt-3">
                                    <Badge variant={statusVariant(hosting?.domain_status ?? 'none')}>
                                        {DOMAIN_STATUS_LABELS[hosting?.domain_status ?? 'none']}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-violet-50 p-3 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                                <Shield className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-sm text-surface-500 dark:text-surface-400">Canonical domain</p>
                                <p className="mt-1 font-semibold text-surface-900 dark:text-white">
                                    {hosting?.canonical_domain ?? 'Staging will become canonical first'}
                                </p>
                                {hosting?.last_error && (
                                    <p className="mt-3 text-sm text-red-600 dark:text-red-400">{hosting.last_error}</p>
                                )}
                            </div>
                        </div>
                    </Card>
                </div>

                <Card>
                    <CardHeader
                        title="Domain connection"
                        description="Point the customer domain to your Ploi site, then request SSL."
                    />

                    <div className="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Custom domain</label>
                                <input
                                    type="text"
                                    value={domainData.custom_domain}
                                    onChange={(event) => setDomainData('custom_domain', event.target.value)}
                                    placeholder="blog.example.com"
                                    className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                />
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button
                                    onClick={() => saveDomain(route('sites.hosting.domain', { site: site.id }))}
                                    loading={savingDomain}
                                >
                                    Save domain
                                </Button>
                                <Button
                                    onClick={() => router.post(route('sites.hosting.verify-dns', { site: site.id }))}
                                    variant="secondary"
                                >
                                    Verify DNS and request SSL
                                </Button>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-surface-200 bg-surface-50 p-5 dark:border-surface-700 dark:bg-surface-800/60">
                            <p className="text-sm font-semibold text-surface-900 dark:text-white">Expected DNS</p>
                            {site.dns_expectation ? (
                                <div className="mt-3 space-y-2 text-sm text-surface-600 dark:text-surface-400">
                                    <p>Type: <span className="font-medium text-surface-900 dark:text-white">{site.dns_expectation.type}</span></p>
                                    <p>Target: <span className="font-medium text-surface-900 dark:text-white">{site.dns_expectation.value ?? 'Not configured'}</span></p>
                                    <p className="text-xs">
                                        Apex domains should point to the public IP. Subdomains should point to the configured CNAME target.
                                    </p>
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-surface-500 dark:text-surface-400">
                                    Save a domain first to see the DNS target to configure.
                                </p>
                            )}
                        </div>
                    </div>
                </Card>

                <Card>
                    <CardHeader
                        title="Theme"
                        description="Templates stay intentionally simple in V1. Branding changes are applied to dynamic pages and exports."
                    />

                    <div className="mt-5 grid gap-4 lg:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Template</label>
                            <select
                                value={themeData.template_key}
                                onChange={(event) => setThemeData('template_key', event.target.value as 'editorial' | 'magazine' | 'minimal')}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                                {TEMPLATE_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Brand name</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.brand_name ?? ''}
                                onChange={(event) => updateThemeField('brand_name', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Hero title</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.hero_title ?? ''}
                                onChange={(event) => updateThemeField('hero_title', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Hero description</label>
                            <textarea
                                rows={3}
                                value={themeData.theme_settings.hero_description ?? ''}
                                onChange={(event) => updateThemeField('hero_description', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Accent color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.accent_color ?? ''}
                                onChange={(event) => updateThemeField('accent_color', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Surface color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.surface_color ?? ''}
                                onChange={(event) => updateThemeField('surface_color', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Text color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.text_color ?? ''}
                                onChange={(event) => updateThemeField('text_color', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Footer text</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.footer_text ?? ''}
                                onChange={(event) => updateThemeField('footer_text', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Heading font</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.heading_font ?? ''}
                                onChange={(event) => updateThemeField('heading_font', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body font</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.body_font ?? ''}
                                onChange={(event) => updateThemeField('body_font', event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            />
                        </div>

                        {SOCIAL_KEYS.map((key) => (
                            <div key={key}>
                                <label className="block text-sm font-medium capitalize text-surface-700 dark:text-surface-300">{key}</label>
                                <input
                                    type="url"
                                    value={themeData.theme_settings.social_links?.[key] ?? ''}
                                    onChange={(event) => updateSocialField(key, event.target.value)}
                                    className="mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                />
                            </div>
                        ))}
                    </div>

                    <div className="mt-5 flex justify-end">
                        <Button
                            onClick={() => saveTheme(route('sites.hosting.theme', { site: site.id }))}
                            loading={savingTheme}
                            icon={Palette}
                        >
                            Save theme
                        </Button>
                    </div>
                </Card>

                <div className="grid gap-6 xl:grid-cols-3">
                    {(['home', 'about', 'legal'] as HostedPageKind[]).map((kind) => (
                        <HostedPageFormCard key={kind} siteId={site.id} kind={kind} page={pagesByKind.get(kind)} />
                    ))}
                </div>

                <Card>
                    <CardHeader
                        title="Exports"
                        description="Static exports reuse the same renderer as the live hosted blog."
                    />

                    <div className="mt-5 flex flex-wrap items-center gap-3">
                        <Button
                            onClick={() => router.post(route('sites.export-site', { site: site.id }))}
                            icon={Download}
                        >
                            Generate site ZIP
                        </Button>

                        {site.site_export_available && (
                            <Button
                                as="link"
                                href={route('sites.download-site-export', { site: site.id })}
                                variant="secondary"
                                icon={Download}
                            >
                                Download latest ZIP
                            </Button>
                        )}
                    </div>

                    <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">
                        Individual article HTML exports are available from each article page.
                    </p>
                </Card>
            </div>
        </AppLayout>
    );
}
