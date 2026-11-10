<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (!esAdministrador()) {
    header('Location: ../admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

if (!csrfValido($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud inválida. Recarga el panel e inténtalo nuevamente.');
}

require_once __DIR__ . '/../config/conexion.php';

function redirigirAdmin(string $mensaje, string $tipo = 'success', string $destino = ''): never
{
    $_SESSION['admin_notice'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    header('Location: ../admin/' . $destino);
    exit;
}

function enteroFormulario(string $campo, int $minimo, int $maximo): ?int
{
    $valor = filter_var($_POST[$campo] ?? null, FILTER_VALIDATE_INT);
    if ($valor === false || $valor === null || $valor < $minimo || $valor > $maximo) {
        return null;
    }

    return $valor;
}

$accion = (string) ($_POST['accion'] ?? '');
$citaSeleccionadaParaRetorno = null;

try {
    switch ($accion) {
        case 'crear_insumo':
            $nombre = trim((string) ($_POST['nombre_insumo'] ?? ''));
            $stock = enteroFormulario('stock_actual', 0, 1000000);
            $minimo = enteroFormulario('stock_minimo', 0, 1000000);
            if ($nombre === '' || mb_strlen($nombre) > 100 || $stock === null || $minimo === null) {
                redirigirAdmin('Revisa el nombre y las cantidades del insumo.', 'error');
            }

            $consulta = $pdo->prepare(
                'INSERT INTO insumos (nombre_insumo, stock_actual, stock_minimo) VALUES (:nombre, :stock, :minimo)'
            );
            $consulta->execute(['nombre' => $nombre, 'stock' => $stock, 'minimo' => $minimo]);
            redirigirAdmin('Insumo registrado. Ya puedes asignarlo a un servicio.', 'success', '#insumos-por-servicio');

        case 'reponer_stock':
            $idInsumo = enteroFormulario('id_insumo', 1, PHP_INT_MAX);
            $idServicio = enteroFormulario('id_servicio', 1, PHP_INT_MAX);
            $cantidad = enteroFormulario('cantidad', 1, 1000000);
            if ($idInsumo === null || $idServicio === null || $cantidad === null) {
                redirigirAdmin('Selecciona el servicio y el insumo, e indica una cantidad positiva.', 'error');
            }

            $pdo->beginTransaction();
            $buscarServicio = $pdo->prepare('SELECT id_servicio FROM servicios WHERE id_servicio = :id FOR UPDATE');
            $buscarServicio->execute(['id' => $idServicio]);
            if (!$buscarServicio->fetchColumn()) {
                throw new DomainException('No se encontró el servicio seleccionado.');
            }

            $buscarAsignacion = $pdo->prepare(
                'SELECT 1 FROM servicio_insumos
                 WHERE id_servicio = :servicio AND id_insumo = :insumo FOR UPDATE'
            );
            $buscarAsignacion->execute(['servicio' => $idServicio, 'insumo' => $idInsumo]);
            if (!$buscarAsignacion->fetchColumn()) {
                throw new DomainException('El insumo seleccionado no está asignado al servicio.');
            }

            $buscarInsumo = $pdo->prepare('SELECT stock_actual FROM insumos WHERE id_insumo = :id FOR UPDATE');
            $buscarInsumo->execute(['id' => $idInsumo]);
            $stockGeneral = $buscarInsumo->fetchColumn();
            if ($stockGeneral === false) {
                throw new DomainException('No se encontró el insumo seleccionado.');
            }

            $crearStockServicio = $pdo->prepare(
                'INSERT IGNORE INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
                 VALUES (:servicio, :insumo, 0)'
            );
            $crearStockServicio->execute(['servicio' => $idServicio, 'insumo' => $idInsumo]);
            $buscarStockServicio = $pdo->prepare(
                'SELECT stock_actual FROM servicio_insumo_stock
                 WHERE id_servicio = :servicio AND id_insumo = :insumo FOR UPDATE'
            );
            $buscarStockServicio->execute(['servicio' => $idServicio, 'insumo' => $idInsumo]);
            $stockServicio = $buscarStockServicio->fetchColumn();
            if ($stockServicio === false) {
                throw new RuntimeException('No se pudo preparar el stock del servicio.');
            }
            if (
                (int) $stockGeneral > 2147483647 - $cantidad
                || (int) $stockServicio > 2147483647 - $cantidad
            ) {
                throw new DomainException('La cantidad supera el máximo permitido para el stock.');
            }

            $actualizarStockGeneral = $pdo->prepare(
                'UPDATE insumos SET stock_actual = stock_actual + :cantidad WHERE id_insumo = :id'
            );
            $actualizarStockGeneral->execute(['cantidad' => $cantidad, 'id' => $idInsumo]);
            $actualizarStockServicio = $pdo->prepare(
                'UPDATE servicio_insumo_stock
                 SET stock_actual = stock_actual + :cantidad
                 WHERE id_servicio = :servicio AND id_insumo = :insumo'
            );
            $actualizarStockServicio->execute([
                'cantidad' => $cantidad,
                'servicio' => $idServicio,
                'insumo' => $idInsumo,
            ]);
            $pdo->commit();
            redirigirAdmin(
                'El stock general y el del servicio se actualizaron correctamente.',
                'success',
                '#agregar-stock'
            );

        case 'actualizar_minimo':
            $idInsumo = enteroFormulario('id_insumo', 1, PHP_INT_MAX);
            $minimo = enteroFormulario('stock_minimo', 0, 1000000);
            if ($idInsumo === null || $minimo === null) {
                redirigirAdmin('El stock mínimo debe ser un número válido.', 'error');
            }

            $actualizar = $pdo->prepare('UPDATE insumos SET stock_minimo = :minimo WHERE id_insumo = :id');
            $actualizar->execute(['minimo' => $minimo, 'id' => $idInsumo]);
            if ($actualizar->rowCount() === 0) {
                $existe = $pdo->prepare('SELECT 1 FROM insumos WHERE id_insumo = :id');
                $existe->execute(['id' => $idInsumo]);
                if (!$existe->fetchColumn()) {
                    throw new DomainException('No se encontró el insumo seleccionado.');
                }
            }
            redirigirAdmin('Stock mínimo actualizado.');

        case 'asignar_insumo_servicio':
            $idServicio = enteroFormulario('id_servicio', 1, PHP_INT_MAX);
            $idInsumo = enteroFormulario('id_insumo', 1, PHP_INT_MAX);
            $cantidad = enteroFormulario('cantidad', 1, 1000000);
            if ($idServicio === null || $idInsumo === null || $cantidad === null) {
                redirigirAdmin(
                    'Selecciona el servicio, el insumo y una cantidad positiva.',
                    'error',
                    '#insumos-por-servicio'
                );
            }

            $pdo->beginTransaction();
            $buscarServicio = $pdo->prepare('SELECT id_servicio FROM servicios WHERE id_servicio = :id FOR UPDATE');
            $buscarServicio->execute(['id' => $idServicio]);
            if (!$buscarServicio->fetchColumn()) {
                throw new DomainException('No se encontró el servicio seleccionado.');
            }

            $buscarInsumo = $pdo->prepare('SELECT id_insumo FROM insumos WHERE id_insumo = :id');
            $buscarInsumo->execute(['id' => $idInsumo]);
            if (!$buscarInsumo->fetchColumn()) {
                throw new DomainException('No se encontró el insumo seleccionado.');
            }

            $guardarRelacion = $pdo->prepare(
                'INSERT INTO servicio_insumos (id_servicio, id_insumo, cantidad)
                 VALUES (:servicio, :insumo, :cantidad)
                 ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)'
            );
            $guardarRelacion->execute([
                'servicio' => $idServicio,
                'insumo' => $idInsumo,
                'cantidad' => $cantidad,
            ]);
            $crearStockServicio = $pdo->prepare(
                'INSERT IGNORE INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
                 VALUES (:servicio, :insumo, 0)'
            );
            $crearStockServicio->execute(['servicio' => $idServicio, 'insumo' => $idInsumo]);

            $marcarConfigurado = $pdo->prepare(
                'UPDATE servicios SET insumos_configurados = 1 WHERE id_servicio = :id'
            );
            $marcarConfigurado->execute(['id' => $idServicio]);
            $pdo->commit();
            redirigirAdmin('Consumo del servicio guardado.', 'success', '#insumos-por-servicio');

        case 'actualizar_insumos_cita':
            $idCita = enteroFormulario('id_cita', 1, PHP_INT_MAX);
            $citaSeleccionadaParaRetorno = $idCita;
            if ($idCita === null) {
                redirigirAdmin('Selecciona una cita y revisa sus insumos.', 'error');
            }

            $cantidadesEnviadas = [];
            $cantidadesRecibidas = [];
            if (array_key_exists('id_insumo', $_POST) || array_key_exists('cantidad', $_POST)) {
                $idInsumo = filter_var($_POST['id_insumo'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if (
                    $idInsumo === false || $idInsumo === null || $idInsumo < 1
                    || $cantidad === false || $cantidad === null || $cantidad < 0 || $cantidad > 1000000
                ) {
                    redirigirAdmin(
                        'Selecciona un insumo y una cantidad entre 0 y 1,000,000.',
                        'error',
                        '?cita=' . $idCita . '#insumos-cita'
                    );
                }
                $cantidadesEnviadas[$idInsumo] = $cantidad;
            } elseif (isset($_POST['insumos']) && is_array($_POST['insumos'])) {
                $cantidadesRecibidas = $_POST['insumos'];
            } else {
                redirigirAdmin(
                    'Selecciona un insumo para actualizar la cita.',
                    'error',
                    '?cita=' . $idCita . '#insumos-cita'
                );
            }

            foreach ($cantidadesRecibidas as $idInsumoRecibido => $cantidadRecibida) {
                $idInsumo = filter_var((string) $idInsumoRecibido, FILTER_VALIDATE_INT);
                $cantidad = filter_var($cantidadRecibida, FILTER_VALIDATE_INT);
                if (
                    $idInsumo === false || $idInsumo < 1
                    || $cantidad === false || $cantidad < 0 || $cantidad > 1000000
                ) {
                    redirigirAdmin(
                        'Las cantidades deben ser números entre 0 y 1,000,000.',
                        'error',
                        '?cita=' . $idCita . '#insumos-cita'
                    );
                }
                $cantidadesEnviadas[$idInsumo] = $cantidad;
            }

            $pdo->beginTransaction();

            $buscarCita = $pdo->prepare(
                'SELECT estado, id_servicio FROM citas WHERE id_cita = :id FOR UPDATE'
            );
            $buscarCita->execute(['id' => $idCita]);
            $cita = $buscarCita->fetch();
            if (!$cita) {
                throw new DomainException('No se encontró la cita seleccionada.');
            }
            if ($cita['estado'] !== 'confirmada') {
                throw new DomainException('Solo se pueden ajustar los insumos de citas confirmadas.');
            }
            $idServicioCita = (int) $cita['id_servicio'];

            $buscarConsumoActual = $pdo->prepare(
                'SELECT id_insumo, cantidad FROM cita_insumos WHERE id_cita = :id ORDER BY id_insumo'
            );
            $buscarConsumoActual->execute(['id' => $idCita]);
            $consumosAnteriores = [];
            foreach ($buscarConsumoActual->fetchAll() as $relacion) {
                $consumosAnteriores[(int) $relacion['id_insumo']] = (int) $relacion['cantidad'];
            }

            $cantidadesFinales = $consumosAnteriores;
            foreach ($cantidadesEnviadas as $idInsumo => $cantidad) {
                if ($cantidad === 0) {
                    unset($cantidadesFinales[$idInsumo]);
                } else {
                    $cantidadesFinales[$idInsumo] = $cantidad;
                }
            }

            $idsInsumos = array_values(array_unique(array_merge(
                array_keys($consumosAnteriores),
                array_keys($cantidadesEnviadas)
            )));
            sort($idsInsumos, SORT_NUMERIC);
            $buscarStock = $pdo->prepare(
                'SELECT i.nombre_insumo, i.stock_actual, sis.stock_actual AS stock_servicio
                 FROM insumos i
                 LEFT JOIN servicio_insumo_stock sis
                   ON sis.id_servicio = :servicio AND sis.id_insumo = i.id_insumo
                 WHERE i.id_insumo = :insumo FOR UPDATE'
            );
            $buscarAsignacionServicio = $pdo->prepare(
                'SELECT 1 FROM servicio_insumos
                 WHERE id_servicio = :servicio AND id_insumo = :insumo FOR UPDATE'
            );
            $crearStockServicio = $pdo->prepare(
                'INSERT IGNORE INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
                 VALUES (:servicio, :insumo, 0)'
            );
            $stockPorInsumo = [];
            foreach ($idsInsumos as $idInsumo) {
                $crearStockServicio->execute(['servicio' => $idServicioCita, 'insumo' => $idInsumo]);
                $buscarStock->execute(['servicio' => $idServicioCita, 'insumo' => $idInsumo]);
                $insumo = $buscarStock->fetch();
                if (!$insumo) {
                    throw new DomainException('Uno de los insumos de la cita ya no está disponible.');
                }
                $buscarAsignacionServicio->execute(['servicio' => $idServicioCita, 'insumo' => $idInsumo]);
                $stockPorInsumo[$idInsumo] = [
                    'nombre' => $insumo['nombre_insumo'],
                    'stock' => (int) $insumo['stock_actual'],
                    'stock_servicio' => (int) ($insumo['stock_servicio'] ?? 0),
                    'asignado' => (bool) $buscarAsignacionServicio->fetchColumn(),
                ];
            }

            foreach ($idsInsumos as $idInsumo) {
                $cantidadAnterior = $consumosAnteriores[$idInsumo] ?? 0;
                $cantidadNueva = $cantidadesFinales[$idInsumo] ?? 0;
                $diferencia = $cantidadNueva - $cantidadAnterior;
                if ($diferencia > 0 && !$stockPorInsumo[$idInsumo]['asignado']) {
                    throw new DomainException(
                        $stockPorInsumo[$idInsumo]['nombre']
                        . ' no está asignado al servicio de esta cita.'
                    );
                }
                if (
                    $diferencia > $stockPorInsumo[$idInsumo]['stock']
                    || $diferencia > $stockPorInsumo[$idInsumo]['stock_servicio']
                ) {
                    throw new DomainException(
                        'No hay suficientes unidades de '
                        . $stockPorInsumo[$idInsumo]['nombre']
                        . ' en el stock general y el asignado a este servicio.'
                    );
                }
                if (
                    $diferencia < 0 && (
                        $stockPorInsumo[$idInsumo]['stock'] > 2147483647 + $diferencia
                        || $stockPorInsumo[$idInsumo]['stock_servicio'] > 2147483647 + $diferencia
                    )
                ) {
                    throw new DomainException('El ajuste supera el máximo permitido para el inventario.');
                }
            }

            $descontarDiferencia = $pdo->prepare(
                'UPDATE insumos SET stock_actual = stock_actual - :cantidad WHERE id_insumo = :id'
            );
            $reponerDiferencia = $pdo->prepare(
                'UPDATE insumos SET stock_actual = stock_actual + :cantidad WHERE id_insumo = :id'
            );
            $descontarDiferenciaServicio = $pdo->prepare(
                'UPDATE servicio_insumo_stock
                 SET stock_actual = stock_actual - :cantidad
                 WHERE id_servicio = :servicio AND id_insumo = :id'
            );
            $reponerDiferenciaServicio = $pdo->prepare(
                'UPDATE servicio_insumo_stock
                 SET stock_actual = stock_actual + :cantidad
                 WHERE id_servicio = :servicio AND id_insumo = :id'
            );
            foreach ($idsInsumos as $idInsumo) {
                $diferencia = ($cantidadesFinales[$idInsumo] ?? 0) - ($consumosAnteriores[$idInsumo] ?? 0);
                if ($diferencia > 0) {
                    $descontarDiferencia->execute(['cantidad' => $diferencia, 'id' => $idInsumo]);
                    $descontarDiferenciaServicio->execute([
                        'cantidad' => $diferencia,
                        'servicio' => $idServicioCita,
                        'id' => $idInsumo,
                    ]);
                } elseif ($diferencia < 0) {
                    $reponerDiferencia->execute(['cantidad' => abs($diferencia), 'id' => $idInsumo]);
                    $reponerDiferenciaServicio->execute([
                        'cantidad' => abs($diferencia),
                        'servicio' => $idServicioCita,
                        'id' => $idInsumo,
                    ]);
                }
            }

            $eliminarConsumo = $pdo->prepare('DELETE FROM cita_insumos WHERE id_cita = :id');
            $eliminarConsumo->execute(['id' => $idCita]);
            $guardarConsumo = $pdo->prepare(
                'INSERT INTO cita_insumos (id_cita, id_insumo, cantidad) VALUES (:cita, :insumo, :cantidad)'
            );
            foreach ($cantidadesFinales as $idInsumo => $cantidad) {
                $guardarConsumo->execute([
                    'cita' => $idCita,
                    'insumo' => $idInsumo,
                    'cantidad' => $cantidad,
                ]);
            }

            $pdo->commit();
            redirigirAdmin(
                'Consumo actualizado únicamente para esta cita.',
                'success',
                '?cita=' . $idCita . '#insumos-cita'
            );

        case 'crear_servicio':
            $nombre = trim((string) ($_POST['nombre_servicio'] ?? ''));
            $consumo = array_key_exists('consumo_insumo', $_POST)
                ? enteroFormulario('consumo_insumo', 0, 1000000)
                : 0;
            if ($nombre === '' || mb_strlen($nombre) > 100 || $consumo === null) {
                redirigirAdmin('Revisa el nombre y el consumo configurado para el servicio.', 'error');
            }

            $consulta = $pdo->prepare(
                'INSERT INTO servicios (nombre_servicio, consumo_insumo) VALUES (:nombre, :consumo)'
            );
            $consulta->execute(['nombre' => $nombre, 'consumo' => $consumo]);
            redirigirAdmin('Servicio creado.');

        case 'actualizar_servicio':
            $idServicio = enteroFormulario('id_servicio', 1, PHP_INT_MAX);
            $nombre = trim((string) ($_POST['nombre_servicio'] ?? ''));
            $consumo = array_key_exists('consumo_insumo', $_POST)
                ? enteroFormulario('consumo_insumo', 0, 1000000)
                : null;
            if (
                $idServicio === null || $nombre === '' || mb_strlen($nombre) > 100
                || (array_key_exists('consumo_insumo', $_POST) && $consumo === null)
            ) {
                redirigirAdmin('Revisa los datos del servicio.', 'error');
            }

            $pdo->beginTransaction();
            $buscar = $pdo->prepare('SELECT consumo_insumo FROM servicios WHERE id_servicio = :id FOR UPDATE');
            $buscar->execute(['id' => $idServicio]);
            $servicio = $buscar->fetch();
            if (!$servicio) {
                throw new DomainException('No se encontró el servicio seleccionado.');
            }

            if ($consumo !== null && (int) $servicio['consumo_insumo'] !== $consumo) {
                $citasActivas = $pdo->prepare(
                    "SELECT COUNT(*) FROM citas WHERE id_servicio = :id AND estado = 'confirmada'"
                );
                $citasActivas->execute(['id' => $idServicio]);
                if ((int) $citasActivas->fetchColumn() > 0) {
                    throw new DomainException('No se puede cambiar el consumo mientras haya citas confirmadas para este servicio.');
                }
            }

            $actualizar = $pdo->prepare(
                'UPDATE servicios SET nombre_servicio = :nombre, consumo_insumo = :consumo WHERE id_servicio = :id'
            );
            $actualizar->execute([
                'nombre' => $nombre,
                'consumo' => $consumo ?? (int) $servicio['consumo_insumo'],
                'id' => $idServicio,
            ]);
            $pdo->commit();
            redirigirAdmin('Servicio actualizado.');

        default:
            redirigirAdmin('Acción administrativa no reconocida.', 'error');
    }
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $destino = $citaSeleccionadaParaRetorno === null
        ? ''
        : '?cita=' . $citaSeleccionadaParaRetorno . '#insumos-cita';
    redirigirAdmin($exception->getMessage(), 'error', $destino);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SAGER admin action failed: ' . $exception->getMessage());
    $destino = $citaSeleccionadaParaRetorno === null
        ? ''
        : '?cita=' . $citaSeleccionadaParaRetorno . '#insumos-cita';
    redirigirAdmin('No se pudo guardar el cambio. Revisa los datos e inténtalo nuevamente.', 'error', $destino);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SAGER unexpected admin action failure: ' . $exception->getMessage());
    $destino = $citaSeleccionadaParaRetorno === null
        ? ''
        : '?cita=' . $citaSeleccionadaParaRetorno . '#insumos-cita';
    redirigirAdmin('Ocurrió un error inesperado. Inténtalo nuevamente.', 'error', $destino);
}