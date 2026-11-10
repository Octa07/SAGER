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
$idCitaSolicitada = filter_var($_GET['cita'] ?? null, FILTER_VALIDATE_INT);
$idCitaSeleccionada = $idCitaSolicitada === false || $idCitaSolicitada === null || $idCitaSolicitada < 1
    ? null
    : $idCitaSolicitada;
$datos = Sistema::datosDashboard($pdo, $pagina, $idCitaSeleccionada);
$citas = $datos['citas'];
$insumosPorCita = $datos['insumosPorCita'];
$insumos = $datos['insumos'];
$servicios = $datos['servicios'];
$insumosPorServicio = $datos['insumosPorServicio'];
$stockPorServicio = $datos['stockPorServicio'];
$citaSeleccionada = $datos['citaSeleccionada'];
$insumosCitaSeleccionada = $datos['insumosCitaSeleccionada'];
$consumosCitaSeleccionada = $datos['consumosCitaSeleccionada'];
$insumoCitaSeleccionadoSolicitado = filter_var($_GET['insumo'] ?? null, FILTER_VALIDATE_INT);
$insumoCitaSeleccionado = $insumoCitaSeleccionadoSolicitado === false
    ? null
    : $insumoCitaSeleccionadoSolicitado;
$totalCitas = $datos['totalCitas'];
$pagina = $datos['pagina'];
$totalPaginas = $datos['totalPaginas'];
$porPagina = $datos['porPagina'];
$csrfToken = tokenCsrf();
$notificacion = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
define('SAGER_VIEW', true);
require __DIR__ . '/../views/admin/dashboard.php';