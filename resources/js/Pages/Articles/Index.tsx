import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    FileText, ExternalLink, Edit, Trash2, CheckCircle, Clock, Loader2, AlertCircle,
    Search, ChevronLeft, ChevronRight, Hash, Eye, Send, BookOpen
} from 'lucide-react';
import clsx from 'clsx';
import { Article, Site, PaginatedData, PageProps } from '@/types';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

interface ArticlesIndexProps extends PageProps {
    articles: PaginatedData<Article>;
    sites: Site[];
    filters: {
        site_id?: number;
        status?: string;
        search?: string;
    };
    stats: {
        total: number;
        review: number;
        approved: number;
        published: number;
    };
}

const STAT_CARDS = [
    { key: 'total', label: 'Total', icon: Hash, color: 'primary' },
    { key: 'review', label: 'En review', icon: Eye, color: 'amber' },
    { key: 'approved', label: 'Approuvés', icon: CheckCircle, color: 'blue' },
    { key: 'published', label: 'Publiés', icon: Send, color: 'primary' },
] as const;

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: typeof Clock; animate?: boolean }> = {
    generating: { label: 'En génération', color: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400', icon: Loader2, animate: true },
    review: { label: 'En review', color: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', icon: Eye },
    approved: { label: 'Approuvé', color: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400', icon: CheckCircle },
    published: { label: 'Publié', color: 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400', icon: CheckCircle },
    failed: { label: 'Échec', color: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400', icon: AlertCircle },
};

export default function ArticlesIndex({ articles, sites, filters, stats }: ArticlesIndexProps) {
    const handleDelete = (article: Article) => {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
            router.delete(route('articles.destroy', { article: article.id }));
        }
    };

    const handleApprove = (article: Article) => {
        router.post(route('articles.approve', { article: article.id }));
    };

    const handlePublish = (article: Article) => {
        router.post(route('articles.publish', { article: article.id }));
    };

    const getColorClasses = (color: string) => {
        const colors = {
            primary: { text: 'text-primary-600 dark:text-primary-400', iconBg: 'bg-primary-100 dark:bg-primary-500/10' },
            blue: { text: 'text-blue-600 dark:text-blue-400', iconBg: 'bg-blue-100 dark:bg-blue-500/10' },
            amber: { text: 'text-amber-600 dark:text-amber-400', iconBg: 'bg-amber-100 dark:bg-amber-500/10' },
        };
        return colors[color as keyof typeof colors] || colors.primary;
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">Articles</h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Générés automatiquement par l'Autopilot
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <BookOpen className="h-5 w-5 text-primary-500" />
                        <span className="text-sm font-medium text-surface-700 dark:text-surface-300">
                            {stats.total} articles générés
                        </span>
                    </div>
                </div>
            }
        >
            <Head title="Articles" />

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {STAT_CARDS.map((card) => {
                    const colors = getColorClasses(card.color);
                    const Icon = card.icon;
                    const value = stats[card.key];
                    return (
                        <div
                            key={card.key}
                            className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-5 hover:shadow-md transition-shadow"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm font-medium text-surface-500 dark:text-surface-400">{card.label}</p>
                                    <p className={clsx(
                                        'mt-2 font-display text-2xl font-bold',
                                        card.key === 'review' ? 'text-amber-600 dark:text-amber-400' :
                                        card.key === 'approved' ? 'text-blue-600 dark:text-blue-400' :
                                        card.key === 'published' ? 'text-primary-600 dark:text-primary-400' : 'text-surface-900 dark:text-white'
                                    )}>
                                        {value}
                                    </p>
                                </div>
                                <div className={clsx('rounded-xl p-2.5', colors.iconBg)}>
                                    <Icon className={clsx('h-5 w-5', colors.text)} />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Filters */}
            <div className="mt-6 flex flex-wrap gap-3">
                <select
                    value={filters.site_id || ''}
                    onChange={(e) =>
                        router.get(route('articles.index'), {
                            ...filters,
                            site_id: e.target.value || undefined,
                        })
                    }
                    className={clsx(
                        'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">Tous les sites</option>
                    {sites.map((site) => (
                        <option key={site.id} value={site.id}>
                            {site.name}
                        </option>
                    ))}
                </select>

                <select
                    value={filters.status || ''}
                    onChange={(e) =>
                        router.get(route('articles.index'), {
                            ...filters,
                            status: e.target.value || undefined,
                        })
                    }
                    className={clsx(
                        'rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors'
                    )}
                >
                    <option value="">Tous les statuts</option>
                    <option value="generating">En génération</option>
                    <option value="review">En review</option>
                    <option value="approved">Approuvé</option>
                    <option value="published">Publié</option>
                    <option value="failed">Échec</option>
                </select>

                <div className="relative flex-1 min-w-[200px]">
                    <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="search"
                        placeholder="Rechercher un article..."
                        value={filters.search || ''}
                        onChange={(e) =>
                            router.get(
                                route('articles.index'),
                                { ...filters, search: e.target.value || undefined },
                                { preserveState: true }
                            )
                        }
                        className={clsx(
                            'w-full rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 pl-10 pr-4 py-2.5 text-sm text-surface-900 dark:text-white',
                            'placeholder:text-surface-400',
                            'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                            'transition-colors'
                        )}
                    />
                </div>
            </div>

            {/* Articles Table / Empty State */}
            <div className="mt-6">
                {articles.data.length === 0 ? (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-12 text-center">
                        <div className="mx-auto w-14 h-14 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-4">
                            <FileText className="h-7 w-7 text-surface-400" />
                        </div>
                        <h3 className="font-display font-semibold text-surface-900 dark:text-white mb-1">
                            Aucun article trouvé
                        </h3>
                        <p className="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto">
                            Les articles seront générés automatiquement par l'Autopilot une fois vos sites configurés.
                        </p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-surface-100 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-800/50">
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            Titre
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            Mots
                                        </th>
                                        <th className="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            Statut
                                        </th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            Créé
                                        </th>
                                        <th className="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-surface-100 dark:divide-surface-800">
                                    {articles.data.map((article) => {
                                        const statusConfig = STATUS_CONFIG[article.status as keyof typeof STATUS_CONFIG] || STATUS_CONFIG.review;
                                        const StatusIcon = statusConfig.icon;
                                        return (
                                            <tr key={article.id} className="hover:bg-surface-50/50 dark:hover:bg-surface-800/50 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="max-w-md">
                                                        <Link
                                                            href={route('articles.show', { article: article.id })}
                                                            className="font-medium text-surface-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                                        >
                                                            {article.title}
                                                        </Link>
                                                        {article.site && (
                                                            <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                                                {article.site.domain}
                                                            </p>
                                                        )}
                                                        {article.keyword && (
                                                            <p className="mt-0.5 text-xs text-primary-600 dark:text-primary-400 font-medium">
                                                                "{article.keyword.keyword}"
                                                            </p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="font-display text-sm font-semibold text-surface-700 dark:text-surface-300">
                                                        {article.word_count?.toLocaleString() || '—'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <div className="flex flex-col items-center gap-1.5">
                                                        <span className={clsx(
                                                            'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium',
                                                            statusConfig.color
                                                        )}>
                                                            <StatusIcon className={clsx(
                                                                'h-3.5 w-3.5',
                                                                statusConfig.animate && 'animate-spin'
                                                            )} />
                                                            {statusConfig.label}
                                                        </span>
                                                        {article.published_url && (
                                                            <a
                                                                href={article.published_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium"
                                                            >
                                                                Voir
                                                                <ExternalLink className="h-3 w-3" />
                                                            </a>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className="text-sm text-surface-500 dark:text-surface-400">
                                                        {format(new Date(article.created_at), 'd MMM yyyy', { locale: fr })}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex justify-end gap-2">
                                                        {article.status === 'review' && (
                                                            <button
                                                                onClick={() => handleApprove(article)}
                                                                className={clsx(
                                                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5',
                                                                    'text-xs font-medium border border-primary-200 dark:border-primary-500/30',
                                                                    'text-primary-700 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20',
                                                                    'transition-colors'
                                                                )}
                                                            >
                                                                <CheckCircle className="h-3.5 w-3.5" />
                                                                Approuver
                                                            </button>
                                                        )}
                                                        {article.status === 'approved' && (
                                                            <button
                                                                onClick={() => handlePublish(article)}
                                                                className={clsx(
                                                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5',
                                                                    'text-xs font-semibold text-white',
                                                                    'bg-gradient-to-r from-primary-500 to-primary-600',
                                                                    'shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg',
                                                                    'transition-all'
                                                                )}
                                                            >
                                                                <Send className="h-3.5 w-3.5" />
                                                                Publier
                                                            </button>
                                                        )}
                                                        <Link
                                                            href={route('articles.edit', { article: article.id })}
                                                            className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300 transition-colors"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                        <button
                                                            onClick={() => handleDelete(article)}
                                                            className="rounded-lg p-2 text-surface-400 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {articles.meta.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-surface-100 dark:border-surface-800 px-6 py-4">
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{articles.meta.from}</span>
                                    {' '}à{' '}
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{articles.meta.to}</span>
                                    {' '}sur{' '}
                                    <span className="font-medium text-surface-700 dark:text-surface-300">{articles.meta.total}</span>
                                    {' '}articles
                                </p>
                                <div className="flex gap-2">
                                    {articles.links.prev ? (
                                        <Link
                                            href={articles.links.prev}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600 transition-colors"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                            Précédent
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 dark:border-surface-800 px-3 py-2 text-sm font-medium text-surface-300 dark:text-surface-600 cursor-not-allowed">
                                            <ChevronLeft className="h-4 w-4" />
                                            Précédent
                                        </span>
                                    )}
                                    {articles.links.next ? (
                                        <Link
                                            href={articles.links.next}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600 transition-colors"
                                        >
                                            Suivant
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-surface-100 dark:border-surface-800 px-3 py-2 text-sm font-medium text-surface-300 dark:text-surface-600 cursor-not-allowed">
                                            Suivant
                                            <ChevronRight className="h-4 w-4" />
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
