import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { User, Lock, Trash2 } from 'lucide-react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900">Profil</h1>
                    <p className="mt-1 text-sm text-surface-500">
                        Gérez vos informations personnelles et vos préférences de sécurité
                    </p>
                </div>
            }
        >
            <Head title="Profil" />

            <div className="space-y-6">
                {/* Profile Information */}
                <div className="bg-white rounded-2xl border border-surface-200 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-primary-100">
                            <User className="h-6 w-6 text-primary-600" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900">
                                Informations du profil
                            </h2>
                            <p className="mt-1 text-sm text-surface-500">
                                Mettez à jour votre nom et votre adresse email
                            </p>
                        </div>
                    </div>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </div>

                {/* Password */}
                <div className="bg-white rounded-2xl border border-surface-200 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100">
                            <Lock className="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900">
                                Mot de passe
                            </h2>
                            <p className="mt-1 text-sm text-surface-500">
                                Assurez-vous d'utiliser un mot de passe long et sécurisé
                            </p>
                        </div>
                    </div>
                    <UpdatePasswordForm />
                </div>

                {/* Delete Account */}
                <div className="bg-white rounded-2xl border border-red-200 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-100">
                            <Trash2 className="h-6 w-6 text-red-600" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900">
                                Supprimer le compte
                            </h2>
                            <p className="mt-1 text-sm text-surface-500">
                                Supprimez définitivement votre compte et toutes vos données
                            </p>
                        </div>
                    </div>
                    <DeleteUserForm />
                </div>
            </div>
        </AppLayout>
    );
}
