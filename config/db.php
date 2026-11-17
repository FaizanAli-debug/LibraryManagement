<?php
mysqli_report(MYSQLI_REPORT_OFF);

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'root',
    'password' => '',
    'database' => 'library_management',
];

$localConfigPath = __DIR__ . '/local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (is_array($localConfig)) {
        $config = array_merge($config, array_intersect_key($localConfig, $config));
    }
}

$hostEnv = getenv('DB_HOST');
$portEnv = getenv('DB_PORT');
$userEnv = getenv('DB_USER');
$passEnv = getenv('DB_PASS');
$nameEnv = getenv('DB_NAME');

if ($hostEnv !== false && $hostEnv !== '') {
    $config['host'] = $hostEnv;
}

if ($portEnv !== false && $portEnv !== '' && ctype_digit((string)$portEnv)) {
    $port = (int)$portEnv;

    if ($port > 0 && $port <= 65535) {
        $config['port'] = $port;
    }
}

if ($userEnv !== false && $userEnv !== '') {
    $config['username'] = $userEnv;
}

if ($passEnv !== false) {
    $config['password'] = $passEnv;
}

if ($nameEnv !== false && $nameEnv !== '') {
    $config['database'] = $nameEnv;
}

if ((string)$config['host'] === '' || (string)$config['database'] === '') {
    error_log('LibraryManagement database configuration is incomplete.');
    http_response_code(500);
    exit('The library system is temporarily unavailable. Please try again later.');
}

$conn = @new mysqli(
    (string)$config['host'],
    (string)$config['username'],
    (string)$config['password'],
    (string)$config['database'],
    (int)$config['port']
);

if ($conn->connect_error) {
    error_log('LibraryManagement database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('The library system is temporarily unavailable. Please try again later.');
}

if (!$conn->set_charset('utf8mb4')) {
    error_log('LibraryManagement database charset could not be set.');
    http_response_code(500);
    exit('The library system is temporarily unavailable. Please try again later.');
}
