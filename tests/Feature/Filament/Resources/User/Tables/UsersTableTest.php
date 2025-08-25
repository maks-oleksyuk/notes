<?php

declare(strict_types=1);

use App\Filament\Resources\User\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

covers(App\Filament\Resources\User\Tables\UsersTable::class);
uses(RefreshDatabase::class);

describe('Filament | User Table', function (): void {
    it('renders valid table configuration', function (): void {
        livewire(ListUsers::class)
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('email')
            ->assertTableColumnExists('created_at')
            ->assertTableFilterExists('name')
            ->assertTableFilterExists('email')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete')
            ->assertTableBulkActionExists('delete');
    });

    it('has correct filters configuration', function (): void {
        livewire(ListUsers::class)
            ->assertTableFilterExists('name', fn ($filter): bool => $filter->getFormSchema()[0]->getLabel() === 'Name'
                && $filter->getFormSchema()[0]->getPlaceholder() === 'Enter name to filter'
            )
            ->assertTableFilterExists('email', fn ($filter): bool => $filter->getFormSchema()[0]->getLabel() === 'Email'
                && $filter->getFormSchema()[0]->getPlaceholder() === 'Enter email to filter'
            );
    });

    it('has view and delete actions with empty labels', function (): void {
        livewire(ListUsers::class)
            ->assertTableActionExists('view', fn ($action): bool => $action->getLabel() === '')
            ->assertTableActionExists('delete', fn ($action): bool => $action->getLabel() === '');
    });

    it('applies the `name` filter correctly', function (): void {
        $users = User::factory(5)->create();
        $filteredUser = $users->first();
        $nonFilteredUsers = $users->reject(fn ($user): bool => $user->name === $filteredUser->name);

        livewire(ListUsers::class)
            ->assertCanSeeTableRecords($users)
            ->filterTable('name', ['name' => $filteredUser->name])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$filteredUser])
            ->assertCanNotSeeTableRecords($nonFilteredUsers)
            ->resetTableFilters()
            ->filterTable('name', ['name' => ''])
            ->assertCanSeeTableRecords($users);
    });

    it('applies the `email` filter correctly', function (): void {
        $users = User::factory(5)->create();
        $filteredUser = $users->first();
        $nonFilteredUsers = $users->reject(fn ($user): bool => $user->email === $filteredUser->email);

        livewire(ListUsers::class)
            ->assertCanSeeTableRecords($users)
            ->filterTable('email', ['email' => $filteredUser->email])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$filteredUser])
            ->assertCanNotSeeTableRecords($nonFilteredUsers)
            ->resetTableFilters()
            ->filterTable('email', ['email' => ''])
            ->assertCanSeeTableRecords($users);
    });
});
