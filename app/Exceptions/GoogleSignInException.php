<?php

declare(strict_types=1);

namespace App\Exceptions;

final class GoogleSignInException extends \RuntimeException
{
    public static function failed(\Throwable $previous): self
    {
        return new self(__('auth/google.failed'), previous: $previous);
    }

    public static function unverifiedEmail(): self
    {
        return new self(__('auth/google.unverified_email'));
    }

    public static function noAccount(): self
    {
        return new self(__('auth/google.no_account'));
    }

    public static function identityMismatch(): self
    {
        return new self(__('auth/google.identity_mismatch'));
    }
}
