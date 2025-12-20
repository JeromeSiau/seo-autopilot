import clsx from 'clsx';
import { LucideIcon, TrendingUp, TrendingDown } from 'lucide-react';

interface StatCardProps {
    title: string;
    value: string | number;
    icon: LucideIcon;
    trend?: {
        value: number;
        label: string;
    };
    color?: 'indigo' | 'green' | 'yellow' | 'red' | 'blue' | 'purple';
}

const colorClasses = {
    indigo: 'bg-indigo-50 text-indigo-600',
    green: 'bg-green-50 text-green-600',
    yellow: 'bg-yellow-50 text-yellow-600',
    red: 'bg-red-50 text-red-600',
    blue: 'bg-blue-50 text-blue-600',
    purple: 'bg-purple-50 text-purple-600',
};

export function StatCard({ title, value, icon: Icon, trend, color = 'indigo' }: StatCardProps) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5">
            <div className="flex items-center gap-4">
                <div className={clsx('rounded-lg p-3', colorClasses[color])}>
                    <Icon className="h-6 w-6" />
                </div>
                <div className="flex-1">
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <div className="mt-1 flex items-baseline gap-2">
                        <p className="text-2xl font-semibold text-gray-900">{value}</p>
                        {trend && (
                            <span
                                className={clsx(
                                    'inline-flex items-center gap-1 text-sm font-medium',
                                    trend.value >= 0 ? 'text-green-600' : 'text-red-600'
                                )}
                            >
                                {trend.value >= 0 ? (
                                    <TrendingUp className="h-4 w-4" />
                                ) : (
                                    <TrendingDown className="h-4 w-4" />
                                )}
                                {Math.abs(trend.value)}%
                            </span>
                        )}
                    </div>
                    {trend && <p className="mt-1 text-xs text-gray-500">{trend.label}</p>}
                </div>
            </div>
        </div>
    );
}
