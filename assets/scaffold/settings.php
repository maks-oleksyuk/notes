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

// Docker (Dokploy) environment.
if (getenv('DB_HOST')) {
  $databases['default']['default'] = [
    'driver' => 'mysql',
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'prefix' => '',
    'isolation_level' => 'READ COMMITTED',
  ];
  $settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
  $settings['trusted_host_patterns'] = ['^' . preg_quote(getenv('DRUPAL_HOST')) . '$'];
  // App is reachable only through the Dokploy (Traefik) proxy.
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];
}

$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private';
$settings['skip_permissions_hardening'] = TRUE;
