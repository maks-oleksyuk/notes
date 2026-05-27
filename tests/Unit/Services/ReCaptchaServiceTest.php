<?php

declare(strict_types=1);

use App\Services\ReCaptchaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

covers(ReCaptchaService::class);

describe('Services | ReCaptcha', function (): void {
    beforeEach(function (): void {
        $this->service = App::make(ReCaptchaService::class);
        config(['recaptcha.secret_key' => 'test-secret', 'recaptcha.min_score' => 0.5]);
    });

    it('returns false for blank token without calling Google', function (string $token): void {
        Http::fake();

        expect($this->service->verify($token, 'login'))->toBeFalse();

        Http::assertNothingSent();
    })->with(['', '   ']);

    it('returns false for invalid response', function (array $body, int $status = 200): void {
        Http::fake(['*' => Http::response($body, $status)]);

        expect($this->service->verify('token', 'login'))->toBeFalse();
    })->with([
        'http failure' => [[], 500],
        'http failure with valid-looking body' => [['success' => true, 'score' => 0.9, 'action' => 'login'], 500],
        'success false' => [['success' => false, 'score' => 0.9, 'action' => 'login']],
        'success key absent' => [['score' => 0.9, 'action' => 'login']],
        'score below minimum' => [['success' => true, 'score' => 0.4, 'action' => 'login']],
        'score key absent, min above zero' => [['success' => true, 'action' => 'login']],
        'action mismatch' => [['success' => true, 'score' => 0.9, 'action' => 'register']],
    ]);

    it('returns true for valid response', function (array $body): void {
        Http::fake(['*' => Http::response($body)]);

        expect($this->service->verify('token', 'login'))->toBeTrue();
    })->with([
        'full response' => [['success' => true, 'score' => 0.9, 'action' => 'login']],
        'score at minimum' => [['success' => true, 'score' => 0.5, 'action' => 'login']],
    ]);

    it('returns true when score key is absent and minimum is zero', function (): void {
        config(['recaptcha.min_score' => 0.0]);
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'login'])]);

        expect($this->service->verify('token', 'login'))->toBeTrue();
    });

    it('returns true when action key is absent and empty string action is expected', function (): void {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9])]);

        expect($this->service->verify('token', ''))->toBeTrue();
    });

    it('sends secret, token and remote ip to google siteverify', function (): void {
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        $this->service->verify('my-token', 'login');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'siteverify')
            && $request['secret'] === 'test-secret'
            && $request['response'] === 'my-token'
            && isset($request['remoteip'])
        );
    });
});
