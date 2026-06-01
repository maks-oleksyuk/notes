<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Filament\SpreadsheetImporter;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;

final class UserImporter extends SpreadsheetImporter
{
    /**
     * @return ImportColumn[]
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('John Doe')
                ->guess(['full name', 'full_name', 'fullname']),

            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255'])
                ->example('john@example.com')
                ->guess(['e-mail', 'mail', 'email address']),
        ];
    }

    /**
     * @return Toggle[]
     */
    #[\Override]
    public static function getOptionsFormComponents(): array
    {
        return [
            Toggle::make('skipExisting')
                ->label('Skip existing users')
                ->columnSpanFull()
                ->helperText('When on, rows whose email already exists are skipped. When off, those users are updated.')
                ->default(false),
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     */
    public function resolveRecord(array $row): ?Model
    {
        $user = User::query()->firstOrNew(['email' => $row['email']]);

        if ($user->exists && $this->option('skipExisting', false) === true) {
            return null;
        }

        $user->name = (string) $row['name'];

        return $user;
    }

    public static function getModalDescription(): string
    {
        return 'Upload a CSV or XLSX file, download a template below, then map your columns. Existing users are matched by email.';
    }

    public static function getFileUploadHint(): string
    {
        return 'Accepted formats: CSV, XLS, XLSX.';
    }
}
