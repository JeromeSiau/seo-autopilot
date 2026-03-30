import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { HostedAsset, HostedAuthor, HostedCategory, HostedDeployEvent, HostedExportRun, HostedHealthCheck, HostedNavigationItem, HostedPage, HostedPageKind, HostedPageSection, HostedPageSectionItem, HostedRedirect, HostedTag, HostedThemeSettings, PageProps, Site } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Activity, ArrowLeft, Download, ExternalLink, Globe, Palette, Plus, Server, Shield, Trash2 } from 'lucide-react';

interface HostingPageProps extends PageProps {
    site: Site;
}

type SystemHostedPageKind = Extract<HostedPageKind, 'home' | 'about' | 'legal'>;

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

const PAGE_TITLES: Record<SystemHostedPageKind, string> = {
    home: 'Home',
    about: 'About',
    legal: 'Legal',
};

const SYSTEM_PAGE_KINDS: SystemHostedPageKind[] = ['home', 'about', 'legal'];
const SOCIAL_KEYS = ['x', 'linkedin', 'github'] as const;
const REDIRECT_STATUS_OPTIONS = [
    { value: 301, label: '301 Permanent' },
    { value: 302, label: '302 Temporary' },
] as const;
const ASSET_TYPE_OPTIONS = [
    { value: 'logo', label: 'Logo' },
    { value: 'social', label: 'Social image' },
    { value: 'image', label: 'Image' },
    { value: 'document', label: 'Document' },
] as const;
const NAVIGATION_PLACEMENT_OPTIONS = [
    { value: 'header', label: 'Header' },
    { value: 'footer', label: 'Footer' },
] as const;
const NAVIGATION_TYPE_OPTIONS = [
    { value: 'path', label: 'Internal path' },
    { value: 'url', label: 'External URL' },
] as const;
const SECTION_TYPE_OPTIONS = [
    { value: 'rich_text', label: 'Rich text' },
    { value: 'callout', label: 'Callout' },
    { value: 'cta_banner', label: 'CTA banner' },
    { value: 'feature_grid', label: 'Feature grid' },
    { value: 'pricing_grid', label: 'Pricing grid' },
    { value: 'faq', label: 'FAQ' },
    { value: 'hero', label: 'Hero' },
    { value: 'testimonial_grid', label: 'Testimonials' },
    { value: 'stat_grid', label: 'Stats' },
] as const;
const INPUT_CLASS =
    'mt-1.5 w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white';
const TEXTAREA_CLASS = `${INPUT_CLASS} min-h-[160px]`;

interface HostedSeoFormState {
    meta_title: string;
    meta_description: string;
    canonical_url: string;
    social_title: string;
    social_description: string;
    social_image_asset_id: number | string | null;
    social_image_url: string;
    robots_noindex: boolean;
    schema_enabled: boolean;
    show_in_sitemap: boolean;
    show_in_feed: boolean;
    breadcrumbs_enabled: boolean;
}

type HostedSeoSetData = <K extends keyof HostedSeoFormState>(key: K, value: HostedSeoFormState[K]) => void;

function createDefaultSection(type: HostedPageSection['type']): HostedPageSection {
    switch (type) {
        case 'hero':
            return {
                type,
                eyebrow: '',
                title: '',
                body: '',
                cta_label: '',
                cta_href: '',
                secondary_cta_label: '',
                secondary_cta_href: '',
            };
        case 'callout':
            return {
                type,
                eyebrow: '',
                title: '',
                body: '',
                cta_label: '',
                cta_href: '',
            };
        case 'cta_banner':
            return {
                type,
                eyebrow: '',
                title: '',
                body: '',
                cta_label: '',
                cta_href: '',
                secondary_cta_label: '',
                secondary_cta_href: '',
            };
        case 'feature_grid':
            return {
                type,
                title: '',
                items: [{ title: '', body: '' }],
            };
        case 'pricing_grid':
            return {
                type,
                title: '',
                body: '',
                items: [{ title: '', price: '', body: '', meta: '', cta_label: '', href: '' }],
            };
        case 'faq':
            return {
                type,
                title: '',
                items: [{ question: '', answer: '' }],
            };
        case 'testimonial_grid':
            return {
                type,
                title: '',
                items: [{ title: '', body: '' }],
            };
        case 'stat_grid':
            return {
                type,
                title: '',
                items: [{ title: '', body: '' }],
            };
        default:
            return {
                type: 'rich_text',
                heading: '',
                body_html: '',
            };
    }
}

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

