<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Filament\Forms\Components\ReCaptchaV3;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Schemas\Schema;

final class Login extends FilamentLogin
{
    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
            ReCaptchaV3::make('recaptcha_token')->recaptchaAction('login'),
        ]);
    }
}
