<?php

declare(strict_types=1);

use App\Filament\Actions\SpreadsheetImportAction;
use App\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Fixtures\Imports\StubImporter;

covers(SpreadsheetImportAction::class);

describe('Filament | Action | SpreadsheetImport', function (): void {
    describe('headingOptions()', function (): void {
        it('returns empty array for non-array input', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            expect($method->invoke($action, null))->toBeEmpty()
                ->and($method->invoke($action, 'string'))->toBeEmpty()
                ->and($method->invoke($action, 42))->toBeEmpty();
        });

        it('returns value-label pairs with no prefix when headings are unique', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            expect($method->invoke($action, ['name', 'email', 'phone']))->toBe([
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
            ]);
        });

        it('prefixes duplicate heading labels with warning symbol while keeping value unchanged', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            $result = $method->invoke($action, ['name', 'email', 'email']);

            expect($result)->toBe(['name' => 'name', 'email' => '⚠ email']);
        });

        it('marks all duplicated headings with a warning symbol', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            $result = $method->invoke($action, ['email', 'email', 'name', 'name', 'phone']);

            expect($result)->toBe([
                'email' => '⚠ email',
                'name' => '⚠ name',
                'phone' => 'phone',
            ]);
        });

        it('filters out non-scalar values', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            $result = $method->invoke($action, ['name', null, [], 'email']);

            expect($result)->toBe(['name' => 'name', 'email' => 'email']);
        });

        it('casts non-string scalar headings to strings', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'headingOptions');

            expect($method->invoke($action, [1.5, 'name']))->toBe(['1.5' => '1.5', 'name' => 'name']);
        });
    });

    describe('guessHeading()', function (): void {
        it('returns the first guess that matches a heading after slugification', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'guessHeading');

            expect($method->invoke($action, ['full name', 'full_name'], ['name', 'full_name', 'email']))->toBe('full_name');
        });

        it('returns null when no guess matches any heading', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'guessHeading');

            expect($method->invoke($action, ['full name'], ['email', 'phone']))->toBeNull();
        });
    });

    describe('detectHeadings()', function (): void {
        it('returns an empty array when the upload path cannot be resolved', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'detectHeadings');

            expect($method->invoke($action, ''))->toBeEmpty()
                ->and($method->invoke($action, null))->toBeEmpty();
        });

        it('reads, casts to string, and drops blank cells from a spreadsheet heading row', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('imports/headings.xlsx', 'x');

            // A genuine int heading (42) and a blank cell exercise both the (string) cast and the scalar filter.
            Excel::shouldReceive('toArray')->once()->andReturn([[['name', 42, '   ']]]);

            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'detectHeadings');

            expect($method->invoke($action, 'imports/headings.xlsx'))->toBe(['name', '42']);
        });
    });

    describe('runImport()', function (): void {
        it('returns early without creating an import when the file path is empty', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'runImport');

            $method->invoke($action, ['file' => '']);

            $this->assertDatabaseCount(Import::class, 0);
        });

        it('returns early without creating an import when the file path is not a string', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'runImport');

            $method->invoke($action, ['file' => []]);

            $this->assertDatabaseCount(Import::class, 0);
        });

        it('records the import with zero total rows until finalization updates it', function (): void {
            Excel::fake();
            Storage::fake('local');
            Storage::disk('local')->put('imports/users.csv', "name,email\nJane,jane@example.com");
            $this->actingAs(User::factory()->create());

            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);
            $method = new ReflectionMethod($action, 'runImport');

            // A bare string path (not the array FileUpload state) also exercises the non-array branch.
            $method->invoke($action, [
                'file' => 'imports/users.csv',
                'mapping' => ['name' => 'name', 'email' => 'email'],
            ]);

            // Excel::fake() skips the queued job, so the AfterImport finalizer never runs.
            expect(Import::query()->sole()->total_rows)->toBe(0);
        });

        it('hands the importer a filtered mapping and the options from the array file state', function (): void {
            Excel::fake();
            Storage::fake('local');
            Storage::disk('local')->put('imports/users.csv', "name,email\nJane,jane@example.com");
            $this->actingAs(User::factory()->create());
            StubImporter::reset();

            $action = SpreadsheetImportAction::make()->importer(StubImporter::class);
            $method = new ReflectionMethod($action, 'runImport');

            // The array FileUpload state exercises the reset() branch; the blank mapping
            // value must be dropped, and the options array passed through verbatim.
            $method->invoke($action, [
                'file' => ['imports/users.csv'],
                'mapping' => ['name' => 'name', 'email' => '', 'phone' => 'phone'],
                'options' => ['skipExisting' => true],
            ]);

            expect(StubImporter::$capturedMapping)->toBe(['name' => 'name', 'phone' => 'phone'])
                ->and(StubImporter::$capturedOptions)->toBe(['skipExisting' => true]);
        });
    });

    it('has a default name of import', function (): void {
        expect(SpreadsheetImportAction::getDefaultName())->toBe('import');
    });

    describe('configuration', function (): void {
        it('configures the modal from the importer', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);

            expect($action->getIcon())->toBe(Heroicon::OutlinedDocumentArrowUp)
                ->and($action->getModalDescription())->toBe(UserImporter::getModalDescription())
                ->and($action->getModalWidth())->toBe(Width::ExtraLarge)
                ->and($action->getModalSubmitActionLabel())->toBe(__('filament-actions::import.modal.actions.import.label'))
                ->and($action->getModalFooterActionsAlignment())->toBe(Alignment::End)
                ->and($action->getGroupedIcon())->toBe(Heroicon::ArrowUpTray)
                ->and($action->getLabel())->toBe(__('filament-actions::import.label', ['label' => $action->getPluralModelLabel()]))
                ->and($action->getModalHeading())->toBe(__('filament-actions::import.modal.heading', ['label' => $action->getTitleCasePluralModelLabel()]));
        });

        it('uses a registered icon alias for the grouped icon when one is set', function (): void {
            FilamentIcon::register([ActionsIconAlias::IMPORT_ACTION_GROUPED => Heroicon::Star]);

            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);

            expect($action->getGroupedIcon())->toBe(Heroicon::Star);
        });

        it('keeps the model label in modal heading even when a custom button label is set', function (): void {
            $action = SpreadsheetImportAction::make()
                ->importer(UserImporter::class)
                ->pluralModelLabel('users')
                ->label('Import');

            expect($action->getModalHeading())->toBe('Import Users')
                ->and($action->getLabel())->toBe('Import');
        });
    });

    describe('buildSchema()', function (): void {
        it('assembles the import modal schema', function (): void {
            $action = SpreadsheetImportAction::make()->importer(UserImporter::class);

            /** @var list<Component> $schema */
            $schema = new ReflectionMethod($action, 'buildSchema')->invoke($action);
            assert($schema[2] instanceof FileUpload);

            expect($schema)->toHaveCount(5)
                ->and($schema[0])->toBeInstanceOf(Hidden::class)
                ->and($schema[1])->toBeInstanceOf(Actions::class)
                ->and($schema[2]->getMaxSize())->toBe(5120)
                ->and($schema[2]->getAcceptedFileTypes())->toBe([
                    'text/csv',
                    'text/x-csv',
                    'application/csv',
                    'application/x-csv',
                    'text/comma-separated-values',
                    'text/x-comma-separated-values',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->and($schema[3])->toBeInstanceOf(Fieldset::class)
                ->and($schema[3]->getColumns('lg'))->toBe(1)
                ->and($schema[4])->toBeInstanceOf(Fieldset::class);

            /** @var list<Action> $downloads */
            $downloads = $schema[1]->getDefaultChildComponents();
            expect($downloads)->toHaveCount(2)
                ->and($downloads[0]->getName())->toBe('downloadCsvTemplate')
                ->and($downloads[0]->getLabel())->toBe(__('filament/import.templates.download', ['format' => 'CSV']))
                ->and($downloads[1]->getName())->toBe('downloadXlsxTemplate')
                ->and($downloads[1]->getLabel())->toBe(__('filament/import.templates.download', ['format' => 'XLSX']));

            /** @var list<Component> $mappingFields */
            $mappingFields = $schema[3]->getDefaultChildComponents();
            assert($mappingFields[0] instanceof Select);

            expect($mappingFields)->toHaveCount(2)
                ->and($mappingFields[0]->isNative())->toBeFalse()
                ->and($mappingFields[1])->toBeInstanceOf(Select::class);
        });

        it('toggles the options fieldset visibility by whether the importer exposes option components', function (bool $hasOptions, bool $visible): void {
            StubImporter::reset();
            StubImporter::$optionComponents = $hasOptions ? [Hidden::make('skipExisting')] : [];

            $action = SpreadsheetImportAction::make()->importer(StubImporter::class);

            /** @var list<Component> $schema */
            $schema = new ReflectionMethod($action, 'buildSchema')->invoke($action);

            expect($schema[4]->isVisible())->toBe($visible);
        })->with([
            'hidden when none' => [false, false],
            'visible when present' => [true, true],
        ]);

        it('labels mapping fields from the column label, humanizing the name when none is set', function (): void {
            StubImporter::reset();
            StubImporter::$columns = [
                ImportColumn::make('email_address')->label('Custom Email'),
                ImportColumn::make('first_name'),
            ];

            $action = SpreadsheetImportAction::make()->importer(StubImporter::class);

            /** @var list<Component> $schema */
            $schema = new ReflectionMethod($action, 'buildSchema')->invoke($action);

            /** @var list<Select> $mappingFields */
            $mappingFields = $schema[3]->getDefaultChildComponents();

            // Explicit label is kept; a column without one falls back to the humanized name.
            expect($mappingFields[0]->getLabel())->toBe('Custom Email')
                ->and($mappingFields[1]->getLabel())->toBe('First Name');
        });
    });
});
