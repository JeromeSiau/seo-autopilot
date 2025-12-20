<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixUsersWithoutTeam extends Command
{
    protected $signature = 'users:fix-teams';

    protected $description = 'Create teams for users who do not have one';

    public function handle(): int
    {
        $users = User::whereNull('team_id')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have teams.');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} users without teams.");

        foreach ($users as $user) {
            $user->createTeam($user->name . "'s Team");
            $this->line("Created team for: {$user->email}");
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }
}
