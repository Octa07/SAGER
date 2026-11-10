<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/conexion.php';

fwrite(STDOUT, "Nombre del administrador: ");
$nombre = trim((string) fgets(STDIN));
fwrite(STDOUT, "Correo electrónico: ");
$correo = trim((string) fgets(STDIN));
fwrite(STDOUT, "Contraseña (mínimo 12 caracteres): ");
$password = trim((string) fgets(STDIN));

if ($nombre === '' || mb_strlen($nombre) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100 || strlen($password) < 12) {
    fwrite(STDERR, "Datos inválidos. No se creó el administrador.\n");
    exit(1);
}

$consulta = $pdo->prepare(
    "INSERT INTO usuarios (nombre, correo, password_hash, rol) VALUES (:nombre, :correo, :password_hash, 'admin')"
);
$consulta->execute([
    'nombre' => $nombre,
    'correo' => $correo,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Administrador creado correctamente.\n");