<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configuredDatabase = getenv('DB_NAME');
$testDatabase = getenv('SAGER_TEST_DB_NAME');
if (
    getenv('SAGER_TEST_ALLOW_MUTATIONS') !== '1'
    || !is_string($configuredDatabase)
    || $configuredDatabase !== $testDatabase
    || !str_ends_with($configuredDatabase, '_test')
) {
    fwrite(STDERR, "Pruebas canceladas: configura una base aislada con sufijo _test y SAGER_TEST_ALLOW_MUTATIONS=1.\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexion.php';

$baseUrl = rtrim(getenv('SAGER_TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'sager-reserva-test-');
if ($cookieFile === false) {
    throw new RuntimeException('No se pudo crear el almacén temporal de cookies.');
}

$resultados = [];
$correo = 'qa-reserva-' . bin2hex(random_bytes(6)) . '@example.test';
$nombre = 'QA reserva automatizada';
$fecha = (new DateTimeImmutable('+5 days'))->format('Y-m-d');
$minuto = random_int(0, 1438);
$hora = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
$minutoDuplicado = ($minuto + 1) % 1440;
$horaAlterna = sprintf('%02d:%02d', intdiv($minutoDuplicado, 60), $minutoDuplicado % 60);
$idServicio = 1;
$idInsumo = null;
$idInsumoExtra = null;
$nombreInsumoExtra = 'QA-RESERVA-' . strtoupper(bin2hex(random_bytes(5)));
$stockOriginales = [];
$stockAntesReserva = [];
$stockServicioOriginales = [];
$stockServicioAntesReserva = [];
$insumosServicio = [];
$idCitaPrueba = null;
$citasIniciales = null;

function requestSager(string $baseUrl, string $path, string $cookieFile, ?array $form = null): array
{
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    if ($form !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('Falló la solicitud HTTP: ' . $error);
    }

    return ['status' => $status, 'body' => $body];
}

function addResult(array &$results, string $id, bool $passed, string $detail): void
{
    $results[] = ['id' => $id, 'passed' => $passed, 'detail' => $detail];
}

function stockSnapshot(PDO $pdo, array $idInsumos): array
{
    $snapshot = [];
    $consulta = $pdo->prepare('SELECT stock_actual FROM insumos WHERE id_insumo = :id');
    foreach ($idInsumos as $idInsumo) {
        $consulta->execute(['id' => $idInsumo]);
        $stock = $consulta->fetchColumn();
        if ($stock === false) {
            throw new RuntimeException('No se encontró un insumo del escenario de reserva.');
        }
        $snapshot[(int) $idInsumo] = (int) $stock;
    }

    return $snapshot;
}

function serviceStockSnapshot(PDO $pdo, int $idServicio, array $idInsumos): array
{
    $snapshot = [];
    $consulta = $pdo->prepare(
        'SELECT stock_actual FROM servicio_insumo_stock
         WHERE id_servicio = :servicio AND id_insumo = :insumo'
    );
    foreach ($idInsumos as $idInsumo) {
        $consulta->execute(['servicio' => $idServicio, 'insumo' => $idInsumo]);
        $stock = $consulta->fetchColumn();
        if ($stock === false) {
            throw new RuntimeException('No se encontró el stock asignado al servicio de prueba.');
        }
        $snapshot[(int) $idInsumo] = (int) $stock;
    }

    return $snapshot;
}

function appointmentIngredients(PDO $pdo, int $idCita): array
{
    $consulta = $pdo->prepare(
        'SELECT id_insumo, cantidad FROM cita_insumos WHERE id_cita = :id ORDER BY id_insumo'
    );
    $consulta->execute(['id' => $idCita]);
    $consumos = [];
    foreach ($consulta->fetchAll() as $relacion) {
        $consumos[(int) $relacion['id_insumo']] = (int) $relacion['cantidad'];
    }

    return $consumos;
}

try {
    if (strtolower((string) $pdo->query('SELECT DATABASE()')->fetchColumn()) !== strtolower($configuredDatabase)) {
        throw new RuntimeException('La conexión PDO no apunta a la base de pruebas declarada.');
    }

    $citasIniciales = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    $servicio = $pdo->prepare('SELECT id_servicio FROM servicios WHERE id_servicio = :id');
    $servicio->execute(['id' => $idServicio]);
    $servicioExiste = $servicio->fetchColumn();
    $consultaInsumosServicio = $pdo->prepare(
        'SELECT si.id_insumo, si.cantidad
         FROM servicio_insumos si
         WHERE si.id_servicio = :id
         ORDER BY si.id_insumo'
    );
    $consultaInsumosServicio->execute(['id' => $idServicio]);
    foreach ($consultaInsumosServicio->fetchAll() as $relacion) {
        $insumosServicio[(int) $relacion['id_insumo']] = (int) $relacion['cantidad'];
    }

    if ($servicioExiste === false || $insumosServicio === []) {
        throw new RuntimeException('La semilla de pruebas debe incluir un servicio con insumos requeridos.');
    }

    $idInsumo = (int) array_key_first($insumosServicio);
    $stockOriginales = stockSnapshot($pdo, array_keys($insumosServicio));
    $crearStockServicio = $pdo->prepare(
        'INSERT IGNORE INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
         VALUES (:servicio, :insumo, 0)'
    );
    foreach (array_keys($insumosServicio) as $id) {
        $crearStockServicio->execute(['servicio' => $idServicio, 'insumo' => $id]);
    }
    $stockServicioOriginales = serviceStockSnapshot($pdo, $idServicio, array_keys($insumosServicio));
    $crearInsumoExtra = $pdo->prepare(
        'INSERT INTO insumos (nombre_insumo, stock_actual, stock_minimo) VALUES (:nombre, 50, 1)'
    );
    $crearInsumoExtra->execute(['nombre' => $nombreInsumoExtra]);
    $idInsumoExtra = (int) $pdo->lastInsertId();
    $asignarInsumoExtra = $pdo->prepare(
        'INSERT INTO servicio_insumos (id_servicio, id_insumo, cantidad) VALUES (:servicio, :insumo, 2)'
    );
    $asignarInsumoExtra->execute(['servicio' => $idServicio, 'insumo' => $idInsumoExtra]);
    $crearStockServicio->execute(['servicio' => $idServicio, 'insumo' => $idInsumoExtra]);
    $insumosServicio[$idInsumoExtra] = 2;

    $stockOriginales[$idInsumoExtra] = 50;
    $prepararStock = $pdo->prepare('UPDATE insumos SET stock_actual = :stock WHERE id_insumo = :id');
    $prepararStockServicio = $pdo->prepare(
        'UPDATE servicio_insumo_stock
         SET stock_actual = :stock
         WHERE id_servicio = :servicio AND id_insumo = :insumo'
    );
    foreach ($insumosServicio as $id => $cantidad) {
        $stockAntesReserva[$id] = max($stockOriginales[$id], $cantidad + 10);
        $prepararStock->execute(['stock' => $stockAntesReserva[$id], 'id' => $id]);
        $stockServicioAntesReserva[$id] = max($stockServicioOriginales[$id] ?? 0, $cantidad + 10);
        $prepararStockServicio->execute([
            'stock' => $stockServicioAntesReserva[$id],
            'servicio' => $idServicio,
            'insumo' => $id,
        ]);
    }

    $pagina = requestSager($baseUrl, '/public/', $cookieFile);
    $tokenEncontrado = preg_match('/name="csrf_token" value="([^"]+)"/', $pagina['body'], $coincidencias);
    $token = $tokenEncontrado === 1 ? html_entity_decode($coincidencias[1], ENT_QUOTES, 'UTF-8') : '';
    addResult(
        $resultados,
        'RES-00',
        $pagina['status'] === 200 && $token !== '',
        'La página pública carga y entrega un token CSRF.'
    );

    if ($token === '') {
        throw new RuntimeException('No se obtuvo el token CSRF de la página pública.');
    }

    $reserva = [
        'csrf_token' => $token,
        'nombre' => $nombre,
        'correo' => $correo,
        'id_servicio' => (string) $idServicio,
        'fecha_cita' => $fecha,
        'hora_cita' => $hora,
    ];

    $respuestaReserva = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $reserva);
    $cuerpoReserva = json_decode($respuestaReserva['body'], true);
    $idCitaPrueba = is_array($cuerpoReserva) && isset($cuerpoReserva['id_cita'])
        ? (int) $cuerpoReserva['id_cita']
        : null;
    $stockEsperadoTrasReserva = $stockAntesReserva;
    foreach ($insumosServicio as $id => $cantidad) {
        $stockEsperadoTrasReserva[$id] -= $cantidad;
    }
    $stockTrasReserva = stockSnapshot($pdo, array_keys($insumosServicio));
    $stockServicioEsperadoTrasReserva = $stockServicioAntesReserva;
    foreach ($insumosServicio as $id => $cantidad) {
        $stockServicioEsperadoTrasReserva[$id] -= $cantidad;
    }
    $stockServicioTrasReserva = serviceStockSnapshot($pdo, $idServicio, array_keys($insumosServicio));
    $citasTrasReserva = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    addResult(
        $resultados,
        'RES-01',
        $respuestaReserva['status'] === 201
            && $idCitaPrueba !== null
            && $citasTrasReserva === $citasIniciales + 1
            && $stockTrasReserva === $stockEsperadoTrasReserva
            && $stockServicioTrasReserva === $stockServicioEsperadoTrasReserva
            && appointmentIngredients($pdo, $idCitaPrueba) === $insumosServicio,
        'Una reserva descuenta el stock general y el asignado a su servicio y guarda el consumo por cita.'
    );

    $respuestaDuplicado = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $reserva);
    $stockTrasDuplicado = stockSnapshot($pdo, array_keys($insumosServicio));
    $stockServicioTrasDuplicado = serviceStockSnapshot($pdo, $idServicio, array_keys($insumosServicio));
    $citasTrasDuplicado = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    addResult(
        $resultados,
        'RES-02',
        $respuestaDuplicado['status'] === 409
            && $citasTrasDuplicado === $citasIniciales + 1
            && $stockTrasDuplicado === $stockTrasReserva
            && $stockServicioTrasDuplicado === $stockServicioTrasReserva
            && $idCitaPrueba !== null
            && appointmentIngredients($pdo, $idCitaPrueba) === $insumosServicio,
        'El horario duplicado se rechaza sin crear otra cita ni volver a descontar insumos.'
    );

    $datosInvalidos = $reserva;
    $datosInvalidos['correo'] = 'correo-no-valido';
    $respuestaInvalida = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $datosInvalidos);
    addResult(
        $resultados,
        'RES-03',
        $respuestaInvalida['status'] === 422
            && (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn() === $citasIniciales + 1
            && stockSnapshot($pdo, array_keys($insumosServicio)) === $stockTrasReserva
            && serviceStockSnapshot($pdo, $idServicio, array_keys($insumosServicio)) === $stockServicioTrasReserva,
        'Una dirección de correo inválida responde HTTP 422 sin cambios persistidos.'
    );

    $datosSinCsrf = $reserva;
    $datosSinCsrf['csrf_token'] = 'invalid-token';
    $respuestaCsrf = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $datosSinCsrf);
    addResult(
        $resultados,
        'RES-04',
        $respuestaCsrf['status'] === 419
            && (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn() === $citasIniciales + 1,
        'Una solicitud con token CSRF inválido se rechaza antes de escribir.'
    );

    $actualizarStock = $pdo->prepare('UPDATE insumos SET stock_actual = 0 WHERE id_insumo = :id');
    $actualizarStock->execute(['id' => $idInsumo]);
    $reservaSinStock = $reserva;
    $reservaSinStock['hora_cita'] = $horaAlterna;
    $respuestaSinStock = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $reservaSinStock);
    $citasTrasSinStock = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    $stockTrasSinStock = stockSnapshot($pdo, array_keys($insumosServicio));
    $stockServicioTrasSinStock = serviceStockSnapshot($pdo, $idServicio, array_keys($insumosServicio));
    $stockEsperadoSinStock = $stockTrasReserva;
    $stockEsperadoSinStock[$idInsumo] = 0;
    $snapshotTrasFaltaStock = $idCitaPrueba === null
        ? []
        : appointmentIngredients($pdo, $idCitaPrueba);
    addResult(
        $resultados,
        'RES-05',
        $respuestaSinStock['status'] === 409
            && $citasTrasSinStock === $citasIniciales + 1
            && $stockTrasSinStock === $stockEsperadoSinStock
            && $stockServicioTrasSinStock === $stockServicioTrasReserva
            && $idCitaPrueba !== null
            && $snapshotTrasFaltaStock === $insumosServicio,
        'La falta de un solo insumo rechaza la reserva sin crear una cita ni descontar los demás.'
    );
} catch (Throwable $exception) {
    addResult($resultados, 'RES-ERR', false, $exception->getMessage());
} finally {
    try {
        if ($stockOriginales !== [] || $idInsumoExtra !== null) {
            $pdo->beginTransaction();
            $buscarCliente = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo');
            $buscarCliente->execute(['correo' => $correo]);
            $idCliente = $buscarCliente->fetchColumn();

            if ($idCliente !== false) {
                $eliminarCita = $pdo->prepare(
                    'DELETE FROM citas WHERE id_cliente = :cliente AND fecha_cita = :fecha AND hora_cita = :hora'
                );
                $eliminarCita->execute([
                    'cliente' => $idCliente,
                    'fecha' => $fecha,
                    'hora' => $hora . ':00',
                ]);
                $eliminarCliente = $pdo->prepare(
                    'DELETE FROM usuarios WHERE id_usuario = :id AND correo = :correo'
                );
                $eliminarCliente->execute(['id' => $idCliente, 'correo' => $correo]);
            }

            $restaurarStock = $pdo->prepare('UPDATE insumos SET stock_actual = :stock WHERE id_insumo = :id');
            $restaurarStockServicio = $pdo->prepare(
                'UPDATE servicio_insumo_stock
                 SET stock_actual = :stock
                 WHERE id_servicio = :servicio AND id_insumo = :insumo'
            );
            foreach ($stockOriginales as $id => $stock) {
                if ($id === $idInsumoExtra) {
                    continue;
                }
                $restaurarStock->execute(['stock' => $stock, 'id' => $id]);
                $restaurarStockServicio->execute([
                    'stock' => $stockServicioOriginales[$id],
                    'servicio' => $idServicio,
                    'insumo' => $id,
                ]);
            }

            if ($idInsumoExtra !== null) {
                $eliminarInsumoExtra = $pdo->prepare('DELETE FROM insumos WHERE id_insumo = :id');
                $eliminarInsumoExtra->execute(['id' => $idInsumoExtra]);
            }
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        addResult($resultados, 'RES-CLEANUP', false, 'No se pudo restaurar la semilla: ' . $exception->getMessage());
    }

    if (is_file($cookieFile) && !unlink($cookieFile)) {
        addResult($resultados, 'RES-CLEANUP', false, 'No se pudo eliminar la cookie temporal.');
    }
}

foreach ($resultados as $resultado) {
    echo $resultado['id'] . ' | ' . ($resultado['passed'] ? 'APROBADA' : 'FALLIDA')
        . ' | ' . $resultado['detail'] . PHP_EOL;
}

$fallas = count(array_filter($resultados, static fn (array $resultado): bool => !$resultado['passed']));
echo 'RESUMEN: ' . count($resultados) . ' casos; '
    . (count($resultados) - $fallas) . ' aprobados; ' . $fallas . ' fallidos.' . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
