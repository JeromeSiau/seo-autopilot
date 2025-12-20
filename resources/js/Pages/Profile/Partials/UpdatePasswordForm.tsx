import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import { Check } from 'lucide-react';
import clsx from 'clsx';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    const inputClasses = (hasError: boolean) =>
        clsx(
            'block w-full rounded-xl border px-4 py-2.5 text-surface-900',
            'placeholder:text-surface-400',
            'focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500',
            'transition-colors',
            hasError ? 'border-red-300 bg-red-50' : 'border-surface-300 bg-white'
        );

    return (
        <form onSubmit={updatePassword} className="space-y-5">
            <div>
                <label
                    htmlFor="current_password"
                    className="block text-sm font-medium text-surface-700 mb-1.5"
                >
                    Mot de passe actuel
                </label>
                <input
                    id="current_password"
                    ref={currentPasswordInput}
                    type="password"
                    className={inputClasses(!!errors.current_password)}
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    autoComplete="current-password"
                />
                {errors.current_password && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.current_password}</p>
                )}
            </div>

            <div>
                <label
                    htmlFor="password"
                    className="block text-sm font-medium text-surface-700 mb-1.5"
                >
                    Nouveau mot de passe
                </label>
                <input
                    id="password"
                    ref={passwordInput}
                    type="password"
                    className={inputClasses(!!errors.password)}
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    autoComplete="new-password"
                />
                {errors.password && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>
                )}
            </div>

            <div>
                <label
                    htmlFor="password_confirmation"
                    className="block text-sm font-medium text-surface-700 mb-1.5"
                >
                    Confirmer le mot de passe
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    className={inputClasses(!!errors.password_confirmation)}
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    autoComplete="new-password"
                />
                {errors.password_confirmation && (
                    <p className="mt-1.5 text-sm text-red-600">{errors.password_confirmation}</p>
                )}
            </div>

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
