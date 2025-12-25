import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Check, X } from 'lucide-react';
import clsx from 'clsx';
import Step1Site from './Steps/Step1Site';
import Step2GSC from './Steps/Step2GSC';
import Step3Business from './Steps/Step3Business';
import Step4Config from './Steps/Step4Config';
import Step5Integration from './Steps/Step5Integration';
import Step6Launch from './Steps/Step6Launch';
import { CrawlStatusIndicator } from '@/Components/Onboarding/CrawlStatusIndicator';

interface Team {
    id: number;
    name: string;
    articles_limit: number;
}

interface Site {
    id: number;
    domain: string;
    name: string;
    language: string;
    business_description?: string;
    gsc_connected?: boolean;
    gsc_property_id?: string;
    ga4_connected?: boolean;
    ga4_property_id?: string;
    crawl_status?: 'pending' | 'running' | 'partial' | 'completed' | 'failed';
    crawl_pages_count?: number;
    settings?: {
        articles_per_week: number;
        publish_days: string[];
        auto_publish: boolean;
    };
}

interface WizardProps extends PageProps {
    team: Team;
    site?: Site | null;
    resumeStep?: number;
}

const STEPS = [
    { number: 1, title: 'Site', description: 'Infos de base' },
    { number: 2, title: 'Search Console', description: 'Connexion GSC' },
    { number: 3, title: 'Business', description: 'Votre activité' },
    { number: 4, title: 'Configuration', description: 'Rythme de publication' },
    { number: 5, title: 'Publication', description: 'Intégration CMS' },
    { number: 6, title: 'Lancement', description: 'Activer l\'autopilot' },
];

