<?php

declare(strict_types=1);

use App\Filament\Resources\User\Pages\ListUsers;
use App\Filament\Resources\User\UserResource;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Pest\Expectation;
use Tests\Feature\Filament\Fixtures\FilamentForm;

covers(UserResource::class);

describe('Filament | Resource | User', function (): void {
    it('allows creating users',
        fn (): Expectation => expect(UserResource::canCreate())->toBeTrue()
    );

    it('disallows editing users',
        fn (): Expectation => expect(UserResource::canEdit(User::factory()->create()))->toBeFalse()
    );

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

    // No return type on the closure: PHPStan mistypes assertSuccessful() as
    // TestResponse instead of Testable (phpstan/phpstan#15095).
    it('returns a Table instance configured by UsersTable', function (): void {
        Livewire::test(ListUsers::class)->assertSuccessful();
    });

    it('returns valid pages routes',
        fn (): Expectation => expect(UserResource::getPages())->toHaveKeys(['index', 'view'])
    );
});
