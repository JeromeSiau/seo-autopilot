import { useTranslations } from '@/hooks/useTranslations';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import clsx from 'clsx';

export default function DeleteUserForm() {
    const { t } = useTranslations();
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <>
            <div className="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-4 mb-5">
                <div className="flex gap-3">
                    <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-red-800 dark:text-red-300">
                        {t?.profile?.delete?.warning ?? 'Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data you wish to retain.'}
                    </p>
                </div>
            </div>

            <button
                type="button"
                onClick={confirmUserDeletion}
                className={clsx(
                    'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                    'text-sm font-semibold text-white',
                    'bg-red-600 hover:bg-red-700',
                    'focus:outline-none focus:ring-2 focus:ring-red-500/50',
                    'transition-all'
                )}
            >
                {t?.profile?.delete?.button ?? 'Delete Account'}
            </button>

            {/* Modal */}
            {confirmingUserDeletion && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div
                        className="fixed inset-0 bg-surface-900/50 dark:bg-black/70 backdrop-blur-sm"
                        onClick={closeModal}
                    />
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div className="relative w-full max-w-lg transform rounded-2xl bg-white dark:bg-surface-900 p-6 shadow-xl dark:shadow-card-dark border dark:border-surface-800 transition-all">
                            <button
                                onClick={closeModal}
                                className="absolute right-4 top-4 p-2 text-surface-400 hover:text-surface-600 dark:hover:text-white rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>

                            <div className="flex items-start gap-4 mb-6">
                                <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-500/15">
                                    <AlertTriangle className="h-6 w-6 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <h3 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                        {t?.profile?.delete?.confirmTitle ?? 'Delete your account?'}
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                        {t?.profile?.delete?.confirmWarning ?? 'This action is irreversible. All your data will be permanently deleted.'}
                                    </p>
                                </div>
                            </div>

                            <form onSubmit={deleteUser}>
                                <div className="mb-6">
                                    <label
                                        htmlFor="password"
                                        className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                                    >
                                        {t?.profile?.delete?.confirmPassword ?? 'Confirm your password'}
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        ref={passwordInput}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className={clsx(
                                            'block w-full rounded-xl border px-4 py-2.5 text-surface-900 dark:text-white',
                                            'placeholder:text-surface-400',
                                            'focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500',
                                            'transition-colors',
                                            errors.password
                                                ? 'border-red-300 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10'
                                                : 'border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800'
                                        )}
                                        placeholder={t?.auth?.login?.password ?? 'Password'}
                                    />
                                    {errors.password && (
                                        <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.password}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className={clsx(
                                            'inline-flex items-center rounded-xl px-5 py-2.5',
                                            'text-sm font-semibold text-surface-700 dark:text-surface-300',
                                            'bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700',
                                            'focus:outline-none focus:ring-2 focus:ring-surface-500/50',
                                            'transition-all'
                                        )}
                                    >
                                        {t?.profile?.delete?.cancel ?? 'Cancel'}
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className={clsx(
                                            'inline-flex items-center gap-2 rounded-xl px-5 py-2.5',
                                            'text-sm font-semibold text-white',
                                            'bg-red-600 hover:bg-red-700',
                                            'focus:outline-none focus:ring-2 focus:ring-red-500/50',
                                            'disabled:opacity-50 disabled:cursor-not-allowed',
                                            'transition-all'
                                        )}
                                    >
                                        {t?.profile?.delete?.confirmButton ?? 'Delete permanently'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
