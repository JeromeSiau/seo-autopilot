import { useState, FormEvent } from 'react';
import axios from 'axios';
import { Plug, ArrowRight, ChevronLeft, Check, AlertCircle, Eye, EyeOff } from 'lucide-react';
import clsx from 'clsx';

type PlatformType = 'wordpress' | 'webflow' | 'shopify';

interface Platform {
    id: PlatformType;
    name: string;
    description: string;
    icon: React.ReactNode;
    color: string;
}

interface Props {
    siteId: number;
    existingIntegration?: {
        type: PlatformType;
        name: string;
    } | null;
    onSuccess: () => void;
    onSkip?: () => void;
    onBack?: () => void;
    showSkip?: boolean;
    showBack?: boolean;
}

const PLATFORMS: Platform[] = [
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
                <path d="M17.803 6.958c0 1.271-.479 2.26-1.343 3.176l-.69.752c-.577.577-.895 1.343-.895 2.145v.434h3.03v-.434c0-.802.318-1.568.895-2.145l.69-.752c.864-.916 1.343-1.905 1.343-3.176 0-2.638-2.058-4.791-5.036-4.791-2.979 0-5.037 2.153-5.037 4.791h3.03c0-.964.73-1.761 2.007-1.761 1.278 0 2.006.797 2.006 1.761zm-3.491 9.537c0 1.014.822 1.836 1.836 1.836s1.836-.822 1.836-1.836-.822-1.836-1.836-1.836-1.836.822-1.836 1.836zM6.86 13.465h3.03V6.958c0-.964.729-1.761 2.006-1.761v-3.03c-2.978 0-5.036 2.153-5.036 4.791v6.507z"/>
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
                <path d="M15.337 3.415c-.03-.145-.147-.227-.257-.239-.106-.012-2.355-.17-2.355-.17s-1.878-1.842-2.071-2.032c-.195-.19-.57-.133-.717-.09-.025.007-.489.15-1.246.384C8.239.376 7.565-.001 6.72 0 5.046 0 3.92 1.67 3.539 3.26c-1.01.313-1.726.535-1.797.557-.559.175-.575.192-.648.717C1.022 5.07 0 14.124 0 14.124l11.232 2.109 6.075-1.507s-1.916-11.069-1.97-11.311z"/>
            </svg>
        ),
        color: 'bg-[#96bf48]',
    },
];

