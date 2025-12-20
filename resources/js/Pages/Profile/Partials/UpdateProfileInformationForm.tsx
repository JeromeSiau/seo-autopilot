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
                    className="block text-sm font-medium text-surface-700 mb-1.5"
                >
                    Nom
                </label>
                <input
                    id="name"
                    type="text"
                    className={clsx(
                        'block w-full rounded-xl border px-4 py-2.5 text-surface-900',
                        'placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.name
                            ? 'border-red-300 bg-red-50'
                            : 'border-surface-300 bg-white'
                    )}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    autoComplete="name"
                />
                {errors.name && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.name}</p>
                )}
            </div>

            <div>
                <label
                    htmlFor="email"
                    className="block text-sm font-medium text-surface-700 mb-1.5"
                >
                    Adresse email
                </label>
                <input
                    id="email"
                    type="email"
                    className={clsx(
                        'block w-full rounded-xl border px-4 py-2.5 text-surface-900',
                        'placeholder:text-surface-400',
                        'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
                        'transition-colors',
                        errors.email
                            ? 'border-red-300 bg-red-50'
                            : 'border-surface-300 bg-white'
                    )}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                    autoComplete="username"
                />
                {errors.email && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.email}</p>
                )}
            </div>

            {mustVerifyEmail && user.email_verified_at === null && (
                <div className="rounded-xl bg-amber-50 border border-amber-200 p-4">
                    <p className="text-sm text-amber-800">
                        Votre adresse email n'est pas vérifiée.{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="font-medium text-amber-900 underline hover:no-underline"
                        >
                            Cliquez ici pour renvoyer le lien de vérification.
                        </Link>
                    </p>

                    {status === 'verification-link-sent' && (
                        <p className="mt-2 text-sm font-medium text-green-600">
                            Un nouveau lien de vérification a été envoyé à votre adresse email.
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
                    Enregistrer
                </button>

                {recentlySuccessful && (
                    <span className="inline-flex items-center gap-1.5 text-sm text-green-600">
                        <Check className="h-4 w-4" />
                        Enregistré
                    </span>
                )}
            </div>
        </form>
    );
}
