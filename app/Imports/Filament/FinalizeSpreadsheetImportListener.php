<?php

declare(strict_types=1);

namespace App\Imports\Filament;

use Filament\Actions\Action;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Number;
use Illuminate\Translation\Translator;

final readonly class FinalizeSpreadsheetImportListener
{
    public function __construct(
        private Translator $translator,
        private UrlGenerator $urlGenerator,
        private FilesystemManager $filesystem,
    ) {}

    public function handle(SpreadsheetImporter $importer): void
    {
        if (! ($import = $this->finalizeImport($importer)) instanceof Import) {
            return;
        }

        $failedCount = $import->failedRows()->count();

        $notification = Notification::make()
            ->title(__('filament-actions::import.notifications.completed.title'))
            ->body(SpreadsheetImporter::getCompletedNotificationBody($import, $failedCount))
            ->status($this->notificationStatus($failedCount, $import->total_rows));

        if ($failedCount > 0) {
            $notification->actions([$this->downloadFailedRowsAction($importer, $import, $failedCount)]);
        }

        $notification->sendToDatabase($import->user);
    }

    /**
     * Finalize an import whose queue chain died (chunk job exhausted retries).
     * Without this the Import row stays open forever, the uploaded file is
     * never deleted, and the user never hears back.
     */
    public function handleFailure(SpreadsheetImporter $importer): void
    {
        if (! ($import = $this->finalizeImport($importer)) instanceof Import) {
            return;
        }

        Notification::make()
            ->title(__('filament/import.errors.failed.title'))
            ->body(__('filament/import.errors.failed.body', [
                'count' => Number::format($import->processed_rows),
            ]))
            ->status('danger')
            ->sendToDatabase($import->user);
    }

    /**
     * Persist completion state and remove the uploaded file.
     * Returns null for stateless importers or already-finalized imports (idempotency guard).
     */
    private function finalizeImport(SpreadsheetImporter $importer): ?Import
    {
        if ($importer->importId === null) {
            return null;
        }

        $import = Import::with('user')->find($importer->importId);

        if ($import === null || $import->completed_at !== null) {
            return null;
        }

        $import->forceFill([
            'completed_at' => now(),
            'total_rows' => $import->processed_rows,
        ])->save();

        if (filled($import->file_path)) {
            $this->filesystem->disk('local')->delete($import->file_path);
        }

        return $import;
    }

    private function downloadFailedRowsAction(SpreadsheetImporter $importer, Import $import, int $failedCount): Action
    {
        return Action::make('downloadFailedRows')
            ->label($this->translator->choice('filament-actions::import.notifications.completed.actions.download_failed_rows_csv.label', $failedCount))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('danger')
            ->url(
                $this->urlGenerator->signedRoute(
                    'filament.imports.failed-rows.download',
                    ['authGuard' => $importer->authGuard, 'import' => $import->getKey()],
                    absolute: false,
                ),
                shouldOpenInNewTab: true,
            )
            ->markAsRead();
    }

    private function notificationStatus(int $failedCount, int $totalRows): string
    {
        return match (true) {
            $failedCount === 0 => 'success',
            $failedCount >= $totalRows => 'danger',
            default => 'warning',
        };
    }
}
