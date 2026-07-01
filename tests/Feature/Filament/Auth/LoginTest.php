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

        expect($components)->toHaveCount(4)
            ->and($components[0])->toBeInstanceOf(TextInput::class)
            ->and($components[0]->getName())->toBe('email')
            ->and($components[1])->toBeInstanceOf(TextInput::class)
            ->and($components[1]->getName())->toBe('password')
            ->and($components[2])->toBeInstanceOf(Checkbox::class)
            ->and($components[2]->getName())->toBe('remember')
            ->and($components[3])->toBeInstanceOf(ReCaptchaV3::class)
            ->and($components[3]->getName())->toBe('recaptcha_token')
            ->and($components[3]->getRecaptchaAction())->toBe('login');
    });
});
