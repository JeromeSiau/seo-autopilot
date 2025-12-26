# Team Management Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement frontend team management with team switcher, member invitations, and role management.

**Architecture:** Backend controllers handle team operations with email invitations via magic links. Frontend uses React/Inertia with TeamSwitcher component in sidebar and Settings/Team page for management.

**Tech Stack:** Laravel 11, Inertia.js, React, TypeScript, Tailwind CSS

---

## Task 1: Create TeamInvitation Migration and Model

**Files:**
- Create: `database/migrations/2025_12_26_200000_create_team_invitations_table.php`
- Create: `app/Models/TeamInvitation.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_team_invitations_table
```

**Step 2: Write migration**

```php
// database/migrations/xxxx_create_team_invitations_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
```

**Step 3: Create TeamInvitation model**

```php
// app/Models/TeamInvitation.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamInvitation extends Model
{
    protected $fillable = ['team_id', 'email', 'role', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (TeamInvitation $invitation) {
            $invitation->token = $invitation->token ?? Str::random(64);
            $invitation->expires_at = $invitation->expires_at ?? now()->addDays(7);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
```

**Step 4: Add relation to Team model**

Edit `app/Models/Team.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function invitations(): HasMany
{
    return $this->hasMany(TeamInvitation::class);
}
```

**Step 5: Run migration**

```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations app/Models
git commit -m "feat: add TeamInvitation model and migration"
```

---

## Task 2: Create Team Controllers

**Files:**
- Create: `app/Http/Controllers/Web/TeamController.php`
- Create: `app/Http/Controllers/Web/TeamMemberController.php`
- Create: `app/Http/Controllers/Web/TeamInvitationController.php`

**Step 1: Create TeamController**

```php
// app/Http/Controllers/Web/TeamController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team->update($validated);

        return back()->with('success', 'Team name updated.');
    }

    public function switch(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (!$user->belongsToTeam($team)) {
            abort(403, 'You do not belong to this team.');
        }

        $user->switchTeam($team);

        return back()->with('success', "Switched to {$team->name}.");
    }
}
```

**Step 2: Create TeamMemberController**

```php
// app/Http/Controllers/Web/TeamMemberController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function update(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('updateMember', [$team, $user]);

        $validated = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $team->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back()->with('success', 'Member role updated.');
    }

    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('removeMember', [$team, $user]);

        if ($team->owner_id === $user->id) {
            return back()->with('error', 'Cannot remove the team owner.');
        }

        $team->users()->detach($user->id);

        if ($user->current_team_id === $team->id) {
            $user->update(['current_team_id' => null]);
        }

        return back()->with('success', 'Member removed from team.');
    }
}
```

**Step 3: Create TeamInvitationController**

```php
// app/Http/Controllers/Web/TeamInvitationController.php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('invite', $team);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,member',
        ]);

        // Check if user already in team
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $existingUser->belongsToTeam($team)) {
            return back()->with('error', 'This user is already a member of the team.');
        }

        // Check if invitation already pending
        $existingInvitation = $team->invitations()
            ->where('email', $validated['email'])
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return back()->with('error', 'An invitation is already pending for this email.');
        }

        $invitation = $team->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        Mail::to($validated['email'])->send(new TeamInvitationMail($invitation));

        return back()->with('success', 'Invitation sent.');
    }

    public function destroy(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('invite', $team);

        if ($invitation->team_id !== $team->id) {
            abort(404);
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }

    public function accept(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = TeamInvitation::where('token', $token)->first();

        if (!$invitation) {
            return Inertia::render('Auth/InvitationError', [
                'message' => 'This invitation link is invalid.',
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Auth/InvitationError', [
                'message' => 'This invitation has expired. Please request a new one.',
            ]);
        }

        $user = $request->user();

        // Not logged in
        if (!$user) {
            $existingUser = User::where('email', $invitation->email)->first();

            if ($existingUser) {
                // Redirect to login with return URL
                session(['pending_invitation' => $token]);
                return redirect()->route('login')->with('info', 'Please log in to accept your invitation.');
            } else {
                // Redirect to register with email prefilled
                session(['pending_invitation' => $token]);
                return redirect()->route('register', ['email' => $invitation->email]);
            }
        }

        // Logged in but wrong email
        if ($user->email !== $invitation->email) {
            return Inertia::render('Auth/InvitationError', [
                'message' => "This invitation was sent to {$invitation->email}. Please log in with that email address.",
            ]);
        }

        // Accept invitation
        $this->acceptInvitation($user, $invitation);

        return redirect()->route('dashboard')->with('success', "You've joined {$invitation->team->name}!");
    }

    protected function acceptInvitation(User $user, TeamInvitation $invitation): void
    {
        $team = $invitation->team;

        if (!$user->belongsToTeam($team)) {
            $user->teams()->attach($team->id, ['role' => $invitation->role]);
        }

        if (!$user->current_team_id) {
            $user->update(['current_team_id' => $team->id]);
        }

        $invitation->delete();
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Web
git commit -m "feat: add Team, TeamMember, TeamInvitation controllers"
```

