import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { BrandAsset, BrandRule, PageProps, Site } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, RefreshCw, Save, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface BrandKitPageProps extends PageProps {
    site: Site;
    brandAssets: BrandAsset[];
    brandRules: BrandRule[];
    assetTypes: BrandAsset['type'][];
    ruleCategories: BrandRule['category'][];
    contentImportSummary: {
        available_pages: number;
        imported_page_assets: number;
        available_articles: number;
        imported_article_assets: number;
    };
}

const fieldClass =
    'mt-1.5 block w-full rounded-xl border border-surface-200 bg-white px-4 py-3 text-sm text-surface-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white';

function titleForType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function AssetCard({
    siteId,
    assetTypes,
    asset,
}: {
    siteId: number;
    assetTypes: BrandAsset['type'][];
    asset?: BrandAsset;
}) {
    const isEditing = Boolean(asset);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        type: asset?.type ?? assetTypes[0],
        title: asset?.title ?? '',
        source_url: asset?.source_url ?? '',
        content: asset?.content ?? '',
        priority: asset?.priority ?? 50,
        is_active: asset?.is_active ?? true,
    });

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!isEditing) {
                    reset();
                    setData('type', assetTypes[0]);
                    setData('priority', 50);
                    setData('is_active', true);
                }
            },
        };

        if (isEditing && asset) {
            patch(route('sites.brand-assets.update', { site: siteId, brandAsset: asset.id }), options);
            return;
        }

        post(route('sites.brand-assets.store', { site: siteId }), options);
    };

    return (
        <Card className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-surface-900 dark:text-white">
                        {isEditing ? data.title || 'Brand asset' : 'Add brand asset'}
                    </h3>
                    <p className="text-sm text-surface-500 dark:text-surface-400">
                        Reference content, claims, FAQs or examples the AI should respect.
                    </p>
                </div>

                {isEditing && asset && (
                    <button
                        type="button"
                        onClick={() => router.delete(route('sites.brand-assets.destroy', { site: siteId, brandAsset: asset.id }), { preserveScroll: true })}
                        className="rounded-lg p-2 text-surface-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                    <select
                        value={data.type}
                        onChange={(event) => setData('type', event.target.value as BrandAsset['type'])}
                        className={fieldClass}
                    >
                        {assetTypes.map((type) => (
                            <option key={type} value={type}>
                                {titleForType(type)}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Priority</label>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        value={data.priority}
                        onChange={(event) => setData('priority', Number(event.target.value))}
                        className={fieldClass}
                    />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Title</label>
                <input
                    type="text"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    className={fieldClass}
                />
                {errors.title && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.title}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Source URL</label>
                <input
                    type="url"
                    value={data.source_url}
                    onChange={(event) => setData('source_url', event.target.value)}
                    className={fieldClass}
                    placeholder="https://example.com/page"
                />
                {errors.source_url && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.source_url}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Content</label>
                <textarea
                    rows={6}
                    value={data.content}
                    onChange={(event) => setData('content', event.target.value)}
                    className={fieldClass}
                    placeholder="Paste the important guidance, proof points, positioning, or policy text."
                />
                {errors.content && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.content}</p>}
            </div>

            <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(event) => setData('is_active', event.target.checked)}
                    className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                />
                Active in generation
            </label>

            <div className="flex justify-end">
                <Button onClick={submit} loading={processing} icon={Save}>
                    {isEditing ? 'Save asset' : 'Add asset'}
                </Button>
            </div>
        </Card>
    );
}

function RuleCard({
    siteId,
    ruleCategories,
    rule,
}: {
    siteId: number;
    ruleCategories: BrandRule['category'][];
    rule?: BrandRule;
}) {
    const isEditing = Boolean(rule);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        category: rule?.category ?? ruleCategories[0],
        label: rule?.label ?? '',
        value: rule?.value ?? '',
        priority: rule?.priority ?? 50,
        is_active: rule?.is_active ?? true,
    });

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!isEditing) {
                    reset();
                    setData('category', ruleCategories[0]);
                    setData('priority', 50);
                    setData('is_active', true);
                }
            },
        };

        if (isEditing && rule) {
            patch(route('sites.brand-rules.update', { site: siteId, brandRule: rule.id }), options);
            return;
        }

        post(route('sites.brand-rules.store', { site: siteId }), options);
    };

    return (
        <Card className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-surface-900 dark:text-white">
                        {isEditing ? data.label || 'Brand rule' : 'Add brand rule'}
                    </h3>
                    <p className="text-sm text-surface-500 dark:text-surface-400">
                        Hard guardrails for the writing model and review flow.
                    </p>
                </div>

                {isEditing && rule && (
                    <button
                        type="button"
                        onClick={() => router.delete(route('sites.brand-rules.destroy', { site: siteId, brandRule: rule.id }), { preserveScroll: true })}
                        className="rounded-lg p-2 text-surface-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Category</label>
                    <select
                        value={data.category}
                        onChange={(event) => setData('category', event.target.value as BrandRule['category'])}
                        className={fieldClass}
                    >
                        {ruleCategories.map((category) => (
                            <option key={category} value={category}>
                                {titleForType(category)}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Priority</label>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        value={data.priority}
                        onChange={(event) => setData('priority', Number(event.target.value))}
                        className={fieldClass}
                    />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Label</label>
                <input
                    type="text"
                    value={data.label}
                    onChange={(event) => setData('label', event.target.value)}
                    className={fieldClass}
                />
                {errors.label && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.label}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-surface-700 dark:text-surface-300">Rule</label>
                <textarea
                    rows={4}
                    value={data.value}
                    onChange={(event) => setData('value', event.target.value)}
                    className={fieldClass}
                    placeholder="Example: Never claim guarantees. Mention implementation tradeoffs when relevant."
                />
                {errors.value && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.value}</p>}
            </div>

            <label className="flex items-center gap-3 text-sm text-surface-700 dark:text-surface-300">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(event) => setData('is_active', event.target.checked)}
                    className="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                />
                Active in generation
            </label>

            <div className="flex justify-end">
                <Button onClick={submit} loading={processing} icon={Save}>
                    {isEditing ? 'Save rule' : 'Add rule'}
                </Button>
            </div>
        </Card>
    );
}

export default function BrandKit({
    site,
    brandAssets,
    brandRules,
    assetTypes,
    ruleCategories,
    contentImportSummary,
}: BrandKitPageProps) {
    const [isImportingHostedPages, setIsImportingHostedPages] = useState(false);
    const [isImportingPublishedArticles, setIsImportingPublishedArticles] = useState(false);

    const importHostedPages = () => {
        setIsImportingHostedPages(true);

        router.post(
            route('sites.brand-kit.import-hosted-pages', { site: site.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsImportingHostedPages(false),
            }
        );
    };

    const importPublishedArticles = () => {
        setIsImportingPublishedArticles(true);

        router.post(
            route('sites.brand-kit.import-published-articles', { site: site.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsImportingPublishedArticles(false),
            }
        );
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
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">Brand Kit</h1>
                                <Badge variant={site.mode === 'hosted' ? 'primary' : 'secondary'}>
                                    {site.mode === 'hosted' ? 'Hosted' : 'External'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                Guardrails and reference material for {site.name}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            icon={RefreshCw}
                            loading={isImportingPublishedArticles}
                            disabled={contentImportSummary.available_articles === 0}
                            onClick={importPublishedArticles}
                        >
                            Import published articles
                        </Button>

                        {site.mode === 'hosted' && (
                            <Button
                                type="button"
                                variant="secondary"
                                icon={RefreshCw}
                                loading={isImportingHostedPages}
                                disabled={contentImportSummary.available_pages === 0}
                                onClick={importHostedPages}
                            >
                                Import hosted pages
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Brand Kit - ${site.name}`} />

            <div className="space-y-6">
                <div className={`grid gap-4 ${site.mode === 'hosted' ? 'xl:grid-cols-4' : 'xl:grid-cols-3'}`}>
                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-blue-50 p-3 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
                                <BookOpen className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="font-semibold text-surface-900 dark:text-white">Assets</h2>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Structured source material the model can reuse.
                                </p>
                                <p className="mt-2 text-sm text-surface-700 dark:text-surface-300">
                                    {brandAssets.filter((asset) => asset.is_active).length} active of {brandAssets.length}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-emerald-50 p-3 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="font-semibold text-surface-900 dark:text-white">Rules</h2>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Non-negotiable constraints for tone, claims and CTA behavior.
                                </p>
                                <p className="mt-2 text-sm text-surface-700 dark:text-surface-300">
                                    {brandRules.filter((rule) => rule.is_active).length} active of {brandRules.length}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-violet-50 p-3 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                                <RefreshCw className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="font-semibold text-surface-900 dark:text-white">Article Sync</h2>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Reuse published articles as style samples for future generations.
                                </p>
                                <p className="mt-2 text-sm text-surface-700 dark:text-surface-300">
                                    {contentImportSummary.imported_article_assets} imported assets from {contentImportSummary.available_articles} eligible articles
                                </p>
                            </div>
                        </div>
                    </Card>

                    {site.mode === 'hosted' && (
                        <Card>
                            <div className="flex items-start gap-4">
                                <div className="rounded-xl bg-amber-50 p-3 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                                    <RefreshCw className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="font-semibold text-surface-900 dark:text-white">Hosted Sync</h2>
                                    <p className="text-sm text-surface-500 dark:text-surface-400">
                                        Pull published hosted pages into the brand knowledge base.
                                    </p>
                                    <p className="mt-2 text-sm text-surface-700 dark:text-surface-300">
                                        {contentImportSummary.imported_page_assets} imported assets from {contentImportSummary.available_pages} eligible pages
                                    </p>
                                </div>
                            </div>
                        </Card>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="space-y-4">
                        <AssetCard siteId={site.id} assetTypes={assetTypes} />
                        {brandAssets.map((asset) => (
                            <AssetCard key={asset.id} siteId={site.id} assetTypes={assetTypes} asset={asset} />
                        ))}
                    </div>

                    <div className="space-y-4">
                        <RuleCard siteId={site.id} ruleCategories={ruleCategories} />
                        {brandRules.map((rule) => (
                            <RuleCard key={rule.id} siteId={site.id} ruleCategories={ruleCategories} rule={rule} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
