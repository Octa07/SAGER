<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Sistema.php';

$servicios = Sistema::servicios($pdo);
$csrfToken = tokenCsrf();
require __DIR__ . '/../views/reserva.php';