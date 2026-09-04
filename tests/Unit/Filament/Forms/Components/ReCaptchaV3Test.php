<?php

declare(strict_types=1);

use App\Filament\Forms\Components\ReCaptchaV3;
use App\Rules\ValidReCaptchaToken;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Collection;

covers(ReCaptchaV3::class);

beforeEach(fn (): ReCaptchaV3 => $this->component = ReCaptchaV3::make('recaptcha_token'));

arch('extends Hidden')
    ->expect(ReCaptchaV3::class)
    ->toExtend(Hidden::class);

describe('Filament | Forms | Components | ReCaptchaV3', function (): void {
    it('initialises with correct defaults', function (): void {
        expect($this->component->getRecaptchaAction())->toBeEmpty()
            ->and($this->component->getSubmitMethod())->toBe('authenticate')
            ->and($this->component->getErrorField())->toBe('email')
            ->and($this->component->getColumnSpan('default'))->toBe('hidden');
    });

    it('fluent setters return static and update state', function (string $setter, string $getter, string $value): void {
        $component = $this->component;
        $result = $component->$setter($value);

        expect($result)->toBe($component)
            ->and($component->$getter())->toBe($value);
    })->with([
        'recaptchaAction' => ['recaptchaAction', 'getRecaptchaAction', 'login'],
        'submitMethod' => ['submitMethod',    'getSubmitMethod',    'create'],
        'errorField' => ['errorField',      'getErrorField',      'username'],
    ]);

    it('registers ValidReCaptchaToken rule with action and errorField from component state', function (): void {
        $component = ReCaptchaV3::make('token')
            ->recaptchaAction('login')
            ->errorField('username');

        $rule = new Collection($component->getValidationRules())->first(fn ($r): bool => $r instanceof ValidReCaptchaToken);

        expect($rule)->toBeInstanceOf(ValidReCaptchaToken::class);
        assert($rule instanceof ValidReCaptchaToken);

        $action = new ReflectionProperty($rule, 'action')->getValue($rule);
        $errorField = new ReflectionProperty($rule, 'errorField')->getValue($rule);

        expect($action)->toBe('login')
            ->and($errorField)->toBe('username');
    });
});
