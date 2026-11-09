<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (!esAdministrador()) {
    header('Location: ../views/login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Sistema.php';

$datos = Sistema::datosDashboard($pdo);
$citas = $datos['citas'];
$insumos = $datos['insumos'];
require __DIR__ . '/../views/dashboard.php';