---

## Task 3: Create Team Policy

**Files:**
- Create: `app/Policies/TeamPolicy.php`

**Step 1: Create policy**

```bash
php artisan make:policy TeamPolicy --model=Team
```

**Step 2: Write policy**

```php
// app/Policies/TeamPolicy.php
<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    public function invite(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    public function updateMember(User $user, Team $team, User $member): bool
    {
        // Only owner can change roles
        return $team->owner_id === $user->id;
    }

    public function removeMember(User $user, Team $team, User $member): bool
    {
        // Can't remove yourself
        if ($user->id === $member->id) {
            return false;
        }

        // Owner can remove anyone
        if ($team->owner_id === $user->id) {
            return true;
        }

        // Admin can remove members only
        $userRole = $user->teams()->where('team_id', $team->id)->first()?->pivot?->role;
        $memberRole = $member->teams()->where('team_id', $team->id)->first()?->pivot?->role;

        return $userRole === 'admin' && $memberRole === 'member';
    }

    protected function isOwnerOrAdmin(User $user, Team $team): bool
    {
        if ($team->owner_id === $user->id) {
            return true;
        }

        $pivot = $user->teams()->where('team_id', $team->id)->first()?->pivot;
        return $pivot && in_array($pivot->role, ['owner', 'admin']);
    }
}
```

**Step 3: Register policy in AuthServiceProvider**

Edit `app/Providers/AuthServiceProvider.php` or `bootstrap/app.php` if using Laravel 11:

```php
// In boot() method or policies array:
use App\Models\Team;
use App\Policies\TeamPolicy;

protected $policies = [
    Team::class => TeamPolicy::class,
];
```

**Step 4: Commit**

```bash
git add app/Policies app/Providers
git commit -m "feat: add TeamPolicy for authorization"
```

---

## Task 4: Create Team Invitation Email

**Files:**
- Create: `app/Mail/TeamInvitationMail.php`
- Create: `resources/views/emails/team-invitation.blade.php`

**Step 1: Create mail class**

```bash
php artisan make:mail TeamInvitationMail
```

**Step 2: Write mail class**

```php
// app/Mail/TeamInvitationMail.php
<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->team->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'teamName' => $this->invitation->team->name,
                'role' => $this->invitation->role,
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at->format('F j, Y'),
            ],
        );
    }
}
```

**Step 3: Create email template**

```blade
{{-- resources/views/emails/team-invitation.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f5; padding: 40px 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="color: #18181b; font-size: 24px; margin-bottom: 16px;">
            You're invited to join {{ $teamName }}
        </h1>

        <p style="color: #52525b; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
            You've been invited to join <strong>{{ $teamName }}</strong> as a <strong>{{ ucfirst($role) }}</strong>.
        </p>

        <a href="{{ $acceptUrl }}" style="display: inline-block; background: #10b981; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">
            Accept Invitation
        </a>

        <p style="color: #a1a1aa; font-size: 14px; margin-top: 32px;">
            This invitation expires on {{ $expiresAt }}.
        </p>

        <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 32px 0;">

        <p style="color: #a1a1aa; font-size: 12px;">
            If you didn't expect this invitation, you can ignore this email.
        </p>
    </div>
</body>
</html>
```

**Step 4: Commit**

```bash
git add app/Mail resources/views/emails
git commit -m "feat: add team invitation email"
```

---

## Task 5: Add Routes

**Files:**
- Modify: `routes/web.php`

**Step 1: Add team routes**

Add after the Settings routes in `routes/web.php`:

