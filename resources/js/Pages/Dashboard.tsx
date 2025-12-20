import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Globe,
    Search,
    FileText,
    MousePointer,
    Eye,
    TrendingUp,
    Plus,
    ArrowRight,
} from 'lucide-react';
import { StatCard } from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Badge, getStatusVariant } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { DashboardStats, Article, Keyword, AnalyticsData, PageProps } from '@/types';
import { format } from 'date-fns';

interface DashboardPageProps extends PageProps {
    stats: DashboardStats;
    recentArticles: Article[];
    topKeywords: Keyword[];
    analyticsData: AnalyticsData[];
}

export default function Dashboard({
    stats,
    recentArticles = [],
    topKeywords = [],
    analyticsData = [],
}: DashboardPageProps) {
    const usagePercentage = stats.articles_limit > 0
        ? Math.round((stats.articles_used / stats.articles_limit) * 100)
        : 0;

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <div className="flex gap-3">
                        <Button as="link" href={route('keywords.index')} variant="secondary" icon={Search}>
                            Discover Keywords
                        </Button>
                        <Button as="link" href={route('articles.create')} icon={Plus}>
                            Generate Article
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />

            {/* Stats Grid */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Sites"
                    value={stats.total_sites}
                    icon={Globe}
                    color="blue"
                />
                <StatCard
                    title="Keywords"
                    value={stats.total_keywords}
                    icon={Search}
                    color="purple"
                />
                <StatCard
                    title="Articles"
                    value={stats.total_articles}
                    icon={FileText}
                    color="green"
                    trend={stats.articles_this_month > 0 ? {
                        value: stats.articles_this_month,
                        label: 'this month',
                    } : undefined}
                />
                <StatCard
                    title="Published"
                    value={stats.articles_published}
                    icon={TrendingUp}
                    color="indigo"
                />
            </div>

            {/* Usage Bar */}
            <Card className="mt-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-500">Monthly Usage</p>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">
                            {stats.articles_used} / {stats.articles_limit} articles
                        </p>
                    </div>
                    <Button as="link" href={route('settings.billing')} variant="outline" size="sm">
                        Upgrade Plan
                    </Button>
                </div>
                <div className="mt-4">
                    <div className="h-2 w-full rounded-full bg-gray-200">
                        <div
                            className={`h-2 rounded-full transition-all ${
                                usagePercentage >= 90 ? 'bg-red-500' :
                                usagePercentage >= 70 ? 'bg-yellow-500' : 'bg-indigo-600'
                            }`}
                            style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                        />
                    </div>
                </div>
            </Card>

            {/* Analytics Chart */}
            {analyticsData.length > 0 && (
                <Card className="mt-6">
                    <CardHeader
                        title="Performance Overview"
                        description="Clicks and impressions over time"
                    />
                    <div className="mt-6 h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={analyticsData}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                <XAxis
                                    dataKey="date"
                                    tickFormatter={(value) => format(new Date(value), 'MMM d')}
                                    tick={{ fontSize: 12 }}
                                    stroke="#9ca3af"
                                />
                                <YAxis
                                    yAxisId="left"
                                    tick={{ fontSize: 12 }}
                                    stroke="#9ca3af"
                                />
                                <YAxis
                                    yAxisId="right"
                                    orientation="right"
                                    tick={{ fontSize: 12 }}
                                    stroke="#9ca3af"
                                />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'white',
                                        border: '1px solid #e5e7eb',
                                        borderRadius: '8px',
                                    }}
                                />
                                <Line
                                    yAxisId="left"
                                    type="monotone"
                                    dataKey="clicks"
                                    stroke="#4f46e5"
                                    strokeWidth={2}
                                    dot={false}
                                    name="Clicks"
                                />
                                <Line
                                    yAxisId="right"
                                    type="monotone"
                                    dataKey="impressions"
                                    stroke="#10b981"
                                    strokeWidth={2}
                                    dot={false}
                                    name="Impressions"
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </Card>
            )}

            {/* Two Column Layout */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Recent Articles */}
                <Card padding="none">
                    <div className="border-b border-gray-200 px-6 py-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-gray-900">Recent Articles</h3>
                            <Link
                                href={route('articles.index')}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                View all <ArrowRight className="inline h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                    <div className="divide-y divide-gray-200">
                        {recentArticles.length === 0 ? (
                            <div className="px-6 py-8 text-center text-sm text-gray-500">
                                No articles yet. Generate your first article!
                            </div>
                        ) : (
                            recentArticles.slice(0, 5).map((article) => (
                                <Link
                                    key={article.id}
                                    href={route('articles.show', article.id)}
                                    className="block px-6 py-4 hover:bg-gray-50"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-900">
                                                {article.title}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500">
                                                {article.word_count} words • {format(new Date(article.created_at), 'MMM d, yyyy')}
                                            </p>
                                        </div>
                                        <Badge variant={getStatusVariant(article.status)}>
                                            {article.status}
                                        </Badge>
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>
                </Card>

                {/* Top Keywords */}
                <Card padding="none">
                    <div className="border-b border-gray-200 px-6 py-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-gray-900">Top Keywords</h3>
                            <Link
                                href={route('keywords.index')}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                View all <ArrowRight className="inline h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                    <div className="divide-y divide-gray-200">
                        {topKeywords.length === 0 ? (
                            <div className="px-6 py-8 text-center text-sm text-gray-500">
                                No keywords yet. Connect Google Search Console to discover opportunities.
                            </div>
                        ) : (
                            topKeywords.slice(0, 5).map((keyword) => (
                                <div key={keyword.id} className="px-6 py-4">
                                    <div className="flex items-center justify-between">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-900">
                                                {keyword.keyword}
                                            </p>
                                            <div className="mt-1 flex items-center gap-3 text-xs text-gray-500">
                                                {keyword.volume && (
                                                    <span className="flex items-center gap-1">
                                                        <Eye className="h-3 w-3" />
                                                        {keyword.volume.toLocaleString()}
                                                    </span>
                                                )}
                                                {keyword.position && (
                                                    <span className="flex items-center gap-1">
                                                        <TrendingUp className="h-3 w-3" />
                                                        Pos {keyword.position.toFixed(1)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="ml-4 flex items-center gap-2">
                                            <div className="text-right">
                                                <p className="text-sm font-semibold text-indigo-600">
                                                    {keyword.score}
                                                </p>
                                                <p className="text-xs text-gray-500">score</p>
                                            </div>
                                            <Badge variant={getStatusVariant(keyword.status)}>
                                                {keyword.status}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </Card>
            </div>

            {/* Quick Stats Row */}
            {(stats.total_clicks > 0 || stats.total_impressions > 0) && (
                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Total Clicks"
                        value={stats.total_clicks.toLocaleString()}
                        icon={MousePointer}
                        color="green"
                    />
                    <StatCard
                        title="Total Impressions"
                        value={stats.total_impressions.toLocaleString()}
                        icon={Eye}
                        color="blue"
                    />
                    <StatCard
                        title="Avg Position"
                        value={stats.avg_position.toFixed(1)}
                        icon={TrendingUp}
                        color="purple"
                    />
                </div>
            )}
        </AppLayout>
    );
}
