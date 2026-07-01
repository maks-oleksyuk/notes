<?php

declare(strict_types=1);

use App\Exports\ImporterTemplateExport;

covers(ImporterTemplateExport::class);

describe('Export | ImporterTemplate', function (): void {
    it('exposes the headings and rows it was built with', function (): void {
        $export = new ImporterTemplateExport(['name', 'email'], [['John Doe', 'john@example.com']]);

        expect($export->headings())->toBe(['name', 'email'])
            ->and($export->array())->toBe([['John Doe', 'john@example.com']]);
    });
});
