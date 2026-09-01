<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

final class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
    }
}
