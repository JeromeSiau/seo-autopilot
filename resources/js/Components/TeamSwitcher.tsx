import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Check, Building2 } from 'lucide-react';
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
    const dropdownRef = useRef<HTMLDivElement>(null);

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
                </div>
            )}
        </div>
    );
}
