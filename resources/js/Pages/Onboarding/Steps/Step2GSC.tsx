import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';
import { Search, ArrowRight } from 'lucide-react';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

export default function Step2GSC({ siteId, onNext, onBack }: Props) {
    const [loading, setLoading] = useState(false);

    const handleConnect = async () => {
        setLoading(true);
        try {
            const response = await axios.post(route('onboarding.step2', siteId), { skip: false });
            if (response.data.redirect) {
                window.location.href = response.data.redirect;
            }
        } catch (error) {
            console.error(error);
            setLoading(false);
        }
    };

    const handleSkip = async () => {
        setLoading(true);
        try {
            await axios.post(route('onboarding.step2', siteId), { skip: true });
            onNext();
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
                    <Search className="h-8 w-8 text-indigo-600" />
                </div>
                <h2 className="mt-4 text-2xl font-bold text-gray-900">Google Search Console</h2>
                <p className="mt-2 text-gray-600">
                    Connectez votre Search Console pour découvrir automatiquement des opportunités de mots-clés
                </p>
            </div>

            <div className="rounded-lg bg-indigo-50 p-4">
                <h3 className="font-medium text-indigo-900">Pourquoi connecter GSC ?</h3>
                <ul className="mt-2 space-y-1 text-sm text-indigo-700">
                    <li>• Découverte automatique de mots-clés performants</li>
                    <li>• Identification des opportunités (positions 5-30)</li>
                    <li>• Suivi des performances de vos articles</li>
                </ul>
            </div>

            <div className="space-y-3">
                <Button onClick={handleConnect} loading={loading} className="w-full">
                    Connecter Google Search Console
                </Button>
                <button
                    onClick={handleSkip}
                    disabled={loading}
                    className="flex w-full items-center justify-center gap-2 text-sm text-gray-500 hover:text-gray-700"
                >
                    Passer cette étape <ArrowRight className="h-4 w-4" />
                </button>
            </div>

            <div className="flex justify-start">
                <Button variant="secondary" onClick={onBack}>Retour</Button>
            </div>
        </div>
    );
}
