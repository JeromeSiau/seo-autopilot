import { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import clsx from 'clsx';

interface ScheduledArticle {
    id: number;
    date: string;
    keyword: string;
    volume: number | null;
    difficulty: number | null;
    score: number;
    status: 'planned' | 'generating' | 'ready' | 'published' | 'skipped';
    article_id: number | null;
}

interface Props {
    siteId: number;
    initialMonth?: string;
}

export default function ContentCalendar({ siteId, initialMonth }: Props) {
    const [month, setMonth] = useState(initialMonth || new Date().toISOString().slice(0, 7));
    const [articles, setArticles] = useState<ScheduledArticle[]>([]);
    const [availableMonths, setAvailableMonths] = useState<string[]>([]);
    const [loading, setLoading] = useState(true);
    const [initialized, setInitialized] = useState(false);

    useEffect(() => {
        fetchCalendar();
    }, [month]);

    // Jump to first available month on initial load if current month has no articles
    useEffect(() => {
        if (!initialized && availableMonths.length > 0 && !availableMonths.includes(month)) {
            setMonth(availableMonths[0]);
            setInitialized(true);
        } else if (availableMonths.length > 0) {
            setInitialized(true);
        }
    }, [availableMonths, month, initialized]);

    const fetchCalendar = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/sites/${siteId}/content-plan?month=${month}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!res.ok) {
                console.error('API error:', res.status);
                setLoading(false);
                return;
            }

            const data = await res.json();
            setArticles(data.articles || []);
            if (data.available_months) {
                setAvailableMonths(data.available_months);
            }
        } catch (e) {
            console.error('Failed to fetch calendar', e);
        }
        setLoading(false);
    };

    // Find navigation bounds based on available months
    const currentMonthIndex = availableMonths.indexOf(month);
    const hasAvailableMonths = availableMonths.length > 0;

    // If we have available months, only navigate within those
    // If current month is not in list, find nearest available month
    const canGoPrev = hasAvailableMonths
        ? (currentMonthIndex > 0 || (currentMonthIndex === -1 && month > availableMonths[0]))
        : true;
    const canGoNext = hasAvailableMonths
        ? (currentMonthIndex < availableMonths.length - 1 || (currentMonthIndex === -1 && month < availableMonths[availableMonths.length - 1]))
        : true;

    const prevMonth = () => {
        if (hasAvailableMonths) {
            if (currentMonthIndex > 0) {
                setMonth(availableMonths[currentMonthIndex - 1]);
            } else if (currentMonthIndex === -1) {
                // Current month not in list, find previous available
                const prev = availableMonths.filter(m => m < month).pop();
                if (prev) setMonth(prev);
            }
        } else {
            const d = new Date(month + '-01');
            d.setMonth(d.getMonth() - 1);
            setMonth(d.toISOString().slice(0, 7));
        }
    };

    const nextMonth = () => {
        if (hasAvailableMonths) {
            if (currentMonthIndex >= 0 && currentMonthIndex < availableMonths.length - 1) {
                setMonth(availableMonths[currentMonthIndex + 1]);
            } else if (currentMonthIndex === -1) {
                // Current month not in list, find next available
                const next = availableMonths.find(m => m > month);
                if (next) setMonth(next);
            }
        } else {
            const d = new Date(month + '-01');
            d.setMonth(d.getMonth() + 1);
            setMonth(d.toISOString().slice(0, 7));
        }
    };

    // Generate calendar grid
    const firstDay = new Date(month + '-01');
    const lastDay = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0);
    const startPadding = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // Monday start
    const totalDays = lastDay.getDate();

    const days: (number | null)[] = [];
    for (let i = 0; i < startPadding; i++) {
        days.push(null);
    }
    for (let i = 1; i <= totalDays; i++) {
        days.push(i);
    }

    const getArticleForDay = (day: number | null) => {
        if (!day) return null;
        const dateStr = `${month}-${String(day).padStart(2, '0')}`;
        return articles.find(a => a.date === dateStr);
    };

    const monthName = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(firstDay);

    return (
        <div className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-surface-200 dark:border-surface-800">
                <button
                    onClick={prevMonth}
                    disabled={!canGoPrev}
                    className={clsx(
                        'p-2 rounded-lg transition-colors',
                        canGoPrev
                            ? 'hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
                            : 'text-surface-300 dark:text-surface-600 cursor-not-allowed'
                    )}
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <h3 className="font-semibold text-lg capitalize text-surface-900 dark:text-white">
                    {monthName}
                </h3>
                <button
                    onClick={nextMonth}
                    disabled={!canGoNext}
                    className={clsx(
                        'p-2 rounded-lg transition-colors',
                        canGoNext
                            ? 'hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
                            : 'text-surface-300 dark:text-surface-600 cursor-not-allowed'
                    )}
                >
                    <ChevronRight className="h-5 w-5" />
                </button>
            </div>

            {/* Day headers */}
            <div className="grid grid-cols-7 border-b border-surface-200 dark:border-surface-800">
                {['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(day => (
                    <div key={day} className="p-2 text-center text-xs font-medium text-surface-500 dark:text-surface-400">
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar grid */}
            <div className="grid grid-cols-7">
                {days.map((day, index) => {
                    const article = getArticleForDay(day);
                    const isToday = day && new Date().toISOString().slice(0, 10) === `${month}-${String(day).padStart(2, '0')}`;

                    return (
                        <div
                            key={index}
                            className={clsx(
                                'min-h-[100px] p-2 border-b border-r border-surface-100 dark:border-surface-800',
                                !day && 'bg-surface-50 dark:bg-surface-900',
                            )}
                        >
                            {day && (
                                <>
                                    <span className={clsx(
                                        'inline-flex items-center justify-center w-7 h-7 rounded-full text-sm',
                                        isToday && 'bg-primary-500 text-white font-semibold',
                                        !isToday && 'text-surface-600 dark:text-surface-400'
                                    )}>
                                        {day}
                                    </span>

                                    {article && (
                                        <div className={clsx(
                                            'mt-1 p-2 rounded-lg text-xs',
                                            article.status === 'planned' && 'bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20',
                                            article.status === 'generating' && 'bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20',
                                            article.status === 'ready' && 'bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20',
                                            article.status === 'published' && 'bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20',
                                        )}>
                                            <p className="font-medium text-surface-700 dark:text-surface-300 line-clamp-2">
                                                {article.keyword}
                                            </p>
                                            {article.volume && (
                                                <p className="mt-1 text-surface-500 dark:text-surface-400">
                                                    Vol: {article.volume.toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
