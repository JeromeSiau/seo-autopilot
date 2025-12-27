import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

export default function Create() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('teams.store'));
    };

    return (
        <GuestLayout>
            <Head title={t?.teams?.create?.title ?? 'Create your team'} />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                    {t?.teams?.create?.heading ?? 'Create your first team'}
                </h1>
                <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                    {t?.teams?.create?.description ?? 'Teams help you organize your sites and collaborate with others.'}
                </p>
            </div>

            <form onSubmit={submit} data-testid="team-create-form">
                <div>
                    <InputLabel htmlFor="name" value={t?.teams?.create?.name ?? 'Team name'} />

                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t?.teams?.create?.placeholder ?? 'My Company'}
                        required
                    />

                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton className="w-full justify-center" disabled={processing} data-testid="team-create-submit">
                        {processing
                            ? (t?.teams?.create?.creating ?? 'Creating...')
                            : (t?.teams?.create?.submit ?? 'Create team')}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
