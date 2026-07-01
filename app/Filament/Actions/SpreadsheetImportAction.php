<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Imports\Filament\SpreadsheetImporter;
use Filament\Actions\Action;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Filament action that drives a maatwebsite/excel importer.
 *
 * Mirrors the built-in ImportAction DX (`->importer(MyImporter::class)`) while
 * supporting XLSX, column mapping, and downloadable templates. The importer
 * class is the single source of truth and stays reusable outside Filament.
 */
final class SpreadsheetImportAction extends Action
{
    /**
     * Client-side visibility for the mapping and options fieldsets: reacts
     * instantly when the file is removed in FilePond, without the server
     * round trip that `->visible()` would need.
     */
    private const string FILE_IS_READABLE_JS = <<<'JS'
        Object.keys($get('file') ?? {}).length > 0 && Object.keys($get('_headings') ?? {}).length > 0
        JS;

    /** @var class-string<SpreadsheetImporter> */
    private string $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon(Heroicon::OutlinedDocumentArrowUp);

        $this->label(fn (SpreadsheetImportAction $action): string => __('filament-actions::import.label', ['label' => $action->getPluralModelLabel()]));

        $this->modalHeading(fn (SpreadsheetImportAction $action): string => __('filament-actions::import.modal.heading', ['label' => $action->getTitleCasePluralModelLabel()]));

        $this->modalDescription(fn (): ?string => $this->importer::getModalDescription());

        $this->modalSubmitActionLabel(__('filament-actions::import.modal.actions.import.label'));

        $this->groupedIcon(FilamentIcon::resolve(ActionsIconAlias::IMPORT_ACTION_GROUPED) ?? Heroicon::ArrowUpTray);

        $this->modalWidth(Width::ExtraLarge);

        $this->schema(fn (): array => $this->buildSchema());

        $this->modalFooterActionsAlignment(Alignment::End);

        $this->action(function (array $data): void {
            /** @var array<string, mixed> $data */
            $this->runImport($data);
        });

