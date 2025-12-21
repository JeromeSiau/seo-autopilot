import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

export default function ConfirmPassword() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t?.auth?.confirmPassword?.title ?? 'Confirm Password'} />

            <div className="mb-4 text-sm text-surface-600 dark:text-surface-400">
                {t?.auth?.confirmPassword?.description ?? 'This is a secure area of the application. Please confirm your password before continuing.'}
            </div>

            <form onSubmit={submit}>
                <div className="mt-4">
                    <InputLabel htmlFor="password" value={t?.auth?.confirmPassword?.password ?? 'Password'} />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-6 flex items-center justify-end">
                    <PrimaryButton className="w-full" disabled={processing}>
                        {t?.auth?.confirmPassword?.submit ?? 'Confirm'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
