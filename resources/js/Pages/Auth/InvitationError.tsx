import { AlertCircle } from 'lucide-react';
import { Head, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';

type ErrorType = 'invalid' | 'expired' | 'email_mismatch';

interface InvitationErrorProps {
    error: ErrorType;
    email?: string;
}

const errorContent: Record<ErrorType, { title: string; description: string }> = {
    invalid: {
        title: 'Invalid Invitation',
        description: 'This invitation link is invalid or has already been used.',
    },
    expired: {
        title: 'Invitation Expired',
        description: 'This invitation has expired. Please ask the team owner for a new invitation.',
    },
    email_mismatch: {
        title: 'Wrong Account',
        description: 'This invitation was sent to {email}. Please log in with that email address.',
    },
};

export default function InvitationError({ error, email }: InvitationErrorProps) {
    const { auth } = usePage<PageProps>().props;
    const isAuthenticated = !!auth?.user;

    const content = errorContent[error];
    const description = content.description.replace('{email}', email || '');

    return (
        <GuestLayout>
            <Head title={content.title} />

            <div className="flex flex-col items-center text-center">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-500/10 mb-6">
                    <AlertCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                </div>

                <h1 className="text-xl font-semibold text-surface-900 dark:text-white mb-2">
                    {content.title}
                </h1>

                <p className="text-surface-600 dark:text-surface-400 mb-8">
                    {description}
                </p>

                <Link
                    href={isAuthenticated ? route('dashboard') : route('login')}
                    className="inline-flex items-center justify-center rounded-xl border border-transparent bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition duration-200 ease-in-out hover:bg-primary-700 focus:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-surface-800 active:bg-primary-800 shadow-sm hover:shadow-md"
                >
                    {isAuthenticated ? 'Go to Dashboard' : 'Go to Login'}
                </Link>
            </div>
        </GuestLayout>
    );
}
