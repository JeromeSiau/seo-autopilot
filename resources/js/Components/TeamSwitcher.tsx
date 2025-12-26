import { useState, useRef, useEffect, FormEventHandler } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage, useForm } from '@inertiajs/react';
import { ChevronDown, Check, Building2, Plus, X, AlertCircle } from 'lucide-react';
import clsx from 'clsx';
import { PageProps, UserTeam } from '@/types';

const roleColors = {
    owner: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400',
    admin: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
    member: 'bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-400',
};

export default function TeamSwitcher() {
    const { auth } = usePage<PageProps>().props;
    const [isOpen, setIsOpen] = useState(false);
    const [isSwitching, setIsSwitching] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const handleCreateTeam: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('teams.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowCreateModal(false);
                reset();
            },
        });
    };

    const currentTeam = auth.user.current_team;
    const teams = auth.user.teams;

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Return null if no teams or no current team
    if (!teams || teams.length === 0 || !currentTeam) {
        return null;
    }

    // Find current team's role from teams array
    const currentTeamData = teams.find((t) => t.id === currentTeam.id);
    const currentRole = currentTeamData?.role ?? 'member';

    const handleTeamSwitch = (team: UserTeam) => {
        if (team.id === currentTeam.id || isSwitching) {
            setIsOpen(false);
            return;
        }

        setIsSwitching(true);
        router.post(
            route('teams.switch', { team: team.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSwitching(false);
                    setIsOpen(false);
                },
            }
        );
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                disabled={isSwitching}
                className={clsx(
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all',
                    'bg-surface-50 dark:bg-surface-800/50',
                    'hover:bg-surface-100 dark:hover:bg-surface-800',
                    'text-surface-700 dark:text-surface-300',
                    isSwitching && 'opacity-50 cursor-not-allowed'
                )}
            >
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-500/20">
                    <Building2 className="h-4 w-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div className="flex flex-1 flex-col items-start min-w-0">
                    <span className="truncate w-full text-left">{currentTeam.name}</span>
                    <span
                        className={clsx(
                            'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide',
                            roleColors[currentRole]
                        )}
                    >
                        {currentRole}
                    </span>
                </div>
                <ChevronDown
                    className={clsx(
                        'h-4 w-4 flex-shrink-0 text-surface-400 transition-transform',
                        isOpen && 'rotate-180'
                    )}
                />
            </button>

            {isOpen && (
                <div className="absolute left-0 right-0 z-50 mt-2 origin-top rounded-xl bg-white dark:bg-surface-900 py-2 shadow-lg dark:shadow-card-dark ring-1 ring-surface-200 dark:ring-surface-800">
                    <div className="px-3 py-2 border-b border-surface-100 dark:border-surface-800">
                        <p className="text-xs font-medium uppercase tracking-wide text-surface-400 dark:text-surface-500">
                            Switch Team
                        </p>
                    </div>
                    <div className="max-h-64 overflow-y-auto py-1">
                        {teams.map((team) => {
                            const isCurrentTeam = team.id === currentTeam.id;
                            return (
                                <button
                                    key={team.id}
                                    onClick={() => handleTeamSwitch(team)}
                                    disabled={isSwitching}
                                    className={clsx(
                                        'flex w-full items-center gap-3 px-3 py-2.5 text-sm transition-colors',
                                        isCurrentTeam
                                            ? 'bg-primary-50 dark:bg-primary-500/10'
                                            : 'hover:bg-surface-50 dark:hover:bg-surface-800',
                                        isSwitching && 'cursor-not-allowed'
                                    )}
                                >
                                    <div
                                        className={clsx(
                                            'flex h-8 w-8 items-center justify-center rounded-lg',
                                            isCurrentTeam
                                                ? 'bg-primary-100 dark:bg-primary-500/20'
                                                : 'bg-surface-100 dark:bg-surface-800'
                                        )}
                                    >
                                        <Building2
                                            className={clsx(
                                                'h-4 w-4',
                                                isCurrentTeam
                                                    ? 'text-primary-600 dark:text-primary-400'
                                                    : 'text-surface-500 dark:text-surface-400'
                                            )}
                                        />
                                    </div>
                                    <div className="flex flex-1 flex-col items-start min-w-0">
                                        <span
                                            className={clsx(
                                                'truncate w-full text-left font-medium',
                                                isCurrentTeam
                                                    ? 'text-primary-700 dark:text-primary-400'
                                                    : 'text-surface-700 dark:text-surface-300'
                                            )}
                                        >
                                            {team.name}
                                        </span>
                                        <span
                                            className={clsx(
                                                'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide',
                                                roleColors[team.role]
                                            )}
                                        >
                                            {team.role}
                                        </span>
                                    </div>
                                    {isCurrentTeam && (
                                        <Check className="h-4 w-4 flex-shrink-0 text-primary-600 dark:text-primary-400" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <div className="border-t border-surface-100 dark:border-surface-800 px-3 py-2">
                        <button
                            onClick={() => {
                                setIsOpen(false);
                                setShowCreateModal(true);
                            }}
                            className="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800 hover:text-surface-900 dark:hover:text-surface-200 transition-colors"
                        >
                            <Plus className="h-4 w-4" />
                            Create New Team
                        </button>
                    </div>
                </div>
            )}

            {/* Create Team Modal - rendered via portal to escape sidebar z-index */}
            {showCreateModal && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                    <div
                        className="fixed inset-0 bg-black/50"
                        onClick={() => setShowCreateModal(false)}
                    />
                    <div className="relative w-full max-w-md rounded-2xl bg-white dark:bg-surface-900 p-6 shadow-xl">
                        <button
                            onClick={() => setShowCreateModal(false)}
                            className="absolute right-4 top-4 rounded-lg p-1 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-surface-600 dark:hover:text-surface-300"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <h2 className="text-xl font-semibold text-surface-900 dark:text-white mb-2">
                            Create New Team
                        </h2>

                        <div className="mb-6 flex items-start gap-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                            <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <p>
                                Each team has its own dedicated billing. You will be able to select a plan after creating the team.
                            </p>
                        </div>

                        <form onSubmit={handleCreateTeam}>
                            <div className="mb-4">
                                <label
                                    htmlFor="team-name"
                                    className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1"
                                >
                                    Team Name
                                </label>
                                <input
                                    id="team-name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="My Awesome Team"
                                    className={clsx(
                                        'w-full rounded-lg border px-3 py-2 text-sm',
                                        'bg-white dark:bg-surface-800',
                                        'text-surface-900 dark:text-white',
                                        'placeholder-surface-400 dark:placeholder-surface-500',
                                        errors.name
                                            ? 'border-red-300 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
                                            : 'border-surface-200 dark:border-surface-700 focus:border-primary-500 focus:ring-primary-500',
                                        'focus:outline-none focus:ring-2 focus:ring-offset-0'
                                    )}
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="flex-1 rounded-lg border border-surface-200 dark:border-surface-700 px-4 py-2 text-sm font-medium text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing || !data.name.trim()}
                                    className={clsx(
                                        'flex-1 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors',
                                        processing || !data.name.trim()
                                            ? 'bg-primary-400 cursor-not-allowed'
                                            : 'bg-primary-600 hover:bg-primary-700'
                                    )}
                                >
                                    {processing ? 'Creating...' : 'Create Team'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
