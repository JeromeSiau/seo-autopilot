import { useState } from 'react';
import axios from 'axios';
import { Plug, ArrowRight, ChevronLeft, Download, ExternalLink } from 'lucide-react';
import clsx from 'clsx';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

const PLATFORMS = [
    {
        id: 'wordpress',
        name: 'WordPress',
        description: 'Publication automatique via REST API',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 19.5c-5.247 0-9.5-4.253-9.5-9.5S6.753 2.5 12 2.5s9.5 4.253 9.5 9.5-4.253 9.5-9.5 9.5z"/>
                <path d="M3.28 12c0 3.69 2.14 6.88 5.24 8.41L4.03 8.45A9.465 9.465 0 003.28 12zm14.64-.69c0-1.15-.41-1.95-.77-2.57-.47-.77-.92-1.42-.92-2.18 0-.86.65-1.65 1.56-1.65.04 0 .08.01.12.01-1.65-1.51-3.85-2.43-6.26-2.43-3.24 0-6.09 1.66-7.75 4.18.22.01.42.01.6.01.97 0 2.48-.12 2.48-.12.5-.03.56.71.06.77 0 0-.51.06-1.07.09l3.4 10.12 2.04-6.13-1.45-3.99c-.5-.03-1.07-.09-1.07-.09-.5-.03-.44-.8.06-.77 0 0 1.54.12 2.45.12 1.01 0 2.52-.12 2.52-.12.5-.03.56.71.06.77 0 0-.51.06-1.07.09l3.37 10.02 1.02-3.11c.44-1.31.69-2.25.69-3.06z"/>
            </svg>
        ),
        color: 'bg-[#21759b]',
    },
    {
        id: 'webflow',
        name: 'Webflow',
        description: 'Intégration CMS Webflow',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.803 9.124c-.052.295-.21.637-.472 1.023l-4.087 6.09c-.044-.477-.124-.969-.243-1.472l2.35-4.35c-1.39-.183-2.482-.877-3.268-2.082.026.375.042.796.046 1.263.015 1.54-.157 3.31-.516 5.31-.36 2-.846 3.617-1.462 4.85L6.197 9.124c1.42.174 2.53.867 3.33 2.079-.17-1.574.122-3.365.876-5.373L7.17 12.25c.033-.37.051-.757.051-1.157 0-.766-.049-1.492-.147-2.177L3.5 16.08 7.17 5.87c-.033.372-.05.759-.05 1.16 0 .765.049 1.49.147 2.176l3.574-7.335c.052-.294.21-.637.472-1.023l4.087-6.09c.044.477.124.969.243 1.472l-2.35 4.35c1.39.183 2.482.877 3.268 2.082-.026-.375-.042-.796-.046-1.263-.015-1.54.157-3.31.516-5.31.36-2 .846-3.617 1.462-4.85l3.706 10.635c-1.42-.174-2.53-.867-3.33-2.079.17 1.574-.122 3.365-.876 5.373l3.233-6.42c-.033.37-.051.757-.051 1.157 0 .766.049 1.492.147 2.177l3.574 7.164-3.67 10.21c.033-.372.05-.759.05-1.16 0-.765-.049-1.49-.147-2.176l-3.574 7.335z"/>
            </svg>
        ),
        color: 'bg-[#4353ff]',
    },
    {
        id: 'shopify',
        name: 'Shopify',
        description: 'Publication sur le blog Shopify',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15.337 3.415c-.03-.145-.147-.227-.257-.239-.106-.012-2.355-.17-2.355-.17s-1.878-1.842-2.071-2.032c-.195-.19-.57-.133-.717-.09-.025.007-.489.15-1.246.384C8.239.376 7.565-.001 6.72 0 5.046 0 3.92 1.67 3.539 3.26c-1.01.313-1.726.535-1.797.557-.559.175-.575.192-.648.717C1.022 5.07 0 14.124 0 14.124l11.232 2.109 6.075-1.507s-1.916-11.069-1.97-11.311zM9.428 2.535l-2.008.621c.383-1.477 1.117-2.192 1.756-2.462.255.534.358 1.246.252 1.841zm-1.372-2.48c.114-.037.232-.06.352-.073.37.43.513 1.09.452 1.916L6.82 2.51c.23-1.106.7-1.81 1.236-2.456zm-.908 6.11L6.49 4.46c.618-.19.9-.287 1.559-.5-.252 1.056-.76 2.093-1.13 2.666l.229-1.461zM12.47 3.11c-.006-.007-.348-.012-.824-.006-.08-1.113-.434-2.006-.992-2.668.78.217 1.527.748 1.816 2.674z"/>
            </svg>
        ),
        color: 'bg-[#96bf48]',
    },
];

export default function Step5Integration({ siteId, onNext, onBack }: Props) {
    const [loading, setLoading] = useState<string | null>(null);

    const handleConnect = async (platformId: string) => {
        setLoading(platformId);
        try {
            const response = await axios.post(route('onboarding.step5', siteId), {
                skip: false,
                platform: platformId,
            });
            if (response.data.redirect) {
                window.location.href = response.data.redirect;
            }
        } catch (error) {
            console.error(error);
            setLoading(null);
        }
    };

    const handleSkip = async () => {
        setLoading('skip');
        try {
            await axios.post(route('onboarding.step5', siteId), { skip: true });
            onNext();
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(null);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200">
                    <Plug className="h-7 w-7 text-primary-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900">
                    Intégration Publication
                </h2>
                <p className="mt-2 text-surface-500">
                    Connectez votre CMS pour publier automatiquement les articles
                </p>
            </div>

            {/* Platforms */}
            <div className="space-y-3">
                {PLATFORMS.map((platform) => (
                    <button
                        key={platform.id}
                        onClick={() => handleConnect(platform.id)}
                        disabled={loading !== null}
                        className={clsx(
                            'flex w-full items-center gap-4 rounded-xl border border-surface-200 p-4',
                            'text-left transition-all',
                            'hover:border-primary-300 hover:bg-primary-50/50',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        )}
                    >
                        <div className={clsx(
                            'flex h-12 w-12 items-center justify-center rounded-xl text-white',
                            platform.color
                        )}>
                            {platform.icon}
                        </div>
                        <div className="flex-1">
                            <p className="font-medium text-surface-900">{platform.name}</p>
                            <p className="text-sm text-surface-500">{platform.description}</p>
                        </div>
                        {loading === platform.id ? (
                            <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600" />
                        ) : (
                            <ExternalLink className="h-5 w-5 text-surface-400" />
                        )}
                    </button>
                ))}
            </div>

            {/* Alternative Info */}
            <div className="flex items-center gap-3 rounded-xl bg-surface-50 p-4">
                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-surface-100">
                    <Download className="h-5 w-5 text-surface-500" />
                </div>
                <p className="text-sm text-surface-600">
                    Sans intégration, les articles seront disponibles en téléchargement (HTML, Markdown, ou Word)
                </p>
            </div>

            {/* Skip Option */}
            <button
                onClick={handleSkip}
                disabled={loading !== null}
                className="flex w-full items-center justify-center gap-2 py-2 text-sm text-surface-500 hover:text-surface-700 transition-colors"
            >
                {loading === 'skip' ? (
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-surface-300 border-t-surface-600" />
                ) : (
                    <>
                        Configurer plus tard
                        <ArrowRight className="h-4 w-4" />
                    </>
                )}
            </button>

            {/* Navigation */}
            <div className="pt-2">
                <button
                    onClick={onBack}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Retour
                </button>
            </div>
        </div>
    );
}
