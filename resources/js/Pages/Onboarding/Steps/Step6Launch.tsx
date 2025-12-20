import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { router } from '@inertiajs/react';
import { Rocket, Check } from 'lucide-react';

interface Props {
    siteId: number;
    onBack: () => void;
}

export default function Step6Launch({ siteId, onBack }: Props) {
    const [loading, setLoading] = useState(false);

    const handleLaunch = () => {
        setLoading(true);
        router.post(route('onboarding.complete', siteId));
    };

    return (
        <div className="space-y-6">
            <div className="text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
                    <Rocket className="h-8 w-8 text-indigo-600" />
                </div>
                <h2 className="mt-4 text-2xl font-bold text-gray-900">Prêt à lancer !</h2>
                <p className="mt-2 text-gray-600">
                    Votre autopilot est configuré. Cliquez pour démarrer.
                </p>
            </div>

            <div className="rounded-lg bg-green-50 p-6">
                <h3 className="font-medium text-green-900">Ce qui va se passer :</h3>
                <ul className="mt-3 space-y-2">
                    {[
                        'Analyse de votre site et Search Console',
                        'Découverte de mots-clés pertinents',
                        'Génération automatique d\'articles SEO',
                        'Publication selon votre planning',
                    ].map((item, i) => (
                        <li key={i} className="flex items-center gap-2 text-sm text-green-700">
                            <Check className="h-4 w-4" />
                            {item}
                        </li>
                    ))}
                </ul>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={onBack}>Retour</Button>
                <Button onClick={handleLaunch} loading={loading} className="gap-2">
                    <Rocket className="h-4 w-4" />
                    Activer l'Autopilot
                </Button>
            </div>
        </div>
    );
}