```php
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TeamMemberController;
use App\Http\Controllers\Web\TeamInvitationController;

// Inside auth middleware group:

// Teams
Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

// Team Members
Route::post('/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
Route::delete('/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

// Team Invitations
Route::post('/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
Route::delete('/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');

// Outside auth middleware (invitation accept):
Route::get('/invitations/{token}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
```

**Step 2: Commit**

```bash
git add routes/web.php
git commit -m "feat: add team management routes"
```

---

## Task 6: Update SettingsController for Team Page

**Files:**
- Modify: `app/Http/Controllers/Web/SettingsController.php`

**Step 1: Update team method**

```php
// In SettingsController.php, update/add the team method:

public function team(Request $request): Response
{
    $user = $request->user();
    $team = $user->currentTeam;

    if (!$team) {
        return redirect()->route('dashboard')->with('error', 'No team selected.');
    }

    $members = $team->users()->withPivot('role', 'created_at')->get()->map(fn ($member) => [
        'id' => $member->id,
        'name' => $member->name,
        'email' => $member->email,
        'role' => $member->id === $team->owner_id ? 'owner' : $member->pivot->role,
        'joined_at' => $member->pivot->created_at,
    ]);

    $invitations = $team->invitations()
        ->where('expires_at', '>', now())
        ->get()
        ->map(fn ($inv) => [
            'id' => $inv->id,
            'email' => $inv->email,
            'role' => $inv->role,
            'created_at' => $inv->created_at,
            'expires_at' => $inv->expires_at,
        ]);

    $userRole = $user->id === $team->owner_id
        ? 'owner'
        : $user->teams()->where('team_id', $team->id)->first()?->pivot?->role ?? 'member';

    return Inertia::render('Settings/Team', [
        'team' => [
            'id' => $team->id,
            'name' => $team->name,
            'owner_id' => $team->owner_id,
        ],
        'members' => $members,
        'invitations' => $invitations,
        'userRole' => $userRole,
    ]);
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Web/SettingsController.php
git commit -m "feat: update SettingsController for team page data"
```

---

## Task 7: Update Types for Frontend

**Files:**
- Modify: `resources/js/types/index.d.ts`

**Step 1: Add team-related types**

```typescript
// Add to index.d.ts:

export interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    joined_at: string;
}

export interface TeamInvitation {
    id: number;
    email: string;
    role: 'admin' | 'member';
    created_at: string;
    expires_at: string;
}

export interface UserTeam {
    id: number;
    name: string;
    role: 'owner' | 'admin' | 'member';
}

// Update User interface to include teams:
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_team_id?: number;
    current_team?: Team;
    teams?: UserTeam[];
}
```

**Step 2: Commit**

```bash
git add resources/js/types
git commit -m "feat: add team management types"
```

---

## Task 8: Create TeamSwitcher Component

**Files:**
- Create: `resources/js/Components/TeamSwitcher.tsx`

**Step 1: Create component**

```tsx
// resources/js/Components/TeamSwitcher.tsx
import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Check, Building2 } from 'lucide-react';
import clsx from 'clsx';
import { PageProps, UserTeam } from '@/types';

const ROLE_COLORS = {
    owner: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
    admin: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    member: 'bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-400',
};

export default function TeamSwitcher() {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    const teams = auth.user.teams || [];
    const currentTeam = auth.user.current_team;
    const currentRole = teams.find(t => t.id === currentTeam?.id)?.role || 'member';

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const switchTeam = (teamId: number) => {
        router.post(route('teams.switch', teamId), {}, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    if (!currentTeam || teams.length === 0) {
        return null;
    }

    return (
        <div ref={ref} className="relative px-3 mb-4">
            <button
                onClick={() => setOpen(!open)}
                className={clsx(
                    'w-full flex items-center gap-3 rounded-xl px-3 py-2.5',
                    'bg-surface-50 dark:bg-surface-800/50',
                    'hover:bg-surface-100 dark:hover:bg-surface-800',
                    'border border-surface-200 dark:border-surface-700',
                    'transition-all'
                )}
            >
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-500/20">
                    <Building2 className="h-4 w-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div className="flex-1 text-left min-w-0">
                    <p className="text-sm font-medium text-surface-900 dark:text-white truncate">
                        {currentTeam.name}
                    </p>
                    <p className={clsx('text-xs capitalize', ROLE_COLORS[currentRole].split(' ').slice(2).join(' '))}>
                        {currentRole}
                    </p>
                </div>
                <ChevronDown className={clsx(
                    'h-4 w-4 text-surface-400 transition-transform',
                    open && 'rotate-180'
                )} />
            </button>

            {open && (
                <div className="absolute left-3 right-3 z-50 mt-2 rounded-xl bg-white dark:bg-surface-900 py-2 shadow-lg ring-1 ring-surface-200 dark:ring-surface-700">
                    <p className="px-3 py-1.5 text-xs font-medium text-surface-400 uppercase tracking-wide">
                        Your Teams
                    </p>
                    {teams.map((team) => (
                        <button
                            key={team.id}
                            onClick={() => switchTeam(team.id)}
                            className={clsx(
                                'w-full flex items-center gap-3 px-3 py-2',
                                'hover:bg-surface-50 dark:hover:bg-surface-800',
                                'transition-colors'
                            )}
                        >
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-100 dark:bg-surface-800">
                                <Building2 className="h-4 w-4 text-surface-500" />
                            </div>
                            <div className="flex-1 text-left">
                                <p className="text-sm font-medium text-surface-900 dark:text-white">
                                    {team.name}
                                </p>
                            </div>
                            <span className={clsx(
                                'text-xs px-2 py-0.5 rounded-full capitalize',
                                ROLE_COLORS[team.role]
                            )}>
                                {team.role}
                            </span>
                            {team.id === currentTeam.id && (
                                <Check className="h-4 w-4 text-primary-500" />
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Components/TeamSwitcher.tsx
git commit -m "feat: add TeamSwitcher component"
```

