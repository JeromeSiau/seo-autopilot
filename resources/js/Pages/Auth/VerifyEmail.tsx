import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslations();
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title={t?.auth?.verifyEmail?.title ?? 'Email Verification'} />

            <div className="mb-4 text-sm text-surface-600 dark:text-surface-400">
                {t?.auth?.verifyEmail?.description ?? "Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another."}
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
                    {t?.auth?.verifyEmail?.linkSent ?? 'A new verification link has been sent to the email address you provided during registration.'}
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        {t?.auth?.verifyEmail?.submit ?? 'Resend Verification Email'}
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                    >
                        {t?.auth?.verifyEmail?.logout ?? 'Log Out'}
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
