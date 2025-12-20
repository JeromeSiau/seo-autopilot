import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';

interface Props {
    data: { domain: string; name: string; language: string };
    setData: (data: any) => void;
    onNext: (siteId: number) => void;
}

export default function Step1Site({ data, setData, onNext }: Props) {
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await axios.post(route('onboarding.step1'), data);
            onNext(response.data.site_id);
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
                <h2 className="text-2xl font-bold text-gray-900">Ajouter votre site</h2>
                <p className="mt-2 text-gray-600">Commençons par les informations de base</p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Domaine</label>
                <input
                    type="text"
                    value={data.domain}
                    onChange={(e) => setData({ ...data, domain: e.target.value })}
                    placeholder="monsite.com"
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {errors.domain && <p className="mt-1 text-sm text-red-600">{errors.domain}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Nom du site</label>
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData({ ...data, name: e.target.value })}
                    placeholder="Mon Super Site"
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Langue du contenu</label>
                <select
                    value={data.language}
                    onChange={(e) => setData({ ...data, language: e.target.value })}
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                    <option value="de">Deutsch</option>
                    <option value="es">Español</option>
                    <option value="it">Italiano</option>
                </select>
            </div>

            <div className="flex justify-end">
                <Button type="submit" loading={loading}>Continuer</Button>
            </div>
        </form>
    );
}
