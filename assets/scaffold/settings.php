<?php

// Import core settings, like 'entity_update_batch_size'. This file must be
// presented and scaffolded from core without any changes. It allows to have all
// the changes, additions and removals from core up to date on project.
include __DIR__ . '/default.settings.php';

// Automatically generated include for settings managed by ddev.
$ddev_settings = dirname(__FILE__) . '/settings.ddev.php';
if (getenv('IS_DDEV_PROJECT') == 'true' && is_readable($ddev_settings)) {
  require $ddev_settings;
}

$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private';
$settings['skip_permissions_hardening'] = TRUE;
