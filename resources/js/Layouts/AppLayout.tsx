import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useRef, useEffect } from 'react';
import {
    LayoutDashboard,
    Globe,
    Search,
    FileText,
    Plug,
    BarChart3,
    Settings,
    ChevronDown,
    Menu,
    X,
    LogOut,
    User,
    Zap,
} from 'lucide-react';
import clsx from 'clsx';
import { User as UserType } from '@/types';
import NotificationDropdown from '@/Components/Notifications/NotificationDropdown';
import Logo from '@/Components/Logo';

interface NavItem {
    name: string;
    href: string;
    icon: React.ElementType;
    current: boolean;
}

function SidebarLink({ item }: { item: NavItem }) {
    return (
        <Link
            href={item.href}
            className={clsx(
                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all',
                item.current
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-surface-600 hover:bg-surface-100 hover:text-surface-900'
            )}
        >
            <item.icon
                className={clsx(
                    'h-5 w-5 flex-shrink-0 transition-colors',
                    item.current ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-500'
                )}
            />
            {item.name}
        </Link>
    );
}

export default function AppLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<{ auth: { user: UserType } }>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);

    const currentPath = window.location.pathname;

    // Close user menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
                setUserMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const navigation: NavItem[] = [
        {
            name: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutDashboard,
            current: currentPath === '/dashboard',
        },
        {
            name: 'Sites',
            href: route('sites.index'),
            icon: Globe,
            current: currentPath.startsWith('/sites'),
        },
        {
            name: 'Keywords',
            href: route('keywords.index'),
            icon: Search,
            current: currentPath.startsWith('/keywords'),
        },
        {
            name: 'Articles',
            href: route('articles.index'),
            icon: FileText,
            current: currentPath.startsWith('/articles'),
        },
        {
            name: 'Integrations',
            href: route('integrations.index'),
            icon: Plug,
            current: currentPath.startsWith('/integrations'),
        },
        {
            name: 'Analytics',
            href: route('analytics.index'),
            icon: BarChart3,
            current: currentPath.startsWith('/analytics'),
        },
    ];

    // Calculate usage percentage
    const usagePercentage = auth.user.current_team
        ? Math.round((auth.user.current_team.articles_generated_count / auth.user.current_team.articles_limit) * 100)
        : 0;

    return (
        <div className="min-h-screen bg-surface-50">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-surface-900/50 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Mobile sidebar */}
            <div
                className={clsx(
                    'fixed inset-y-0 left-0 z-50 w-72 transform bg-white transition-transform duration-300 ease-out lg:hidden',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                <div className="flex h-16 items-center justify-between border-b border-surface-200 px-4">
                    <Link href="/" className="flex items-center">
                        <Logo size="md" />
                    </Link>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="p-2 text-surface-500 hover:text-surface-700 rounded-lg hover:bg-surface-100"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <nav className="mt-4 space-y-1 px-3">
                    {navigation.map((item) => (
                        <SidebarLink key={item.name} item={item} />
                    ))}
                </nav>
                <div className="absolute bottom-0 left-0 right-0 border-t border-surface-200 p-3">
                    <Link
                        href={route('settings.index')}
                        className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-surface-600 hover:bg-surface-100"
                    >
                        <Settings className="h-5 w-5 text-surface-400" />
                        Settings
                    </Link>
                </div>
            </div>

            {/* Desktop sidebar */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <div className="flex min-h-0 flex-1 flex-col border-r border-surface-200 bg-white">
                    {/* Logo */}
                    <div className="flex h-16 flex-shrink-0 items-center px-5 border-b border-surface-100">
                        <Link href="/" className="flex items-center">
                            <Logo size="md" />
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="mt-6 flex-1 space-y-1 px-3">
                        {navigation.map((item) => (
                            <SidebarLink key={item.name} item={item} />
                        ))}
                    </nav>

                    {/* Usage indicator */}
                    {auth.user.current_team && (
                        <div className="mx-3 mb-3 rounded-xl bg-surface-50 p-4">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-medium text-surface-500 uppercase tracking-wide">Usage</span>
                                <span className="text-xs font-semibold text-surface-700">
                                    {auth.user.current_team.articles_generated_count}/{auth.user.current_team.articles_limit}
                                </span>
                            </div>
                            <div className="h-1.5 w-full rounded-full bg-surface-200">
                                <div
                                    className={clsx(
                                        'h-1.5 rounded-full transition-all',
                                        usagePercentage >= 90 ? 'bg-red-500' :
                                        usagePercentage >= 70 ? 'bg-yellow-500' : 'bg-primary-500'
                                    )}
                                    style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                                />
                            </div>
                            <Link
                                href={route('settings.billing')}
                                className="mt-3 flex items-center justify-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700"
                            >
                                <Zap className="h-3 w-3" />
                                Upgrade plan
                            </Link>
                        </div>
                    )}

                    {/* Settings */}
                    <div className="border-t border-surface-200 p-3">
                        <Link
                            href={route('settings.index')}
                            className={clsx(
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all',
                                currentPath.startsWith('/settings')
                                    ? 'bg-primary-50 text-primary-700'
                                    : 'text-surface-600 hover:bg-surface-100'
                            )}
                        >
                            <Settings className={clsx(
                                'h-5 w-5',
                                currentPath.startsWith('/settings') ? 'text-primary-600' : 'text-surface-400'
                            )} />
                            Settings
                        </Link>
                    </div>
                </div>
            </div>

            {/* Main content area */}
            <div className="lg:pl-64">
                {/* Top navbar */}
                <div className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-surface-200 bg-white/80 backdrop-blur-xl px-4 sm:gap-x-6 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        className="p-2 text-surface-500 hover:text-surface-700 lg:hidden rounded-lg hover:bg-surface-100"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    <div className="flex flex-1 justify-end gap-x-3 lg:gap-x-4">
                        {/* Notification dropdown */}
                        <NotificationDropdown initialCount={0} />

                        {/* User menu */}
                        <div className="relative" ref={userMenuRef}>
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100 transition-all"
                            >
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary-100 to-primary-200">
                                    <span className="text-sm font-semibold text-primary-700">
                                        {auth.user.name.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <span className="hidden sm:block">{auth.user.name}</span>
                                <ChevronDown className={clsx(
                                    'h-4 w-4 text-surface-400 transition-transform',
                                    userMenuOpen && 'rotate-180'
                                )} />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl bg-white py-2 shadow-lg ring-1 ring-surface-200">
                                    <div className="px-4 py-2 border-b border-surface-100">
                                        <p className="text-sm font-medium text-surface-900">{auth.user.name}</p>
                                        <p className="text-xs text-surface-500">{auth.user.email}</p>
                                    </div>
                                    <Link
                                        href={route('profile.edit')}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 hover:bg-surface-50 transition-colors"
                                    >
                                        <User className="h-4 w-4 text-surface-400" />
                                        Profile
                                    </Link>
                                    <Link
                                        href={route('settings.index')}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 hover:bg-surface-50 transition-colors"
                                    >
                                        <Settings className="h-4 w-4 text-surface-400" />
                                        Settings
                                    </Link>
                                    <div className="border-t border-surface-100 mt-1 pt-1">
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                        >
                                            <LogOut className="h-4 w-4" />
                                            Log out
                                        </Link>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Page header */}
                {header && (
                    <header className="bg-white border-b border-surface-100">
                        <div className="px-4 py-5 sm:px-6 lg:px-8">{header}</div>
                    </header>
                )}

                {/* Page content */}
                <main className="py-6">
                    <div className="px-4 sm:px-6 lg:px-8">{children}</div>
                </main>
            </div>
        </div>
    );
}
