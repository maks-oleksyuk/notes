<?php

return [
    'BE' => [
        'debug' => false,
        'fileadminDir' => 'files',
        'installToolPassword' => false,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8',
                'driver' => 'mysqli',
            ],
        ],
    ],
    'EXTENSIONS' => [
        'backend' => [
            'backendFavicon' => 'EXT:nts_site/Resources/Public/Icons/backend/favicon.ico',
            'backendLogo' => 'EXT:nts_site/Resources/Public/Icons/backend/logo.svg',
            'loginBackgroundImage' => 'https://picsum.photos/1920/1080',
            'loginFootnote' => '',
            'loginHighlightColor' => '#138808',
            'loginLogo' => '',
            'loginLogoAlt' => 'Typo3 Notes',
        ],
        'extensionmanager' => [
            'automaticInstallation' => '1',
            'offlineMode' => '0',
        ],
        'scheduler' => [
            'maxLifetime' => '1440',
        ],
    ],
    'FE' => [
        'debug' => false,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'GFX' => [
        'processor' => 'GraphicsMagick',
        'processor_effects' => false,
        'processor_enabled' => true,
        'processor_path' => '/usr/bin/',
    ],
    'LOG' => [
        'TYPO3' => [
            'CMS' => [
                'deprecations' => [
                    'writerConfiguration' => [
                        'notice' => [
                            'TYPO3\CMS\Core\Log\Writer\FileWriter' => [
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'MAIL' => [
        'defaultMailFromAddress' => 'noreply@typo3notes.site',
        'defaultMailFromName' => 'Typo3 Notes',
        'transport' => 'sendmail',
        'transport_sendmail_command' => '/usr/local/bin/mailpit sendmail -t --smtp-addr 127.0.0.1:1025',
        'transport_smtp_encrypt' => '',
        'transport_smtp_password' => '',
        'transport_smtp_server' => '',
        'transport_smtp_username' => '',
    ],
    'SYS' => [
        'caching' => [
            'cacheConfigurations' => [
                'hash' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                ],
                'pages' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
                'rootline' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
            ],
        ],
        'devIPmask' => '*',
        'displayErrors' => 0,
        'encryptionKey' => 'bdba01a10945c558e01e5099f37c1db78af184fcb95528d26de2e0da7f3a1df67cb100e92c3eb872db63d1f944d25702',
        'exceptionalErrors' => 12290,
        'features' => [
            'security.usePasswordPolicyForFrontendUsers' => true,
        ],
        'sitename' => 'Notes',
        'systemLocale' => 'en_US.UTF-8',
        'trustedHostsPattern' => '.*.*',
    ],
];
