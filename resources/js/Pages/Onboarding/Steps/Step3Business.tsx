import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

export default function Step3Business({ siteId, onNext, onBack }: Props) {
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [data, setData] = useState({
        business_description: '',
        target_audience: '',
        topics: [] as string[],
    });
    const [topicInput, setTopicInput] = useState('');

    const addTopic = () => {
        if (topicInput.trim() && data.topics.length < 10) {
            setData({ ...data, topics: [...data.topics, topicInput.trim()] });
            setTopicInput('');
        }
    };

    const removeTopic = (index: number) => {
        setData({ ...data, topics: data.topics.filter((_, i) => i !== index) });
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            await axios.post(route('onboarding.step3', siteId), data);
            onNext();
        } catch (error: any) {
            if (error.response?.data?.errors) {
                const errs: Record<string, string> = {};
                Object.entries(error.response.data.errors).forEach(([key, val]) => {
                    errs[key] = Array.isArray(val) ? val[0] : String(val);
                });
                setErrors(errs);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Décrivez votre business</h2>
                <p className="mt-2 text-gray-600">Ces informations aident à générer des mots-clés pertinents</p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Description de votre activité</label>
                <textarea
                    value={data.business_description}
                    onChange={(e) => setData({ ...data, business_description: e.target.value })}
                    placeholder="Décrivez votre entreprise, vos produits ou services en quelques phrases..."
                    rows={4}
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {errors.business_description && (
                    <p className="mt-1 text-sm text-red-600">{errors.business_description}</p>
                )}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Public cible (optionnel)</label>
                <input
                    type="text"
                    value={data.target_audience}
                    onChange={(e) => setData({ ...data, target_audience: e.target.value })}
                    placeholder="Ex: PME, développeurs, parents..."
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Thématiques principales</label>
                <div className="mt-1 flex gap-2">
                    <input
                        type="text"
                        value={topicInput}
                        onChange={(e) => setTopicInput(e.target.value)}
                        onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addTopic())}
                        placeholder="Ajouter une thématique..."
                        className="block flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <Button type="button" variant="secondary" onClick={addTopic}>Ajouter</Button>
                </div>
                {data.topics.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-2">
                        {data.topics.map((topic, index) => (
                            <span
                                key={index}
                                className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700"
                            >
                                {topic}
                                <button type="button" onClick={() => removeTopic(index)} className="hover:text-indigo-900">×</button>
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={onBack}>Retour</Button>
                <Button type="submit" loading={loading}>Continuer</Button>
            </div>
        </form>
    );
}
