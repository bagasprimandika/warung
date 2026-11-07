<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$requiredVariables = [
    'WARUNG_DB_HOST',
    'WARUNG_DB_PORT',
    'WARUNG_DB_NAME',
    'WARUNG_DB_USER',
    'WARUNG_DB_PASSWORD'
];
$missingVariables = array_filter($requiredVariables, static fn (string $name): bool => getenv($name) === false || getenv($name) === '');

if ($missingVariables !== []) {
    fwrite(STDERR, 'Missing required database variables: ' . implode(', ', $missingVariables) . PHP_EOL);
    exit(1);
}

putenv('APP_ENV=production');
require_once __DIR__ . '/database_connection.php';

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_sessions ('
        . 'session_id VARBINARY(128) NOT NULL PRIMARY KEY, '
        . 'session_data MEDIUMBLOB NOT NULL, '
        . 'last_activity INT UNSIGNED NOT NULL, '
        . 'INDEX idx_app_sessions_last_activity (last_activity)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    fwrite(STDOUT, 'MySQL session storage is ready.' . PHP_EOL);
} catch (Throwable $error) {
    error_log('Warung session schema migration failed: ' . $error->getMessage());
    fwrite(STDERR, 'Could not prepare MySQL session storage. Check database connectivity and CREATE TABLE privileges.' . PHP_EOL);
    exit(1);
}