export default function IntegrationForm({
    siteId,
    existingIntegration,
    onSuccess,
    onSkip,
    onBack,
    showSkip = true,
    showBack = true,
}: Props) {
    const [selectedPlatform, setSelectedPlatform] = useState<PlatformType | null>(
        existingIntegration?.type || null
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [showPassword, setShowPassword] = useState(false);

    // Form data
    const [name, setName] = useState(existingIntegration?.name || '');
    const [credentials, setCredentials] = useState<Record<string, string>>({});

    const handleCredentialChange = (field: string, value: string) => {
        setCredentials((prev) => ({ ...prev, [field]: value }));
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!selectedPlatform) return;

        setLoading(true);
        setError(null);

        try {
            await axios.post(route('integrations.store'), {
                site_id: siteId,
                type: selectedPlatform,
                name: name || `Mon ${PLATFORMS.find((p) => p.id === selectedPlatform)?.name}`,
                credentials,
            });
            onSuccess();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Une erreur est survenue');
        } finally {
            setLoading(false);
        }
    };

    const handleSkip = async () => {
        if (!onSkip) return;
        setLoading(true);
        try {
            await axios.post(route('onboarding.step5', siteId), { skip: true });
            onSkip();
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const platform = PLATFORMS.find((p) => p.id === selectedPlatform);

    // Platform selection view
    if (!selectedPlatform) {
        return (
            <div className="space-y-6">
                {/* Header */}
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10">
                        <Plug className="h-7 w-7 text-primary-600" />
                    </div>
                    <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                        Intégration Publication
                    </h2>
                    <p className="mt-2 text-surface-500 dark:text-surface-400">
                        Connectez votre CMS pour publier automatiquement
                    </p>
                </div>

                {/* Platforms */}
                <div className="space-y-3">
                    {PLATFORMS.map((p) => (
                        <button
                            key={p.id}
                            onClick={() => setSelectedPlatform(p.id)}
                            disabled={loading}
                            className={clsx(
                                'flex w-full items-center gap-4 rounded-xl p-4 text-left transition-all',
                                'border border-surface-200 dark:border-surface-700',
                                'hover:border-primary-300 dark:hover:border-primary-600',
                                'hover:bg-primary-50/50 dark:hover:bg-primary-500/10',
                                'disabled:opacity-50 disabled:cursor-not-allowed'
                            )}
                        >
                            <div
                                className={clsx(
                                    'flex h-12 w-12 items-center justify-center rounded-xl text-white',
                                    p.color
                                )}
                            >
                                {p.icon}
                            </div>
                            <div className="flex-1">
                                <p className="font-medium text-surface-900 dark:text-white">
                                    {p.name}
                                </p>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    {p.description}
                                </p>
                            </div>
                            <ArrowRight className="h-5 w-5 text-surface-400" />
                        </button>
                    ))}
                </div>

                {/* Skip Option */}
                {showSkip && onSkip && (
                    <button
                        onClick={handleSkip}
                        disabled={loading}
                        className="flex w-full items-center justify-center gap-2 py-2 text-sm text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-300 transition-colors"
                    >
                        {loading ? (
                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-surface-300 border-t-surface-600" />
                        ) : (
                            <>
                                Configurer plus tard
                                <ArrowRight className="h-4 w-4" />
                            </>
                        )}
                    </button>
                )}

                {/* Navigation */}
                {showBack && onBack && (
                    <div className="pt-2">
                        <button
                            onClick={onBack}
                            className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Retour
                        </button>
                    </div>
                )}
            </div>
        );
    }

    // Form view
    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <button
                    onClick={() => setSelectedPlatform(null)}
                    className="rounded-lg p-2 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300 transition-colors"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <div className="flex items-center gap-3">
                    <div
                        className={clsx(
                            'flex h-10 w-10 items-center justify-center rounded-xl text-white',
                            platform?.color
                        )}
                    >
                        {platform?.icon}
                    </div>
                    <div>
                        <h2 className="font-display text-lg font-bold text-surface-900 dark:text-white">
                            Configurer {platform?.name}
                        </h2>
                        <p className="text-sm text-surface-500 dark:text-surface-400">
                            {platform?.description}
                        </p>
                    </div>
                </div>
            </div>

            {/* Error */}
            {error && (
                <div className="rounded-xl bg-red-50 dark:bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400 flex items-start gap-3">
                    <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                    <p>{error}</p>
                </div>
            )}

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Name */}
                <div>
                    <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                        Nom de l'intégration
                    </label>
                    <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder={`Mon ${platform?.name}`}
                        className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                    />
                </div>

                {/* WordPress Fields */}
                {selectedPlatform === 'wordpress' && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                URL WordPress
                            </label>
                            <input
                                type="url"
                                value={credentials.url || ''}
                                onChange={(e) => handleCredentialChange('url', e.target.value)}
                                placeholder="https://votresite.com"
                                required
                                className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Nom d'utilisateur
                            </label>
                            <input
                                type="text"
                                value={credentials.username || ''}
                                onChange={(e) => handleCredentialChange('username', e.target.value)}
                                required
                                className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Mot de passe d'application
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={credentials.password || ''}
                                    onChange={(e) => handleCredentialChange('password', e.target.value)}
                                    required
                                    className="w-full px-4 py-2.5 pr-10 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                                WordPress → Utilisateurs → Profil → Mots de passe d'application
                            </p>
                        </div>
                    </>
                )}

                {/* Webflow Fields */}
                {selectedPlatform === 'webflow' && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Token API
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={credentials.api_token || ''}
                                    onChange={(e) => handleCredentialChange('api_token', e.target.value)}
                                    required
                                    className="w-full px-4 py-2.5 pr-10 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Collection ID
                            </label>
                            <input
                                type="text"
                                value={credentials.collection_id || ''}
                                onChange={(e) => handleCredentialChange('collection_id', e.target.value)}
                                placeholder="ID de la collection CMS"
                                required
                                className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                            />
                        </div>
                    </>
                )}

                {/* Shopify Fields */}
                {selectedPlatform === 'shopify' && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Domaine Shopify
                            </label>
                            <input
                                type="text"
                                value={credentials.shop_domain || ''}
                                onChange={(e) => handleCredentialChange('shop_domain', e.target.value)}
                                placeholder="votre-boutique.myshopify.com"
                                required
                                className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Token API Admin
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={credentials.api_token || ''}
                                    onChange={(e) => handleCredentialChange('api_token', e.target.value)}
                                    required
                                    className="w-full px-4 py-2.5 pr-10 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                        </div>
                    </>
                )}

                {/* Submit */}
                <button
                    type="submit"
                    disabled={loading}
                    className={clsx(
                        'flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5',
                        'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold',
                        'shadow-lg shadow-primary-500/25 hover:shadow-xl hover:shadow-primary-500/30',
                        'hover:-translate-y-0.5 transition-all',
                        'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                    )}
                >
                    {loading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <>
                            <Check className="h-5 w-5" />
                            Connecter {platform?.name}
                        </>
                    )}
                </button>
            </form>

            {/* Skip Option */}
            {showSkip && onSkip && (
                <button
                    onClick={handleSkip}
                    disabled={loading}
                    className="flex w-full items-center justify-center gap-2 py-2 text-sm text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-300 transition-colors"
                >
                    Configurer plus tard
                    <ArrowRight className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
