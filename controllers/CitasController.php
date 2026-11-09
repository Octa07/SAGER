<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $estado, array $cuerpo): never
{
    http_response_code($estado);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$correo = trim((string) ($_POST['correo'] ?? ''));
$idServicio = filter_var($_POST['id_servicio'] ?? null, FILTER_VALIDATE_INT);
$fecha = (string) ($_POST['fecha_cita'] ?? '');
$hora = (string) ($_POST['hora_cita'] ?? '');

$fechaValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
$horaValidada = DateTimeImmutable::createFromFormat('!H:i', $hora);
$erroresFecha = DateTimeImmutable::getLastErrors();
$erroresHora = DateTimeImmutable::getLastErrors();
$fechaCorrecta = $fechaValidada !== false && ($erroresFecha === false || $erroresFecha['warning_count'] === 0);
$horaCorrecta = $horaValidada !== false && ($erroresHora === false || $erroresHora['warning_count'] === 0);

if (
    $nombre === '' || mb_strlen($nombre) > 100
    || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100
    || !$idServicio || !$fechaCorrecta || !$horaCorrecta
    || $fechaValidada->format('Y-m-d') !== $fecha || $horaValidada->format('H:i') !== $hora
) {
    responder(422, ['ok' => false, 'mensaje' => 'Revisa los datos del formulario e inténtalo nuevamente.']);
}

$fechaHora = $fecha . ' ' . $hora . ':00';
if ($fechaHora < date('Y-m-d H:i:s')) {
    responder(422, ['ok' => false, 'mensaje' => 'Selecciona una fecha y hora futuras.']);
}

try {
    $pdo->beginTransaction();

    $consultaServicio = $pdo->prepare(
        'SELECT id_servicio, consumo_insumo FROM servicios WHERE id_servicio = :id FOR UPDATE'
    );
    $consultaServicio->execute(['id' => $idServicio]);
    $servicio = $consultaServicio->fetch();

    if (!$servicio) {
        throw new DomainException('El servicio seleccionado ya no está disponible.');
    }

    $consultaCita = $pdo->prepare(
        'SELECT id_cita FROM citas WHERE fecha_cita = :fecha AND hora_cita = :hora LIMIT 1'
    );
    $consultaCita->execute(['fecha' => $fecha, 'hora' => $hora . ':00']);
    if ($consultaCita->fetch()) {
        throw new DomainException('Ese horario ya está reservado. Elige otra hora.');
    }

    $consultaInsumo = $pdo->query(
        'SELECT id_insumo, stock_actual FROM insumos ORDER BY id_insumo ASC LIMIT 1 FOR UPDATE'
    );
    $insumo = $consultaInsumo->fetch();
    if (!$insumo) {
        throw new DomainException('No hay insumos configurados para completar la reserva.');
    }

    $consumo = (int) $servicio['consumo_insumo'];
    if ($consumo < 0) {
        throw new DomainException('El servicio tiene una configuración de inventario inválida.');
    }
    if ((int) $insumo['stock_actual'] < $consumo) {
        throw new DomainException('No hay insumos suficientes para ese servicio.');
    }

    $idCliente = Usuario::obtenerOCrearCliente($pdo, $nombre, $correo);

    $insertarCita = $pdo->prepare(
        "INSERT INTO citas (id_cliente, id_servicio, fecha_cita, hora_cita, estado)
         VALUES (:cliente, :servicio, :fecha, :hora, 'confirmada')"
    );
    $insertarCita->execute([
        'cliente' => $idCliente,
        'servicio' => $idServicio,
        'fecha' => $fecha,
        'hora' => $hora . ':00',
    ]);
    $idCita = (int) $pdo->lastInsertId();

    $actualizarStock = $pdo->prepare(
        'UPDATE insumos SET stock_actual = stock_actual - :consumo_restar WHERE id_insumo = :id AND stock_actual >= :consumo_validar'
    );
    $actualizarStock->execute([
        'consumo_restar' => $consumo,
        'consumo_validar' => $consumo,
        'id' => $insumo['id_insumo'],
    ]);
    if ($consumo > 0 && $actualizarStock->rowCount() !== 1) {
        throw new DomainException('No hay insumos suficientes para completar la reserva.');
    }

    $pdo->commit();
    responder(201, ['ok' => true, 'mensaje' => 'Tu cita fue confirmada.', 'id_cita' => $idCita]);
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(409, ['ok' => false, 'mensaje' => $exception->getMessage()]);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        responder(409, ['ok' => false, 'mensaje' => 'Ese horario acaba de ser reservado. Elige otra hora.']);
    }
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo completar la reserva. Inténtalo nuevamente.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(500, ['ok' => false, 'mensaje' => 'Ocurrió un problema al procesar la reserva. Inténtalo nuevamente.']);
}