<?php

declare(strict_types=1);

return [
    'site_key' => env('RECAPTCHA_SITE_KEY'),

    'secret_key' => env('RECAPTCHA_SECRET_KEY'),

    'enabled' => (bool) env('RECAPTCHA_ENABLED', ! in_array(env('APP_ENV'), ['local', 'testing'])),

    'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
];
