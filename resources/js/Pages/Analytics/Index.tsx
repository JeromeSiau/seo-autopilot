import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    TrendingUp,
    TrendingDown,
    MousePointer,
    Eye,
    RefreshCw,
} from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { StatCard } from '@/Components/ui/StatCard';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '@/Components/ui/Table';
import {
    LineChart,
    Line,
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { Site, AnalyticsData, PageProps } from '@/types';
import { format } from 'date-fns';
import { useState } from 'react';

interface AnalyticsIndexProps extends PageProps {
    sites: Site[];
    selectedSite: Site | null;
    analyticsData: AnalyticsData[];
    summary: {
        total_clicks: number;
        total_impressions: number;
        avg_position: number;
        avg_ctr: number;
        clicks_change: number;
        impressions_change: number;
    };
    topPages: Array<{
        page: string;
        clicks: number;
        impressions: number;
        position: number;
        ctr: number;
    }>;
    topQueries: Array<{
        query: string;
        clicks: number;
        impressions: number;
        position: number;
        ctr: number;
    }>;
    dateRange: string;
}

export default function AnalyticsIndex({
    sites,
    selectedSite,
    analyticsData = [],
    summary,
    topPages = [],
    topQueries = [],
    dateRange,
}: AnalyticsIndexProps) {
    const [syncing, setSyncing] = useState(false);

    const handleSiteChange = (siteId: string) => {
        router.get(route('analytics.index'), { site_id: siteId || undefined });
    };

    const handleDateRangeChange = (range: string) => {
        router.get(route('analytics.index'), {
            site_id: selectedSite?.id,
            range,
        });
    };

    const handleSync = () => {
        if (!selectedSite) return;
        setSyncing(true);
        router.post(
            route('analytics.sync', selectedSite.id),
            {},
            {
                onFinish: () => setSyncing(false),
            }
        );
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Analytics</h1>
                    <div className="flex items-center gap-3">
                        <select
                            value={selectedSite?.id || ''}
                            onChange={(e) => handleSiteChange(e.target.value)}
                            className="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Sites</option>
                            {sites.map((site) => (
                                <option key={site.id} value={site.id}>
                                    {site.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={dateRange}
                            onChange={(e) => handleDateRangeChange(e.target.value)}
                            className="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="7">Last 7 days</option>
                            <option value="28">Last 28 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                        {selectedSite && (
                            <Button
                                onClick={handleSync}
                                loading={syncing}
                                variant="secondary"
                                icon={RefreshCw}
                            >
                                Sync
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Analytics" />

            {!selectedSite ? (
                <Card className="text-center">
                    <BarChart3 className="mx-auto h-12 w-12 text-gray-400" />
                    <h3 className="mt-4 text-lg font-semibold text-gray-900">
                        Select a site to view analytics
                    </h3>
                    <p className="mt-2 text-sm text-gray-500">
                        Choose a site from the dropdown above to see performance data.
                    </p>
                </Card>
            ) : !selectedSite.gsc_connected ? (
                <Card className="text-center">
                    <BarChart3 className="mx-auto h-12 w-12 text-gray-400" />
                    <h3 className="mt-4 text-lg font-semibold text-gray-900">
                        Connect Google Search Console
                    </h3>
                    <p className="mt-2 text-sm text-gray-500">
                        Connect GSC to see search performance data for {selectedSite.domain}.
                    </p>
                    <div className="mt-6">
                        <Button as="link" href={route('sites.edit', selectedSite.id)}>
                            Connect GSC
                        </Button>
                    </div>
                </Card>
            ) : (
                <>
                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Total Clicks"
                            value={summary.total_clicks.toLocaleString()}
                            icon={MousePointer}
                            color="indigo"
                            trend={
                                summary.clicks_change !== 0
                                    ? {
                                          value: summary.clicks_change,
                                          label: 'vs previous period',
                                      }
                                    : undefined
                            }
                        />
                        <StatCard
                            title="Impressions"
                            value={summary.total_impressions.toLocaleString()}
                            icon={Eye}
                            color="blue"
                            trend={
                                summary.impressions_change !== 0
                                    ? {
                                          value: summary.impressions_change,
                                          label: 'vs previous period',
                                      }
                                    : undefined
                            }
                        />
                        <StatCard
                            title="Avg CTR"
                            value={`${summary.avg_ctr.toFixed(1)}%`}
                            icon={TrendingUp}
                            color="green"
                        />
                        <StatCard
                            title="Avg Position"
                            value={summary.avg_position.toFixed(1)}
                            icon={BarChart3}
                            color="purple"
                        />
                    </div>

                    {/* Chart */}
                    {analyticsData.length > 0 && (
                        <Card className="mt-6">
                            <CardHeader
                                title="Performance Over Time"
                                description="Clicks and impressions trends"
                            />
                            <div className="mt-6 h-80">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={analyticsData}>
                                        <defs>
                                            <linearGradient id="colorClicks" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.1} />
                                                <stop offset="95%" stopColor="#4f46e5" stopOpacity={0} />
                                            </linearGradient>
                                            <linearGradient
                                                id="colorImpressions"
                                                x1="0"
                                                y1="0"
                                                x2="0"
                                                y2="1"
                                            >
                                                <stop offset="5%" stopColor="#10b981" stopOpacity={0.1} />
                                                <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                            </linearGradient>
                                        </defs>
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
                                            labelFormatter={(value) =>
                                                format(new Date(value), 'MMM d, yyyy')
                                            }
                                        />
                                        <Legend />
                                        <Area
                                            yAxisId="left"
                                            type="monotone"
                                            dataKey="clicks"
                                            stroke="#4f46e5"
                                            fillOpacity={1}
                                            fill="url(#colorClicks)"
                                            strokeWidth={2}
                                            name="Clicks"
                                        />
                                        <Area
                                            yAxisId="right"
                                            type="monotone"
                                            dataKey="impressions"
                                            stroke="#10b981"
                                            fillOpacity={1}
                                            fill="url(#colorImpressions)"
                                            strokeWidth={2}
                                            name="Impressions"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>
                    )}

                    {/* Tables */}
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        {/* Top Pages */}
                        <Card padding="none">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-gray-900">Top Pages</h3>
                            </div>
                            {topPages.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-gray-500">
                                    No page data available yet.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Page
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Clicks
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Pos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {topPages.slice(0, 10).map((page, index) => (
                                                <tr key={index}>
                                                    <td className="max-w-xs truncate px-4 py-3 text-sm text-gray-900">
                                                        {page.page}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                                        {page.clicks.toLocaleString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                                        {page.position.toFixed(1)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>

                        {/* Top Queries */}
                        <Card padding="none">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-gray-900">Top Queries</h3>
                            </div>
                            {topQueries.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-gray-500">
                                    No query data available yet.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Query
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Clicks
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Pos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {topQueries.slice(0, 10).map((query, index) => (
                                                <tr key={index}>
                                                    <td className="max-w-xs truncate px-4 py-3 text-sm text-gray-900">
                                                        {query.query}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                                        {query.clicks.toLocaleString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                                        {query.position.toFixed(1)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
