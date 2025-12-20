import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';
import { Globe, ArrowRight } from 'lucide-react';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

export default function Step5Integration({ siteId, onNext, onBack }: Props) {
    const [loading, setLoading] = useState(false);

    const handleConnect = async () => {
        setLoading(true);
        try {
            const response = await axios.post(route('onboarding.step5', siteId), { skip: false });
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
            await axios.post(route('onboarding.step5', siteId), { skip: true });
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
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                    <Globe className="h-8 w-8 text-green-600" />
                </div>
                <h2 className="mt-4 text-2xl font-bold text-gray-900">Intégration Publication</h2>
                <p className="mt-2 text-gray-600">
                    Connectez votre CMS pour publier automatiquement les articles
                </p>
            </div>

            <div className="grid gap-3">
                {['WordPress', 'Webflow', 'Shopify'].map((platform) => (
                    <button
                        key={platform}
                        onClick={handleConnect}
                        disabled={loading}
                        className="flex items-center gap-4 rounded-lg border p-4 text-left hover:bg-gray-50"
                    >
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 font-bold text-gray-600">
                            {platform[0]}
                        </div>
                        <div>
                            <p className="font-medium text-gray-900">{platform}</p>
                            <p className="text-sm text-gray-500">Publication automatique</p>
                        </div>
                    </button>
                ))}
            </div>

            <div className="rounded-lg bg-gray-50 p-4 text-center text-sm text-gray-600">
                Sans intégration, les articles seront disponibles en téléchargement
            </div>

            <button
                onClick={handleSkip}
                disabled={loading}
                className="flex w-full items-center justify-center gap-2 text-sm text-gray-500 hover:text-gray-700"
            >
                Configurer plus tard <ArrowRight className="h-4 w-4" />
            </button>

            <div className="flex justify-start">
                <Button variant="secondary" onClick={onBack}>Retour</Button>
            </div>
        </div>
    );
}
