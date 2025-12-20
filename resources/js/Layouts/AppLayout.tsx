import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';
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
    Sparkles,
} from 'lucide-react';
import clsx from 'clsx';
import { User as UserType } from '@/types';

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
                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                item.current
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'
            )}
        >
            <item.icon
                className={clsx(
                    'h-5 w-5 flex-shrink-0',
                    item.current ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'
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

    const currentPath = window.location.pathname;

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

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Mobile sidebar */}
            <div
                className={clsx(
                    'fixed inset-y-0 left-0 z-50 w-64 transform bg-white transition-transform duration-300 ease-in-out lg:hidden',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                <div className="flex h-16 items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2">
                        <Sparkles className="h-8 w-8 text-indigo-600" />
                        <span className="text-xl font-bold text-gray-900">SEO Autopilot</span>
                    </Link>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        <X className="h-6 w-6" />
                    </button>
                </div>
                <nav className="mt-4 space-y-1 px-3">
                    {navigation.map((item) => (
                        <SidebarLink key={item.name} item={item} />
                    ))}
                </nav>
            </div>

            {/* Desktop sidebar */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <div className="flex min-h-0 flex-1 flex-col border-r border-gray-200 bg-white">
                    <div className="flex h-16 flex-shrink-0 items-center px-4">
                        <Link href="/" className="flex items-center gap-2">
                            <Sparkles className="h-8 w-8 text-indigo-600" />
                            <span className="text-xl font-bold text-gray-900">SEO Autopilot</span>
                        </Link>
                    </div>
                    <nav className="mt-4 flex-1 space-y-1 px-3">
                        {navigation.map((item) => (
                            <SidebarLink key={item.name} item={item} />
                        ))}
                    </nav>
                    <div className="border-t border-gray-200 p-3">
                        <Link
                            href={route('settings.index')}
                            className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <Settings className="h-5 w-5 text-gray-400" />
                            Settings
                        </Link>
                    </div>
                </div>
            </div>

            {/* Main content area */}
            <div className="lg:pl-64">
                {/* Top navbar */}
                <div className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        className="-m-2.5 p-2.5 text-gray-700 lg:hidden"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="h-6 w-6" />
                    </button>

                    <div className="flex flex-1 justify-end gap-x-4 lg:gap-x-6">
                        {/* Usage indicator */}
                        {auth.user.current_team && (
                            <div className="hidden items-center gap-2 text-sm text-gray-500 sm:flex">
                                <FileText className="h-4 w-4" />
                                <span>
                                    {auth.user.current_team.articles_generated_count} /{' '}
                                    {auth.user.current_team.articles_limit} articles
                                </span>
                            </div>
                        )}

                        {/* User menu */}
                        <div className="relative">
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100">
                                    <User className="h-5 w-5 text-indigo-600" />
                                </div>
                                <span className="hidden sm:block">{auth.user.name}</span>
                                <ChevronDown className="h-4 w-4 text-gray-400" />
                            </button>

                            {userMenuOpen && (
                                <>
                                    <div
                                        className="fixed inset-0 z-40"
                                        onClick={() => setUserMenuOpen(false)}
                                    />
                                    <div className="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-lg bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5">
                                        <Link
                                            href={route('profile.edit')}
                                            className="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                        >
                                            <User className="h-4 w-4" />
                                            Profile
                                        </Link>
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                        >
                                            <LogOut className="h-4 w-4" />
                                            Log out
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Page header */}
                {header && (
                    <header className="bg-white shadow-sm">
                        <div className="px-4 py-4 sm:px-6 lg:px-8">{header}</div>
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
