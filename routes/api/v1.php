<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class)->names('users');
