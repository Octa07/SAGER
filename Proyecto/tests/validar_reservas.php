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
$stockInicial = null;
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

try {
    if (strtolower((string) $pdo->query('SELECT DATABASE()')->fetchColumn()) !== strtolower($configuredDatabase)) {
        throw new RuntimeException('La conexión PDO no apunta a la base de pruebas declarada.');
    }

    $idInsumo = $pdo->query('SELECT id_insumo FROM insumos ORDER BY id_insumo ASC LIMIT 1')->fetchColumn();
    $stockInicial = $idInsumo === false ? null : (int) $pdo->query(
        'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumo
    )->fetchColumn();
    $citasIniciales = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    $servicio = $pdo->prepare('SELECT consumo_insumo FROM servicios WHERE id_servicio = :id');
    $servicio->execute(['id' => $idServicio]);
    $consumo = $servicio->fetchColumn();

    if ($idInsumo === false || $stockInicial === null || $consumo === false) {
        throw new RuntimeException('La semilla de pruebas debe incluir al menos un servicio y un insumo.');
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
    $stockTrasReserva = (int) $pdo->query(
        'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumo
    )->fetchColumn();
    $citasTrasReserva = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    addResult(
        $resultados,
        'RES-01',
        $respuestaReserva['status'] === 201
            && $citasTrasReserva === $citasIniciales + 1
            && $stockTrasReserva === $stockInicial - (int) $consumo,
        'Una reserva válida crea una cita y descuenta exactamente el consumo configurado.'
    );

    $respuestaDuplicado = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $reserva);
    $stockTrasDuplicado = (int) $pdo->query(
        'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumo
    )->fetchColumn();
    $citasTrasDuplicado = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    addResult(
        $resultados,
        'RES-02',
        $respuestaDuplicado['status'] === 409
            && $citasTrasDuplicado === $citasIniciales + 1
            && $stockTrasDuplicado === $stockTrasReserva,
        'El horario duplicado se rechaza sin insertar otra cita ni repetir el descuento.'
    );

    $datosInvalidos = $reserva;
    $datosInvalidos['correo'] = 'correo-no-valido';
    $respuestaInvalida = requestSager($baseUrl, '/controllers/CitasController.php', $cookieFile, $datosInvalidos);
    addResult(
        $resultados,
        'RES-03',
        $respuestaInvalida['status'] === 422
            && (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn() === $citasIniciales + 1
            && (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumo
            )->fetchColumn() === $stockTrasReserva,
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
    $stockTrasSinStock = (int) $pdo->query(
        'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumo
    )->fetchColumn();
    addResult(
        $resultados,
        'RES-05',
        $respuestaSinStock['status'] === 409
            && $citasTrasSinStock === $citasIniciales + 1
            && $stockTrasSinStock === 0,
        'El inventario insuficiente rechaza la reserva sin crear una cita ni stock negativo.'
    );
} catch (Throwable $exception) {
    addResult($resultados, 'RES-ERR', false, $exception->getMessage());
} finally {
    try {
        if ($idInsumo !== null && $stockInicial !== null) {
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

            $restaurarStock = $pdo->prepare(
                'UPDATE insumos SET stock_actual = :stock WHERE id_insumo = :id'
            );
            $restaurarStock->execute(['stock' => $stockInicial, 'id' => $idInsumo]);
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
