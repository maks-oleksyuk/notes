<?php

declare(strict_types=1);

namespace Database\Seeders\Models;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [[
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin_Pass1'),
        ], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('test_Pass2'),
        ], ];

        foreach ($users as $user) {
            User::factory()->create($user);
        }
    }
}
