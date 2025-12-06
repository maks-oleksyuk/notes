<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Repositories\Contracts\Models\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserRepository::class, fn (): UserRepository => new UserRepository(new User));
    }
}