        $this->successNotificationTitle(__('filament-actions::import.notifications.started.title'));
    }

    public static function getDefaultName(): string
    {
        return 'import';
    }

    /**
     * @param  class-string<SpreadsheetImporter>  $importer
     */
    public function importer(string $importer): self
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    private function buildSchema(): array
    {
        return [
            Hidden::make('_headings'),

            Actions::make([
                Action::make('downloadCsvTemplate')
                    ->label(__('filament/import.templates.download', ['format' => 'CSV']))
                    ->color(Color::Gray)
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->size(Size::ExtraSmall)
                    ->outlined()
                    ->action(fn (): BinaryFileResponse => Excel::download(
                        $this->importer::makeTemplateExport(),
                        Str::slug($this->getPluralModelLabel() ?? 'models').'-import-template.csv',
                        ExcelFormat::CSV,
                    )),
                Action::make('downloadXlsxTemplate')
                    ->label(__('filament/import.templates.download', ['format' => 'XLSX']))
                    ->color(Color::Gray)
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->size(Size::ExtraSmall)
                    ->outlined()
                    ->action(fn (): BinaryFileResponse => Excel::download(
                        $this->importer::makeTemplateExport(),
                        Str::slug($this->getPluralModelLabel() ?? 'models').'-import-template.xlsx',
                        ExcelFormat::XLSX,
                    )),
            ])->key('downloadTemplates')->fullWidth(),

            FileUpload::make('file')
                ->hiddenLabel()
                ->acceptedFileTypes([
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
                ->maxSize(5120) // 5 Mb
                ->placeholder($this->importer::getFileUploadHint())
                ->storeFiles()
                ->disk('local')
                ->directory('imports')
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, Component $livewire): void {
                    try {
                        $headings = $this->detectHeadings($state);
                    } catch (\Throwable) {
                        $relativePath = is_array($state) ? reset($state) : $state;
                        // Only a stored file leaves a relative string path to delete; an unreadable
                        // temporary upload is a TemporaryUploadedFile the disk never persisted.
                        if (is_string($relativePath)) {
                            Storage::disk('local')->delete($relativePath);
                        }

                        $set('file', []);
                        $set('_headings', []);
                        // FilePond ignores the cleared state while an upload is in flight,
                        // so remove the file on the live instance instead.
                        // Its `removefile` event also re-enables the Submit button.
                        $resetFilePondScript = <<<'JS'
                            document.querySelectorAll('[x-data^="fileUploadFormComponent"]').forEach((component) => {
                                window.Alpine.$data(component).pond?.removeFiles({ revert: false });
                            });
                            JS;
                        $livewire->js($resetFilePondScript);
                        Notification::make()
                            ->title(__('filament/import.errors.invalid_file.title'))
                            ->body(__('filament/import.errors.invalid_file.body'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $set('_headings', $headings);

                    $duplicates = $headings
                        |> array_count_values(...)
                        |> (fn (array $x): array => array_filter($x, fn (int $count): bool => $count > 1))
                        |> array_keys(...);

                    if ($duplicates !== []) {
                        Notification::make()
                            ->title(__('filament/import.errors.duplicate_headings.title'))
                            ->body(__('filament/import.errors.duplicate_headings.body', ['headings' => implode(', ', $duplicates)]))
                            ->warning()
                            ->send();
                    }

                    foreach ($this->importer::getColumns() as $column) {
                        $set('mapping.'.$column->getName(), $headings !== [] ? $this->guessHeading($column->getGuesses(), $headings) : null);
                    }
                }),

            Fieldset::make(__('filament-actions::import.modal.form.columns.label'))
                ->columns(1)
                ->schema($this->mappingFields())
                ->visibleJs(self::FILE_IS_READABLE_JS),

            Fieldset::make(__('filament/import.options.heading'))
                ->statePath('options')
                ->visible(fn (): bool => $this->importer::getOptionsFormComponents() !== [])
                ->visibleJs(self::FILE_IS_READABLE_JS)
                ->schema($this->importer::getOptionsFormComponents()),
        ];
    }

    /**
     * @return Select[]
     */
    private function mappingFields(): array
    {
        return array_map(
            fn (ImportColumn $column): Select => Select::make('mapping.'.$column->getName())
                ->inlineLabel()
                ->label($column->getLabel() ?? Str::headline($column->getName()))
                ->options(fn (Get $get): array => $this->headingOptions($get('_headings')))
                ->required($column->isMappingRequired())
                ->native(false)
                ->searchable(),
            $this->importer::getColumns(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function runImport(array $data): void
    {
        $relativePath = is_array($data['file'] ?? null) ? reset($data['file']) : ($data['file'] ?? null);

        if (! is_string($relativePath) || $relativePath === '') {
            return;
        }

        $path = Storage::disk('local')->path($relativePath);

        /** @var array<string, string> $mapping */
        $mapping = is_array($data['mapping'] ?? null) ? array_filter($data['mapping']) : [];

        /** @var array<string, mixed> $options */
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];

        $import = new Import;
        $import->forceFill([
            'file_name' => basename($relativePath),
            'file_path' => $relativePath,
            'importer' => $this->importer,
            'user_id' => Auth::id(),
            'total_rows' => 0,
        ])->save();

        try {
            Excel::import(new $this->importer($mapping, $options, $import->id, Filament::getAuthGuard()), $path);
            // Finalization (completed_at, file cleanup, notification) handled by
            // FinalizeSpreadsheetImportListener via AfterImport event.
            // With ShouldQueue on the concrete importer, this returns immediately.
        } catch (\Throwable $throwable) {
            // Thrown before the job chain exists (e.g., unreadable file), so
            // ImportFailed never fires — clean up here or the Import row and
            // the uploaded file are orphaned.
            report($throwable);
            $import->delete();
            Storage::disk('local')->delete($relativePath);

            Notification::make()
                ->title(__('filament/import.errors.failed.title'))
                ->body(__('filament/import.errors.invalid_file.body'))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * Read the first row of the uploaded file to use as mapping options.
     *
     * @return string[]
     */
    private function detectHeadings(mixed $state): array
    {
        $path = $this->resolveUploadPath($state);

        if ($path === null) {
            return [];
        }

        /** @var array<int, array<int, array<int, mixed>>> $result */
        $result = Excel::toArray(new HeadingRowImport, $path);
        /** @var array<int, mixed> $rows */
        $rows = $result[0][0] ?? [];

        return new Collection($rows)
            ->filter(fn (mixed $h): bool => filled($h) && is_scalar($h))
            ->map(fn (bool|float|int|string $h): string => (string) $h)
            ->values()
            ->all();
    }

    private function resolveUploadPath(mixed $state): ?string
    {
        $file = is_array($state) ? reset($state) : $state;

        return match (true) {
            $file instanceof TemporaryUploadedFile => $file->getRealPath(),
            is_string($file) && $file !== '' => Storage::disk('local')->path($file),
            default => null,
        };
    }

    /**
     * @param  string[]  $guesses
     * @param  string[]  $headings
     */
    private function guessHeading(array $guesses, array $headings): ?string
    {
        return new Collection($guesses)
            ->map(fn (string $g): string => Str::slug($g, '_'))
            ->intersect($headings)
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function headingOptions(mixed $headings): array
    {
        if (! is_array($headings)) {
            return [];
        }

        $headings = new Collection($headings)
            ->filter(fn (mixed $heading): bool => is_scalar($heading))
            ->map(fn (bool|float|int|string $heading): string => (string) $heading)
            ->values()
            ->all();

        $counts = array_count_values($headings);

        return array_combine(
            $headings,
            array_map(
                fn (string $h): string => $counts[$h] > 1 ? '⚠ '.$h : $h,
                $headings,
            ),
        );
    }
}
