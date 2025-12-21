import { Link } from '@inertiajs/react';
import { Calendar, LayoutDashboard } from 'lucide-react';
import ContentCalendar from './ContentCalendar';

interface Site {
    id: number;
    name: string;
    domain: string;
}

interface Props {
    site: Site;
    articlesPlanned: number;
}

export default function CalendarReveal({ site, articlesPlanned }: Props) {
    return (
        <div className="space-y-8 animate-in fade-in duration-500">
            {/* Header */}
            <div className="text-center">
                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                    Votre Content Plan est prêt !
                </h1>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    {articlesPlanned} articles planifiés pour les 30 prochains jours
                </p>
            </div>

            {/* Calendar */}
            <ContentCalendar siteId={site.id} />

            {/* Actions */}
            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                    href={route('sites.content-plan-page', { site: site.id })}
                    className="inline-flex items-center gap-2 px-6 py-3 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 transition-colors"
                >
                    <Calendar className="h-5 w-5" />
                    Gérer le Content Plan
                </Link>
                <Link
                    href={route('dashboard')}
                    className="inline-flex items-center gap-2 px-6 py-3 bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 font-semibold rounded-xl hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
                >
                    <LayoutDashboard className="h-5 w-5" />
                    Aller au Dashboard
                </Link>
            </div>
        </div>
    );
}
