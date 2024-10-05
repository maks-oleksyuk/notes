<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

/**
 * Adding the default user TSconfig
 *
 * todo: Update to default and remove in v13.
 * https://docs.typo3.org/m/typo3/reference-tsconfig/main/en-us/UsingSetting/UserTSconfig.html#usersettingdefaultusertsconfig
 */
ExtensionManagementUtility::addUserTSConfig(
    '@import "EXT:nts_site/Configuration/TsConfig/User/default.tsconfig"'
);