function hostedHealthVariant(status: HostedHealthCheck['status'] | HostedDeployEvent['status'] | HostedExportRun['status']): 'default' | 'success' | 'warning' | 'danger' {
    switch (status) {
        case 'healthy':
        case 'success':
        case 'completed':
            return 'success';
        case 'warning':
        case 'pending':
        case 'running':
            return 'warning';
        case 'critical':
        case 'error':
        case 'failed':
            return 'danger';
        default:
            return 'default';
    }
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatBytes(value?: number | null): string {
    if (!value || value <= 0) {
        return 'Unknown size';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function HostedSeoFields({
    data,
    setData,
    assets,
    previewTitle,
    previewDescription,
    previewPath,
    previewBaseUrl,
}: {
    data: HostedSeoFormState;
    setData: HostedSeoSetData;
    assets: HostedAsset[];
    previewTitle: string;
    previewDescription: string;
    previewPath: string;
    previewBaseUrl?: string | null;
}) {
    const snippetTitle = data.social_title || data.meta_title || previewTitle;
    const snippetDescription = data.social_description || data.meta_description || previewDescription;
    const canonical = data.canonical_url || `${previewBaseUrl ?? ''}${previewPath}`;

    return (
        <div className="space-y-4 rounded-2xl border border-surface-200 p-4 dark:border-surface-800">
            <div>
                <p className="text-sm font-semibold text-surface-900 dark:text-white">SEO controls</p>
                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    Override canonical, social preview and indexing behavior for this hosted page.
                </p>
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Canonical URL</label>
                <input type="url" value={data.canonical_url} onChange={(event) => setData('canonical_url', event.target.value)} className={INPUT_CLASS} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Social title</label>
                    <input type="text" value={data.social_title} onChange={(event) => setData('social_title', event.target.value)} className={INPUT_CLASS} />
                </div>
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Social image asset</label>
                    <select
                        value={data.social_image_asset_id ?? ''}
                        onChange={(event) => setData('social_image_asset_id', Number(event.target.value) || '')}
                        className={INPUT_CLASS}
                    >
                        <option value="">Manual URL / theme default</option>
                        {assets
                            .filter((asset) => asset.is_active && (asset.type === 'social' || asset.type === 'image' || asset.type === 'logo'))
                            .map((asset) => (
                                <option key={asset.id} value={asset.id}>
                                    {asset.name}
                                </option>
                            ))}
                    </select>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Social description</label>
                    <textarea
                        rows={3}
                        value={data.social_description}
                        onChange={(event) => setData('social_description', event.target.value)}
                        className={`${INPUT_CLASS} min-h-[120px]`}
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Social image URL</label>
                    <input type="url" value={data.social_image_url} onChange={(event) => setData('social_image_url', event.target.value)} className={INPUT_CLASS} />
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input type="checkbox" checked={data.robots_noindex} onChange={(event) => setData('robots_noindex', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    Force noindex on this page
                </label>
                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input type="checkbox" checked={data.schema_enabled} onChange={(event) => setData('schema_enabled', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    Emit schema markup
                </label>
            </div>

            <div className="grid gap-3 md:grid-cols-3">
                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input type="checkbox" checked={data.show_in_sitemap} onChange={(event) => setData('show_in_sitemap', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    Include in sitemap
                </label>
                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input type="checkbox" checked={data.show_in_feed} onChange={(event) => setData('show_in_feed', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    Include in RSS feed
                </label>
                <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                    <input type="checkbox" checked={data.breadcrumbs_enabled} onChange={(event) => setData('breadcrumbs_enabled', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    Emit breadcrumb schema
                </label>
            </div>

            <div className="rounded-2xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/40">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={data.robots_noindex ? 'warning' : 'success'}>{data.robots_noindex ? 'Noindex' : 'Indexable'}</Badge>
                    <Badge variant={data.schema_enabled ? 'success' : 'default'}>{data.schema_enabled ? 'Schema on' : 'Schema off'}</Badge>
                    <Badge variant={data.show_in_sitemap ? 'success' : 'default'}>{data.show_in_sitemap ? 'In sitemap' : 'Hidden from sitemap'}</Badge>
                    <Badge variant={data.show_in_feed ? 'success' : 'default'}>{data.show_in_feed ? 'In feed' : 'Feed hidden'}</Badge>
                    <Badge variant={data.breadcrumbs_enabled ? 'success' : 'default'}>{data.breadcrumbs_enabled ? 'Breadcrumbs on' : 'Breadcrumbs off'}</Badge>
                </div>
                <div className="mt-4 rounded-xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-950/60">
                    <p className="text-xs text-surface-500 dark:text-surface-400">{canonical}</p>
                    <p className="mt-2 text-base font-semibold text-blue-700 dark:text-blue-400">{snippetTitle || 'Untitled page'}</p>
                    <p className="mt-1 text-sm text-surface-600 dark:text-surface-400">{snippetDescription || 'No description configured yet.'}</p>
                </div>
            </div>
        </div>
    );
}

function HostedSectionsEditor({
    sections,
    onChange,
}: {
    sections: HostedPageSection[];
    onChange: (sections: HostedPageSection[]) => void;
}) {
    const addSection = (type: HostedPageSection['type']) => {
        onChange([...sections, createDefaultSection(type)]);
    };

    const updateSection = (index: number, updates: Partial<HostedPageSection>) => {
        onChange(sections.map((section, sectionIndex) => (
            sectionIndex === index ? { ...section, ...updates } : section
        )));
    };

    const removeSection = (index: number) => {
        onChange(sections.filter((_, sectionIndex) => sectionIndex !== index));
    };

    const updateSectionItems = (index: number, items: HostedPageSectionItem[]) => {
        updateSection(index, { items });
    };

    return (
        <div className="space-y-4 rounded-2xl border border-surface-200 p-4 dark:border-surface-800">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-surface-900 dark:text-white">Reusable sections</p>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Build richer hosted pages with structured blocks instead of relying only on raw HTML.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {SECTION_TYPE_OPTIONS.map((option) => (
                        <Button key={option.value} variant="secondary" size="sm" onClick={() => addSection(option.value)}>
                            Add {option.label}
                        </Button>
                    ))}
                </div>
            </div>

            {sections.length === 0 ? (
                <div className="rounded-xl border border-dashed border-surface-300 p-4 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                    No structured sections yet.
                </div>
            ) : (
                <div className="space-y-4">
                    {sections.map((section, index) => (
                        <div key={`${section.type}-${index}`} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-surface-900 dark:text-white">
                                        {SECTION_TYPE_OPTIONS.find((option) => option.value === section.type)?.label ?? section.type}
                                    </p>
                                    <p className="text-xs text-surface-500 dark:text-surface-400">Section #{index + 1}</p>
                                </div>

                                <Button variant="ghost" size="sm" icon={Trash2} onClick={() => removeSection(index)}>
                                    Remove
                                </Button>
                            </div>

                            <div className="mt-4 space-y-4">
                                {section.type === 'rich_text' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Heading</label>
                                            <input
                                                type="text"
                                                value={section.heading ?? ''}
                                                onChange={(event) => updateSection(index, { heading: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body HTML</label>
                                            <textarea
                                                rows={6}
                                                value={section.body_html ?? ''}
                                                onChange={(event) => updateSection(index, { body_html: event.target.value })}
                                                className={TEXTAREA_CLASS}
                                            />
                                        </div>
                                    </>
                                )}

                                {section.type === 'hero' && (
                                    <>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Eyebrow</label>
                                                <input
                                                    type="text"
                                                    value={section.eyebrow ?? ''}
                                                    onChange={(event) => updateSection(index, { eyebrow: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Headline</label>
                                                <input
                                                    type="text"
                                                    value={section.title ?? ''}
                                                    onChange={(event) => updateSection(index, { title: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body</label>
                                            <textarea
                                                rows={4}
                                                value={section.body ?? ''}
                                                onChange={(event) => updateSection(index, { body: event.target.value })}
                                                className={TEXTAREA_CLASS}
                                            />
                                        </div>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Primary CTA label</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_label ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_label: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Primary CTA href</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_href ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_href: event.target.value })}
                                                    placeholder="/contact or https://example.com"
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Secondary CTA label</label>
                                                <input
                                                    type="text"
                                                    value={section.secondary_cta_label ?? ''}
                                                    onChange={(event) => updateSection(index, { secondary_cta_label: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Secondary CTA href</label>
                                                <input
                                                    type="text"
                                                    value={section.secondary_cta_href ?? ''}
                                                    onChange={(event) => updateSection(index, { secondary_cta_href: event.target.value })}
                                                    placeholder="/about or https://example.com"
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {section.type === 'callout' && (
                                    <>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Eyebrow</label>
                                                <input
                                                    type="text"
                                                    value={section.eyebrow ?? ''}
                                                    onChange={(event) => updateSection(index, { eyebrow: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                                                <input
                                                    type="text"
                                                    value={section.title ?? ''}
                                                    onChange={(event) => updateSection(index, { title: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body</label>
                                            <textarea
                                                rows={4}
                                                value={section.body ?? ''}
                                                onChange={(event) => updateSection(index, { body: event.target.value })}
                                                className={TEXTAREA_CLASS}
                                            />
                                        </div>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">CTA label</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_label ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_label: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">CTA href</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_href ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_href: event.target.value })}
                                                    placeholder="/pricing or https://example.com"
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {section.type === 'cta_banner' && (
                                    <>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Eyebrow</label>
                                                <input
                                                    type="text"
                                                    value={section.eyebrow ?? ''}
                                                    onChange={(event) => updateSection(index, { eyebrow: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                                                <input
                                                    type="text"
                                                    value={section.title ?? ''}
                                                    onChange={(event) => updateSection(index, { title: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body</label>
                                            <textarea
                                                rows={4}
                                                value={section.body ?? ''}
                                                onChange={(event) => updateSection(index, { body: event.target.value })}
                                                className={TEXTAREA_CLASS}
                                            />
                                        </div>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Primary CTA label</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_label ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_label: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Primary CTA href</label>
                                                <input
                                                    type="text"
                                                    value={section.cta_href ?? ''}
                                                    onChange={(event) => updateSection(index, { cta_href: event.target.value })}
                                                    placeholder="/contact or https://example.com"
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Secondary CTA label</label>
                                                <input
                                                    type="text"
                                                    value={section.secondary_cta_label ?? ''}
                                                    onChange={(event) => updateSection(index, { secondary_cta_label: event.target.value })}
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Secondary CTA href</label>
                                                <input
                                                    type="text"
                                                    value={section.secondary_cta_href ?? ''}
                                                    onChange={(event) => updateSection(index, { secondary_cta_href: event.target.value })}
                                                    placeholder="/pricing or https://example.com"
                                                    className={INPUT_CLASS}
                                                />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {section.type === 'feature_grid' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Section title</label>
                                            <input
                                                type="text"
                                                value={section.title ?? ''}
                                                onChange={(event) => updateSection(index, { title: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            {(section.items ?? []).map((item, itemIndex) => (
                                                <div key={itemIndex} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                    <div className="grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Item title</label>
                                                            <input
                                                                type="text"
                                                                value={item.title ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, title: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div className="flex items-end justify-end">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                icon={Trash2}
                                                                onClick={() => updateSectionItems(index, (section.items ?? []).filter((_, currentIndex) => currentIndex !== itemIndex))}
                                                            >
                                                                Remove item
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="mt-4">
                                                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Item body</label>
                                                        <textarea
                                                            rows={3}
                                                            value={item.body ?? ''}
                                                            onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, body: event.target.value } : currentItem))}
                                                            className={TEXTAREA_CLASS}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => updateSectionItems(index, [...(section.items ?? []), { title: '', body: '' }])}
                                            >
                                                Add feature
                                            </Button>
                                        </div>
                                    </>
                                )}

                                {section.type === 'pricing_grid' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Section title</label>
                                            <input
                                                type="text"
                                                value={section.title ?? ''}
                                                onChange={(event) => updateSection(index, { title: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Intro</label>
                                            <textarea
                                                rows={3}
                                                value={section.body ?? ''}
                                                onChange={(event) => updateSection(index, { body: event.target.value })}
                                                className={TEXTAREA_CLASS}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            {(section.items ?? []).map((item, itemIndex) => (
                                                <div key={itemIndex} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                    <div className="grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Plan name</label>
                                                            <input
                                                                type="text"
                                                                value={item.title ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, title: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Price</label>
                                                            <input
                                                                type="text"
                                                                value={item.price ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, price: event.target.value } : currentItem))}
                                                                placeholder="$990/mo"
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta line</label>
                                                            <input
                                                                type="text"
                                                                value={item.meta ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, meta: event.target.value } : currentItem))}
                                                                placeholder="Best for lean teams"
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div className="flex items-end justify-end">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                icon={Trash2}
                                                                onClick={() => updateSectionItems(index, (section.items ?? []).filter((_, currentIndex) => currentIndex !== itemIndex))}
                                                            >
                                                                Remove plan
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="mt-4">
                                                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Description</label>
                                                        <textarea
                                                            rows={3}
                                                            value={item.body ?? ''}
                                                            onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, body: event.target.value } : currentItem))}
                                                            className={TEXTAREA_CLASS}
                                                        />
                                                    </div>
                                                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">CTA label</label>
                                                            <input
                                                                type="text"
                                                                value={item.cta_label ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, cta_label: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">CTA href</label>
                                                            <input
                                                                type="text"
                                                                value={item.href ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, href: event.target.value } : currentItem))}
                                                                placeholder="/contact or https://example.com"
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => updateSectionItems(index, [...(section.items ?? []), { title: '', price: '', body: '', meta: '', cta_label: '', href: '' }])}
                                            >
                                                Add pricing card
                                            </Button>
                                        </div>
                                    </>
                                )}

                                {section.type === 'testimonial_grid' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Section title</label>
                                            <input
                                                type="text"
                                                value={section.title ?? ''}
                                                onChange={(event) => updateSection(index, { title: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            {(section.items ?? []).map((item, itemIndex) => (
                                                <div key={itemIndex} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                    <div className="grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Author / company</label>
                                                            <input
                                                                type="text"
                                                                value={item.title ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, title: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div className="flex items-end justify-end">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                icon={Trash2}
                                                                onClick={() => updateSectionItems(index, (section.items ?? []).filter((_, currentIndex) => currentIndex !== itemIndex))}
                                                            >
                                                                Remove testimonial
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="mt-4">
                                                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Quote</label>
                                                        <textarea
                                                            rows={3}
                                                            value={item.body ?? ''}
                                                            onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, body: event.target.value } : currentItem))}
                                                            className={TEXTAREA_CLASS}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => updateSectionItems(index, [...(section.items ?? []), { title: '', body: '' }])}
                                            >
                                                Add testimonial
                                            </Button>
                                        </div>
                                    </>
                                )}

                                {section.type === 'stat_grid' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Section title</label>
                                            <input
                                                type="text"
                                                value={section.title ?? ''}
                                                onChange={(event) => updateSection(index, { title: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            {(section.items ?? []).map((item, itemIndex) => (
                                                <div key={itemIndex} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                    <div className="grid gap-4 lg:grid-cols-2">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Stat value</label>
                                                            <input
                                                                type="text"
                                                                value={item.title ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, title: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div className="flex items-end justify-end">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                icon={Trash2}
                                                                onClick={() => updateSectionItems(index, (section.items ?? []).filter((_, currentIndex) => currentIndex !== itemIndex))}
                                                            >
                                                                Remove stat
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="mt-4">
                                                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Stat label</label>
                                                        <textarea
                                                            rows={2}
                                                            value={item.body ?? ''}
                                                            onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, body: event.target.value } : currentItem))}
                                                            className={TEXTAREA_CLASS}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => updateSectionItems(index, [...(section.items ?? []), { title: '', body: '' }])}
                                            >
                                                Add stat
                                            </Button>
                                        </div>
                                    </>
                                )}

                                {section.type === 'faq' && (
                                    <>
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Section title</label>
                                            <input
                                                type="text"
                                                value={section.title ?? ''}
                                                onChange={(event) => updateSection(index, { title: event.target.value })}
                                                className={INPUT_CLASS}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            {(section.items ?? []).map((item, itemIndex) => (
                                                <div key={itemIndex} className="rounded-xl border border-surface-200 p-4 dark:border-surface-800">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon={Trash2}
                                                            onClick={() => updateSectionItems(index, (section.items ?? []).filter((_, currentIndex) => currentIndex !== itemIndex))}
                                                        >
                                                            Remove item
                                                        </Button>
                                                    </div>
                                                    <div className="space-y-4">
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Question</label>
                                                            <input
                                                                type="text"
                                                                value={item.question ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, question: event.target.value } : currentItem))}
                                                                className={INPUT_CLASS}
                                                            />
                                                        </div>
                                                        <div>
                                                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Answer</label>
                                                            <textarea
                                                                rows={3}
                                                                value={item.answer ?? ''}
                                                                onChange={(event) => updateSectionItems(index, (section.items ?? []).map((currentItem, currentIndex) => currentIndex === itemIndex ? { ...currentItem, answer: event.target.value } : currentItem))}
                                                                className={TEXTAREA_CLASS}
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => updateSectionItems(index, [...(section.items ?? []), { question: '', answer: '' }])}
                                            >
                                                Add FAQ item
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function HostedPageFormCard({
    siteId,
    kind,
    page,
    assets,
    previewBaseUrl,
    previewDescriptionFallback,
}: {
    siteId: number;
    kind: SystemHostedPageKind;
    page?: HostedPage;
    assets: HostedAsset[];
    previewBaseUrl?: string | null;
    previewDescriptionFallback: string;
}) {
    const { data, setData, patch, processing } = useForm({
        title: page?.title ?? PAGE_TITLES[kind],
        navigation_label: page?.navigation_label ?? PAGE_TITLES[kind],
        body_html: page?.body_html ?? '',
        sections: page?.sections ?? [],
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
        canonical_url: page?.canonical_url ?? '',
        social_title: page?.social_title ?? '',
        social_description: page?.social_description ?? '',
        social_image_asset_id: page?.social_image_asset_id ?? '',
        social_image_url: page?.social_image_url ?? '',
        robots_noindex: page?.robots_noindex ?? false,
        schema_enabled: page?.schema_enabled ?? true,
        show_in_sitemap: page?.show_in_sitemap ?? kind !== 'legal',
        show_in_feed: page?.show_in_feed ?? false,
        breadcrumbs_enabled: page?.breadcrumbs_enabled ?? true,
        show_in_navigation: page?.show_in_navigation ?? kind !== 'legal',
        sort_order: page?.sort_order ?? (kind === 'home' ? 0 : kind === 'about' ? 200 : 900),
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
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                        <input
                            type="text"
                            value={data.title}
                            onChange={(event) => setData('title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation label</label>
                        <input
                            type="text"
                            value={data.navigation_label}
                            onChange={(event) => setData('navigation_label', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body HTML</label>
                    <textarea
                        value={data.body_html}
                        onChange={(event) => setData('body_html', event.target.value)}
                        rows={8}
                        className={TEXTAREA_CLASS}
                    />
                </div>

                <HostedSectionsEditor sections={data.sections as HostedPageSection[]} onChange={(sections) => setData('sections', sections)} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta title</label>
                        <input
                            type="text"
                            value={data.meta_title}
                            onChange={(event) => setData('meta_title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta description</label>
                        <input
                            type="text"
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <HostedSeoFields
                    data={data}
                    setData={setData as HostedSeoSetData}
                    assets={assets}
                    previewTitle={data.title}
                    previewDescription={previewDescriptionFallback}
                    previewPath={kind === 'home' ? '/' : `/${kind}`}
                    previewBaseUrl={previewBaseUrl}
                />

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation order</label>
                    <input
                        type="number"
                        value={data.sort_order}
                        onChange={(event) => setData('sort_order', Number(event.target.value))}
                        className={INPUT_CLASS}
                    />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.show_in_navigation}
                            onChange={(event) => setData('show_in_navigation', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Show in navigation
                    </label>

                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.is_published}
                            onChange={(event) => setData('is_published', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Publish this page
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button onClick={submit} loading={processing}>
                        Save {PAGE_TITLES[kind]}
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function CustomHostedPageCard({
    siteId,
    page,
    assets,
    previewBaseUrl,
    previewDescriptionFallback,
}: {
    siteId: number;
    page: HostedPage;
    assets: HostedAsset[];
    previewBaseUrl?: string | null;
    previewDescriptionFallback: string;
}) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        title: page.title,
        slug: page.slug ?? '',
        navigation_label: page.navigation_label ?? page.title,
        body_html: page.body_html ?? '',
        sections: page.sections ?? [],
        meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '',
        canonical_url: page.canonical_url ?? '',
        social_title: page.social_title ?? '',
        social_description: page.social_description ?? '',
        social_image_asset_id: page.social_image_asset_id ?? '',
        social_image_url: page.social_image_url ?? '',
        robots_noindex: page.robots_noindex ?? false,
        schema_enabled: page.schema_enabled ?? true,
        show_in_sitemap: page.show_in_sitemap ?? true,
        show_in_feed: page.show_in_feed ?? false,
        breadcrumbs_enabled: page.breadcrumbs_enabled ?? true,
        show_in_navigation: page.show_in_navigation ?? true,
        sort_order: page.sort_order ?? 400,
        is_published: page.is_published,
    });

    const submit = () => {
        patch(route('sites.hosting.custom-pages.update', { site: siteId, hostedPage: page.id }));
    };

    const remove = () => {
        destroy(route('sites.hosting.custom-pages.destroy', { site: siteId, hostedPage: page.id }));
    };

    return (
        <Card>
            <CardHeader
                title={page.title}
                description={`Custom page available at /${page.slug ?? ''}. Changing the slug will automatically create a 301 redirect from the old path.`}
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                        <input
                            type="text"
                            value={data.title}
                            onChange={(event) => setData('title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input
                            type="text"
                            value={data.slug}
                            onChange={(event) => setData('slug', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation label</label>
                        <input
                            type="text"
                            value={data.navigation_label}
                            onChange={(event) => setData('navigation_label', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation order</label>
                        <input
                            type="number"
                            value={data.sort_order}
                            onChange={(event) => setData('sort_order', Number(event.target.value))}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body HTML</label>
                    <textarea
                        value={data.body_html}
                        onChange={(event) => setData('body_html', event.target.value)}
                        rows={8}
                        className={TEXTAREA_CLASS}
                    />
                </div>

                <HostedSectionsEditor sections={data.sections as HostedPageSection[]} onChange={(sections) => setData('sections', sections)} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta title</label>
                        <input
                            type="text"
                            value={data.meta_title}
                            onChange={(event) => setData('meta_title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta description</label>
                        <input
                            type="text"
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <HostedSeoFields
                    data={data}
                    setData={setData as HostedSeoSetData}
                    assets={assets}
                    previewTitle={data.title}
                    previewDescription={previewDescriptionFallback}
                    previewPath={`/${data.slug || page.slug || 'page'}`}
                    previewBaseUrl={previewBaseUrl}
                />

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.show_in_navigation}
                            onChange={(event) => setData('show_in_navigation', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Show in navigation
                    </label>

                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.is_published}
                            onChange={(event) => setData('is_published', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Publish this page
                    </label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button onClick={remove} variant="danger" icon={Trash2} loading={processing}>
                        Delete page
                    </Button>

                    <Button onClick={submit} loading={processing}>
                        Save custom page
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function NewCustomPageCard({
    siteId,
    assets,
    previewBaseUrl,
    previewDescriptionFallback,
}: {
    siteId: number;
    assets: HostedAsset[];
    previewBaseUrl?: string | null;
    previewDescriptionFallback: string;
}) {
    const initialData = {
        title: '',
        slug: '',
        navigation_label: '',
        body_html: '',
        sections: [] as HostedPageSection[],
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        social_title: '',
        social_description: '',
        social_image_asset_id: '' as number | '' | null,
        social_image_url: '',
        robots_noindex: false,
        schema_enabled: true,
        show_in_sitemap: true,
        show_in_feed: false,
        breadcrumbs_enabled: true,
        show_in_navigation: true,
        sort_order: 400,
        is_published: true,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    const submit = () => {
        post(route('sites.hosting.custom-pages.store', { site: siteId }), {
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader
                title="Add custom page"
                description="Create first-party landing pages like pricing, services or case studies."
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                        <input
                            type="text"
                            value={data.title}
                            onChange={(event) => setData('title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input
                            type="text"
                            value={data.slug}
                            onChange={(event) => setData('slug', event.target.value)}
                            placeholder="pricing"
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation label</label>
                        <input
                            type="text"
                            value={data.navigation_label}
                            onChange={(event) => setData('navigation_label', event.target.value)}
                            placeholder="Pricing"
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Navigation order</label>
                        <input
                            type="number"
                            value={data.sort_order}
                            onChange={(event) => setData('sort_order', Number(event.target.value))}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body HTML</label>
                    <textarea
                        value={data.body_html}
                        onChange={(event) => setData('body_html', event.target.value)}
                        rows={6}
                        className={TEXTAREA_CLASS}
                    />
                </div>

                <HostedSectionsEditor sections={data.sections as HostedPageSection[]} onChange={(sections) => setData('sections', sections)} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta title</label>
                        <input
                            type="text"
                            value={data.meta_title}
                            onChange={(event) => setData('meta_title', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Meta description</label>
                        <input
                            type="text"
                            value={data.meta_description}
                            onChange={(event) => setData('meta_description', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <HostedSeoFields
                    data={data}
                    setData={setData as HostedSeoSetData}
                    assets={assets}
                    previewTitle={data.title || 'New page'}
                    previewDescription={previewDescriptionFallback}
                    previewPath={`/${data.slug || 'page'}`}
                    previewBaseUrl={previewBaseUrl}
                />

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.show_in_navigation}
                            onChange={(event) => setData('show_in_navigation', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Show in navigation
                    </label>

                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.is_published}
                            onChange={(event) => setData('is_published', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Publish this page
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button onClick={submit} loading={processing} icon={Plus}>
                        Add custom page
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedAuthorCard({ siteId, author }: { siteId: number; author: HostedAuthor }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        name: author.name,
        slug: author.slug,
        bio: author.bio ?? '',
        avatar_url: author.avatar_url ?? '',
        sort_order: author.sort_order ?? 100,
        is_active: author.is_active,
    });

    return (
        <Card>
            <CardHeader title={author.name} description={`Archive at /authors/${author.slug}`} />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} className={INPUT_CLASS} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Avatar URL</label>
                    <input type="url" value={data.avatar_url} onChange={(event) => setData('avatar_url', event.target.value)} className={INPUT_CLASS} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Bio</label>
                    <textarea value={data.bio} onChange={(event) => setData('bio', event.target.value)} rows={4} className={TEXTAREA_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button onClick={() => destroy(route('sites.hosting.authors.destroy', { site: siteId, hostedAuthor: author.id }))} variant="danger" icon={Trash2} loading={processing}>
                        Delete author
                    </Button>
                    <Button onClick={() => patch(route('sites.hosting.authors.update', { site: siteId, hostedAuthor: author.id }))} loading={processing}>
                        Save author
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function NewHostedAuthorCard({ siteId }: { siteId: number }) {
    const initialData = {
        name: '',
        slug: '',
        bio: '',
        avatar_url: '',
        sort_order: 100,
        is_active: true,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    return (
        <Card>
            <CardHeader title="Add author" description="Create first-party author archive pages and attribution for hosted articles." />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} placeholder="jane-doe" className={INPUT_CLASS} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Avatar URL</label>
                    <input type="url" value={data.avatar_url} onChange={(event) => setData('avatar_url', event.target.value)} className={INPUT_CLASS} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Bio</label>
                    <textarea value={data.bio} onChange={(event) => setData('bio', event.target.value)} rows={4} className={TEXTAREA_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button
                        onClick={() =>
                            post(route('sites.hosting.authors.store', { site: siteId }), {
                                onSuccess: () => reset(),
                            })
                        }
                        loading={processing}
                        icon={Plus}
                    >
                        Add author
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedCategoryCard({ siteId, category }: { siteId: number; category: HostedCategory }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        sort_order: category.sort_order ?? 100,
        is_active: category.is_active,
    });

    return (
        <Card>
            <CardHeader title={category.name} description={`Archive at /categories/${category.slug}`} />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} className={INPUT_CLASS} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Description</label>
                    <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} rows={4} className={TEXTAREA_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button onClick={() => destroy(route('sites.hosting.categories.destroy', { site: siteId, hostedCategory: category.id }))} variant="danger" icon={Trash2} loading={processing}>
                        Delete category
                    </Button>
                    <Button onClick={() => patch(route('sites.hosting.categories.update', { site: siteId, hostedCategory: category.id }))} loading={processing}>
                        Save category
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function NewHostedCategoryCard({ siteId }: { siteId: number }) {
    const initialData = {
        name: '',
        slug: '',
        description: '',
        sort_order: 100,
        is_active: true,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    return (
        <Card>
            <CardHeader title="Add category" description="Organize the hosted blog with category archive pages and SEO grouping." />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} placeholder="seo-strategy" className={INPUT_CLASS} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Description</label>
                    <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} rows={4} className={TEXTAREA_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button
                        onClick={() =>
                            post(route('sites.hosting.categories.store', { site: siteId }), {
                                onSuccess: () => reset(),
                            })
                        }
                        loading={processing}
                        icon={Plus}
                    >
                        Add category
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedTagCard({ siteId, tag }: { siteId: number; tag: HostedTag }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        name: tag.name,
        slug: tag.slug,
        sort_order: tag.sort_order ?? 100,
        is_active: tag.is_active,
    });

    return (
        <Card>
            <CardHeader title={tag.name} description={`Archive at /tags/${tag.slug}`} />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} className={INPUT_CLASS} />
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button onClick={() => destroy(route('sites.hosting.tags.destroy', { site: siteId, hostedTag: tag.id }))} variant="danger" icon={Trash2} loading={processing}>
                        Delete tag
                    </Button>
                    <Button onClick={() => patch(route('sites.hosting.tags.update', { site: siteId, hostedTag: tag.id }))} loading={processing}>
                        Save tag
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function NewHostedTagCard({ siteId }: { siteId: number }) {
    const initialData = {
        name: '',
        slug: '',
        sort_order: 100,
        is_active: true,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    return (
        <Card>
            <CardHeader title="Add tag" description="Attach reusable topic tags to hosted articles and expose archive pages." />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Slug</label>
                        <input type="text" value={data.slug} onChange={(event) => setData('slug', event.target.value)} placeholder="ai-overviews" className={INPUT_CLASS} />
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Sort order</label>
                        <input type="number" value={data.sort_order} onChange={(event) => setData('sort_order', Number(event.target.value))} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active archive
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button
                        onClick={() =>
                            post(route('sites.hosting.tags.store', { site: siteId }), {
                                onSuccess: () => reset(),
                            })
                        }
                        loading={processing}
                        icon={Plus}
                    >
                        Add tag
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedAssetCard({ siteId, asset }: { siteId: number; asset: HostedAsset }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        type: asset.type,
        name: asset.name,
        alt_text: asset.alt_text ?? '',
        is_active: asset.is_active,
    });

    return (
        <Card>
            <CardHeader title={asset.name} description={`${asset.type} · ${asset.path}`} />

            <div className="mt-5 space-y-4">
                {asset.mime_type?.startsWith('image/') && (
                    <img src={asset.public_url} alt={asset.alt_text ?? asset.name} className="h-40 w-full rounded-xl border border-surface-200 object-cover dark:border-surface-700" />
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                        <select value={data.type} onChange={(event) => setData('type', event.target.value as HostedAsset['type'])} className={INPUT_CLASS}>
                            {ASSET_TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Alt text</label>
                    <input type="text" value={data.alt_text} onChange={(event) => setData('alt_text', event.target.value)} className={INPUT_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active asset
                    </label>
                    <div className="text-sm text-surface-500 dark:text-surface-400">
                        {asset.size_bytes ? `${Math.round(asset.size_bytes / 1024)} KB` : 'Unknown size'}
                    </div>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <Button onClick={() => destroy(route('sites.hosting.assets.destroy', { site: siteId, hostedAsset: asset.id }))} variant="danger" icon={Trash2} loading={processing}>
                        Delete asset
                    </Button>
                    <Button onClick={() => patch(route('sites.hosting.assets.update', { site: siteId, hostedAsset: asset.id }))} loading={processing}>
                        Save asset
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function NewHostedAssetCard({ siteId }: { siteId: number }) {
    const initialData = {
        type: 'image' as HostedAsset['type'],
        name: '',
        alt_text: '',
        is_active: true,
        source_url: '',
        asset: null as File | null,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    return (
        <Card>
            <CardHeader title="Upload asset" description="Store hosted media locally so themes and ZIP exports can reuse the same files." />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Name</label>
                        <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                        <select value={data.type} onChange={(event) => setData('type', event.target.value as HostedAsset['type'])} className={INPUT_CLASS}>
                            {ASSET_TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">File</label>
                    <input
                        type="file"
                        onChange={(event) => setData('asset', event.target.files?.[0] ?? null)}
                        className="mt-1.5 block w-full text-sm text-surface-600 file:mr-4 file:rounded-lg file:border-0 file:bg-surface-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-surface-700 hover:file:bg-surface-200 dark:text-surface-300 dark:file:bg-surface-800 dark:file:text-surface-200"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Source URL fallback</label>
                    <input type="url" value={data.source_url} onChange={(event) => setData('source_url', event.target.value)} className={INPUT_CLASS} />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Alt text</label>
                        <input type="text" value={data.alt_text} onChange={(event) => setData('alt_text', event.target.value)} className={INPUT_CLASS} />
                    </div>
                    <label className="flex items-center gap-3 pt-8 text-sm text-surface-700 dark:text-surface-300">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        Active asset
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button
                        onClick={() =>
                            post(route('sites.hosting.assets.store', { site: siteId }), {
                                forceFormData: true,
                                onSuccess: () => reset(),
                            })
                        }
                        loading={processing}
                        icon={Plus}
                    >
                        Upload asset
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedRedirectCard({ siteId, redirect }: { siteId: number; redirect: HostedRedirect }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        source_path: redirect.source_path,
        destination_url: redirect.destination_url,
        http_status: redirect.http_status,
    });

    const submit = () => {
        patch(route('sites.hosting.redirects.update', { site: siteId, hostedRedirect: redirect.id }));
    };

    const remove = () => {
        destroy(route('sites.hosting.redirects.destroy', { site: siteId, hostedRedirect: redirect.id }));
    };

    return (
        <Card>
            <CardHeader
                title={redirect.source_path}
                description={`Redirecting to ${redirect.destination_url}`}
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,220px)_minmax(0,1fr)_180px]">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Source path</label>
                        <input
                            type="text"
                            value={data.source_path}
                            onChange={(event) => setData('source_path', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Destination</label>
                        <input
                            type="text"
                            value={data.destination_url}
                            onChange={(event) => setData('destination_url', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Status</label>
                        <select
                            value={data.http_status}
                            onChange={(event) => setData('http_status', Number(event.target.value) as 301 | 302)}
                            className={INPUT_CLASS}
                        >
                            {REDIRECT_STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-surface-500 dark:text-surface-400">
                    <div className="flex items-center gap-3">
                        <Badge variant="default">{redirect.http_status}</Badge>
                        <span>{redirect.hit_count} hit{redirect.hit_count === 1 ? '' : 's'}</span>
                        <span>Last used: {redirect.last_used_at ? new Date(redirect.last_used_at).toLocaleString() : 'Never'}</span>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button onClick={remove} variant="danger" icon={Trash2} loading={processing}>
                            Delete redirect
                        </Button>
                        <Button onClick={submit} loading={processing}>
                            Save redirect
                        </Button>
                    </div>
                </div>
            </div>
        </Card>
    );
}

function NewHostedRedirectCard({ siteId }: { siteId: number }) {
    const initialData = {
        source_path: '/old-path',
        destination_url: '/new-path',
        http_status: 301 as 301 | 302,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    const submit = () => {
        post(route('sites.hosting.redirects.store', { site: siteId }), {
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader
                title="Add redirect"
                description="Preserve SEO value when a URL changes or when legacy pages need to be forwarded."
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,220px)_minmax(0,1fr)_180px]">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Source path</label>
                        <input
                            type="text"
                            value={data.source_path}
                            onChange={(event) => setData('source_path', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Destination</label>
                        <input
                            type="text"
                            value={data.destination_url}
                            onChange={(event) => setData('destination_url', event.target.value)}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Status</label>
                        <select
                            value={data.http_status}
                            onChange={(event) => setData('http_status', Number(event.target.value) as 301 | 302)}
                            className={INPUT_CLASS}
                        >
                            {REDIRECT_STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button onClick={submit} loading={processing} icon={Plus}>
                        Add redirect
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function HostedNavigationItemCard({ siteId, item }: { siteId: number; item: HostedNavigationItem }) {
    const { data, setData, patch, delete: destroy, processing } = useForm({
        placement: item.placement,
        type: item.type,
        label: item.label,
        path: item.path ?? '',
        url: item.url ?? '',
        open_in_new_tab: item.open_in_new_tab,
        is_active: item.is_active,
        sort_order: item.sort_order,
    });

    const submit = () => {
        patch(route('sites.hosting.navigation-items.update', { site: siteId, hostedNavigationItem: item.id }));
    };

    const remove = () => {
        destroy(route('sites.hosting.navigation-items.destroy', { site: siteId, hostedNavigationItem: item.id }));
    };

    return (
        <Card>
            <CardHeader
                title={item.label}
                description={`${item.placement === 'header' ? 'Header' : 'Footer'} link pointing to ${item.target}`}
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Label</label>
                        <input type="text" value={data.label} onChange={(event) => setData('label', event.target.value)} className={INPUT_CLASS} />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Placement</label>
                        <select
                            value={data.placement}
                            onChange={(event) => setData('placement', event.target.value as 'header' | 'footer')}
                            className={INPUT_CLASS}
                        >
                            {NAVIGATION_PLACEMENT_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_180px]">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Link type</label>
                        <select
                            value={data.type}
                            onChange={(event) => setData('type', event.target.value as 'path' | 'url')}
                            className={INPUT_CLASS}
                        >
                            {NAVIGATION_TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                            {data.type === 'path' ? 'Internal path' : 'External URL'}
                        </label>
                        <input
                            type={data.type === 'path' ? 'text' : 'url'}
                            value={data.type === 'path' ? data.path : data.url}
                            onChange={(event) => data.type === 'path' ? setData('path', event.target.value) : setData('url', event.target.value)}
                            placeholder={data.type === 'path' ? '/pricing' : 'https://example.com'}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Order</label>
                        <input
                            type="number"
                            value={data.sort_order}
                            onChange={(event) => setData('sort_order', Number(event.target.value))}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.open_in_new_tab}
                            onChange={(event) => setData('open_in_new_tab', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Open in new tab
                    </label>

                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) => setData('is_active', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Active
                    </label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-surface-500 dark:text-surface-400">Resolved target: {item.target}</p>
                    <div className="flex items-center gap-3">
                        <Button onClick={remove} variant="danger" icon={Trash2} loading={processing}>
                            Delete link
                        </Button>
                        <Button onClick={submit} loading={processing}>
                            Save link
                        </Button>
                    </div>
                </div>
            </div>
        </Card>
    );
}

function NewHostedNavigationItemCard({ siteId }: { siteId: number }) {
    const initialData = {
        placement: 'header' as 'header' | 'footer',
        type: 'path' as 'path' | 'url',
        label: '',
        path: '/blog',
        url: '',
        open_in_new_tab: false,
        is_active: true,
        sort_order: 100,
    };

    const { data, setData, post, processing, reset } = useForm(initialData);

    const submit = () => {
        post(route('sites.hosting.navigation-items.store', { site: siteId }), {
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader
                title="Add navigation link"
                description="Create a manual header or footer link. If any manual header links exist, they replace the automatic page-based menu."
            />

            <div className="mt-5 space-y-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Label</label>
                        <input type="text" value={data.label} onChange={(event) => setData('label', event.target.value)} className={INPUT_CLASS} />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Placement</label>
                        <select
                            value={data.placement}
                            onChange={(event) => setData('placement', event.target.value as 'header' | 'footer')}
                            className={INPUT_CLASS}
                        >
                            {NAVIGATION_PLACEMENT_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_180px]">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Link type</label>
                        <select
                            value={data.type}
                            onChange={(event) => setData('type', event.target.value as 'path' | 'url')}
                            className={INPUT_CLASS}
                        >
                            {NAVIGATION_TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">
                            {data.type === 'path' ? 'Internal path' : 'External URL'}
                        </label>
                        <input
                            type={data.type === 'path' ? 'text' : 'url'}
                            value={data.type === 'path' ? data.path : data.url}
                            onChange={(event) => data.type === 'path' ? setData('path', event.target.value) : setData('url', event.target.value)}
                            placeholder={data.type === 'path' ? '/pricing' : 'https://example.com'}
                            className={INPUT_CLASS}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Order</label>
                        <input
                            type="number"
                            value={data.sort_order}
                            onChange={(event) => setData('sort_order', Number(event.target.value))}
                            className={INPUT_CLASS}
                        />
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.open_in_new_tab}
                            onChange={(event) => setData('open_in_new_tab', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Open in new tab
                    </label>

                    <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) => setData('is_active', event.target.checked)}
                            className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        />
                        Active
                    </label>
                </div>

                <div className="flex justify-end">
                    <Button onClick={submit} loading={processing} icon={Plus}>
                        Add navigation link
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
    const hostingHealth = site.hosting_health;
    const exportRuns = [...(site.hosted_export_runs ?? [])];
    const deployEvents = [...(site.hosted_deploy_events ?? [])];

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
            logo_asset_id: themeSettings.logo_asset_id ?? null,
            social_image_asset_id: themeSettings.social_image_asset_id ?? null,
            logo_url: themeSettings.logo_url ?? '',
            social_image_url: themeSettings.social_image_url ?? '',
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

    const systemPagesByKind = new Map<SystemHostedPageKind, HostedPage>(
        (site.hosted_pages ?? [])
            .filter((page): page is HostedPage & { kind: SystemHostedPageKind } => page.kind !== 'custom')
            .map((page) => [page.kind, page]),
    );

    const customPages = (site.hosted_pages ?? [])
        .filter((page) => page.kind === 'custom')
        .sort((left, right) => (left.sort_order ?? 400) - (right.sort_order ?? 400) || left.title.localeCompare(right.title));
    const redirects = [...(site.hosted_redirects ?? [])].sort((left, right) => left.source_path.localeCompare(right.source_path));
    const authors = [...(site.hosted_authors ?? [])].sort((left, right) => (left.sort_order ?? 100) - (right.sort_order ?? 100) || left.name.localeCompare(right.name));
    const categories = [...(site.hosted_categories ?? [])].sort((left, right) => (left.sort_order ?? 100) - (right.sort_order ?? 100) || left.name.localeCompare(right.name));
    const tags = [...(site.hosted_tags ?? [])].sort((left, right) => (left.sort_order ?? 100) - (right.sort_order ?? 100) || left.name.localeCompare(right.name));
    const hostedAssets = [...(site.hosted_assets ?? [])];
    const navigationItems = [...(site.hosted_navigation_items ?? [])].sort((left, right) => {
        const placementCompare = left.placement.localeCompare(right.placement);

        return placementCompare || left.sort_order - right.sort_order || left.label.localeCompare(right.label);
    });
    const headerNavigationItems = navigationItems.filter((item) => item.placement === 'header');
    const footerNavigationItems = navigationItems.filter((item) => item.placement === 'footer');

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
                                Manage staging, domain, theme, static pages and exports for {site.name}.
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
                        title="Hosted operations"
                        description="Track DNS, SSL, exports and recent deployment callbacks from the hosted lane."
                    />

                    <div className="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge variant={hostedHealthVariant(hostingHealth?.overall_status ?? 'neutral')}>
                                    {(hostingHealth?.overall_status ?? 'neutral').replace('_', ' ')}
                                </Badge>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Latest export:{' '}
                                    <span className="font-medium text-surface-900 dark:text-white">
                                        {exportRuns[0]?.completed_at ? formatDateTime(exportRuns[0].completed_at) : 'No completed export yet'}
                                    </span>
                                </p>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2">
                                {(hostingHealth?.checks ?? []).map((check) => (
                                    <div key={check.key} className="rounded-2xl border border-surface-200 p-4 dark:border-surface-800">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold text-surface-900 dark:text-white">{check.label}</p>
                                                <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">{check.value}</p>
                                            </div>
                                            <Badge variant={hostedHealthVariant(check.status)}>{check.status}</Badge>
                                        </div>
                                        {check.detail && (
                                            <p className="mt-3 text-xs leading-5 text-surface-500 dark:text-surface-400">{check.detail}</p>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {hostingHealth?.dns_check && (
                                <div className="rounded-2xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/40">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <p className="text-sm font-semibold text-surface-900 dark:text-white">Live DNS check</p>
                                        <Badge variant={hostedHealthVariant(hostingHealth.dns_check.matched ? 'healthy' : 'warning')}>
                                            {hostingHealth.dns_check.matched ? 'Matched' : 'Mismatch'}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 grid gap-3 text-sm text-surface-600 dark:text-surface-400 md:grid-cols-2">
                                        <p>
                                            Expected:{' '}
                                            <span className="font-medium text-surface-900 dark:text-white">
                                                {hostingHealth.dns_check.expected?.type} {hostingHealth.dns_check.expected?.value ?? 'Not configured'}
                                            </span>
                                        </p>
                                        <p>
                                            Live records:{' '}
                                            <span className="font-medium text-surface-900 dark:text-white">
                                                {hostingHealth.dns_check.records.length > 0 ? hostingHealth.dns_check.records.join(', ') : 'None detected'}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="space-y-4">
                            <div className="rounded-2xl border border-surface-200 p-4 dark:border-surface-800">
                                <div className="flex items-center gap-2">
                                    <Download className="h-4 w-4 text-surface-500 dark:text-surface-400" />
                                    <p className="text-sm font-semibold text-surface-900 dark:text-white">Recent exports</p>
                                </div>

                                {exportRuns.length > 0 ? (
                                    <div className="mt-4 space-y-3">
                                        {exportRuns.map((run) => (
                                            <div key={run.id} className="rounded-xl border border-surface-200 p-3 dark:border-surface-800">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-medium text-surface-900 dark:text-white">
                                                            Export #{run.id}
                                                        </p>
                                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                            {run.completed_at ? formatDateTime(run.completed_at) : formatDateTime(run.started_at ?? run.created_at)}
                                                        </p>
                                                    </div>
                                                    <Badge variant={hostedHealthVariant(run.status)}>{run.status}</Badge>
                                                </div>
                                                <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-surface-500 dark:text-surface-400">
                                                    <span>{formatBytes(run.size_bytes)}</span>
                                                    {run.error_message && (
                                                        <span className="text-red-600 dark:text-red-400">{run.error_message}</span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">
                                        No export history yet.
                                    </p>
                                )}
                            </div>

                            <div className="rounded-2xl border border-surface-200 p-4 dark:border-surface-800">
                                <div className="flex items-center gap-2">
                                    <Activity className="h-4 w-4 text-surface-500 dark:text-surface-400" />
                                    <p className="text-sm font-semibold text-surface-900 dark:text-white">Deploy events</p>
                                </div>

                                {deployEvents.length > 0 ? (
                                    <div className="mt-4 space-y-3">
                                        {deployEvents.map((event) => (
                                            <div key={event.id} className="rounded-xl border border-surface-200 p-3 dark:border-surface-800">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-medium text-surface-900 dark:text-white">{event.title}</p>
                                                        <p className="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                                            {formatDateTime(event.occurred_at ?? event.created_at)}
                                                        </p>
                                                    </div>
                                                    <Badge variant={hostedHealthVariant(event.status)}>{event.status}</Badge>
                                                </div>
                                                {event.message && (
                                                    <p className="mt-3 text-xs leading-5 text-surface-500 dark:text-surface-400">{event.message}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-4 text-sm text-surface-500 dark:text-surface-400">
                                        No deploy history yet.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

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
                                    className={INPUT_CLASS}
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
                                className={INPUT_CLASS}
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
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Hero title</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.hero_title ?? ''}
                                onChange={(event) => updateThemeField('hero_title', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Hero description</label>
                            <textarea
                                rows={3}
                                value={themeData.theme_settings.hero_description ?? ''}
                                onChange={(event) => updateThemeField('hero_description', event.target.value)}
                                className={TEXTAREA_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Accent color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.accent_color ?? ''}
                                onChange={(event) => updateThemeField('accent_color', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Surface color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.surface_color ?? ''}
                                onChange={(event) => updateThemeField('surface_color', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Text color</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.text_color ?? ''}
                                onChange={(event) => updateThemeField('text_color', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Logo asset</label>
                            <select
                                value={themeData.theme_settings.logo_asset_id ?? ''}
                                onChange={(event) => updateThemeField('logo_asset_id', Number(event.target.value) || null)}
                                className={INPUT_CLASS}
                            >
                                <option value="">Manual URL / none</option>
                                {hostedAssets
                                    .filter((asset) => asset.is_active && (asset.type === 'logo' || asset.type === 'image'))
                                    .map((asset) => (
                                        <option key={asset.id} value={asset.id}>
                                            {asset.name}
                                        </option>
                                    ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Logo URL</label>
                            <input
                                type="url"
                                value={themeData.theme_settings.logo_url ?? ''}
                                onChange={(event) => updateThemeField('logo_url', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Social image asset</label>
                            <select
                                value={themeData.theme_settings.social_image_asset_id ?? ''}
                                onChange={(event) => updateThemeField('social_image_asset_id', Number(event.target.value) || null)}
                                className={INPUT_CLASS}
                            >
                                <option value="">Manual URL / none</option>
                                {hostedAssets
                                    .filter((asset) => asset.is_active && (asset.type === 'social' || asset.type === 'image'))
                                    .map((asset) => (
                                        <option key={asset.id} value={asset.id}>
                                            {asset.name}
                                        </option>
                                    ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Default social image</label>
                            <input
                                type="url"
                                value={themeData.theme_settings.social_image_url ?? ''}
                                onChange={(event) => updateThemeField('social_image_url', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Footer text</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.footer_text ?? ''}
                                onChange={(event) => updateThemeField('footer_text', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Heading font</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.heading_font ?? ''}
                                onChange={(event) => updateThemeField('heading_font', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Body font</label>
                            <input
                                type="text"
                                value={themeData.theme_settings.body_font ?? ''}
                                onChange={(event) => updateThemeField('body_font', event.target.value)}
                                className={INPUT_CLASS}
                            />
                        </div>

                        {SOCIAL_KEYS.map((key) => (
                            <div key={key}>
                                <label className="block text-sm font-medium capitalize text-surface-700 dark:text-surface-300">{key}</label>
                                <input
                                    type="url"
                                    value={themeData.theme_settings.social_links?.[key] ?? ''}
                                    onChange={(event) => updateSocialField(key, event.target.value)}
                                    className={INPUT_CLASS}
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

                <Card>
                    <CardHeader
                        title="Navigation"
                        description="Manual navigation lets the hosted lane expose curated header and footer links. Header links override the automatic page-based menu."
                    />

                    <div className="mt-5 space-y-6">
                        <NewHostedNavigationItemCard siteId={site.id} />

                        <div className="grid gap-6 xl:grid-cols-2">
                            <div>
                                <h3 className="text-base font-semibold text-surface-900 dark:text-white">Header links</h3>
                                {headerNavigationItems.length > 0 ? (
                                    <div className="mt-4 space-y-6">
                                        {headerNavigationItems.map((item) => (
                                            <HostedNavigationItemCard key={item.id} siteId={site.id} item={item} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                        No manual header links yet. The hosted site will keep using the automatic page-based navigation until you add one.
                                    </div>
                                )}
                            </div>

                            <div>
                                <h3 className="text-base font-semibold text-surface-900 dark:text-white">Footer links</h3>
                                {footerNavigationItems.length > 0 ? (
                                    <div className="mt-4 space-y-6">
                                        {footerNavigationItems.map((item) => (
                                            <HostedNavigationItemCard key={item.id} siteId={site.id} item={item} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                        No manual footer links yet.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                <Card>
                    <CardHeader
                        title="Media library"
                        description="Upload hosted assets once, then reuse them across the live theme and ZIP exports."
                    />

                    <div className="mt-5 space-y-6">
                        <NewHostedAssetCard siteId={site.id} />

                        {hostedAssets.length > 0 ? (
                            <div className="grid gap-6 xl:grid-cols-2">
                                {hostedAssets.map((asset) => (
                                    <HostedAssetCard key={asset.id} siteId={site.id} asset={asset} />
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                No hosted assets uploaded yet.
                            </div>
                        )}
                    </div>
                </Card>

                <div className="grid gap-6 xl:grid-cols-3">
                    {SYSTEM_PAGE_KINDS.map((kind) => (
                        <HostedPageFormCard
                            key={kind}
                            siteId={site.id}
                            kind={kind}
                            page={systemPagesByKind.get(kind)}
                            assets={hostedAssets}
                            previewBaseUrl={liveUrl}
                            previewDescriptionFallback={site.business_description ?? ''}
                        />
                    ))}
                </div>

                <Card>
                    <CardHeader
                        title="Custom pages"
                        description="Add first-party landing pages to the hosted lane and keep them in navigation, sitemap and exports."
                    />

                    <div className="mt-5 space-y-6">
                        <NewCustomPageCard
                            siteId={site.id}
                            assets={hostedAssets}
                            previewBaseUrl={liveUrl}
                            previewDescriptionFallback={site.business_description ?? ''}
                        />

                        {customPages.length > 0 ? (
                            <div className="grid gap-6 xl:grid-cols-2">
                                {customPages.map((page) => (
                                    <CustomHostedPageCard
                                        key={page.id}
                                        siteId={site.id}
                                        page={page}
                                        assets={hostedAssets}
                                        previewBaseUrl={liveUrl}
                                        previewDescriptionFallback={site.business_description ?? ''}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                No custom pages yet. Add pricing, services, resources or case-study pages here.
                            </div>
                        )}
                    </div>
                </Card>

                <Card>
                    <CardHeader
                        title="Authors and taxonomies"
                        description="Define the first-party editorial structure used by hosted article pages, archives and exports."
                    />

                    <div className="mt-5 space-y-8">
                        <div className="grid gap-6 xl:grid-cols-3">
                            <NewHostedAuthorCard siteId={site.id} />
                            <NewHostedCategoryCard siteId={site.id} />
                            <NewHostedTagCard siteId={site.id} />
                        </div>

                        <div className="space-y-6">
                            <div>
                                <h3 className="text-base font-semibold text-surface-900 dark:text-white">Authors</h3>
                                {authors.length > 0 ? (
                                    <div className="mt-4 grid gap-6 xl:grid-cols-2">
                                        {authors.map((author) => (
                                            <HostedAuthorCard key={author.id} siteId={site.id} author={author} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                        No hosted authors yet.
                                    </div>
                                )}
                            </div>

                            <div>
                                <h3 className="text-base font-semibold text-surface-900 dark:text-white">Categories</h3>
                                {categories.length > 0 ? (
                                    <div className="mt-4 grid gap-6 xl:grid-cols-2">
                                        {categories.map((category) => (
                                            <HostedCategoryCard key={category.id} siteId={site.id} category={category} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                        No hosted categories yet.
                                    </div>
                                )}
                            </div>

                            <div>
                                <h3 className="text-base font-semibold text-surface-900 dark:text-white">Tags</h3>
                                {tags.length > 0 ? (
                                    <div className="mt-4 grid gap-6 xl:grid-cols-2">
                                        {tags.map((tag) => (
                                            <HostedTagCard key={tag.id} siteId={site.id} tag={tag} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                        No hosted tags yet.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

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

                    {exportRuns[0] && (
                        <div className="mt-4 rounded-2xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-900/40">
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge variant={hostedHealthVariant(exportRuns[0].status)}>{exportRuns[0].status}</Badge>
                                <p className="text-sm text-surface-600 dark:text-surface-400">
                                    Latest run finished at{' '}
                                    <span className="font-medium text-surface-900 dark:text-white">
                                        {formatDateTime(exportRuns[0].completed_at ?? exportRuns[0].started_at ?? exportRuns[0].created_at)}
                                    </span>
                                </p>
                            </div>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardHeader
                        title="Redirects"
                        description="Keep legacy URLs alive and forward old paths to the right destination."
                    />

                    <div className="mt-5 space-y-6">
                        <NewHostedRedirectCard siteId={site.id} />

                        {redirects.length > 0 ? (
                            <div className="space-y-4">
                                {redirects.map((redirect) => (
                                    <HostedRedirectCard key={redirect.id} siteId={site.id} redirect={redirect} />
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-surface-300 p-6 text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400">
                                No redirects configured yet. Add them when URLs change or when you migrate old content into the hosted lane.
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
