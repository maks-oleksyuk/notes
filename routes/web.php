<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): View => view('welcome'));

Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [GoogleController::class, 'callback'])
    ->name('auth.google.callback')
    ->middleware('throttle:google');
