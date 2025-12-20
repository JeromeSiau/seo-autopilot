import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';

interface Props {
    siteId: number;
    team: { articles_limit: number };
    onNext: () => void;
    onBack: () => void;
}

const DAYS = [
    { key: 'mon', label: 'Lun' },
    { key: 'tue', label: 'Mar' },
    { key: 'wed', label: 'Mer' },
    { key: 'thu', label: 'Jeu' },
    { key: 'fri', label: 'Ven' },
    { key: 'sat', label: 'Sam' },
    { key: 'sun', label: 'Dim' },
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
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Configuration Autopilot</h2>
                <p className="mt-2 text-gray-600">Définissez le rythme de publication</p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">
                    Articles par semaine: {data.articles_per_week}
                </label>
                <input
                    type="range"
                    min={1}
                    max={maxPerWeek}
                    value={data.articles_per_week}
                    onChange={(e) => setData({ ...data, articles_per_week: parseInt(e.target.value) })}
                    className="mt-2 w-full"
                />
                <div className="mt-1 flex justify-between text-xs text-gray-500">
                    <span>1</span>
                    <span>{maxPerWeek}</span>
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Jours de publication</label>
                <div className="mt-2 flex gap-2">
                    {DAYS.map((day) => (
                        <button
                            key={day.key}
                            type="button"
                            onClick={() => toggleDay(day.key)}
                            className={`flex-1 rounded-lg py-2 text-sm font-medium transition-colors ${
                                data.publish_days.includes(day.key)
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {day.label}
                        </button>
                    ))}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Mode de publication</label>
                <div className="mt-2 space-y-2">
                    <label className="flex items-center gap-3 rounded-lg border p-4 cursor-pointer hover:bg-gray-50">
                        <input
                            type="radio"
                            checked={data.auto_publish}
                            onChange={() => setData({ ...data, auto_publish: true })}
                            className="h-4 w-4 text-indigo-600"
                        />
                        <div>
                            <p className="font-medium text-gray-900">Auto-publish (recommandé)</p>
                            <p className="text-sm text-gray-500">Les articles sont publiés automatiquement</p>
                        </div>
                    </label>
                    <label className="flex items-center gap-3 rounded-lg border p-4 cursor-pointer hover:bg-gray-50">
                        <input
                            type="radio"
                            checked={!data.auto_publish}
                            onChange={() => setData({ ...data, auto_publish: false })}
                            className="h-4 w-4 text-indigo-600"
                        />
                        <div>
                            <p className="font-medium text-gray-900">Review avant publication</p>
                            <p className="text-sm text-gray-500">Validez chaque article avant publication</p>
                        </div>
                    </label>
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={onBack}>Retour</Button>
                <Button type="submit" loading={loading}>Continuer</Button>
            </div>
        </form>
    );
}
