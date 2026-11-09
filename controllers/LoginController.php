<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/login.php');
    exit;
}

$correo = trim((string) ($_POST['correo'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($correo === '' || $password === '' || !csrfValido($_POST['csrf_token'] ?? null)) {
    $_SESSION['login_error'] = 'Credenciales inválidas.';
    header('Location: ../views/login.php');
    exit;
}

$usuario = filter_var($correo, FILTER_VALIDATE_EMAIL) ? Usuario::buscarPorCorreo($pdo, $correo) : null;

if (!$usuario || $usuario['rol'] !== 'admin' || !password_verify($password, $usuario['password_hash'])) {
    $_SESSION['login_error'] = 'Credenciales inválidas.';
    header('Location: ../views/login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario'] = [
    'id' => (int) $usuario['id_usuario'],
    'nombre' => $usuario['nombre'],
    'correo' => $usuario['correo'],
    'rol' => $usuario['rol'],
];
unset($_SESSION['login_error']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header('Location: DashboardController.php');
exit;