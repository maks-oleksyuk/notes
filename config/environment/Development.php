<?php

$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
    $GLOBALS['TYPO3_CONF_VARS'],
    [
        'BE' => [
            'debug' => true,
        ],
        'FE' => [
            'debug' => true,
        ],
        'SYS' => [
            'displayErrors' => 1,
        ],
    ]
);