export default function Wizard({ team, site: initialSite, resumeStep }: WizardProps) {
    const [currentStep, setCurrentStep] = useState(resumeStep || 1);
    const [siteId, setSiteId] = useState<number | null>(initialSite?.id || null);
    const [siteData, setSiteData] = useState({
        domain: initialSite?.domain || '',
        name: initialSite?.name || '',
        language: initialSite?.language || 'fr',
    });
    const [crawlStatus, setCrawlStatus] = useState<Site['crawl_status']>(initialSite?.crawl_status || 'pending');
    const [crawlPagesCount, setCrawlPagesCount] = useState(initialSite?.crawl_pages_count || 0);

    // Écouter les updates de crawl en temps réel
    useEffect(() => {
        if (!siteId) return;

        const channel = (window as any).Echo?.private(`site.${siteId}`);
        if (!channel) return;

        const handler = (e: { status: string; pagesCount: number }) => {
            setCrawlStatus(e.status as Site['crawl_status']);
            setCrawlPagesCount(e.pagesCount);
        };

        channel.listen('.SiteCrawlProgress', handler);

        return () => {
            channel.stopListening('.SiteCrawlProgress', handler);
        };
    }, [siteId]);

    const nextStep = () => setCurrentStep((s) => Math.min(s + 1, 6));
    const prevStep = () => setCurrentStep((s) => Math.max(s - 1, 1));

    return (
        <div className="min-h-screen bg-surface-50 dark:bg-surface-900 transition-colors">
            <Head title="Configuration du site" />

            {/* Header */}
            <header className="border-b border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                    <Link href="/" className="flex items-center">
                        <span className="font-display text-xl font-bold text-surface-900 dark:text-white">
                            RankCruise
                            <span className="inline-block w-1.5 h-1.5 bg-primary-500 rounded-full ml-0.5 align-super" />
                        </span>
                    </Link>
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-white hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <X className="h-4 w-4" />
                        <span className="hidden sm:inline">Quitter</span>
                    </Link>
                </div>
            </header>

            <div className="mx-auto max-w-5xl px-4 py-8 lg:py-12">
                {/* Progress Steps */}
                <div className="mb-10">
                    {/* Desktop Progress - Compact horizontal */}
                    <div className="hidden md:block">
                        <div className="flex items-center justify-center gap-2">
                            {STEPS.map((step, index) => (
                                <div key={step.number} className="flex items-center">
                                    <div className="flex flex-col items-center">
                                        <div
                                            className={clsx(
                                                'flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition-all',
                                                step.number < currentStep
                                                    ? 'bg-primary-500 text-white dark:shadow-green-glow'
                                                    : step.number === currentStep
                                                    ? 'bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-400 ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-900'
                                                    : 'bg-surface-100 dark:bg-surface-800 text-surface-400'
                                            )}
                                        >
                                            {step.number < currentStep ? (
                                                <Check className="h-4 w-4" />
                                            ) : (
                                                step.number
                                            )}
                                        </div>
                                        <span className={clsx(
                                            'mt-1.5 text-xs font-medium text-center whitespace-nowrap',
                                            step.number <= currentStep ? 'text-surface-700 dark:text-surface-300' : 'text-surface-400'
                                        )}>
                                            {step.title}
                                        </span>
                                    </div>
                                    {index < STEPS.length - 1 && (
                                        <div className={clsx(
                                            'mx-2 h-0.5 w-8 lg:w-16 mt-[-18px]',
                                            step.number < currentStep ? 'bg-primary-500' : 'bg-surface-200 dark:bg-surface-700'
                                        )} />
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Mobile Progress */}
                    <div className="md:hidden">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-sm font-medium text-surface-700 dark:text-surface-300">
                                Étape {currentStep} sur {STEPS.length}
                            </span>
                            <span className="text-sm text-surface-500 dark:text-surface-400">
                                {STEPS[currentStep - 1].title}
                            </span>
                        </div>
                        <div className="h-2 w-full rounded-full bg-surface-200 dark:bg-surface-800">
                            <div
                                className="h-2 rounded-full bg-primary-500 dark:shadow-[0_0_10px_rgba(16,185,129,0.4)] transition-all duration-300"
                                style={{ width: `${(currentStep / STEPS.length) * 100}%` }}
                            />
                        </div>
                    </div>

                    {/* Crawl Status Indicator */}
                    {siteId && crawlStatus && crawlStatus !== 'pending' && (
                        <div className="mt-4 flex justify-center">
                            <CrawlStatusIndicator
                                status={crawlStatus}
                                pagesCount={crawlPagesCount}
                            />
                        </div>
                    )}
                </div>

                {/* Step Content */}
                <div className="mx-auto max-w-2xl">
                    <div className="rounded-2xl bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl p-6 sm:p-8 shadow-sm dark:shadow-card-dark border border-surface-200 dark:border-surface-800">
                        {currentStep === 1 && (
                            <Step1Site
                                data={siteData}
                                setData={setSiteData}
                                onNext={(id) => {
                                    setSiteId(id);
                                    setCrawlStatus('running');
                                    nextStep();
                                }}
                            />
                        )}
                        {currentStep === 2 && siteId && (
                            <Step2GSC
                                siteId={siteId}
                                gscConnected={initialSite?.gsc_connected || false}
                                gscPropertyId={initialSite?.gsc_property_id}
                                ga4Connected={initialSite?.ga4_connected || false}
                                ga4PropertyId={initialSite?.ga4_property_id}
                                onNext={nextStep}
                                onBack={prevStep}
                            />
                        )}
                        {currentStep === 3 && siteId && (
                            <Step3Business siteId={siteId} onNext={nextStep} onBack={prevStep} />
                        )}
                        {currentStep === 4 && siteId && (
                            <Step4Config siteId={siteId} team={team} onNext={nextStep} onBack={prevStep} />
                        )}
                        {currentStep === 5 && siteId && (
                            <Step5Integration siteId={siteId} onNext={nextStep} onBack={prevStep} />
                        )}
                        {currentStep === 6 && siteId && (
                            <Step6Launch siteId={siteId} onBack={prevStep} />
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
