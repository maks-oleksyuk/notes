<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ReCaptchaService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

final readonly class ValidReCaptchaToken implements ValidationRule
{
    public function __construct(
        private string $action,
        private string $errorField,
        private Application $application,
        private Repository $repository,
    ) {}

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! $this->repository->boolean('recaptcha.enabled')) {
            return;
        }

        if ($this->application->make(ReCaptchaService::class)->verify(is_string($value) ? $value : '', $this->action)) {
            return;
        }

        $prefix = str_contains($attribute, '.') ? mb_substr($attribute, 0, mb_strrpos($attribute, '.') + 1) : '';

        throw ValidationException::withMessages([
            $prefix.$this->errorField => __('Our robot detector is suspicious. Try again.'),
        ]);
    }
}
