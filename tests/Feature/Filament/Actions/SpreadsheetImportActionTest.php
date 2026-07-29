<?php

declare(strict_types=1);

use App\Exports\ImporterTemplateExport;
use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Resources\User\Pages\ListUsers;
use App\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Livewire\livewire;

covers(SpreadsheetImportAction::class);

describe('Filament | Action | SpreadsheetImport', function (): void {
    beforeEach(function (): void {
        $this->actingAs(User::factory()->create());
    });

    it('sends a warning notification when the uploaded file contains duplicate headings', function (): void {
        $file = UploadedFile::fake()->createWithContent('dupes.csv', "email,email,name\nfoo@bar.com,baz@bar.com,Foo");

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm(['file' => [$file]])
            ->assertNotified(
                Notification::make()
                    ->title(__('filament/import.errors.duplicate_headings.title'))
                    ->body(__('filament/import.errors.duplicate_headings.body', ['headings' => 'email']))
                    ->warning(),
            );
    });

    it('does not send a notification when the uploaded file has no duplicate headings', function (): void {
        $file = UploadedFile::fake()->createWithContent('clean.csv', "email,name\nfoo@bar.com,Foo");

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm(['file' => [$file]])
            ->assertNotNotified(
                Notification::make()
                    ->title(__('filament/import.errors.duplicate_headings.title'))
                    ->warning(),
            );
    });

    it('discards an unreadable spreadsheet and notifies the user', function (): void {
        $file = UploadedFile::fake()->createWithContent('broken.xlsx', 'definitely-not-a-real-xlsx');

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm(['file' => [$file]])
            ->assertNotified(
                Notification::make()
                    ->title(__('filament/import.errors.invalid_file.title'))
                    ->body(__('filament/import.errors.invalid_file.body'))
                    ->danger(),
            );
    });

    it('deletes a stored file that becomes unreadable when re-mapped to the field', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('imports/broken.xlsx', 'definitely-not-a-real-xlsx');

        livewire(ListUsers::class)
            ->mountAction('import')
            ->set('mountedActions.0.data.file', 'imports/broken.xlsx')
            ->assertNotified(
                Notification::make()
                    ->title(__('filament/import.errors.invalid_file.title'))
                    ->body(__('filament/import.errors.invalid_file.body'))
                    ->danger(),
            )
            ->assertSet('mountedActions.0.data.file', [])
            ->assertSet('mountedActions.0.data._headings', [])
            ->assertJs(
                <<<'JS'
                document.querySelectorAll('[x-data^="fileUploadFormComponent"]').forEach((component) => {
                    window.Alpine.$data(component).pond?.removeFiles({ revert: false });
                });
                JS,
            );

        expect(Storage::disk('local')->exists('imports/broken.xlsx'))->toBeFalse();
    });

    it('downloads a template matching the importer columns', function (string $action, string $filename): void {
        Excel::fake();

        livewire(ListUsers::class)
            ->callAction([
                TestAction::make('import'),
                TestAction::make($action)->schemaComponent('downloadTemplates'),
            ]);

        Excel::assertDownloaded(
            $filename,
            fn (ImporterTemplateExport $export): bool => $export->headings() === ['name', 'email']
                && $export->array() === [['John Doe', 'john@example.com']],
        );
    })->with([
        'CSV' => ['downloadCsvTemplate', 'users-import-template.csv'],
        'XLSX' => ['downloadXlsxTemplate', 'users-import-template.xlsx'],
    ]);

    it('auto-maps columns by guessing them from the heading row', function (): void {
        // Headings deliberately differ from the column names, so only the guess
        // logic can map them (name guesses "full_name", email guesses "mail").
        $file = UploadedFile::fake()->createWithContent('users.csv', "full_name,mail\nJane,jane@example.com");

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm(['file' => [$file]])
            ->assertSet('mountedActions.0.data.mapping.name', 'full_name')
            ->assertSet('mountedActions.0.data.mapping.email', 'mail');
    });

    it('imports rows from a mapped spreadsheet and records the import', function (): void {
        Storage::fake('local');

        $userId = auth()->id();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nJane Doe,jane@example.com");

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm([
                'file' => [$file],
                'mapping' => ['name' => 'name', 'email' => 'email'],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified(
                Notification::make()
                    ->title(__('filament-actions::import.notifications.started.title'))
                    ->success(),
            );

        $this->assertDatabaseHas(User::class, ['email' => 'jane@example.com']);

        $import = Import::query()->sole();

        expect($import->importer)->toBe(UserImporter::class)
            ->and($import->getAttribute('user_id'))->toBe($userId)
            ->and($import->file_name)->not->toBeEmpty();
    });

    it('cleans up the import row and notifies when importing throws', function (): void {
        Storage::fake('local');
        Exceptions::fake();

        // Delegate to the real instance, so heading detection (Excel::toArray) keeps
        // working, but make the import itself throw to hit the cleanup path.
        $excel = Mockery::mock(App::make('excel'))->makePartial();
        $excel->shouldReceive('import')->once()->andThrow(new RuntimeException('boom'));
        app()->instance('excel', $excel);

        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nJane Doe,jane@example.com");

        livewire(ListUsers::class)
            ->mountAction('import')
            ->fillForm([
                'file' => [$file],
                'mapping' => ['name' => 'name', 'email' => 'email'],
            ])
            ->callMountedAction()
            ->assertNotified(
                Notification::make()
                    ->title(__('filament/import.errors.failed.title'))
                    ->body(__('filament/import.errors.invalid_file.body'))
                    ->danger(),
            )
            // The action halts on failure, so the "started" success notification never fires.
            ->assertNotNotified(
                Notification::make()
                    ->title(__('filament-actions::import.notifications.started.title'))
                    ->success(),
            )
            ->assertActionHalted('import');

        // The orphaned import row and its uploaded file are both cleaned up.
        $this->assertDatabaseCount(Import::class, 0);
        expect(Storage::disk('local')->allFiles('imports'))->toBeEmpty();

        // The failure is reported so the orphaned import is traceable.
        Exceptions::assertReported(fn (RuntimeException $throwable): bool => $throwable->getMessage() === 'boom');
    });
});
