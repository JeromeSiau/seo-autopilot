import IntegrationForm from '@/Components/Integration/IntegrationForm';

interface Props {
    siteId: number;
    onNext: () => void;
    onBack: () => void;
}

export default function Step5Integration({ siteId, onNext, onBack }: Props) {
    return (
        <IntegrationForm
            siteId={siteId}
            onSuccess={onNext}
            onSkip={onNext}
            onBack={onBack}
            showSkip={true}
            showBack={true}
        />
    );
}
