<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

covers(UserResource::class);
uses(RefreshDatabase::class);

it('allows creating users',
    fn () => expect(UserResource::canCreate())->toBeTrue()
);

it('disallows editing users', function () {
    $user = User::factory()->create();
    expect(UserResource::canEdit($user))->toBeFalse();
});

class DummyFormComponent implements HasForms
{
    use InteractsWithForms;
}

it('returns a valid form schema', function () {
    $form = UserResource::form(Form::make(new DummyFormComponent));
    $schema = $form->getComponents();

    expect($schema)
        ->toHaveCount(4)
        ->and($schema[0])->toBeInstanceOf(TextInput::class)
        ->and($schema[1])->toBeInstanceOf(TextInput::class)
        ->and($schema[2])->toBeInstanceOf(DateTimePicker::class)
        ->and($schema[3])->toBeInstanceOf(DateTimePicker::class);
});

it('renders valid table configuration', function () {
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

it('applies the `name` filter correctly', function () {
    $users = User::factory(5)->create();
    $filteredUser = $users->first();
    $nonFilteredUsers = $users->reject(fn ($user) => $user->name === $filteredUser->name);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->filterTable('name', ['name' => $filteredUser->name])
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$filteredUser])
        ->assertCanNotSeeTableRecords($nonFilteredUsers);
});

it('applies the `email` filter correctly', function () {
    $users = User::factory(5)->create();
    $filteredUser = $users->first();
    $nonFilteredUsers = $users->reject(fn ($user) => $user->email === $filteredUser->email);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->filterTable('email', ['email' => $filteredUser->email])
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$filteredUser])
        ->assertCanNotSeeTableRecords($nonFilteredUsers);
});

it('returns valid pages routes', function () {
    $pages = UserResource::getPages();
    expect($pages)->toHaveKeys(['index', 'view']);
});
