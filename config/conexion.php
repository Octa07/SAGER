<?php
declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: 'Localhost';
$dbName = getenv('DB_NAME') ?: 'sistema_reservas';
$dbUser = getenv('DB_USER') ?: 'usuarioClases';
$dbPassword = getenv('DB_PASSWORD') ?: 'Pass123';

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