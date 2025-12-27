import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

interface Props {
    error: 'invalid' | 'expired' | 'email_mismatch';
    message: string;
}

export default function InvitationError({ error, message }: Props) {
    const getIcon = () => {
        switch (error) {
            case 'expired':
                return (
                    <svg className="mx-auto h-12 w-12 text-yellow-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                );
            case 'email_mismatch':
                return (
                    <svg className="mx-auto h-12 w-12 text-orange-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                );
            default:
                return (
                    <svg className="mx-auto h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                );
        }
    };

    const getTitle = () => {
        switch (error) {
            case 'expired':
                return 'Invitation Expired';
            case 'email_mismatch':
                return 'Wrong Account';
            default:
                return 'Invalid Invitation';
        }
    };

    return (
        <GuestLayout>
            <Head title={getTitle()} />

            <div className="text-center">
                {getIcon()}

                <h1 className="mt-4 text-2xl font-bold text-surface-900 dark:text-white">
                    {getTitle()}
                </h1>

                <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                    {message}
                </p>

                <div className="mt-6">
                    <Link
                        href="/"
                        className="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                    >
                        Go to Homepage
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
