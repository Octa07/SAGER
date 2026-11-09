<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (!esAdministrador()) {
    header('Location: ../admin/login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Sistema.php';

$paginaSolicitada = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pagina = $paginaSolicitada === false ? 1 : max(1, $paginaSolicitada);
$datos = Sistema::datosDashboard($pdo, $pagina);
$citas = $datos['citas'];
$insumos = $datos['insumos'];
$servicios = $datos['servicios'];
$totalCitas = $datos['totalCitas'];
$pagina = $datos['pagina'];
$totalPaginas = $datos['totalPaginas'];
$porPagina = $datos['porPagina'];
$csrfToken = tokenCsrf();
$notificacion = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
define('SAGER_VIEW', true);
require __DIR__ . '/../views/admin/dashboard.php';