<?php

declare(strict_types=1);

use App\Imports\Filament\SpreadsheetImporter;
use App\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Collection;

covers(UserImporter::class, SpreadsheetImporter::class);

describe('Import | User', function (): void {
    it('rejects an email containing whitespace instead of importing it', function (): void {
        $importer = new UserImporter;

        $importer->model(['name' => 'John', 'email' => 'john1 @example.com']);

        expect($importer->failedRows)->toHaveCount(1)
            ->and($importer->importedRows)->toBe(0);

        $this->assertDatabaseMissing(User::class, ['email' => 'john1 @example.com']);
    });

    it('imports a valid row', function (): void {
        $importer = new UserImporter;

        $importer->model(['name' => 'Jane', 'email' => 'jane@example.com']);

        expect($importer->importedRows)->toBe(1)
            ->and($importer->failedRows)->toBeEmpty();

        $this->assertDatabaseHas(User::class, ['email' => 'jane@example.com', 'name' => 'Jane']);
    });

    it('trims surrounding whitespace before validating and saving', function (): void {
        $importer = new UserImporter;

        $importer->model(['name' => '  Spacey  ', 'email' => '  spacey@example.com  ']);

        expect($importer->importedRows)->toBe(1);

        $this->assertDatabaseHas(User::class, ['email' => 'spacey@example.com', 'name' => 'Spacey']);
    });

    it('skips existing users when skipExisting is enabled', function (): void {
        $user = User::factory()->create(['name' => 'Original']);

        $importer = new UserImporter(options: ['skipExisting' => true]);

        $importer->model(['name' => 'Replacement', 'email' => $user->email]);

        expect($importer->skippedRows)->toBe(1)
            ->and($importer->importedRows)->toBe(0)
            ->and($importer->failedRows)->toBeEmpty()
            ->and($user->refresh()->name)->toBe('Original');
    });

    it('updates existing users by default when no options are passed', function (): void {
        $user = User::factory()->create(['name' => 'Original']);

        $importer = new UserImporter;

        $importer->model(['name' => 'Replacement', 'email' => $user->email]);

        expect($importer->importedRows)->toBe(1)
            ->and($importer->skippedRows)->toBe(0)
            ->and($user->refresh()->name)->toBe('Replacement');
    });

    it('updates existing users when skipExisting is disabled', function (): void {
        $user = User::factory()->create(['name' => 'Original']);

        $importer = new UserImporter(options: ['skipExisting' => false]);

        $importer->model(['name' => 'Replacement', 'email' => $user->email]);

        expect($importer->importedRows)->toBe(1)
            ->and($importer->skippedRows)->toBe(0)
            ->and($user->refresh()->name)->toBe('Replacement');

        $this->assertDatabaseCount(User::class, 1);
    });

    it('remaps spreadsheet headings to column names through the mapping', function (): void {
        $importer = new UserImporter(mapping: ['name' => 'full_name', 'email' => 'mail']);

        $importer->model(['full_name' => 'Mapped', 'mail' => 'mapped@example.com', 'irrelevant' => 'noise']);

        expect($importer->importedRows)->toBe(1);

        $this->assertDatabaseHas(User::class, ['email' => 'mapped@example.com', 'name' => 'Mapped']);
    });

    it('records every validation error for a failed row alongside its data', function (): void {
        $importer = new UserImporter;

        $importer->model(['name' => null, 'email' => 'not-an-email']);

        expect($importer->failedRows)->toHaveCount(1)
            ->and($importer->failedRows[0]['row'])->toBe(['name' => null, 'email' => 'not-an-email'])
            ->and($importer->failedRows[0]['errors'])->toHaveCount(2);
    });

    it('defines correct validation rules for name column', function (): void {
        $column = new Collection(UserImporter::getColumns())->first(fn ($c): bool => $c->getName() === 'name');
        assert($column instanceof ImportColumn);

        expect($column->getDataValidationRules())->toBe(['required', 'max:255']);
    });

    it('defines correct validation rules for email column', function (): void {
        $column = new Collection(UserImporter::getColumns())->first(fn ($c): bool => $c->getName() === 'email');
        assert($column instanceof ImportColumn);

        expect($column->getDataValidationRules())->toBe(['required', 'email:strict']);
    });

    it('provides guess hints for name column mapping', function (): void {
        $column = new Collection(UserImporter::getColumns())->first(fn ($c): bool => $c->getName() === 'name');
        assert($column instanceof ImportColumn);

        expect($column->getGuesses())->toContain('full_name')->toContain('fullname');
    });

    it('provides guess hints for email column mapping', function (): void {
        $column = new Collection(UserImporter::getColumns())->first(fn ($c): bool => $c->getName() === 'email');
        assert($column instanceof ImportColumn);

        expect($column)
            ->and($column->getGuesses())->toContain('e-mail')
            ->toContain('mail')
            ->toContain('email address');
    });

    it('returns a non-empty modal description', function (): void {
        expect(UserImporter::getModalDescription())->not->toBeEmpty();
    });

    it('returns a file upload hint listing supported formats', function (): void {
        expect(UserImporter::getFileUploadHint())->toBe('Supported: CSV, XLS, XLSX');
    });

    it('returns options form components with a skip existing toggle', function (): void {
        $components = UserImporter::getOptionsFormComponents();

        expect($components)->toHaveCount(1)
            ->and($components[0]->getName())->toBe('skipExisting')
            ->and($components[0]->getDefaultState())->toBeFalse();
    });
});
