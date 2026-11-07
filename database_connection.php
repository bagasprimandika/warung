<?php

$readDatabaseEnv = static function (string $name, string $default = ''): string {
    $value = getenv($name);

    return $value === false ? $default : $value;
};

$isProduction = $readDatabaseEnv('APP_ENV') === 'production'
    || $readDatabaseEnv('VERCEL_ENV') === 'production';

$host = $readDatabaseEnv('WARUNG_DB_HOST', $isProduction ? '' : '127.0.0.1');
$portValue = $readDatabaseEnv('WARUNG_DB_PORT', $isProduction ? '' : '3306');
$dbname = $readDatabaseEnv('WARUNG_DB_NAME', $isProduction ? '' : 'penjualan_sederhana');
$dbuser = $readDatabaseEnv('WARUNG_DB_USER', $isProduction ? '' : 'root');
$dbpass = $readDatabaseEnv('WARUNG_DB_PASSWORD');
$requiredVariables = [
    'WARUNG_DB_HOST' => $host,
    'WARUNG_DB_PORT' => $portValue,
    'WARUNG_DB_NAME' => $dbname,
    'WARUNG_DB_USER' => $dbuser,
    'WARUNG_DB_PASSWORD' => $dbpass
];
$missingVariables = [];

if ($isProduction) {
    foreach ($requiredVariables as $name => $value) {
        if ($value === '') {
            $missingVariables[] = $name;
        }
    }
}

if ($missingVariables !== []) {
    error_log('Missing required Warung database environment variables: ' . implode(', ', $missingVariables));
    http_response_code(500);
    exit('Konfigurasi database Production belum lengkap.');
}

$port = filter_var($portValue, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535]
]);

if ($port === false) {
    error_log('WARUNG_DB_PORT must be a valid TCP port.');
    http_response_code(500);
    exit('Konfigurasi database tidak valid.');
}

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 10
];

if ($isProduction) {
    $sslCaPem = $readDatabaseEnv('WARUNG_DB_SSL_CA');
    $sslCaPath = '/etc/ssl/certs/ca-certificates.crt';

    if ($sslCaPem !== '') {
        $sslCaPath = sys_get_temp_dir() . '/warung-mysql-ca.pem';
        if (file_put_contents($sslCaPath, $sslCaPem, LOCK_EX) === false) {
            error_log('Could not write the configured MySQL CA certificate.');
            http_response_code(500);
            exit('Konfigurasi SSL database tidak valid.');
        }
        chmod($sslCaPath, 0600);
    }

    if (!is_file($sslCaPath)) {
        error_log('The system CA bundle is unavailable for the Production MySQL connection.');
        http_response_code(500);
        exit('Sertifikat keamanan database tidak tersedia.');
    }

    $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
    $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        $pdoOptions
    );
} catch (PDOException $error) {
    error_log('Warung MySQL connection failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa koneksi dan konfigurasi MySQL.');
}
