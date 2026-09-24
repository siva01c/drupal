<?php

/**
 * Startup Factory — local settings override.
 *
 * Copy this file to settings.local.php for local dev overrides.
 * On the VPS, environment variables are injected via Docker Compose .env.
 *
 * Usage: uncomment the include at the bottom of settings.php, or include
 * this file directly in settings.php for Docker environments.
 */

// ---------------------------------------------------------------------------
// Database — reads from Docker Compose environment variables
// ---------------------------------------------------------------------------
$databases['default']['default'] = [
  'database'  => getenv('MYSQL_DATABASE') ?: 'drupal',
  'username'  => getenv('MYSQL_USER')     ?: 'drupal',
  'password'  => getenv('MYSQL_PASSWORD') ?: 'drupal',
  'host'      => 'mariadb',
  'port'      => '3306',
  'driver'    => 'mysql',
  'prefix'    => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// ---------------------------------------------------------------------------
// Redis cache backend — enabled after Redis module is installed.
// Skip during site:install (Drush CLI) to avoid container compilation errors.
// Enable manually after install: drush en redis && drush cr
// ---------------------------------------------------------------------------
if (extension_loaded('redis') && php_sapi_name() !== 'cli') {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host']      = 'redis';
  $settings['redis.connection']['port']      = 6379;

  $settings['cache']['default'] = 'cache.backend.redis';

  $settings['cache']['bins']['render']     = 'cache.backend.memory';
  $settings['cache']['bins']['page']       = 'cache.backend.memory';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.memory';
}

// ---------------------------------------------------------------------------
// Trusted host patterns — allow ops-proxy domain + local dev
// ---------------------------------------------------------------------------
$settings['trusted_host_patterns'] = [
  '^startupfactory\.local$',
  '^localhost$',
  // Wildcard for project subdomains (e.g., demo-project.startupfactory.local)
  '^.*\.startupfactory\.local$',
  // VPS domain — set via environment variable for flexibility
  '^' . str_replace('.', '\.', getenv('VIRTUAL_HOST') ?: 'startupfactory.local') . '$',
];

// ---------------------------------------------------------------------------
// File paths
// ---------------------------------------------------------------------------
$settings['file_public_path']  = 'sites/default/files';
$settings['file_private_path'] = '/var/www/tmp/private';
$settings['file_temp_path']    = '/var/www/tmp';

// ---------------------------------------------------------------------------
// Config sync directory
// ---------------------------------------------------------------------------
$settings['config_sync_directory'] = '../config/sync';

// ---------------------------------------------------------------------------
// Hash salt — override with a real value in production via env var
// ---------------------------------------------------------------------------
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'startupfactory-dev-hash-change-in-production';

// ---------------------------------------------------------------------------
// Reverse proxy (ops-proxy sits in front on VPS)
// ---------------------------------------------------------------------------
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1'];

// ---------------------------------------------------------------------------
// Environment indicator
// ---------------------------------------------------------------------------
$settings['deployment_identifier'] = getenv('TARGET_ENV') ?: 'dev';

// ---------------------------------------------------------------------------
// Load settings.local.php if present (for per-developer overrides)
// ---------------------------------------------------------------------------
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