---

## Task 9: Add TeamSwitcher to AppLayout

**Files:**
- Modify: `resources/js/Layouts/AppLayout.tsx`

**Step 1: Import TeamSwitcher**

Add import at top:

```tsx
import TeamSwitcher from '@/Components/TeamSwitcher';
```

**Step 2: Add TeamSwitcher after logo in sidebar**

Find the desktop sidebar section (around line 220-230) and add TeamSwitcher after the logo div:

```tsx
{/* Logo */}
<div className="flex h-16 flex-shrink-0 items-center px-5 border-b border-surface-100 dark:border-surface-800">
    <Link href="/" className="flex items-center">
        <Logo size="md" />
    </Link>
</div>

{/* Team Switcher */}
<div className="mt-4">
    <TeamSwitcher />
</div>

{/* Navigation */}
<nav className="mt-2 flex-1 px-3">
```

Do the same for mobile sidebar.

**Step 3: Commit**

```bash
git add resources/js/Layouts/AppLayout.tsx
git commit -m "feat: add TeamSwitcher to sidebar"
```

---

## Task 10: Create Settings/Team Page

**Files:**
- Create: `resources/js/Pages/Settings/Team.tsx`

**Step 1: Create page**

```tsx
// resources/js/Pages/Settings/Team.tsx
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Users, Mail, Trash2, ChevronLeft, UserPlus } from 'lucide-react';
import clsx from 'clsx';
import { PageProps, TeamMember, TeamInvitation } from '@/types';

interface TeamPageProps extends PageProps {
    team: { id: number; name: string; owner_id: number };
    members: TeamMember[];
    invitations: TeamInvitation[];
    userRole: 'owner' | 'admin' | 'member';
}

const ROLE_COLORS = {
    owner: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
    admin: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    member: 'bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-400',
};

function RenameTeamForm({ team, canEdit }: { team: { id: number; name: string }; canEdit: boolean }) {
    const { data, setData, patch, processing, errors } = useForm({ name: team.name });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('teams.update', team.id));
    };

    if (!canEdit) return null;

    return (
        <form onSubmit={submit} className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 p-6 mb-6">
            <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-4">Team Name</h2>
            <div className="flex gap-3">
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="flex-1 rounded-lg border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2 text-surface-900 dark:text-white"
                />
                <button
                    type="submit"
                    disabled={processing}
                    className="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 disabled:opacity-50"
                >
                    Save
                </button>
            </div>
            {errors.name && <p className="mt-2 text-sm text-red-500">{errors.name}</p>}
        </form>
    );
}

function InviteForm({ teamId, canInvite }: { teamId: number; canInvite: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'member' as 'admin' | 'member',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('teams.invitations.store', teamId), {
            onSuccess: () => reset(),
        });
    };

    if (!canInvite) return null;

    return (
        <form onSubmit={submit} className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 p-6 mb-6">
            <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                <UserPlus className="h-5 w-5" />
                Invite Member
            </h2>
            <div className="flex flex-col sm:flex-row gap-3">
                <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="email@example.com"
                    className="flex-1 rounded-lg border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2 text-surface-900 dark:text-white"
                />
                <select
                    value={data.role}
                    onChange={(e) => setData('role', e.target.value as 'admin' | 'member')}
                    className="rounded-lg border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 px-4 py-2 text-surface-900 dark:text-white"
                >
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
                <button
                    type="submit"
                    disabled={processing}
                    className="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 disabled:opacity-50 flex items-center gap-2"
                >
                    <Mail className="h-4 w-4" />
                    Send Invite
                </button>
            </div>
            {errors.email && <p className="mt-2 text-sm text-red-500">{errors.email}</p>}
        </form>
    );
}

function MembersList({
    teamId,
    members,
    userRole,
    currentUserId,
    ownerId
}: {
    teamId: number;
    members: TeamMember[];
    userRole: string;
    currentUserId: number;
    ownerId: number;
}) {
    const canChangeRole = userRole === 'owner';
    const canRemove = (member: TeamMember) => {
        if (member.id === currentUserId) return false;
        if (member.id === ownerId) return false;
        if (userRole === 'owner') return true;
        if (userRole === 'admin' && member.role === 'member') return true;
        return false;
    };

    const updateRole = (memberId: number, role: string) => {
        router.post(route('teams.members.update', [teamId, memberId]), { role });
    };

    const removeMember = (memberId: number) => {
        if (confirm('Are you sure you want to remove this member?')) {
            router.delete(route('teams.members.destroy', [teamId, memberId]));
        }
    };

    return (
        <div className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 p-6 mb-6">
            <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                <Users className="h-5 w-5" />
                Members ({members.length})
            </h2>
            <div className="divide-y divide-surface-100 dark:divide-surface-800">
                {members.map((member) => (
                    <div key={member.id} className="flex items-center gap-4 py-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20">
                            <span className="text-sm font-semibold text-primary-700 dark:text-primary-400">
                                {member.name.charAt(0).toUpperCase()}
                            </span>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-surface-900 dark:text-white truncate">
                                {member.name}
                            </p>
                            <p className="text-xs text-surface-500 truncate">{member.email}</p>
                        </div>
                        {canChangeRole && member.role !== 'owner' ? (
                            <select
                                value={member.role}
                                onChange={(e) => updateRole(member.id, e.target.value)}
                                className={clsx(
                                    'text-xs px-2 py-1 rounded-lg border-0 capitalize',
                                    ROLE_COLORS[member.role]
                                )}
                            >
                                <option value="admin">Admin</option>
                                <option value="member">Member</option>
                            </select>
                        ) : (
                            <span className={clsx(
                                'text-xs px-2 py-1 rounded-full capitalize',
                                ROLE_COLORS[member.role]
                            )}>
                                {member.role}
                            </span>
                        )}
                        {canRemove(member) && (
                            <button
                                onClick={() => removeMember(member.id)}
                                className="p-2 text-surface-400 hover:text-red-500 transition-colors"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

function PendingInvitations({ teamId, invitations, canManage }: { teamId: number; invitations: TeamInvitation[]; canManage: boolean }) {
    if (!canManage || invitations.length === 0) return null;

    const cancelInvitation = (invitationId: number) => {
        router.delete(route('teams.invitations.destroy', [teamId, invitationId]));
    };

    return (
        <div className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 p-6">
            <h2 className="text-lg font-semibold text-surface-900 dark:text-white mb-4 flex items-center gap-2">
                <Mail className="h-5 w-5" />
                Pending Invitations ({invitations.length})
            </h2>
            <div className="divide-y divide-surface-100 dark:divide-surface-800">
                {invitations.map((invitation) => (
                    <div key={invitation.id} className="flex items-center gap-4 py-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <Mail className="h-4 w-4 text-surface-500" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-surface-900 dark:text-white truncate">
                                {invitation.email}
                            </p>
                            <p className="text-xs text-surface-500">
                                Expires {new Date(invitation.expires_at).toLocaleDateString()}
                            </p>
                        </div>
                        <span className={clsx(
                            'text-xs px-2 py-1 rounded-full capitalize',
                            ROLE_COLORS[invitation.role]
                        )}>
                            {invitation.role}
                        </span>
                        <button
                            onClick={() => cancelInvitation(invitation.id)}
                            className="p-2 text-surface-400 hover:text-red-500 transition-colors"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Team({ auth, team, members, invitations, userRole }: TeamPageProps) {
    const canEdit = userRole === 'owner' || userRole === 'admin';
    const canInvite = userRole === 'owner' || userRole === 'admin';

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <a
                        href={route('settings.index')}
                        className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <ChevronLeft className="h-5 w-5 text-surface-500" />
                    </a>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-surface-900 dark:text-white">
                            Team Settings
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Manage your team members and invitations
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Team Settings" />

            <RenameTeamForm team={team} canEdit={canEdit} />
            <InviteForm teamId={team.id} canInvite={canInvite} />
            <MembersList
                teamId={team.id}
                members={members}
                userRole={userRole}
                currentUserId={auth.user.id}
                ownerId={team.owner_id}
            />
            <PendingInvitations teamId={team.id} invitations={invitations} canManage={canInvite} />
        </AppLayout>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Pages/Settings/Team.tsx
git commit -m "feat: add Settings/Team page"
```

