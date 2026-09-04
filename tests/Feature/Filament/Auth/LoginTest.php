<?php

declare(strict_types=1);

use App\Filament\Auth\Login;
use App\Filament\Forms\Components\ReCaptchaV3;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Tests\Feature\Filament\Fixtures\FilamentForm;

covers(Login::class);

describe('Filament | Auth | Login', function (): void {
    it('renders login page', function (): void {
        Livewire::test(Login::class)->assertSuccessful();
    });

    it('form contains email, password, remember and recaptcha fields', function (): void {
        $components = (new Login)->form(Schema::make(FilamentForm::make()))->getComponents();

        assert($components[0] instanceof TextInput);
        assert($components[1] instanceof TextInput);
        assert($components[2] instanceof Checkbox);
        assert($components[3] instanceof ReCaptchaV3);

        expect($components)->toHaveCount(4)
            ->and($components[0]->getName())->toBe('email')
            ->and($components[1]->getName())->toBe('password')
            ->and($components[2]->getName())->toBe('remember')
            ->and($components[3]->getName())->toBe('recaptcha_token')
            ->and($components[3]->getRecaptchaAction())->toBe('login');
    });
});
