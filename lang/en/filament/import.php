<?php

declare(strict_types=1);

return [
    'templates' => [
        'download' => 'Download :format example',
    ],

    'options' => [
        'heading' => 'Options',
    ],

    'errors' => [
        'invalid_file' => [
            'title' => 'Invalid file',
            'body' => 'The uploaded file could not be read. Please check the file and try again.',
        ],
        'failed' => [
            'title' => 'Import failed',
            'body' => 'Something went wrong while processing your import. :count rows were processed before the failure.',
        ],
        'duplicate' => 'Duplicate value: a record with the same unique field already exists.',
        'duplicate_headings' => [
            'title' => 'Duplicate column headings',
            'body' => 'The file contains duplicate headings: :headings. Only the last column with each duplicated heading will be imported.',
        ],
    ],
];
