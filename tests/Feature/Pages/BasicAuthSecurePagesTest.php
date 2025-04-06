<?php

declare(strict_types=1);

use function Pest\Laravel\get;

beforeEach(
    fn (): string => $this->validCredentials = base64_encode(
        sprintf(
            '%s:%s',
            config('very_basic_auth.user'),
            config('very_basic_auth.password'),
        )
    )
);

// You can't use `config()` here, although you really want to.
dataset('protected routes', [
    'logs' => 'logs',
    'pulse' => 'pulse',
]);

describe('Basic Auth', function (): void {
    it('denies access without authentication for `:dataset`',
        fn ($route) => get($route)->assertUnauthorized()
    )->with('protected routes');

    it('denies access with invalid credentials for `:dataset`',
        fn ($route) => test()->withHeaders(['Authorization' => 'Basic '.base64_encode('invalid:credentials')])
            ->get($route)
            ->assertUnauthorized()
    )->with('protected routes');

    it('grants access with valid credentials for `:dataset`',
        fn ($route) => test()->withHeaders(['Authorization' => 'Basic '.test()->validCredentials])
            ->get($route)
            ->assertOk()
    )->with('protected routes');
});
