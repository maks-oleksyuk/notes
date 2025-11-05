<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final readonly class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('email')->sortable(),
                TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                Filter::make('name')
                    ->schema([
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
                    ->schema([
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
            ->recordActions([
                ViewAction::make()->label(''),
                DeleteAction::make()->label(''),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
