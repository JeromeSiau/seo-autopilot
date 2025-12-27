import { useState, FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Mail, Trash2, ChevronDown, Save, UserPlus } from 'lucide-react';
import clsx from 'clsx';
import { PageProps, Team, TeamMember, TeamInvitation } from '@/types';

interface TeamPageProps extends PageProps {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    userRole: 'owner' | 'admin' | 'member';
}

const roleColors = {
    owner: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400',
    admin: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
    member: 'bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-400',
};

function RenameTeamForm({ team }: { team: Team }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: team.name,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        patch(route('teams.update', { team: team.id }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6" data-testid="team-rename-section">
            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-4">
                Renommer l'equipe
            </h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label
                        htmlFor="team-name"
                        className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                    >
                        Nom de l'equipe
                    </label>
                    <input
                        id="team-name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={clsx(
                            'w-full rounded-xl border px-4 py-2.5 text-sm transition-colors',
                            'bg-white dark:bg-surface-800',
                            'text-surface-900 dark:text-white',
                            'placeholder-surface-400 dark:placeholder-surface-500',
                            errors.name
                                ? 'border-red-300 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
                                : 'border-surface-200 dark:border-surface-700 focus:border-primary-500 focus:ring-primary-500',
                            'focus:outline-none focus:ring-2 focus:ring-offset-0'
                        )}
                        placeholder="Mon equipe"
                    />
                    {errors.name && (
                        <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.name}</p>
                    )}
                </div>
                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={processing || data.name === team.name}
                        data-testid="team-rename-submit"
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all',
                            'bg-primary-500 text-white',
                            'hover:bg-primary-600 hover:-translate-y-0.5',
                            'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0'
                        )}
                    >
                        <Save className="h-4 w-4" />
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    );
}

function InviteForm({ teamId }: { teamId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'member' as 'admin' | 'member',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('teams.invitations.store', { team: teamId }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6" data-testid="team-invite-section">
            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-4">
                Inviter un membre
            </h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="flex-1">
                        <label
                            htmlFor="invite-email"
                            className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                        >
                            Adresse email
                        </label>
                        <input
                            id="invite-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className={clsx(
                                'w-full rounded-xl border px-4 py-2.5 text-sm transition-colors',
                                'bg-white dark:bg-surface-800',
                                'text-surface-900 dark:text-white',
                                'placeholder-surface-400 dark:placeholder-surface-500',
                                errors.email
                                    ? 'border-red-300 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
                                    : 'border-surface-200 dark:border-surface-700 focus:border-primary-500 focus:ring-primary-500',
                                'focus:outline-none focus:ring-2 focus:ring-offset-0'
                            )}
                            placeholder="collegue@exemple.com"
                        />
                        {errors.email && (
                            <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.email}</p>
                        )}
                    </div>
                    <div className="sm:w-40">
                        <label
                            htmlFor="invite-role"
                            className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5"
                        >
                            Role
                        </label>
                        <select
                            id="invite-role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value as 'admin' | 'member')}
                            className={clsx(
                                'w-full rounded-xl border px-4 py-2.5 text-sm transition-colors',
                                'bg-white dark:bg-surface-800',
                                'text-surface-900 dark:text-white',
                                'border-surface-200 dark:border-surface-700',
                                'focus:border-primary-500 focus:ring-primary-500',
                                'focus:outline-none focus:ring-2 focus:ring-offset-0'
                            )}
                        >
                            <option value="member">Membre</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={processing || !data.email}
                        className={clsx(
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all',
                            'bg-primary-500 text-white',
                            'hover:bg-primary-600 hover:-translate-y-0.5',
                            'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0'
                        )}
                    >
                        <Mail className="h-4 w-4" />
                        Envoyer l'invitation
                    </button>
                </div>
            </form>
        </div>
    );
}

