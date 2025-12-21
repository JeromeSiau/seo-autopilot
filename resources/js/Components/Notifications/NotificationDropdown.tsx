import { useState, useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { Bell, Check, CheckCheck } from 'lucide-react';
import axios from 'axios';

interface Notification {
    id: number;
    type: string;
    title: string;
    message: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
    site?: { domain: string };
}

interface Props {
    initialCount?: number;
}

export default function NotificationDropdown({ initialCount = 0 }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(initialCount);
    const [loading, setLoading] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const fetchNotifications = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('notifications.index'));
            setNotifications(response.data.notifications);
            setUnreadCount(response.data.unread_count);
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleOpen = () => {
        setIsOpen(!isOpen);
        if (!isOpen && notifications.length === 0) {
            fetchNotifications();
        }
    };

    const markAsRead = async (id: number) => {
        try {
            await axios.post(route('notifications.read', id));
            setNotifications(notifications.map(n =>
                n.id === id ? { ...n, read_at: new Date().toISOString() } : n
            ));
            setUnreadCount(Math.max(0, unreadCount - 1));
        } catch (error) {
            console.error(error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.post(route('notifications.readAll'));
            setNotifications(notifications.map(n => ({ ...n, read_at: new Date().toISOString() })));
            setUnreadCount(0);
        } catch (error) {
            console.error(error);
        }
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const hours = Math.floor(diff / (1000 * 60 * 60));
        if (hours < 1) return 'À l\'instant';
        if (hours < 24) return `Il y a ${hours}h`;
        const days = Math.floor(hours / 24);
        if (days === 1) return 'Hier';
        return `Il y a ${days} jours`;
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'review_needed': return '⚠️';
            case 'published': return '✅';
            case 'publish_failed': return '🔴';
            case 'quota_warning': return '⚡';
            case 'keywords_found': return '🔍';
            default: return '📌';
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={handleOpen}
                className="relative h-9 w-9 flex items-center justify-center rounded-lg text-surface-500 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-700 dark:hover:text-surface-200 transition-colors"
            >
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-medium text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 rounded-xl bg-white dark:bg-surface-900 shadow-lg dark:shadow-card-dark ring-1 ring-surface-200 dark:ring-surface-800 z-50">
                    <div className="flex items-center justify-between border-b border-surface-100 dark:border-surface-800 px-4 py-3">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Notifications</h3>
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllAsRead}
                                className="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-500"
                            >
                                <CheckCheck className="h-3 w-3" />
                                Tout marquer lu
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {loading ? (
                            <div className="py-8 text-center text-surface-500 dark:text-surface-400">Chargement...</div>
                        ) : notifications.length === 0 ? (
                            <div className="py-8 text-center text-surface-500 dark:text-surface-400">Aucune notification</div>
                        ) : (
                            notifications.map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`border-b border-surface-100 dark:border-surface-800 last:border-0 ${
                                        !notification.read_at ? 'bg-primary-50 dark:bg-primary-500/10' : ''
                                    }`}
                                >
                                    <div className="flex gap-3 p-4">
                                        <span className="text-lg">{getTypeIcon(notification.type)}</span>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-surface-900 dark:text-white text-sm">
                                                {notification.title}
                                            </p>
                                            {notification.message && (
                                                <p className="text-xs text-surface-500 dark:text-surface-400 truncate mt-0.5">
                                                    {notification.message}
                                                </p>
                                            )}
                                            <p className="text-xs text-surface-400 dark:text-surface-500 mt-1">
                                                {formatTime(notification.created_at)}
                                            </p>
                                        </div>
                                        {!notification.read_at && (
                                            <button
                                                onClick={() => markAsRead(notification.id)}
                                                className="text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                                            >
                                                <Check className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                    {notification.action_url && (
                                        <Link
                                            href={notification.action_url}
                                            className="block border-t border-surface-100 dark:border-surface-800 bg-surface-50 dark:bg-surface-800/50 px-4 py-2 text-xs text-primary-600 dark:text-primary-400 hover:bg-surface-100 dark:hover:bg-surface-800"
                                            onClick={() => setIsOpen(false)}
                                        >
                                            Voir détails →
                                        </Link>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
