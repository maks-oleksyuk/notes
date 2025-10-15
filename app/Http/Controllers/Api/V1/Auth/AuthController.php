<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Api\V1\Auth\LoginAction;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\TokenResource;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Auth')]
final class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
    ) {}

    /**
     * @throws \Throwable
     */
    #[Endpoint('Login', 'Login to the application', false)]
    #[ResponseFromApiResource(TokenResource::class)]
    public function login(LoginRequest $request): TokenResource
    {
        $tokenData = ($this->loginAction)(
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        return new TokenResource($tokenData);
    }
}