---

## Task 11: Update Auth Flow for Pending Invitations

**Files:**
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`

**Step 1: Handle pending invitation after login**

In `AuthenticatedSessionController.php`, update the `store` method:

```php
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    // Check for pending invitation
    if ($token = session('pending_invitation')) {
        session()->forget('pending_invitation');
        return redirect()->route('invitations.accept', $token);
    }

    return redirect()->intended(route('dashboard', absolute: false));
}
```

**Step 2: Handle pending invitation after register**

In `RegisteredUserController.php`, update the `store` method:

```php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // Create a personal team for the user
    $user->createTeam($request->name . "'s Team");

    event(new Registered($user));
    Auth::login($user);

    // Check for pending invitation
    if ($token = session('pending_invitation')) {
        session()->forget('pending_invitation');
        return redirect()->route('invitations.accept', $token);
    }

    return redirect(route('dashboard', absolute: false));
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Auth
git commit -m "feat: handle pending invitations in auth flow"
```

---

## Task 12: Pass User Teams to Frontend

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

**Step 1: Add teams to shared data**

In the `share` method, update the auth user data:

```php
'auth' => [
    'user' => $request->user() ? [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'current_team_id' => $request->user()->current_team_id,
        'current_team' => $request->user()->currentTeam,
        'teams' => $request->user()->teams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'role' => $team->pivot->role,
        ]),
    ] : null,
],
```

**Step 2: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat: share user teams with frontend"
```

