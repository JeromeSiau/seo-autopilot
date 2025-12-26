import { useTranslations } from '@/hooks/useTranslations';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Check } from 'lucide-react';
import clsx from 'clsx';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { t } = useTranslations();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div>
                <label
                    htmlFor="name"
                    className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                >
                    {t?.profile?.name ?? 'Name'}
                </label>
                <input
                    id="name"
                    type="text"
                    className={clsx(
                        'block w-full rounded-xl border px-4 py-2.5 text-surface-900 dark:text-white',
                        'placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.name
                            ? 'border-red-300 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10'
                            : 'border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800'
                    )}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    autoComplete="name"
                />
                {errors.name && (
                    <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                )}
            </div>

            <div>
                <label
                    htmlFor="email"
                    className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                >
                    {t?.profile?.email ?? 'Email address'}
                </label>
                <input
                    id="email"
                    type="email"
                    className={clsx(
                        'block w-full rounded-xl border px-4 py-2.5 text-surface-900 dark:text-white',
                        'placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.email
                            ? 'border-red-300 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10'
                            : 'border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800'
                    )}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                    autoComplete="username"
                />
                {errors.email && (
                    <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.email}</p>
                )}
            </div>

            {mustVerifyEmail && user.email_verified_at === null && (
                <div className="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-4">
                    <p className="text-sm text-amber-800 dark:text-amber-400">
                        {t?.profile?.emailNotVerified ?? 'Your email address is not verified.'}{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="font-medium text-amber-900 dark:text-amber-300 underline hover:no-underline"
                        >
                            {t?.profile?.resendLink ?? 'Click here to resend the verification link.'}
                        </Link>
                    </p>

                    {status === 'verification-link-sent' && (
                        <p className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                            {t?.profile?.verificationSent ?? 'A new verification link has been sent to your email address.'}
                        </p>
                    )}
                </div>
            )}

            <div className="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    disabled={processing}
                    className={clsx(
                        'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                        'text-sm font-semibold text-white',
                        'bg-primary-600 hover:bg-primary-700',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                        'disabled:opacity-50 disabled:cursor-not-allowed',
                        'transition-all'
                    )}
                >
                    {t?.common?.save ?? 'Save'}
                </button>

                {recentlySuccessful && (
                    <span className="inline-flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
                        <Check className="h-4 w-4" />
                        {t?.profile?.saved ?? 'Saved'}
                    </span>
                )}
            </div>
        </form>
    );
}
