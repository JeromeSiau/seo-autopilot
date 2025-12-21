import { useEffect } from 'react';
import confetti from 'canvas-confetti';
import { PartyPopper } from 'lucide-react';

interface Props {
    articlesPlanned: number;
}

export default function Celebration({ articlesPlanned }: Props) {
    useEffect(() => {
        // Fire confetti
        const duration = 2000;
        const end = Date.now() + duration;

        const frame = () => {
            confetti({
                particleCount: 3,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#10b981', '#34d399', '#6ee7b7'],
            });
            confetti({
                particleCount: 3,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#10b981', '#34d399', '#6ee7b7'],
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        };

        frame();
    }, []);

    return (
        <div className="flex flex-col items-center justify-center min-h-[500px] text-center">
            <div className="relative">
                <div className="absolute inset-0 animate-ping rounded-full bg-primary-400/30" />
                <div className="relative inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-primary-600">
                    <PartyPopper className="h-12 w-12 text-white" />
                </div>
            </div>

            <h1 className="mt-8 text-4xl font-bold text-surface-900 dark:text-white">
                C'est prêt !
            </h1>

            <p className="mt-4 text-xl text-surface-600 dark:text-surface-400 max-w-md">
                Votre Content Plan pour les 30 prochains jours est prêt avec{' '}
                <span className="font-semibold text-primary-600 dark:text-primary-400">
                    {articlesPlanned} articles
                </span>
            </p>
        </div>
    );
}
