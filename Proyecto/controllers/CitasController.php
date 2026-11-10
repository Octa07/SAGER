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

if (!csrfValido($_POST['csrf_token'] ?? null)) {
    responder(419, ['ok' => false, 'mensaje' => 'La sesión expiró. Recarga la página e inténtalo nuevamente.']);
}

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
        'SELECT id_servicio, insumos_configurados FROM servicios WHERE id_servicio = :id FOR UPDATE'
    );
    $consultaServicio->execute(['id' => $idServicio]);
    $servicio = $consultaServicio->fetch();

    if (!$servicio) {
        throw new DomainException('El servicio seleccionado ya no está disponible.');
    }
    if ((int) $servicio['insumos_configurados'] !== 1) {
        throw new DomainException('Este servicio aún no tiene sus insumos configurados. Contacta al administrador.');
    }

    $consultaCita = $pdo->prepare(
        'SELECT id_cita FROM citas WHERE fecha_cita = :fecha AND hora_cita = :hora LIMIT 1'
    );
    $consultaCita->execute(['fecha' => $fecha, 'hora' => $hora . ':00']);
    if ($consultaCita->fetch()) {
        throw new DomainException('Ese horario ya está reservado. Elige otra hora.');
    }

    $consultaInsumos = $pdo->prepare(
        'SELECT si.id_insumo, si.cantidad, i.nombre_insumo, i.stock_actual,
                sis.stock_actual AS stock_servicio
         FROM servicio_insumos si
         INNER JOIN insumos i ON i.id_insumo = si.id_insumo
         LEFT JOIN servicio_insumo_stock sis
           ON sis.id_servicio = si.id_servicio AND sis.id_insumo = si.id_insumo
         WHERE si.id_servicio = :id
         ORDER BY si.id_insumo
         FOR UPDATE'
    );
    $consultaInsumos->execute(['id' => $idServicio]);
    $insumosRequeridos = $consultaInsumos->fetchAll();

    foreach ($insumosRequeridos as $insumo) {
        $cantidadRequerida = (int) $insumo['cantidad'];
        if ($cantidadRequerida < 1) {
            throw new DomainException('El servicio tiene una cantidad de insumos inválida.');
        }
        if ((int) $insumo['stock_actual'] < $cantidadRequerida) {
            throw new DomainException(
                'No hay suficientes unidades generales de ' . $insumo['nombre_insumo'] . '.'
            );
        }
        if ($insumo['stock_servicio'] === null || (int) $insumo['stock_servicio'] < $cantidadRequerida) {
            throw new DomainException(
                'No hay suficientes unidades de ' . $insumo['nombre_insumo'] . ' asignadas a ese servicio.'
            );
        }
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

    $guardarConsumoCita = $pdo->prepare(
        'INSERT INTO cita_insumos (id_cita, id_insumo, cantidad) VALUES (:cita, :insumo, :cantidad)'
    );
    $actualizarStock = $pdo->prepare(
        'UPDATE insumos
         SET stock_actual = stock_actual - :cantidad_restar
         WHERE id_insumo = :id AND stock_actual >= :cantidad_validar'
    );
    $actualizarStockServicio = $pdo->prepare(
        'UPDATE servicio_insumo_stock
         SET stock_actual = stock_actual - :cantidad_restar
         WHERE id_servicio = :servicio AND id_insumo = :id AND stock_actual >= :cantidad_validar'
    );
    foreach ($insumosRequeridos as $insumo) {
        $cantidad = (int) $insumo['cantidad'];
        $guardarConsumoCita->execute([
            'cita' => $idCita,
            'insumo' => $insumo['id_insumo'],
            'cantidad' => $cantidad,
        ]);
        $actualizarStock->execute([
            'cantidad_restar' => $cantidad,
            'cantidad_validar' => $cantidad,
            'id' => $insumo['id_insumo'],
        ]);
        if ($actualizarStock->rowCount() !== 1) {
            throw new DomainException(
                'No hay suficientes unidades de ' . $insumo['nombre_insumo'] . ' para completar la reserva.'
            );
        }
        $actualizarStockServicio->execute([
            'cantidad_restar' => $cantidad,
            'cantidad_validar' => $cantidad,
            'servicio' => $idServicio,
            'id' => $insumo['id_insumo'],
        ]);
        if ($actualizarStockServicio->rowCount() !== 1) {
            throw new DomainException(
                'No hay suficientes unidades de ' . $insumo['nombre_insumo'] . ' asignadas a ese servicio.'
            );
        }
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