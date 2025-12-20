import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import Step1Site from './Steps/Step1Site';
import Step2GSC from './Steps/Step2GSC';
import Step3Business from './Steps/Step3Business';
import Step4Config from './Steps/Step4Config';
import Step5Integration from './Steps/Step5Integration';
import Step6Launch from './Steps/Step6Launch';

interface Team {
    id: number;
    name: string;
    articles_limit: number;
}

interface WizardProps extends PageProps {
    team: Team;
}

export default function Wizard({ team }: WizardProps) {
    const [currentStep, setCurrentStep] = useState(1);
    const [siteId, setSiteId] = useState<number | null>(null);
    const [siteData, setSiteData] = useState({
        domain: '',
        name: '',
        language: 'fr',
    });

    const steps = [
        { number: 1, title: 'Site' },
        { number: 2, title: 'Search Console' },
        { number: 3, title: 'Business' },
        { number: 4, title: 'Configuration' },
        { number: 5, title: 'Publication' },
        { number: 6, title: 'Lancement' },
    ];

    const nextStep = () => setCurrentStep((s) => Math.min(s + 1, 6));
    const prevStep = () => setCurrentStep((s) => Math.max(s - 1, 1));

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title="Configuration du site" />

            <div className="mx-auto max-w-3xl px-4 py-12">
                {/* Progress bar */}
                <div className="mb-8">
                    <div className="flex justify-between">
                        {steps.map((step) => (
                            <div
                                key={step.number}
                                className={`flex flex-col items-center ${
                                    step.number <= currentStep ? 'text-indigo-600' : 'text-gray-400'
                                }`}
                            >
                                <div
                                    className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium ${
                                        step.number < currentStep
                                            ? 'bg-indigo-600 text-white'
                                            : step.number === currentStep
                                            ? 'border-2 border-indigo-600 text-indigo-600'
                                            : 'border-2 border-gray-300 text-gray-400'
                                    }`}
                                >
                                    {step.number < currentStep ? '✓' : step.number}
                                </div>
                                <span className="mt-2 text-xs font-medium hidden sm:block">
                                    {step.title}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 h-2 rounded-full bg-gray-200">
                        <div
                            className="h-2 rounded-full bg-indigo-600 transition-all duration-300"
                            style={{ width: `${((currentStep - 1) / 5) * 100}%` }}
                        />
                    </div>
                </div>

                {/* Step content */}
                <div className="rounded-xl bg-white p-8 shadow-sm">
                    {currentStep === 1 && (
                        <Step1Site
                            data={siteData}
                            setData={setSiteData}
                            onNext={(id) => {
                                setSiteId(id);
                                nextStep();
                            }}
                        />
                    )}
                    {currentStep === 2 && siteId && (
                        <Step2GSC siteId={siteId} onNext={nextStep} onBack={prevStep} />
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
    );
}
