<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveLocale;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

covers(ResolveLocale::class);

beforeEach(function (): void {
    config(['app.available_locales' => ['en', 'uk', 'pl']]);
    app()->setLocale('en');
});

dataset('locale source priority', [
    'valid lang param' => ['/?lang=uk', null, 'uk'],
    'invalid lang param falls back' => ['/?lang=xx', null, 'en'],
    'user preferred locale' => ['/', 'pl', 'pl'],
    'lang param overrides user preference' => ['/?lang=uk', 'pl', 'uk'],
    'invalid user locale falls back' => ['/', 'fr', 'en'],
]);

dataset('Accept-Language fallback', [
    'matching language' => ['uk,en;q=0.9', 'uk'],
    'no matching language' => ['fr,de;q=0.9', 'en'],
]);

describe('Http | Middleware | ResolveLocale', function (): void {
    it('resolves locale by source priority', function (string $url, ?string $userLocale, string $expected): void {
        $request = Request::create($url);

        if ($userLocale !== null) {
            $user = Mockery::mock(HasLocalePreference::class);
            $user->shouldReceive('preferredLocale')->andReturn($userLocale);
            $request->setUserResolver(fn () => $user);
        }

        App::make(ResolveLocale::class)->handle($request, fn ($r): ResponseFactory|Response => response('ok'));

        expect(app()->getLocale())->toBe($expected);
    })->with('locale source priority');

    it('falls back to Accept-Language header', function (string $header, string $expected): void {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => $header]);

        App::make(ResolveLocale::class)->handle($request, fn ($r): ResponseFactory|Response => response('ok'));

        expect(app()->getLocale())->toBe($expected);
    })->with('Accept-Language fallback');

    it('ignores non-HasLocalePreference user', function (): void {
        $user = Mockery::mock(Authenticatable::class);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        App::make(ResolveLocale::class)->handle($request, fn ($r): ResponseFactory|Response => response('ok'));

        expect(app()->getLocale())->toBe('en');
    });
});
