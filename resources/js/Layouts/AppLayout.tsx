import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useRef, useEffect } from 'react';
import {
    LayoutDashboard,
    Globe,
    Search,
    FileText,
    Plug,
    BarChart3,
    TrendingUp,
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
import ThemeToggle from '@/Components/ThemeToggle';
import { useTranslations } from '@/hooks/useTranslations';

interface NavItem {
    name: string;
    href: string;
    icon: React.ElementType;
    current: boolean;
}

interface NavSection {
    title: string;
    items: NavItem[];
}

function SidebarLink({ item }: { item: NavItem }) {
    return (
        <Link
            href={item.href}
            className={clsx(
                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all',
                item.current
                    ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400'
                    : 'text-surface-600 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-900 dark:hover:text-white'
            )}
        >
            <item.icon
                className={clsx(
                    'h-5 w-5 flex-shrink-0 transition-colors',
                    item.current ? 'text-primary-600 dark:text-primary-400' : 'text-surface-400 group-hover:text-surface-500 dark:group-hover:text-surface-300'
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
    const { t } = useTranslations();
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

    const navigation: NavSection[] = [
        {
            title: t?.nav?.main ?? 'MAIN',
            items: [
                {
                    name: t?.nav?.dashboard ?? 'Dashboard',
                    href: route('dashboard'),
                    icon: LayoutDashboard,
                    current: currentPath === '/dashboard',
                },
                {
                    name: t?.nav?.sites ?? 'Sites',
                    href: route('sites.index'),
                    icon: Globe,
                    current: currentPath.startsWith('/sites'),
                },
                {
                    name: t?.nav?.articles ?? 'Articles',
                    href: route('articles.index'),
                    icon: FileText,
                    current: currentPath.startsWith('/articles'),
                },
                {
                    name: t?.nav?.keywords ?? 'Keywords',
                    href: route('keywords.index'),
                    icon: Search,
                    current: currentPath.startsWith('/keywords'),
                },
            ],
        },
        {
            title: t?.nav?.analytics ?? 'ANALYTICS',
            items: [
                {
                    name: t?.nav?.performance ?? 'Performance',
                    href: route('analytics.index'),
                    icon: BarChart3,
                    current: currentPath === '/analytics',
                },
                {
                    name: t?.nav?.rankings ?? 'Rankings',
                    href: route('analytics.index') + '?tab=rankings',
                    icon: TrendingUp,
                    current: currentPath.startsWith('/analytics') && window.location.search.includes('rankings'),
                },
            ],
        },
        {
            title: t?.nav?.settings ?? 'SETTINGS',
            items: [
                {
                    name: t?.nav?.integrations ?? 'Integrations',
                    href: route('integrations.index'),
                    icon: Plug,
                    current: currentPath.startsWith('/integrations'),
                },
                {
                    name: t?.nav?.settings ?? 'Settings',
                    href: route('settings.index'),
                    icon: Settings,
                    current: currentPath.startsWith('/settings'),
                },
            ],
        },
    ];

    // Calculate usage percentage
    const usagePercentage = auth.user.current_team
        ? Math.round((auth.user.current_team.articles_generated_count / auth.user.current_team.articles_limit) * 100)
        : 0;

    return (
        <div className="min-h-screen bg-surface-50 dark:bg-surface-900 transition-colors duration-300">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-surface-900/50 dark:bg-black/60 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Mobile sidebar */}
            <div
                className={clsx(
                    'fixed inset-y-0 left-0 z-50 w-72 transform bg-white dark:bg-surface-900 transition-transform duration-300 ease-out lg:hidden',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                <div className="flex h-16 items-center justify-between border-b border-surface-200 dark:border-surface-800 px-4">
                    <Link href="/" className="flex items-center">
                        <Logo size="md" />
                    </Link>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="p-2 text-surface-500 hover:text-surface-700 dark:hover:text-white rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <nav className="mt-4 px-3">
                    {navigation.map((section) => (
                        <div key={section.title} className="mb-6">
                            <h3 className="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-surface-400 dark:text-surface-500">
                                {section.title}
                            </h3>
                            <div className="space-y-1">
                                {section.items.map((item) => (
                                    <SidebarLink key={item.name} item={item} />
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>
            </div>

            {/* Desktop sidebar */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <div className="flex min-h-0 flex-1 flex-col border-r border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900">
                    {/* Logo */}
                    <div className="flex h-16 flex-shrink-0 items-center px-5 border-b border-surface-100 dark:border-surface-800">
                        <Link href="/" className="flex items-center">
                            <Logo size="md" />
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="mt-6 flex-1 px-3">
                        {navigation.map((section) => (
                            <div key={section.title} className="mb-6">
                                <h3 className="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-surface-400 dark:text-surface-500">
                                    {section.title}
                                </h3>
                                <div className="space-y-1">
                                    {section.items.map((item) => (
                                        <SidebarLink key={item.name} item={item} />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </nav>

                    {/* Usage indicator */}
                    {auth.user.current_team && (
                        <div className="mx-3 mb-3 rounded-xl bg-surface-50 dark:bg-surface-800/50 p-4">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">{t?.usage?.title ?? 'Usage'}</span>
                                <span className="text-xs font-semibold text-surface-700 dark:text-surface-300">
                                    {auth.user.current_team.articles_generated_count}/{auth.user.current_team.articles_limit}
                                </span>
                            </div>
                            <div className="h-1.5 w-full rounded-full bg-surface-200 dark:bg-surface-700">
                                <div
                                    className={clsx(
                                        'h-1.5 rounded-full transition-all',
                                        usagePercentage >= 90 ? 'bg-red-500' :
                                        usagePercentage >= 70 ? 'bg-yellow-500' : 'bg-primary-500 dark:shadow-[0_0_10px_rgba(16,185,129,0.4)]'
                                    )}
                                    style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                                />
                            </div>
                            <Link
                                href={route('settings.billing')}
                                className="mt-3 flex items-center justify-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                            >
                                <Zap className="h-3 w-3" />
                                {t?.usage?.upgradePlan ?? 'Upgrade plan'}
                            </Link>
                        </div>
                    )}

                </div>
            </div>

            {/* Main content area */}
            <div className="lg:pl-64">
                {/* Top navbar */}
                <div className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-surface-200 dark:border-surface-800 bg-white/80 dark:bg-surface-900/90 backdrop-blur-xl px-4 sm:gap-x-6 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        className="p-2 text-surface-500 hover:text-surface-700 dark:hover:text-white lg:hidden rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    <div className="flex flex-1 justify-end gap-x-3 lg:gap-x-4">
                        {/* Theme toggle */}
                        <ThemeToggle />

                        {/* Notification dropdown */}
                        <NotificationDropdown initialCount={0} />

                        {/* User menu */}
                        <div className="relative" ref={userMenuRef}>
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-all"
                            >
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10">
                                    <span className="text-sm font-semibold text-primary-700 dark:text-primary-400">
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
                                <div className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl bg-white dark:bg-surface-900 py-2 shadow-lg dark:shadow-card-dark ring-1 ring-surface-200 dark:ring-surface-800">
                                    <div className="px-4 py-2 border-b border-surface-100 dark:border-surface-800">
                                        <p className="text-sm font-medium text-surface-900 dark:text-white">{auth.user.name}</p>
                                        <p className="text-xs text-surface-500 dark:text-surface-400">{auth.user.email}</p>
                                    </div>
                                    <Link
                                        href={route('profile.edit')}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                    >
                                        <User className="h-4 w-4 text-surface-400" />
                                        {t?.user?.profile ?? 'Profile'}
                                    </Link>
                                    <Link
                                        href={route('settings.index')}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                    >
                                        <Settings className="h-4 w-4 text-surface-400" />
                                        {t?.user?.settings ?? 'Settings'}
                                    </Link>
                                    <div className="border-t border-surface-100 dark:border-surface-800 mt-1 pt-1">
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                        >
                                            <LogOut className="h-4 w-4" />
                                            {t?.user?.logout ?? 'Log out'}
                                        </Link>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Page header */}
                {header && (
                    <header className="bg-white dark:bg-surface-900/50 border-b border-surface-100 dark:border-surface-800">
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
