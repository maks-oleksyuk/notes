<?php

$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
    $GLOBALS['TYPO3_CONF_VARS'],
    [
        'SYS' => [
            'displayErrors' => 1,
        ],
    ]
);
