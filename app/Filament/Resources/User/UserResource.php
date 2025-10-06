<?php

declare(strict_types=1);

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\Pages\ListUsers;
use App\Filament\Resources\User\Pages\ViewUser;
use App\Filament\Resources\User\Tables\UsersTable;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-users';

    #[\Override]
    public static function canCreate(): bool
    {
        return true;
    }

    #[\Override]
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->required(),
                DateTimePicker::make('created_at'),
                DateTimePicker::make('email_verified_at'),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
