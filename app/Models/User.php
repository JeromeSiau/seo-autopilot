<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'notification_email_frequency',
        'notification_immediate_failures',
        'notification_immediate_quota',
        'locale',
        'theme',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_immediate_failures' => 'boolean',
            'notification_immediate_quota' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Get the user's current team (accessor).
     */
    public function getCurrentTeamAttribute(): ?Team
    {
        return $this->team;
    }

    public function hasTeam(): bool
    {
        return $this->team_id !== null;
    }

    public function isTeamOwner(): bool
    {
        return $this->team && $this->team->owner_id === $this->id;
    }

    public function createTeam(string $name): Team
    {
        $team = Team::create([
            'name' => $name,
            'owner_id' => $this->id,
            'plan' => 'starter',
            'articles_limit' => 10,
        ]);

        $this->update(['team_id' => $team->id]);

        return $team;
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }
}
