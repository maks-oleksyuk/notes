<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Rules\ValidReCaptchaToken;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\App;

final class ReCaptchaV3 extends Hidden
{
    #[\Override]
    protected string $view = 'filament.forms.components.re-captcha-v3';

    private string $captchaAction = '';

    private string $submitMethod = 'authenticate';

    private string $errorField = 'email';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (): ValidReCaptchaToken => App::make(ValidReCaptchaToken::class, [
            'action' => $this->captchaAction,
            'errorField' => $this->errorField,
        ]));
    }

    public function recaptchaAction(string $action): static
    {
        $this->captchaAction = $action;

        return $this;
    }

    public function getRecaptchaAction(): string
    {
        return $this->captchaAction;
    }

    public function submitMethod(string $method): static
    {
        $this->submitMethod = $method;

        return $this;
    }

    public function getSubmitMethod(): string
    {
        return $this->submitMethod;
    }

    public function errorField(string $field): static
    {
        $this->errorField = $field;

        return $this;
    }

    public function getErrorField(): string
    {
        return $this->errorField;
    }
}
