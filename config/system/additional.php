<?php

use TYPO3\CMS\Core\Core\Environment;

class AdditionalConfiguration
{
    /**
     * Connect to database using env values.
     */
    public function databaseConnection(): self
    {
        $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
            $GLOBALS['TYPO3_CONF_VARS'],
            [
                'DB' => [
                    'Connections' => [
                        'Default' => [
                            'dbname' => getenv('DB_NAME'),
                            'host' =>  getenv('DB_HOST'),
                            'password' =>  getenv('DB_PASS'),
                            'port' => (int)getenv('DB_PORT'),
                            'user' =>  getenv('DB_USER'),
                        ],
                    ],
                ],
            ]
        );

        return $this;
    }

    /**
     * Append TYPO3_CONTEXT to site name in the TYPO3 backend.
     */
    public function appendContextToSiteName(): self
    {
        $currentContext = Environment::getContext();

        if (!$currentContext->isProduction()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] .= " ($currentContext)";
        }

        return $this;
    }

    /**
     * Include additional configurations by TYPO3_CONTEXT server variable.
     */
    public function loadContextDependentConfigurations(): self
    {
        $currentContext = Environment::getContext();
        $configPath = Environment::getConfigPath();

        do {
            $orderedListOfContextNames[] = (string)$currentContext;
        } while (($currentContext = $currentContext->getParent()));
        $orderedListOfContextNames = array_reverse($orderedListOfContextNames ?? []);

        foreach ($orderedListOfContextNames as $contextName) {
            $filePath = "$configPath/environment/$contextName.php";
            if (file_exists($filePath)) {
                require $filePath;
            }
        }

        return $this;
    }
}

$additionalConfiguration = new AdditionalConfiguration();
$additionalConfiguration
    ->databaseConnection()
    ->appendContextToSiteName()
    ->loadContextDependentConfigurations();
