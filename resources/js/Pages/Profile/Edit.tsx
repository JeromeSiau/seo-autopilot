import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
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
    const { t } = useTranslations();

    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">{t?.profile?.title ?? 'Profile'}</h1>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        {t?.profile?.subtitle ?? 'Manage your personal information and security preferences'}
                    </p>
                </div>
            }
        >
            <Head title={t?.profile?.title ?? 'Profile'} />

            <div className="space-y-6">
                {/* Profile Information */}
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-500/15">
                            <User className="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                {t?.profile?.info?.title ?? 'Profile Information'}
                            </h2>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                {t?.profile?.info?.subtitle ?? 'Update your name and email address'}
                            </p>
                        </div>
                    </div>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </div>

                {/* Password */}
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-500/15">
                            <Lock className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                {t?.profile?.password?.title ?? 'Password'}
                            </h2>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                {t?.profile?.password?.subtitle ?? 'Use a long, secure password'}
                            </p>
                        </div>
                    </div>
                    <UpdatePasswordForm />
                </div>

                {/* Delete Account */}
                <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-red-200 dark:border-red-500/20 p-6">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-500/15">
                            <Trash2 className="h-6 w-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white">
                                {t?.profile?.delete?.title ?? 'Delete Account'}
                            </h2>
                            <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                                {t?.profile?.delete?.subtitle ?? 'Permanently delete your account and all your data'}
                            </p>
                        </div>
                    </div>
                    <DeleteUserForm />
                </div>
            </div>
        </AppLayout>
    );
}
