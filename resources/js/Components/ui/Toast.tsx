import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { X, CheckCircle, XCircle, AlertCircle } from 'lucide-react';
import { clsx } from 'clsx';
import { PageProps } from '@/types';

interface ToastProps {
    message: string;
    type: 'success' | 'error' | 'warning';
    onClose: () => void;
}

function Toast({ message, type, onClose }: ToastProps) {
    useEffect(() => {
        const timer = setTimeout(onClose, 5000);
        return () => clearTimeout(timer);
    }, [onClose]);

    const icons = {
        success: CheckCircle,
        error: XCircle,
        warning: AlertCircle,
    };
    const Icon = icons[type];

    const styles = {
        success: 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        error: 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        warning: 'bg-yellow-50 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    };

    return (
        <div className={clsx('flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg', styles[type])}>
            <Icon className="h-5 w-5 flex-shrink-0" />
            <p className="text-sm font-medium">{message}</p>
            <button
                onClick={onClose}
                className="ml-auto rounded p-1 hover:bg-black/5 dark:hover:bg-white/5"
                aria-label="Close notification"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}

export function ToastContainer() {
    const { flash } = usePage<PageProps>().props;
    const [toasts, setToasts] = useState<Array<{ id: number; message: string; type: 'success' | 'error' }>>([]);

    useEffect(() => {
        if (flash?.success) {
            setToasts((prev) => [...prev, { id: Date.now(), message: flash.success!, type: 'success' }]);
        }
        if (flash?.error) {
            setToasts((prev) => [...prev, { id: Date.now(), message: flash.error!, type: 'error' }]);
        }
    }, [flash?.success, flash?.error]);

    const removeToast = (id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    };

    if (toasts.length === 0) return null;

    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            {toasts.map((toast) => (
                <Toast
                    key={toast.id}
                    message={toast.message}
                    type={toast.type}
                    onClose={() => removeToast(toast.id)}
                />
            ))}
        </div>
    );
}
