<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Imports\Filament\SpreadsheetImporter;
use Filament\Actions\Action;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
    /** @var class-string<SpreadsheetImporter> */
    private string $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (SpreadsheetImportAction $action): string => __('filament-actions::import.label', ['label' => $action->getPluralModelLabel()]));

        $this->icon(Heroicon::OutlinedDocumentArrowUp);

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
        $importer = $this->importer;

        return [
            Hidden::make('_headings'),

            Actions::make([
                Action::make('downloadCsvTemplate')
                    ->label('Download CSV example')
                    ->size(Size::ExtraSmall)
                    ->outlined()
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (): BinaryFileResponse => Excel::download(
                        $this->importer::makeTemplateExport(),
                        'import-template.csv',
                        ExcelFormat::CSV,
                    )),
                Action::make('downloadXlsxTemplate')
                    ->label('Download XLSX example')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->outlined()
                    ->size(Size::ExtraSmall)
                    ->color('gray')
                    ->action(fn (): BinaryFileResponse => Excel::download(
                        $this->importer::makeTemplateExport(),
                        'import-template.xlsx',
                        ExcelFormat::XLSX,
                    )),
            ])->fullWidth(),

            FileUpload::make('file')
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
                ->helperText($importer::getFileUploadHint())
                ->storeFiles()
                ->disk('local')
                ->directory('imports')
                ->required()
                ->live()
                ->extraAttributes([
                    'x-init' => '$el.closest(\'form\')?.addEventListener(\'form-processing-finished\', () => $wire.$refresh())',
                ])
                ->afterStateUpdated(function (mixed $state, Set $set) use ($importer): void {
                    $headings = $this->detectHeadings($state);
                    $set('_headings', $headings);

                    foreach ($importer::getColumns() as $column) {
                        $set('mapping.'.$column->getName(), $headings !== [] ? $this->guessHeading($column->getGuesses(), $headings) : null);
                    }
                }),

            Fieldset::make(__('filament-actions::import.modal.form.columns.label'))
                ->visible(fn (Get $get): bool => filled($get('file')) && filled($get('_headings')))
                ->columns(1)
                ->schema($this->mappingFields()),

            Fieldset::make('Options')
                ->statePath('options')
                ->visible(fn (Get $get): bool => filled($get('file')) && filled($get('_headings')) && $importer::getOptionsFormComponents() !== [])
                ->schema($importer::getOptionsFormComponents()),
        ];
    }

    /**
     * @return Select[]
     */
    private function mappingFields(): array
    {
        $fields = [];

        foreach ($this->importer::getColumns() as $column) {
            $fields[] = Select::make('mapping.'.$column->getName())
                ->inlineLabel()
                ->label($column->getLabel() ?? Str::headline($column->getName()))
                ->options(fn (Get $get): array => $this->headingOptions($get('_headings')))
                ->required($column->isMappingRequired())
                ->native(false)
                ->searchable();
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function runImport(array $data): void
    {
        $importer = $this->importer;

        $relativePath = $data['file'] ?? null;

        if (! is_string($relativePath) || $relativePath === '') {
            return;
        }

        $path = Storage::disk('local')->path($relativePath);

        /** @var array<string, string> $mapping */
        $mapping = is_array($data['mapping'] ?? null) ? array_filter($data['mapping']) : [];

        /** @var array<string, mixed> $options */
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];

        $import = new Import;
        $import->file_name = basename($relativePath);
        $import->file_path = $relativePath;
        $import->importer = $importer; // @phpstan-ignore assign.propertyType
        $import->setAttribute('user_id', Auth::id());
        $import->total_rows = 0;
        $import->save();

        $instance = new $importer($mapping, $options);
        Excel::import($instance, $path);

        Storage::disk('local')->delete($relativePath);

        $total = $instance->getImportedRows() + $instance->getSkippedRows() + $instance->getFailedRowsCount();

        $import->update([
            'completed_at' => now(),
            'processed_rows' => $total,
            'successful_rows' => $instance->getImportedRows(),
            'total_rows' => $total,
        ]);

        $failed = $instance->getFailedRowsCount();

        if ($failed > 0) {
            $this->persistFailedRows($instance, $import);
        }

        $authGuard = config('filament.auth.guard', 'web');
        $notification = Notification::make()
            ->title('Import completed')
            ->body($importer::getCompletedNotificationBody($instance))
            ->status($failed > 0 ? 'warning' : 'success');

        if ($failed > 0) {
            $notification->actions([
                Action::make('downloadFailedRows')
                    ->label('Download failed rows')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('danger')
                    ->url(URL::signedRoute(
                        'filament.imports.failed-rows.download',
                        ['authGuard' => $authGuard, 'import' => $import],
                        absolute: false,
                    ))
                    ->openUrlInNewTab(),
            ]);
        }

        $notification->send();
    }

    private function persistFailedRows(SpreadsheetImporter $instance, Import $import): void
    {
        $rows = array_map(
            fn (array $failed): array => [
                'import_id' => $import->getKey(),
                'data' => json_encode($failed['row']),
                'validation_error' => implode(' | ', $failed['errors']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $instance->getFailedRows(),
        );

        FailedImportRow::query()->insert($rows);
    }

    /**
     * Read the first row of the uploaded file to use as mapping options.
     *
     * @return array<int, string>
     */
    private function detectHeadings(mixed $state): array
    {
        $path = $this->resolveUploadPath($state);

        if ($path === null) {
            return [];
        }

        /** @var array<int, array<int, array<int, mixed>>> $sheets */
        $sheets = Excel::toArray(new HeadingRowImport, $path);

        $firstRow = $sheets[0][0] ?? [];

        return new Collection($firstRow)
            ->filter(fn (mixed $heading): bool => filled($heading) && is_scalar($heading))
            ->map(fn (bool|float|int|string $heading): string => (string) $heading)
            ->values()
            ->all();
    }

    private function resolveUploadPath(mixed $state): ?string
    {
        $file = is_array($state) ? reset($state) : $state;

        if ($file instanceof TemporaryUploadedFile) {
            return $file->getRealPath();
        }

        if (is_string($file) && $file !== '') {
            return Storage::disk('local')->path($file);
        }

        return null;
    }

    /**
     * @param  array<string>  $guesses
     * @param  array<int, string>  $headings
     */
    private function guessHeading(array $guesses, array $headings): ?string
    {
        foreach ($guesses as $guess) {
            foreach ($headings as $heading) {
                if (mb_strtolower(mb_trim($guess)) === mb_strtolower(mb_trim($heading))) {
                    return $heading;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headingOptions(mixed $headings): array
    {
        if (! is_array($headings)) {
            return [];
        }

        /** @var array<int, string> $headings */
        return array_combine($headings, $headings);
    }
}
