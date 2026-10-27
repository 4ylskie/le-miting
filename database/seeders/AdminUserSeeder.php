<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user.
     */
    public function run(): void
    {
        $email = env('ADMIN_INITIAL_EMAIL', 'admin@lemiting.com');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'password')),
                'role' => 'admin',
            ]
        );
    }
}
