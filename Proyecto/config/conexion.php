<?php
declare(strict_types=1);

$dbSettings = [
    'DB_HOST' => getenv('DB_HOST'),
    'DB_NAME' => getenv('DB_NAME'),
    'DB_USER' => getenv('DB_USER'),
    'DB_PASSWORD' => getenv('DB_PASSWORD'),
];
$missingSettings = array_keys(array_filter(
    $dbSettings,
    static fn ($value): bool => $value === false || trim($value) === ''
));

if ($missingSettings !== []) {
    $message = 'Faltan variables de configuración de base de datos: ' . implode(', ', $missingSettings);
    error_log($message);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Revisa la configuración local.');
}

$dbHost = $dbSettings['DB_HOST'];
$dbName = $dbSettings['DB_NAME'];
$dbUser = $dbSettings['DB_USER'];
$dbPassword = $dbSettings['DB_PASSWORD'];

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('SAGER database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Revisa la configuración local.');
}