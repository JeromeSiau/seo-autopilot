import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import clsx from 'clsx';

export default function DeleteUserForm() {
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
            <div className="rounded-xl bg-red-50 border border-red-200 p-4 mb-5">
                <div className="flex gap-3">
                    <AlertTriangle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-red-800">
                        Une fois votre compte supprimé, toutes ses ressources et données seront
                        définitivement effacées. Avant de supprimer votre compte, veuillez télécharger
                        toutes les données que vous souhaitez conserver.
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
                Supprimer le compte
            </button>

            {/* Modal */}
            {confirmingUserDeletion && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div
                        className="fixed inset-0 bg-surface-900/50 backdrop-blur-sm"
                        onClick={closeModal}
                    />
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div className="relative w-full max-w-lg transform rounded-2xl bg-white p-6 shadow-xl transition-all">
                            <button
                                onClick={closeModal}
                                className="absolute right-4 top-4 p-2 text-surface-400 hover:text-surface-600 rounded-lg hover:bg-surface-100 transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>

                            <div className="flex items-start gap-4 mb-6">
                                <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-100">
                                    <AlertTriangle className="h-6 w-6 text-red-600" />
                                </div>
                                <div>
                                    <h3 className="font-display text-lg font-semibold text-surface-900">
                                        Supprimer votre compte ?
                                    </h3>
                                    <p className="mt-1 text-sm text-surface-500">
                                        Cette action est irréversible. Toutes vos données seront
                                        définitivement supprimées.
                                    </p>
                                </div>
                            </div>

                            <form onSubmit={deleteUser}>
                                <div className="mb-6">
                                    <label
                                        htmlFor="password"
                                        className="block text-sm font-medium text-surface-700 mb-1.5"
                                    >
                                        Confirmez votre mot de passe
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        ref={passwordInput}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className={clsx(
                                            'block w-full rounded-xl border px-4 py-2.5 text-surface-900',
                                            'placeholder:text-surface-400',
                                            'focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500',
                                            'transition-colors',
                                            errors.password
                                                ? 'border-red-300 bg-red-50'
                                                : 'border-surface-300 bg-white'
                                        )}
                                        placeholder="Mot de passe"
                                    />
                                    {errors.password && (
                                        <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className={clsx(
                                            'inline-flex items-center rounded-xl px-5 py-2.5',
                                            'text-sm font-semibold text-surface-700',
                                            'bg-surface-100 hover:bg-surface-200',
                                            'focus:outline-none focus:ring-2 focus:ring-surface-500/50',
                                            'transition-all'
                                        )}
                                    >
                                        Annuler
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
                                        Supprimer définitivement
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
