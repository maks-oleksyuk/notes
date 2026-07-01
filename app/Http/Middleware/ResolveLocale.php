<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final readonly class ResolveLocale
{
    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @param  \Closure(Request): (HttpFoundationResponse)  $next
     */
    public function handle(Request $request, \Closure $next): HttpFoundationResponse
    {
        /** @var non-empty-list<string> $availableLocales */
        $availableLocales = $this->config->array('app.available_locales');

        $queryLocale = $request->query('lang');
        $userLocale = $request->user() instanceof HasLocalePreference
            ? $request->user()->preferredLocale()
            : null;

        $locale = match (true) {
            is_string($queryLocale) && in_array($queryLocale, $availableLocales, true) => $queryLocale,
            is_string($userLocale) && in_array($userLocale, $availableLocales, true) => $userLocale,
            default => $request->getPreferredLanguage($availableLocales),
        };

        /** @var non-empty-string $locale */
        if ($locale !== app()->getLocale()) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
