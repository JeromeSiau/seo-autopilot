import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Settings, ChevronRight, ChevronLeft, Zap, Eye } from 'lucide-react';
import clsx from 'clsx';

interface Props {
    siteId: number;
    team: { articles_limit: number };
    onNext: () => void;
    onBack: () => void;
}

const DAYS = [
    { key: 'mon', label: 'L', fullLabel: 'Lundi' },
    { key: 'tue', label: 'M', fullLabel: 'Mardi' },
    { key: 'wed', label: 'M', fullLabel: 'Mercredi' },
    { key: 'thu', label: 'J', fullLabel: 'Jeudi' },
    { key: 'fri', label: 'V', fullLabel: 'Vendredi' },
    { key: 'sat', label: 'S', fullLabel: 'Samedi' },
    { key: 'sun', label: 'D', fullLabel: 'Dimanche' },
];

export default function Step4Config({ siteId, team, onNext, onBack }: Props) {
    const defaultPerWeek = team.articles_limit <= 10 ? 2 : team.articles_limit <= 30 ? 7 : 25;
    const maxPerWeek = team.articles_limit <= 10 ? 3 : team.articles_limit <= 30 ? 10 : 30;

    const [loading, setLoading] = useState(false);
    const [data, setData] = useState({
        articles_per_week: defaultPerWeek,
        publish_days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
        auto_publish: true,
    });

    const toggleDay = (day: string) => {
        if (data.publish_days.includes(day)) {
            if (data.publish_days.length > 1) {
                setData({ ...data, publish_days: data.publish_days.filter((d) => d !== day) });
            }
        } else {
            setData({ ...data, publish_days: [...data.publish_days, day] });
        }
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);

        try {
            await axios.post(route('onboarding.step4', siteId), data);
            onNext();
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-amber-200">
                    <Settings className="h-7 w-7 text-amber-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900">
                    Configuration Autopilot
                </h2>
                <p className="mt-2 text-surface-500">
                    Définissez le rythme de publication
                </p>
            </div>

            {/* Articles per Week */}
            <div className="rounded-xl bg-surface-50 p-5">
                <div className="flex items-center justify-between mb-4">
                    <label className="text-sm font-medium text-surface-700">
                        Articles par semaine
                    </label>
                    <span className="font-display text-2xl font-bold text-primary-600">
                        {data.articles_per_week}
                    </span>
                </div>
                <input
                    type="range"
                    min={1}
                    max={maxPerWeek}
                    value={data.articles_per_week}
                    onChange={(e) => setData({ ...data, articles_per_week: parseInt(e.target.value) })}
                    className="w-full h-2 bg-surface-200 rounded-full appearance-none cursor-pointer
                        [&::-webkit-slider-thumb]:appearance-none
                        [&::-webkit-slider-thumb]:h-5
                        [&::-webkit-slider-thumb]:w-5
                        [&::-webkit-slider-thumb]:rounded-full
                        [&::-webkit-slider-thumb]:bg-primary-500
                        [&::-webkit-slider-thumb]:shadow-md
                        [&::-webkit-slider-thumb]:cursor-pointer
                        [&::-webkit-slider-thumb]:transition-transform
                        [&::-webkit-slider-thumb]:hover:scale-110"
                />
                <div className="mt-2 flex justify-between text-xs text-surface-500">
                    <span>1 article</span>
                    <span>{maxPerWeek} articles</span>
                </div>
            </div>

            {/* Publish Days */}
            <div>
                <label className="block text-sm font-medium text-surface-700 mb-3">
                    Jours de publication
                </label>
                <div className="flex gap-2">
                    {DAYS.map((day) => (
                        <button
                            key={day.key}
                            type="button"
                            onClick={() => toggleDay(day.key)}
                            title={day.fullLabel}
                            className={clsx(
                                'flex-1 rounded-xl py-3 text-sm font-semibold transition-all',
                                data.publish_days.includes(day.key)
                                    ? 'bg-primary-500 text-white shadow-green'
                                    : 'bg-surface-100 text-surface-500 hover:bg-surface-200'
                            )}
                        >
                            {day.label}
                        </button>
                    ))}
                </div>
                <p className="mt-2 text-xs text-surface-500">
                    {data.publish_days.length} jour{data.publish_days.length > 1 ? 's' : ''} sélectionné{data.publish_days.length > 1 ? 's' : ''}
                </p>
            </div>

            {/* Publish Mode */}
            <div>
                <label className="block text-sm font-medium text-surface-700 mb-3">
                    Mode de publication
                </label>
                <div className="space-y-3">
                    <label
                        className={clsx(
                            'flex items-start gap-4 rounded-xl border p-4 cursor-pointer transition-all',
                            data.auto_publish
                                ? 'border-primary-500 bg-primary-50/50 ring-2 ring-primary-500/20'
                                : 'border-surface-200 hover:border-surface-300 hover:bg-surface-50'
                        )}
                    >
                        <input
                            type="radio"
                            checked={data.auto_publish}
                            onChange={() => setData({ ...data, auto_publish: true })}
                            className="sr-only"
                        />
                        <div className={clsx(
                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                            data.auto_publish ? 'bg-primary-100' : 'bg-surface-100'
                        )}>
                            <Zap className={clsx(
                                'h-5 w-5',
                                data.auto_publish ? 'text-primary-600' : 'text-surface-400'
                            )} />
                        </div>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <p className={clsx(
                                    'font-medium',
                                    data.auto_publish ? 'text-primary-900' : 'text-surface-900'
                                )}>
                                    Auto-publish
                                </p>
                                <span className="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">
                                    Recommandé
                                </span>
                            </div>
                            <p className="mt-0.5 text-sm text-surface-500">
                                Les articles sont publiés automatiquement selon le planning
                            </p>
                        </div>
                    </label>

                    <label
                        className={clsx(
                            'flex items-start gap-4 rounded-xl border p-4 cursor-pointer transition-all',
                            !data.auto_publish
                                ? 'border-primary-500 bg-primary-50/50 ring-2 ring-primary-500/20'
                                : 'border-surface-200 hover:border-surface-300 hover:bg-surface-50'
                        )}
                    >
                        <input
                            type="radio"
                            checked={!data.auto_publish}
                            onChange={() => setData({ ...data, auto_publish: false })}
                            className="sr-only"
                        />
                        <div className={clsx(
                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                            !data.auto_publish ? 'bg-primary-100' : 'bg-surface-100'
                        )}>
                            <Eye className={clsx(
                                'h-5 w-5',
                                !data.auto_publish ? 'text-primary-600' : 'text-surface-400'
                            )} />
                        </div>
                        <div>
                            <p className={clsx(
                                'font-medium',
                                !data.auto_publish ? 'text-primary-900' : 'text-surface-900'
                            )}>
                                Review avant publication
                            </p>
                            <p className="mt-0.5 text-sm text-surface-500">
                                Validez chaque article avant publication
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            {/* Actions */}
            <div className="flex items-center justify-between pt-2">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Retour
                </button>
                <button
                    type="submit"
                    disabled={loading}
                    className={clsx(
                        'flex items-center gap-2 rounded-xl px-6 py-3',
                        'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold',
                        'shadow-green hover:shadow-green-lg hover:-translate-y-0.5',
                        'transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                    )}
                >
                    {loading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <>
                            Continuer
                            <ChevronRight className="h-5 w-5" />
                        </>
                    )}
                </button>
            </div>
        </form>
    );
}
