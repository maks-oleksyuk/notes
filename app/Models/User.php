<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\DynamicScopes\FilterBetweenDatesScope;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @mixin IdeHelperUser
 */
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements FilamentUser
{
    /** @use FilterBetweenDatesScope<User> */
    use FilterBetweenDatesScope;

    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    #[\Override]
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
    ];

    #[\Override]
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'google_id' => 'string',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
