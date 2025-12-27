<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Jérôme Siau',
            'email' => 'siau.jerome@gmail.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
    }
}