---

## Task 13: Create Invitation Error Page

**Files:**
- Create: `resources/js/Pages/Auth/InvitationError.tsx`

**Step 1: Create page**

```tsx
// resources/js/Pages/Auth/InvitationError.tsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';

interface Props {
    message: string;
}

export default function InvitationError({ message }: Props) {
    return (
        <GuestLayout>
            <Head title="Invitation Error" />

            <div className="text-center">
                <div className="flex justify-center mb-4">
                    <div className="h-16 w-16 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center">
                        <AlertCircle className="h-8 w-8 text-red-500" />
                    </div>
                </div>

                <h1 className="text-xl font-semibold text-surface-900 dark:text-white mb-2">
                    Invitation Error
                </h1>

                <p className="text-surface-600 dark:text-surface-400 mb-6">
                    {message}
                </p>

                <Link
                    href={route('dashboard')}
                    className="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600"
                >
                    Go to Dashboard
                </Link>
            </div>
        </GuestLayout>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Pages/Auth/InvitationError.tsx
git commit -m "feat: add invitation error page"
```

---

## Task 14: Final Testing & Cleanup

**Step 1: Run migrations**

```bash
php artisan migrate
```

**Step 2: Clear caches**

```bash
php artisan optimize:clear
```

**Step 3: Test the flow**

1. Go to Settings > Team
2. Rename team
3. Invite a new member by email
4. Check email received
5. Open invitation link
6. Test team switching in sidebar

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete team management implementation"
```

---

**Plan complete and saved to `docs/plans/2025-12-26-team-management-implementation.md`.**

Two execution options:

1. **Subagent-Driven (this session)** - Fresh subagent per task, review between tasks
2. **Parallel Session (separate)** - Open new session with executing-plans

Which approach?