function MembersList({
    team,
    members,
    userRole,
}: {
    team: Team;
    members: TeamMember[];
    userRole: 'owner' | 'admin' | 'member';
}) {
    const [openDropdown, setOpenDropdown] = useState<number | null>(null);

    const canManageMember = (member: TeamMember): boolean => {
        // Owner cannot be managed
        if (member.id === team.owner_id) return false;
        // Only owner can manage admins
        if (member.role === 'admin' && userRole !== 'owner') return false;
        // Owner can manage anyone except themselves
        if (userRole === 'owner') return true;
        // Admin can manage members only
        if (userRole === 'admin' && member.role === 'member') return true;
        return false;
    };

    const canChangeRole = (member: TeamMember): boolean => {
        // Only owner can change roles
        return userRole === 'owner' && member.id !== team.owner_id;
    };

    const handleRoleChange = (memberId: number, newRole: 'admin' | 'member') => {
        setOpenDropdown(null);
        router.post(
            route('teams.members.update', { team: team.id, member: memberId }),
            { role: newRole },
            { preserveScroll: true }
        );
    };

    const handleRemove = (memberId: number) => {
        if (!confirm('Etes-vous sur de vouloir retirer ce membre de l\'equipe ?')) return;
        router.delete(route('teams.members.destroy', { team: team.id, member: memberId }), {
            preserveScroll: true,
        });
    };

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6" data-testid="team-members-section">
            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-4">
                Membres de l'equipe
            </h2>
            <div className="divide-y divide-surface-100 dark:divide-surface-800">
                {members.map((member) => (
                    <div
                        key={member.id}
                        className="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                    >
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 font-semibold">
                                {member.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <p className="font-medium text-surface-900 dark:text-white truncate">
                                    {member.name}
                                </p>
                                <p className="text-sm text-surface-500 dark:text-surface-400 truncate">
                                    {member.email}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 flex-shrink-0">
                            {canChangeRole(member) ? (
                                <div className="relative">
                                    <button
                                        onClick={() =>
                                            setOpenDropdown(openDropdown === member.id ? null : member.id)
                                        }
                                        className={clsx(
                                            'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide transition-colors',
                                            roleColors[member.role],
                                            'hover:opacity-80'
                                        )}
                                    >
                                        {member.role}
                                        <ChevronDown className="h-3 w-3" />
                                    </button>
                                    {openDropdown === member.id && (
                                        <div className="absolute right-0 z-10 mt-1 w-32 origin-top-right rounded-xl bg-white dark:bg-surface-900 py-1 shadow-lg ring-1 ring-surface-200 dark:ring-surface-800">
                                            <button
                                                onClick={() => handleRoleChange(member.id, 'admin')}
                                                className={clsx(
                                                    'block w-full px-4 py-2 text-left text-sm',
                                                    member.role === 'admin'
                                                        ? 'bg-surface-50 dark:bg-surface-800 text-surface-900 dark:text-white'
                                                        : 'text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800'
                                                )}
                                            >
                                                Admin
                                            </button>
                                            <button
                                                onClick={() => handleRoleChange(member.id, 'member')}
                                                className={clsx(
                                                    'block w-full px-4 py-2 text-left text-sm',
                                                    member.role === 'member'
                                                        ? 'bg-surface-50 dark:bg-surface-800 text-surface-900 dark:text-white'
                                                        : 'text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800'
                                                )}
                                            >
                                                Membre
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <span
                                    className={clsx(
                                        'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide',
                                        roleColors[member.role]
                                    )}
                                    data-testid={`member-role-${member.role}`}
                                >
                                    {member.role}
                                </span>
                            )}
                            {canManageMember(member) && (
                                <button
                                    onClick={() => handleRemove(member.id)}
                                    className="p-2 rounded-lg text-surface-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                    title="Retirer de l'equipe"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function PendingInvitations({
    teamId,
    invitations,
}: {
    teamId: number;
    invitations: TeamInvitation[];
}) {
    const handleCancel = (invitationId: number) => {
        if (!confirm('Etes-vous sur de vouloir annuler cette invitation ?')) return;
        router.delete(route('teams.invitations.destroy', { team: teamId, invitation: invitationId }), {
            preserveScroll: true,
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    if (invitations.length === 0) {
        return null;
    }

    return (
        <div className="bg-white dark:bg-surface-900/50 dark:backdrop-blur-xl rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
            <h2 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-4">
                Invitations en attente
            </h2>
            <div className="divide-y divide-surface-100 dark:divide-surface-800">
                {invitations.map((invitation) => (
                    <div
                        key={invitation.id}
                        className="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                    >
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400">
                                <UserPlus className="h-5 w-5" />
                            </div>
                            <div className="min-w-0">
                                <p className="font-medium text-surface-900 dark:text-white truncate">
                                    {invitation.email}
                                </p>
                                <p className="text-sm text-surface-500 dark:text-surface-400">
                                    Expire le {formatDate(invitation.expires_at)}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 flex-shrink-0">
                            <span
                                className={clsx(
                                    'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold uppercase tracking-wide',
                                    roleColors[invitation.role]
                                )}
                            >
                                {invitation.role}
                            </span>
                            <button
                                onClick={() => handleCancel(invitation.id)}
                                className="p-2 rounded-lg text-surface-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                title="Annuler l'invitation"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function TeamPage({ team, members, invitations, userRole }: TeamPageProps) {
    const isOwnerOrAdmin = userRole === 'owner' || userRole === 'admin';

    return (
        <AppLayout
            header={
                <div>
                    <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                        Equipe
                    </h1>
                    <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                        Gerez les membres de votre equipe et les invitations
                    </p>
                </div>
            }
        >
            <Head title="Equipe" />

            <div className="space-y-6">
                {isOwnerOrAdmin && <RenameTeamForm team={team} />}
                {isOwnerOrAdmin && <InviteForm teamId={team.id} />}
                <MembersList team={team} members={members} userRole={userRole} />
                {isOwnerOrAdmin && <PendingInvitations teamId={team.id} invitations={invitations} />}
            </div>
        </AppLayout>
    );
}
