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
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchCalendar();
    }, [month]);

    const fetchCalendar = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/api/sites/${siteId}/content-plan?month=${month}`);
            const data = await res.json();
            setArticles(data.articles || []);
        } catch (e) {
            console.error('Failed to fetch calendar', e);
        }
        setLoading(false);
    };

    const prevMonth = () => {
        const d = new Date(month + '-01');
        d.setMonth(d.getMonth() - 1);
        setMonth(d.toISOString().slice(0, 7));
    };

    const nextMonth = () => {
        const d = new Date(month + '-01');
        d.setMonth(d.getMonth() + 1);
        setMonth(d.toISOString().slice(0, 7));
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
                    className="p-2 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <h3 className="font-semibold text-lg capitalize text-surface-900 dark:text-white">
                    {monthName}
                </h3>
                <button
                    onClick={nextMonth}
                    className="p-2 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors"
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
