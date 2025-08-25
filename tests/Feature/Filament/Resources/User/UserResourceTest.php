<?php

declare(strict_types=1);

use App\Filament\Resources\User\Pages\ListUsers;
use App\Filament\Resources\User\UserResource;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Expectation;
use Tests\Feature\Filament\Fixtures\FilamentForm;

covers(UserResource::class);
uses(RefreshDatabase::class);

describe('Filament | User Resource', function (): void {
    it('allows creating users',
        fn (): Expectation => expect(UserResource::canCreate())->toBeTrue()
    );

    it('disallows editing users', function (): void {
        $user = User::factory()->create();
        expect(UserResource::canEdit($user))->toBeFalse();
    });

    it('returns a valid form schema', function (): void {
        $form = UserResource::form(Schema::make(FilamentForm::make()));
        $schema = $form->getComponents();

        expect($schema)
            ->toHaveCount(4)
            ->and($schema[0])->toBeInstanceOf(TextInput::class)
            ->and($schema[1])->toBeInstanceOf(TextInput::class)
            ->and($schema[2])->toBeInstanceOf(DateTimePicker::class)
            ->and($schema[3])->toBeInstanceOf(DateTimePicker::class);
    });

    it('returns a Table instance configured by UsersTable', function (): void {
        Livewire::test(ListUsers::class)->assertSuccessful();
    });

    it('returns valid pages routes', function (): void {
        $pages = UserResource::getPages();
        expect($pages)->toHaveKeys(['index', 'view']);
    });
});
