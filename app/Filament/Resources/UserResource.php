<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

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
    public static function form(Form $form): Form
    {
        return $form
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
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('email')->sortable(),
                TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                Filter::make('name')
                    ->form([
                        TextInput::make('name')
                            ->placeholder('Enter name to filter')
                            ->label('Name'),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => is_string($data['name'] ?? null)
                            ? $query->whereLike('name', sprintf('%%%s%%', $data['name']))
                            : $query
                    ),
                Filter::make('email')
                    ->form([
                        TextInput::make('email')
                            ->placeholder('Enter email to filter')
                            ->label('Email'),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => is_string($data['email'] ?? null)
                            ? $query->whereLike('email', sprintf('%%%s%%', $data['email']))
                            : $query
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters()
            ->actions([
                ViewAction::make()->label(''),
                DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
