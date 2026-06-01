<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final readonly class ReCaptchaService
{
    public function __construct(
        private Request $request,
        private Repository $config,
    ) {}

    public function verify(string $token, string $action): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout($this->config->integer('recaptcha.connect_timeout'))
                ->timeout($this->config->integer('recaptcha.timeout'))
                ->baseUrl('https://www.google.com/recaptcha/api/')
                ->post('siteverify', [
                    'secret' => $this->config->string('recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $this->request->ip(),
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if ($response->failed()) {
            return false;
        }

        /** @var array{success?: bool, score?: float, action?: string} $data */
        $data = $response->json() ?? [];

        $minScore = $this->config->float('recaptcha.min_score');

        return ($data['success'] ?? false)
            && ($data['score'] ?? 0.0) >= $minScore
            && ($data['action'] ?? '') === $action;
    }
}
