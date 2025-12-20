import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Briefcase, ChevronRight, ChevronLeft, X, Plus } from 'lucide-react';
import clsx from 'clsx';

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
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-100 to-purple-200">
                    <Briefcase className="h-7 w-7 text-purple-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900">
                    Décrivez votre business
                </h2>
                <p className="mt-2 text-surface-500">
                    Ces informations aident à générer des mots-clés pertinents
                </p>
            </div>

            {/* Business Description */}
            <div>
                <label className="block text-sm font-medium text-surface-700 mb-1.5">
                    Description de votre activité
                </label>
                <textarea
                    value={data.business_description}
                    onChange={(e) => setData({ ...data, business_description: e.target.value })}
                    placeholder="Décrivez votre entreprise, vos produits ou services en quelques phrases..."
                    rows={4}
                    className={clsx(
                        'block w-full rounded-xl border bg-white px-4 py-3 text-surface-900 placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors resize-none',
                        errors.business_description ? 'border-red-300' : 'border-surface-200'
                    )}
                />
                {errors.business_description && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.business_description}</p>
                )}
            </div>

            {/* Target Audience */}
            <div>
                <label className="block text-sm font-medium text-surface-700 mb-1.5">
                    Public cible <span className="text-surface-400 font-normal">(optionnel)</span>
                </label>
                <input
                    type="text"
                    value={data.target_audience}
                    onChange={(e) => setData({ ...data, target_audience: e.target.value })}
                    placeholder="Ex: PME, développeurs, parents..."
                    className={clsx(
                        'block w-full rounded-xl border bg-white px-4 py-3 text-surface-900 placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors border-surface-200'
                    )}
                />
            </div>

            {/* Topics */}
            <div>
                <label className="block text-sm font-medium text-surface-700 mb-1.5">
                    Thématiques principales
                </label>
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={topicInput}
                        onChange={(e) => setTopicInput(e.target.value)}
                        onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addTopic())}
                        placeholder="Ajouter une thématique..."
                        className={clsx(
                            'block flex-1 rounded-xl border bg-white px-4 py-3 text-surface-900 placeholder:text-surface-400',
                            'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                            'transition-colors border-surface-200'
                        )}
                    />
                    <button
                        type="button"
                        onClick={addTopic}
                        disabled={!topicInput.trim() || data.topics.length >= 10}
                        className={clsx(
                            'flex items-center gap-1.5 rounded-xl border border-surface-200 px-4 py-3',
                            'text-sm font-medium text-surface-700 bg-white',
                            'hover:bg-surface-50 hover:border-surface-300 transition-colors',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        )}
                    >
                        <Plus className="h-4 w-4" />
                        Ajouter
                    </button>
                </div>
                {data.topics.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {data.topics.map((topic, index) => (
                            <span
                                key={index}
                                className="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700"
                            >
                                {topic}
                                <button
                                    type="button"
                                    onClick={() => removeTopic(index)}
                                    className="rounded-full p-0.5 hover:bg-primary-100 transition-colors"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}
                <p className="mt-2 text-xs text-surface-500">
                    {data.topics.length}/10 thématiques ajoutées
                </p>
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
