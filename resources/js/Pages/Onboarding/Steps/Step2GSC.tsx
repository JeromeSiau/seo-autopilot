import { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, ArrowRight, ChevronLeft, TrendingUp, Target, BarChart3, Check, Globe, AlertCircle, BarChart2, XCircle, AlertTriangle } from 'lucide-react';
import clsx from 'clsx';
import { useTranslations } from '@/hooks/useTranslations';

interface GscSite {
    url: string;
    domain: string;
    permission: string;
    is_domain_property: boolean;
}

interface Ga4Property {
    property_id: string;
    display_name: string;
    account_name: string;
}

interface Props {
    siteId: number;
    gscConnected: boolean;
    gscPropertyId?: string;
    ga4Connected: boolean;
    ga4PropertyId?: string;
    onboardingComplete?: boolean;
    onNext: () => void;
    onBack: () => void;
}

const SKIP_VALUE = '__skip__';

type Step = 'connect' | 'select-gsc' | 'select-ga4' | 'summary';

export default function Step2GSC({ siteId, gscConnected, gscPropertyId, ga4Connected, ga4PropertyId, onboardingComplete, onNext, onBack }: Props) {
    const { t } = useTranslations();
    const step2 = t?.onboarding?.step2;

    const [loading, setLoading] = useState(false);

    // GSC state
    const [gscSites, setGscSites] = useState<GscSite[]>([]);
    const [selectedGscProperty, setSelectedGscProperty] = useState<string | null>(gscPropertyId || null);
    const [gscSuggested, setGscSuggested] = useState<string | null>(null);
    const [fetchingGsc, setFetchingGsc] = useState(false);
    const [gscSkipped, setGscSkipped] = useState(false);

    // GA4 state
    const [ga4Properties, setGa4Properties] = useState<Ga4Property[]>([]);
    const [selectedGa4Property, setSelectedGa4Property] = useState<string | null>(ga4PropertyId || null);
    const [ga4Suggested, setGa4Suggested] = useState<string | null>(null);
    const [fetchingGa4, setFetchingGa4] = useState(false);
    const [ga4Skipped, setGa4Skipped] = useState(false);

    // Search state
    const [gscSearch, setGscSearch] = useState('');
    const [ga4Search, setGa4Search] = useState('');

    const [error, setError] = useState<string | null>(null);

    // Determine current step
    const getCurrentStep = (): Step => {
        if (!gscConnected) return 'connect';
        // Use !gscPropertyId to handle both undefined and null from Laravel
        if (!gscPropertyId && selectedGscProperty === null && !gscSkipped) return 'select-gsc';
        if (!ga4PropertyId && selectedGa4Property === null && !ga4Skipped) return 'select-ga4';
        return 'summary';
    };

    const [currentStep, setCurrentStep] = useState<Step>(getCurrentStep());

    // Fetch GSC properties when connected
    useEffect(() => {
        if (gscConnected && !gscPropertyId && currentStep === 'select-gsc') {
            fetchGscSites();
        }
    }, [gscConnected, gscPropertyId, currentStep]);

    // Fetch GA4 properties when on GA4 step
    useEffect(() => {
        if (gscConnected && currentStep === 'select-ga4' && ga4Properties.length === 0) {
            fetchGa4Properties();
        }
    }, [gscConnected, currentStep]);

    const fetchGscSites = async () => {
        setFetchingGsc(true);
        setError(null);
        try {
            const response = await axios.get(route('onboarding.gsc-sites', siteId));
            if (response.data.error) {
                setError(response.data.error);
            } else {
                setGscSites(response.data.sites || []);
                setGscSuggested(response.data.suggested);
                if (response.data.suggested && !selectedGscProperty) {
                    setSelectedGscProperty(response.data.suggested);
                }
            }
        } catch (err) {
            console.error(err);
            setError(step2?.errors?.fetchGsc ?? 'Unable to retrieve GSC properties');
        } finally {
            setFetchingGsc(false);
        }
    };

    const fetchGa4Properties = async () => {
        setFetchingGa4(true);
        setError(null);
        try {
            const response = await axios.get(route('onboarding.ga4-properties', siteId));
            if (response.data.error) {
                setError(response.data.error);
            } else {
                setGa4Properties(response.data.properties || []);
                setGa4Suggested(response.data.suggested);
                if (response.data.suggested && !selectedGa4Property) {
                    setSelectedGa4Property(response.data.suggested);
                }
            }
        } catch (err) {
            console.error(err);
            setError(step2?.errors?.fetchGa4 ?? 'Unable to retrieve GA4 properties');
        } finally {
            setFetchingGa4(false);
        }
    };

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

    const handleSelectGscProperty = async () => {
        if (selectedGscProperty === SKIP_VALUE) {
            setGscSkipped(true);
            setSelectedGscProperty(null);
            setCurrentStep('select-ga4');
            return;
        }

        if (!selectedGscProperty) return;

        setLoading(true);
        try {
            await axios.post(route('onboarding.gsc-property', siteId), {
                property_id: selectedGscProperty,
            });
            setCurrentStep('select-ga4');
        } catch (error) {
            console.error(error);
            setError(step2?.errors?.saveGsc ?? 'Unable to save GSC property');
        } finally {
            setLoading(false);
        }
    };

    const handleSelectGa4Property = async () => {
        if (selectedGa4Property === SKIP_VALUE) {
            setGa4Skipped(true);
            setSelectedGa4Property(null);
            setCurrentStep('summary');
            return;
        }

        if (!selectedGa4Property) return;

        setLoading(true);
        try {
            await axios.post(route('onboarding.ga4-property', siteId), {
                property_id: selectedGa4Property,
            });
            setCurrentStep('summary');
        } catch (error) {
            console.error(error);
            setError(step2?.errors?.saveGa4 ?? 'Unable to save GA4 property');
        } finally {
            setLoading(false);
        }
    };

    const handleSkipAll = async () => {
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

    const handleContinue = () => {
        // If onboarding is already complete, redirect back to site instead of continuing wizard
        if (onboardingComplete) {
            window.location.href = route('sites.show', { site: siteId });
            return;
        }
        onNext();
    };

    // Check what's configured
    const hasGsc = (selectedGscProperty && selectedGscProperty !== SKIP_VALUE) || gscPropertyId;
    const hasGa4 = (selectedGa4Property && selectedGa4Property !== SKIP_VALUE) || ga4PropertyId;
    const hasWarning = !hasGsc || !hasGa4;

    // Benefits for connect step
    const benefits = [
        {
            icon: Target,
            title: step2?.benefits?.autoDiscovery ?? 'Automatic discovery',
            description: step2?.benefits?.autoDiscoveryDesc ?? 'Identifies high-performing keywords',
        },
        {
            icon: TrendingUp,
            title: step2?.benefits?.hiddenOpportunities ?? 'Hidden opportunities',
            description: step2?.benefits?.hiddenOpportunitiesDesc ?? 'Positions 5-30 to exploit',
        },
        {
            icon: BarChart3,
            title: step2?.benefits?.tracking ?? 'Performance tracking',
            description: step2?.benefits?.trackingDesc ?? 'Real-time analytics',
        },
    ];

    // Summary step - show what's configured and warning if needed
    if (currentStep === 'summary') {
        return (
            <div className="space-y-6">
                <div className="text-center">
                    <div className={clsx(
                        'mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br',
                        hasWarning
                            ? 'from-amber-100 to-amber-200 dark:from-amber-500/20 dark:to-amber-500/10'
                            : 'from-green-100 to-green-200 dark:from-green-500/20 dark:to-green-500/10'
                    )}>
                        {hasWarning ? (
                            <AlertTriangle className="h-7 w-7 text-amber-600" />
                        ) : (
                            <Check className="h-7 w-7 text-green-600" />
                        )}
                    </div>
                    <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                        {hasWarning
                            ? (step2?.summary?.titlePartial ?? 'Partial configuration')
                            : (step2?.summary?.titleComplete ?? 'Google connected')}
                    </h2>
                </div>

                {/* Configuration summary */}
                <div className="space-y-3">
                    <div className={clsx(
                        'flex items-center gap-3 rounded-xl p-4',
                        hasGsc
                            ? 'bg-green-50 dark:bg-green-500/10'
                            : 'bg-surface-100 dark:bg-surface-800'
                    )}>
                        <div className={clsx(
                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                            hasGsc
                                ? 'bg-green-500 text-white'
                                : 'bg-surface-300 dark:bg-surface-700 text-surface-500'
                        )}>
                            {hasGsc ? <Check className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                        </div>
                        <div className="flex-1">
                            <p className={clsx(
                                'font-medium',
                                hasGsc ? 'text-green-700 dark:text-green-400' : 'text-surface-500 dark:text-surface-400'
                            )}>
                                {step2?.summary?.searchConsole ?? 'Search Console'}
                            </p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">
                                {hasGsc
                                    ? (gscPropertyId || selectedGscProperty)
                                    : (step2?.summary?.notConfigured ?? 'Not configured')}
                            </p>
                        </div>
                    </div>

                    <div className={clsx(
                        'flex items-center gap-3 rounded-xl p-4',
                        hasGa4
                            ? 'bg-green-50 dark:bg-green-500/10'
                            : 'bg-surface-100 dark:bg-surface-800'
                    )}>
                        <div className={clsx(
                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                            hasGa4
                                ? 'bg-green-500 text-white'
                                : 'bg-surface-300 dark:bg-surface-700 text-surface-500'
                        )}>
                            {hasGa4 ? <Check className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                        </div>
                        <div className="flex-1">
                            <p className={clsx(
                                'font-medium',
                                hasGa4 ? 'text-green-700 dark:text-green-400' : 'text-surface-500 dark:text-surface-400'
                            )}>
                                {step2?.summary?.analytics ?? 'Google Analytics 4'}
                            </p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">
                                {hasGa4
                                    ? `${step2?.summary?.propertyId ?? 'Property ID'}: ${ga4PropertyId || selectedGa4Property}`
                                    : (step2?.summary?.notConfigured ?? 'Not configured')}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Warning if incomplete */}
                {hasWarning && (
                    <div className="rounded-xl bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="font-medium">{step2?.warnings?.title ?? 'Limited features'}</p>
                                <p className="mt-1">
                                    {!hasGsc && !hasGa4 && (step2?.warnings?.noGscNoGa4 ?? "Without Search Console or Analytics, AI will generate topics based on your description, but they will be less targeted.")}
                                    {!hasGsc && hasGa4 && (step2?.warnings?.noGsc ?? "Without Search Console, autopilot won't be able to discover your existing keywords.")}
                                    {hasGsc && !hasGa4 && (step2?.warnings?.noGa4 ?? "Without Analytics, you won't be able to track traffic generated by your articles.")}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <button
                    onClick={handleContinue}
                    className={clsx(
                        'flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5',
                        'bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold',
                        'shadow-lg shadow-primary-500/25 hover:shadow-xl hover:shadow-primary-500/30',
                        'hover:-translate-y-0.5 transition-all'
                    )}
                >
                    {onboardingComplete
                        ? (step2?.summary?.backToSite ?? 'Retour au site')
                        : (t?.onboarding?.continue ?? 'Continue')}
                    <ArrowRight className="h-5 w-5" />
                </button>

                <div className="pt-2">
                    <button
                        onClick={() => setCurrentStep('select-ga4')}
                        className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        {step2?.summary?.modifyConfig ?? 'Modify configuration'}
                    </button>
                </div>
            </div>
        );
    }

    // GA4 property selection
    if (currentStep === 'select-ga4') {
        return (
            <div className="space-y-6">
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-100 to-orange-200 dark:from-orange-500/20 dark:to-orange-500/10">
                        <BarChart2 className="h-7 w-7 text-orange-600" />
                    </div>
                    <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                        {step2?.selectGa4?.title ?? 'Google Analytics 4'}
                    </h2>
                    <p className="mt-2 text-surface-500 dark:text-surface-400">
                        {step2?.selectGa4?.subtitle ?? 'Select the Analytics property to use'}
                    </p>
                </div>

                {/* GSC status badge */}
                <div className={clsx(
                    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                    hasGsc
                        ? 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400'
                        : 'bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400'
                )}>
                    {hasGsc ? <Check className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                    <span>
                        {step2?.selectGa4?.gscStatus ?? 'Search Console'}: {hasGsc
                            ? (selectedGscProperty || gscPropertyId)
                            : (step2?.selectGa4?.gscNotUsed ?? 'Not used')}
                    </span>
                </div>

                {fetchingGa4 ? (
                    <div className="flex items-center justify-center py-8">
                        <div className="h-8 w-8 animate-spin rounded-full border-2 border-orange-500/30 border-t-orange-500" />
                    </div>
                ) : error ? (
                    <div className="rounded-xl bg-red-50 dark:bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400 flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="font-medium">{step2?.errors?.title ?? 'Error'}</p>
                            <p>{error}</p>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {/* Search input */}
                        {ga4Properties.length > 3 && (
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                                <input
                                    type="text"
                                    value={ga4Search}
                                    onChange={(e) => setGa4Search(e.target.value)}
                                    placeholder={step2?.selectGa4?.searchPlaceholder ?? 'Search a property...'}
                                    className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent text-sm"
                                />
                            </div>
                        )}

                        <div className="space-y-2 max-h-64 overflow-y-auto">
                            {/* Skip option */}
                            <button
                                onClick={() => setSelectedGa4Property(SKIP_VALUE)}
                                className={clsx(
                                    'w-full flex items-center gap-3 rounded-xl p-4 text-left transition-all',
                                    selectedGa4Property === SKIP_VALUE
                                        ? 'bg-surface-200 dark:bg-surface-700 ring-2 ring-surface-400'
                                        : 'bg-surface-50 dark:bg-surface-800/50 hover:bg-surface-100 dark:hover:bg-surface-800'
                                )}
                            >
                                <div className={clsx(
                                    'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                                    selectedGa4Property === SKIP_VALUE
                                        ? 'bg-surface-400 text-white'
                                        : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-400'
                                )}>
                                    <XCircle className="h-5 w-5" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="font-medium text-surface-900 dark:text-white">
                                        {step2?.selectGa4?.skipOption ?? "Don't use Analytics"}
                                    </p>
                                    <p className="text-xs text-surface-500 dark:text-surface-400">
                                        {step2?.selectGa4?.skipOptionDesc ?? 'You can configure it later'}
                                    </p>
                                </div>
                                {selectedGa4Property === SKIP_VALUE && (
                                    <Check className="h-5 w-5 flex-shrink-0 text-surface-500" />
                                )}
                            </button>

                            {/* GA4 properties */}
                            {ga4Properties
                                .filter((prop) =>
                                    ga4Search === '' ||
                                    prop.display_name.toLowerCase().includes(ga4Search.toLowerCase()) ||
                                    prop.account_name.toLowerCase().includes(ga4Search.toLowerCase()) ||
                                    prop.property_id.toLowerCase().includes(ga4Search.toLowerCase())
                                )
                                .map((prop) => (
                                    <button
                                        key={prop.property_id}
                                        onClick={() => setSelectedGa4Property(prop.property_id)}
                                        className={clsx(
                                            'w-full flex items-center gap-3 rounded-xl p-4 text-left transition-all',
                                            selectedGa4Property === prop.property_id
                                                ? 'bg-orange-50 dark:bg-orange-500/10 ring-2 ring-orange-500'
                                                : 'bg-surface-50 dark:bg-surface-800/50 hover:bg-surface-100 dark:hover:bg-surface-800'
                                        )}
                                    >
                                        <div className={clsx(
                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                                            selectedGa4Property === prop.property_id
                                                ? 'bg-orange-500 text-white'
                                                : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-400'
                                        )}>
                                            <BarChart2 className="h-5 w-5" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-surface-900 dark:text-white truncate">
                                                {prop.display_name}
                                            </p>
                                            <p className="text-xs text-surface-500 dark:text-surface-400 truncate">
                                                {prop.account_name} • ID: {prop.property_id}
                                            </p>
                                        </div>
                                        {ga4Suggested === prop.property_id && (
                                            <span className="flex-shrink-0 text-xs font-medium text-orange-600 dark:text-orange-400">
                                                {step2?.selectGa4?.recommended ?? 'Recommended'}
                                            </span>
                                        )}
                                        {selectedGa4Property === prop.property_id && (
                                            <Check className="h-5 w-5 flex-shrink-0 text-orange-500" />
                                        )}
                                    </button>
                                ))}
                        </div>
                    </div>
                )}

                <button
                    onClick={handleSelectGa4Property}
                    disabled={loading || selectedGa4Property === null || fetchingGa4}
                    className={clsx(
                        'flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5',
                        'bg-gradient-to-r from-orange-500 to-orange-600 text-white font-semibold',
                        'shadow-lg shadow-orange-500/25 hover:shadow-xl hover:shadow-orange-500/30',
                        'hover:-translate-y-0.5 transition-all',
                        'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                    )}
                >
                    {loading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <>
                            {t?.onboarding?.continue ?? 'Continue'}
                            <ArrowRight className="h-5 w-5" />
                        </>
                    )}
                </button>

                <div className="pt-2">
                    <button
                        onClick={() => {
                            setGscSkipped(false);
                            setCurrentStep('select-gsc');
                        }}
                        className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        {t?.onboarding?.back ?? 'Back'}
                    </button>
                </div>
            </div>
        );
    }

    // GSC property selection
    if (currentStep === 'select-gsc') {
        return (
            <div className="space-y-6">
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-green-100 to-green-200 dark:from-green-500/20 dark:to-green-500/10">
                        <Check className="h-7 w-7 text-green-600" />
                    </div>
                    <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                        {step2?.selectGsc?.title ?? 'Google account connected'}
                    </h2>
                    <p className="mt-2 text-surface-500 dark:text-surface-400">
                        {step2?.selectGsc?.subtitle ?? 'Select the Search Console property to use'}
                    </p>
                </div>

                {fetchingGsc ? (
                    <div className="flex items-center justify-center py-8">
                        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary-500/30 border-t-primary-500" />
                    </div>
                ) : error ? (
                    <div className="rounded-xl bg-red-50 dark:bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400 flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="font-medium">{step2?.errors?.title ?? 'Error'}</p>
                            <p>{error}</p>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {/* Search input */}
                        {gscSites.length > 3 && (
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                                <input
                                    type="text"
                                    value={gscSearch}
                                    onChange={(e) => setGscSearch(e.target.value)}
                                    placeholder={step2?.selectGsc?.searchPlaceholder ?? 'Search a property...'}
                                    className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                />
                            </div>
                        )}

                        <div className="space-y-2 max-h-64 overflow-y-auto">
                            {/* Skip option */}
                            <button
                                onClick={() => setSelectedGscProperty(SKIP_VALUE)}
                                className={clsx(
                                    'w-full flex items-center gap-3 rounded-xl p-4 text-left transition-all',
                                    selectedGscProperty === SKIP_VALUE
                                        ? 'bg-surface-200 dark:bg-surface-700 ring-2 ring-surface-400'
                                        : 'bg-surface-50 dark:bg-surface-800/50 hover:bg-surface-100 dark:hover:bg-surface-800'
                                )}
                            >
                                <div className={clsx(
                                    'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                                    selectedGscProperty === SKIP_VALUE
                                        ? 'bg-surface-400 text-white'
                                        : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-400'
                                )}>
                                    <XCircle className="h-5 w-5" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="font-medium text-surface-900 dark:text-white">
                                        {step2?.selectGsc?.skipOption ?? "Don't use Search Console"}
                                    </p>
                                    <p className="text-xs text-surface-500 dark:text-surface-400">
                                        {step2?.selectGsc?.skipOptionDesc ?? 'You can configure it later'}
                                    </p>
                                </div>
                                {selectedGscProperty === SKIP_VALUE && (
                                    <Check className="h-5 w-5 flex-shrink-0 text-surface-500" />
                                )}
                            </button>

                            {/* GSC sites */}
                            {gscSites
                                .filter((site) =>
                                    gscSearch === '' ||
                                    site.domain.toLowerCase().includes(gscSearch.toLowerCase()) ||
                                    site.url.toLowerCase().includes(gscSearch.toLowerCase())
                                )
                                .map((site) => (
                                    <button
                                        key={site.url}
                                        onClick={() => setSelectedGscProperty(site.url)}
                                        className={clsx(
                                            'w-full flex items-center gap-3 rounded-xl p-4 text-left transition-all',
                                            selectedGscProperty === site.url
                                                ? 'bg-primary-50 dark:bg-primary-500/10 ring-2 ring-primary-500'
                                                : 'bg-surface-50 dark:bg-surface-800/50 hover:bg-surface-100 dark:hover:bg-surface-800'
                                        )}
                                    >
                                        <div className={clsx(
                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                                            selectedGscProperty === site.url
                                                ? 'bg-primary-500 text-white'
                                                : 'bg-surface-200 dark:bg-surface-700 text-surface-500 dark:text-surface-400'
                                        )}>
                                            <Globe className="h-5 w-5" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-surface-900 dark:text-white truncate">
                                                {site.domain}
                                            </p>
                                            <p className="text-xs text-surface-500 dark:text-surface-400 truncate">
                                                {site.url}
                                                {site.is_domain_property && (
                                                    <span className="ml-2 inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-500/20 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400">
                                                        {step2?.selectGsc?.domain ?? 'Domain'}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        {gscSuggested === site.url && (
                                            <span className="flex-shrink-0 text-xs font-medium text-primary-600 dark:text-primary-400">
                                                {step2?.selectGsc?.recommended ?? 'Recommended'}
                                            </span>
                                        )}
                                        {selectedGscProperty === site.url && (
                                            <Check className="h-5 w-5 flex-shrink-0 text-primary-500" />
                                        )}
                                    </button>
                                ))}
                        </div>
                    </div>
                )}

                <button
                    onClick={handleSelectGscProperty}
                    disabled={loading || selectedGscProperty === null || fetchingGsc}
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
                            {t?.onboarding?.continue ?? 'Continue'}
                            <ArrowRight className="h-5 w-5" />
                        </>
                    )}
                </button>

                <div className="pt-2">
                    <button
                        onClick={onBack}
                        className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        {t?.onboarding?.back ?? 'Back'}
                    </button>
                </div>
            </div>
        );
    }

    // Not connected yet, show connect button
    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-500/20 dark:to-blue-500/10">
                    <Search className="h-7 w-7 text-blue-600" />
                </div>
                <h2 className="mt-4 font-display text-2xl font-bold text-surface-900 dark:text-white">
                    {step2?.connect?.title ?? 'Google Connection'}
                </h2>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    {step2?.connect?.subtitle ?? 'Connect Search Console and Analytics for complete tracking'}
                </p>
            </div>

            {/* Benefits */}
            <div className="grid gap-3">
                {benefits.map((benefit) => (
                    <div
                        key={benefit.title}
                        className="flex items-start gap-3 rounded-xl bg-surface-50 dark:bg-surface-800/50 p-4"
                    >
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-500/20">
                            <benefit.icon className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="font-medium text-surface-900 dark:text-white">{benefit.title}</p>
                            <p className="text-sm text-surface-500 dark:text-surface-400">{benefit.description}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Connect Button */}
            <button
                onClick={handleConnect}
                disabled={loading}
                className={clsx(
                    'flex w-full items-center justify-center gap-3 rounded-xl px-6 py-3.5',
                    'bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold',
                    'shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30',
                    'hover:-translate-y-0.5 transition-all',
                    'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none'
                )}
            >
                {loading ? (
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                ) : (
                    <>
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        {step2?.connect?.button ?? 'Connect with Google'}
                    </>
                )}
            </button>

            {/* Skip Option */}
            <button
                onClick={handleSkipAll}
                disabled={loading}
                className="flex w-full items-center justify-center gap-2 py-2 text-sm text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-300 transition-colors"
            >
                {step2?.connect?.skipStep ?? 'Skip this step'}
                <ArrowRight className="h-4 w-4" />
            </button>

            {/* Navigation */}
            <div className="pt-2">
                <button
                    onClick={onBack}
                    className="flex items-center gap-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                    {t?.onboarding?.back ?? 'Back'}
                </button>
            </div>
        </div>
    );
}
