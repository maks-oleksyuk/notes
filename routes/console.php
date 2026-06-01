<?php

declare(strict_types=1);

use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Queue\Console\PruneFailedJobsCommand;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\Console\Commands\PruneExpired;

// Cleanup:
Schedule::command(PruneExpired::class)->daily();
Schedule::command(PruneCommand::class, [
    '--model' => [Import::class, Export::class, FailedImportRow::class],
])->daily();
Schedule::command(PruneFailedJobsCommand::class, ['--hours' => 168])->weekly();
