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
        User::factory()->superAdmin()->create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin_Pass1'),
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('test_Pass2'),
        ]);
    }
}
