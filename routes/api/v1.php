<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware('throttle:login')
    ->withoutMiddleware('auth:sanctum');

Route::apiResource('users', UserController::class)->names('users');
