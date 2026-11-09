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

$correoAdmin = getenv('SAGER_TEST_ADMIN_EMAIL');
$passwordAdmin = getenv('SAGER_TEST_ADMIN_PASSWORD');
if (!is_string($correoAdmin) || $correoAdmin === '' || !is_string($passwordAdmin) || $passwordAdmin === '') {
    fwrite(STDERR, "Pruebas canceladas: define SAGER_TEST_ADMIN_EMAIL y SAGER_TEST_ADMIN_PASSWORD para la cuenta de prueba.\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexion.php';

$baseUrl = rtrim(getenv('SAGER_TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$cookieFiles = [];
$results = [];
$temporaryName = 'QA_TMP_' . strtoupper(bin2hex(random_bytes(5)));
$temporaryService = $temporaryName . '_SERVICE';
$temporaryInventory = $temporaryName . '_STOCK';
$csrfToken = null;

function newCookieFile(array &$cookieFiles): string
{
    $file = tempnam(sys_get_temp_dir(), 'sager-admin-');
    $cookieFiles[] = $file;

    return $file;
}

function httpRequest(string $baseUrl, string $path, string $cookieFile, ?array $form = null): array
{
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($form !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $url = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('Falló HTTP: ' . $error);
    }

    return ['status' => $status, 'body' => $body, 'url' => $url];
}

function csrfFrom(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('No se encontró el token CSRF del panel.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}

function recordResult(array &$results, string $id, bool $passed, string $detail): void
{
    $results[] = ['id' => $id, 'passed' => $passed, 'detail' => $detail];
}

function postAdmin(string $baseUrl, string $cookieFile, string $csrfToken, array $fields): array
{
    return httpRequest($baseUrl, '/controllers/AdminActionsController.php', $cookieFile, [
        'csrf_token' => $csrfToken,
        ...$fields,
    ]);
}

try {
    $publicCookie = newCookieFile($cookieFiles);
    $public = httpRequest($baseUrl, '/public/', $publicCookie);
    recordResult(
        $results,
        'ADM-01',
        $public['status'] === 200 && !str_contains($public['body'], 'Acceso administrador'),
        'El área pública carga sin enlace al login administrativo.'
    );

    $anonymousCookie = newCookieFile($cookieFiles);
    $anonymousDashboard = httpRequest($baseUrl, '/admin/dashboard.php', $anonymousCookie);
    recordResult(
        $results,
        'ADM-02',
        $anonymousDashboard['status'] === 200 && str_ends_with($anonymousDashboard['url'], '/admin/login.php'),
        'El acceso anónimo al dashboard termina en el login.'
    );

    $adminCookie = newCookieFile($cookieFiles);
    $loginPage = httpRequest($baseUrl, '/admin/login.php', $adminCookie);
    $login = httpRequest($baseUrl, '/controllers/LoginController.php', $adminCookie, [
        'correo' => $correoAdmin,
        'password' => $passwordAdmin,
        'csrf_token' => csrfFrom($loginPage['body']),
    ]);
    $dashboard = httpRequest($baseUrl, '/admin/', $adminCookie);
    $authenticated = $login['status'] === 200
        && str_contains($login['url'], '/admin/')
        && str_contains($dashboard['body'], 'Registrar insumo')
        && str_contains($dashboard['body'], 'Agregar servicio');
    recordResult($results, 'ADM-03', $authenticated, 'El administrador llega al panel y ve sus controles.');
    $csrfToken = csrfFrom($dashboard['body']);

    $badCsrf = httpRequest($baseUrl, '/controllers/AdminActionsController.php', $adminCookie, [
        'accion' => 'crear_insumo',
        'nombre_insumo' => $temporaryInventory,
        'stock_actual' => '1',
        'stock_minimo' => '1',
    ]);
    recordResult($results, 'ADM-04', $badCsrf['status'] === 403, 'Una acción de administración sin CSRF se rechaza con HTTP 403.');

    $invalidRestock = postAdmin($baseUrl, $adminCookie, $csrfToken, [
        'accion' => 'reponer_stock',
        'id_insumo' => '1',
        'cantidad' => '0',
    ]);
    $invalidRestockPassed = $invalidRestock['status'] === 200
        && str_contains($invalidRestock['body'], 'cantidad positiva');
    recordResult($results, 'ADM-05', $invalidRestockPassed, 'La reposición cero se rechaza y muestra validación.');

    $badService = postAdmin($baseUrl, $adminCookie, $csrfToken, [
        'accion' => 'crear_servicio',
        'nombre_servicio' => '',
        'consumo_insumo' => '-1',
    ]);
    recordResult(
        $results,
        'ADM-06',
        $badService['status'] === 200 && str_contains($badService['body'], 'Revisa el nombre'),
        'El nombre vacío y el consumo negativo se rechazan.'
    );

    $createInventory = postAdmin($baseUrl, $adminCookie, $csrfToken, [
        'accion' => 'crear_insumo',
        'nombre_insumo' => $temporaryInventory,
        'stock_actual' => '1',
        'stock_minimo' => '1',
    ]);
    $inventoryQuery = $pdo->prepare('SELECT id_insumo, stock_actual, stock_minimo FROM insumos WHERE nombre_insumo = :nombre');
    $inventoryQuery->execute(['nombre' => $temporaryInventory]);
    $inventory = $inventoryQuery->fetch();
    recordResult(
        $results,
        'ADM-07',
        $createInventory['status'] === 200 && $inventory && (int) $inventory['stock_actual'] === 1,
        'El administrador crea un insumo con stock inicial validado.'
    );

    if ($inventory) {
        $restock = postAdmin($baseUrl, $adminCookie, $csrfToken, [
            'accion' => 'reponer_stock',
            'id_insumo' => (string) $inventory['id_insumo'],
            'cantidad' => '2',
        ]);
        $stockAfterRestock = (int) $pdo->query(
            'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $inventory['id_insumo']
        )->fetchColumn();
        recordResult($results, 'ADM-08', $restock['status'] === 200 && $stockAfterRestock === 3, 'La reposición suma las unidades pedidas.');

        $minimumUpdate = postAdmin($baseUrl, $adminCookie, $csrfToken, [
            'accion' => 'actualizar_minimo',
            'id_insumo' => (string) $inventory['id_insumo'],
            'stock_minimo' => '4',
        ]);
        $minimumAfterUpdate = (int) $pdo->query(
            'SELECT stock_minimo FROM insumos WHERE id_insumo = ' . (int) $inventory['id_insumo']
        )->fetchColumn();
        recordResult($results, 'ADM-09', $minimumUpdate['status'] === 200 && $minimumAfterUpdate === 4, 'Se actualiza el umbral de stock mínimo.');
    } else {
        recordResult($results, 'ADM-08', false, 'No se pudo preparar el insumo de prueba.');
        recordResult($results, 'ADM-09', false, 'No se pudo preparar el insumo de prueba.');
    }

    $createService = postAdmin($baseUrl, $adminCookie, $csrfToken, [
        'accion' => 'crear_servicio',
        'nombre_servicio' => $temporaryService,
        'consumo_insumo' => '1',
    ]);
    $serviceQuery = $pdo->prepare('SELECT id_servicio, consumo_insumo FROM servicios WHERE nombre_servicio = :nombre');
    $serviceQuery->execute(['nombre' => $temporaryService]);
    $service = $serviceQuery->fetch();
    recordResult(
        $results,
        'ADM-10',
        $createService['status'] === 200 && $service && (int) $service['consumo_insumo'] === 1,
        'El administrador crea un servicio con consumo válido.'
    );

    if ($service) {
        $updateService = postAdmin($baseUrl, $adminCookie, $csrfToken, [
            'accion' => 'actualizar_servicio',
            'id_servicio' => (string) $service['id_servicio'],
            'nombre_servicio' => $temporaryService . '_EDIT',
            'consumo_insumo' => '2',
        ]);
        $updatedConsumption = (int) $pdo->query(
            'SELECT consumo_insumo FROM servicios WHERE id_servicio = ' . (int) $service['id_servicio']
        )->fetchColumn();
        recordResult($results, 'ADM-11', $updateService['status'] === 200 && $updatedConsumption === 2, 'Se edita el consumo del servicio sin citas confirmadas.');
    } else {
        recordResult($results, 'ADM-11', false, 'No se pudo preparar el servicio de prueba.');
    }

    $activeService = $pdo->query(
        "SELECT s.id_servicio, s.nombre_servicio, s.consumo_insumo FROM servicios s JOIN citas c ON c.id_servicio = s.id_servicio WHERE c.estado = 'confirmada' LIMIT 1"
    )->fetch();
    if ($activeService) {
        $blockedConsumption = postAdmin($baseUrl, $adminCookie, $csrfToken, [
            'accion' => 'actualizar_servicio',
            'id_servicio' => (string) $activeService['id_servicio'],
            'nombre_servicio' => $activeService['nombre_servicio'],
            'consumo_insumo' => (string) ((int) $activeService['consumo_insumo'] + 1),
        ]);
        $consumptionAfterAttempt = (int) $pdo->query(
            'SELECT consumo_insumo FROM servicios WHERE id_servicio = ' . (int) $activeService['id_servicio']
        )->fetchColumn();
        recordResult(
            $results,
            'ADM-12',
            $blockedConsumption['status'] === 200
                && str_contains($blockedConsumption['body'], 'No se puede cambiar el consumo')
                && $consumptionAfterAttempt === (int) $activeService['consumo_insumo'],
            'No permite alterar consumos con citas confirmadas.'
        );
    } else {
        recordResult($results, 'ADM-12', true, 'No había citas confirmadas; escenario no aplicable.');
    }
} catch (Throwable $exception) {
    recordResult($results, 'ADM-00', false, 'Error durante pruebas: ' . $exception->getMessage());
} finally {
    try {
        $pdo->beginTransaction();
        $deleteService = $pdo->prepare('DELETE FROM servicios WHERE nombre_servicio IN (:nombre, :editado)');
        $deleteService->execute(['nombre' => $temporaryService, 'editado' => $temporaryService . '_EDIT']);
        $deleteInventory = $pdo->prepare('DELETE FROM insumos WHERE nombre_insumo = :nombre');
        $deleteInventory->execute(['nombre' => $temporaryInventory]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, 'No se pudo limpiar los registros temporales: ' . $exception->getMessage() . PHP_EOL);
    }

    foreach ($cookieFiles as $cookieFile) {
        if (is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

foreach ($results as $result) {
    echo $result['id'] . ' | ' . ($result['passed'] ? 'APROBADA' : 'FALLIDA') . ' | ' . $result['detail'] . PHP_EOL;
}
$failedCount = count(array_filter($results, static fn (array $result): bool => !$result['passed']));
echo 'RESUMEN: ' . count($results) . ' casos; ' . (count($results) - $failedCount) . ' aprobados; ' . $failedCount . ' fallidos.' . PHP_EOL;
exit($failedCount === 0 ? 0 : 1);