<?php

declare(strict_types=1);

use App\Rules\ValidReCaptchaToken;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\ValidationException;

covers(ValidReCaptchaToken::class);

describe('Rules | ValidReCaptchaToken', function (): void {
    beforeEach(function (): void {
        config(['recaptcha.secret_key' => 'secret', 'recaptcha.min_score' => 0.5, 'recaptcha.enabled' => true]);
        $this->rule = App::make(ValidReCaptchaToken::class, ['action' => 'login', 'errorField' => 'email']);
        // ValidReCaptchaToken::validate() never actually calls $fail() — it throws
        // ValidationException directly — so this stub only needs to satisfy the
        // ValidationRule::validate() closure contract.
        $this->fail = fn (string $message, ?string $translate = null): PotentiallyTranslatedString => new PotentiallyTranslatedString($message, App::make(Translator::class));
    });

    it('skips Google call when recaptcha is disabled', function (): void {
        config(['recaptcha.enabled' => false]);

        $this->rule->validate('recaptcha_token', 'any-token', $this->fail);

        Http::assertNothingSent();
    });

    it('passes when Google verifies token successfully', function (): void {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        expect(fn () => $this->rule->validate('recaptcha_token', 'valid-token', $this->fail))
            ->not->toThrow(ValidationException::class);
    });

    it('throws ValidationException with error on errorField and correct message', function (): void {
        Http::fake(['*' => Http::response(['success' => false])]);

        $exception = null;
        try {
            $this->rule->validate('recaptcha_token', 'bad-token', $this->fail);
        } catch (ValidationException $validationException) {
            $exception = $validationException;
        }

        assert($exception instanceof ValidationException);

        expect($exception)
            ->and($exception->errors())->toHaveKey('email')
            ->and($exception->errors()['email'])->toContain(__('Our robot detector is suspicious. Try again.'));
    });

    it('preserves dot-prefix from attribute in error key', function (): void {
        Http::fake(['*' => Http::response(['success' => false])]);

        try {
            $this->rule->validate('data.recaptcha_token', 'bad-token', $this->fail);
        } catch (ValidationException $validationException) {
            expect($validationException->errors())->toHaveKey('data.email');
        }
    });

    it('treats non-string value as empty token and fails validation', function (): void {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        expect(fn () => $this->rule->validate('recaptcha_token', 42, $this->fail))
            ->toThrow(ValidationException::class);
    });
});
