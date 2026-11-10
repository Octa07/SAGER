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
$temporaryUnassignedInventory = $temporaryName . '_UNASSIGNED';
$idCitaPrueba = null;
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
        $createInventory['status'] === 200
            && $inventory
            && (int) $inventory['stock_actual'] === 1
            && str_contains($createInventory['body'], 'id="assignment-ingredient"')
            && str_contains($createInventory['body'], 'id="stock-ingredient"')
            && str_contains($createInventory['body'], $temporaryInventory),
        'El insumo nuevo aparece para asignarse y para reponer stock.'
    );

    if ($inventory) {
        $stockServiceId = $pdo->query('SELECT id_servicio FROM servicios ORDER BY id_servicio LIMIT 1')->fetchColumn();
        if ($stockServiceId !== false) {
            $restock = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'reponer_stock',
                'id_insumo' => (string) $inventory['id_insumo'],
                'id_servicio' => (string) $stockServiceId,
                'cantidad' => '2',
            ]);
            $stockAfterRestock = (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $inventory['id_insumo']
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-08',
                $restock['status'] === 200
                    && str_contains($restock['body'], 'no está asignado al servicio')
                    && $stockAfterRestock === 1,
                'La reposición rechaza insumos que no están asignados al servicio.'
            );
        } else {
            recordResult($results, 'ADM-08', false, 'No hay un servicio disponible para probar la asignación de stock.');
        }

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
    $serviceQuery = $pdo->prepare(
        'SELECT id_servicio, consumo_insumo, insumos_configurados FROM servicios WHERE nombre_servicio = :nombre'
    );
    $serviceQuery->execute(['nombre' => $temporaryService]);
    $service = $serviceQuery->fetch();
    recordResult(
        $results,
        'ADM-10',
        $createService['status'] === 200 && $service
            && (int) $service['consumo_insumo'] === 1
            && (int) $service['insumos_configurados'] === 0,
        'Un servicio nuevo queda pendiente de configurar sus insumos.'
    );

    $createUnassignedInventory = postAdmin($baseUrl, $adminCookie, $csrfToken, [
        'accion' => 'crear_insumo',
        'nombre_insumo' => $temporaryUnassignedInventory,
        'stock_actual' => '5',
        'stock_minimo' => '1',
    ]);
    $unassignedInventoryQuery = $pdo->prepare(
        'SELECT id_insumo FROM insumos WHERE nombre_insumo = :nombre'
    );
    $unassignedInventoryQuery->execute(['nombre' => $temporaryUnassignedInventory]);
    $unassignedInventoryId = $unassignedInventoryQuery->fetchColumn();

    if ($service) {
        if ($inventory) {
            $updateServiceIngredients = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'asignar_insumo_servicio',
                'id_servicio' => (string) $service['id_servicio'],
                'id_insumo' => (string) $inventory['id_insumo'],
                'cantidad' => '3',
            ]);
            $serviceIngredientQuantity = $pdo->prepare(
                'SELECT cantidad FROM servicio_insumos WHERE id_servicio = :servicio AND id_insumo = :insumo'
            );
            $serviceIngredientQuantity->execute([
                'servicio' => $service['id_servicio'],
                'insumo' => $inventory['id_insumo'],
            ]);
            $serviceStockAfterAssignment = (int) $pdo->query(
                'SELECT stock_actual FROM servicio_insumo_stock WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . (int) $inventory['id_insumo']
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-11',
                $updateServiceIngredients['status'] === 200
                    && (int) $serviceIngredientQuantity->fetchColumn() === 3
                    && $serviceStockAfterAssignment === 0
                    && str_contains(
                        $updateServiceIngredients['body'],
                        'id="service-stock-' . (int) $service['id_servicio'] . '"'
                    )
                    && str_contains($updateServiceIngredients['body'], $temporaryInventory),
                'El servicio muestra su tarjeta con el insumo asignado y su stock correspondiente.'
            );

            $restockAssignedIngredient = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'reponer_stock',
                'id_insumo' => (string) $inventory['id_insumo'],
                'id_servicio' => (string) $service['id_servicio'],
                'cantidad' => '2',
            ]);
            $stockAfterAssignedRestock = (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $inventory['id_insumo']
            )->fetchColumn();
            $serviceStockAfterAssignedRestock = (int) $pdo->query(
                'SELECT stock_actual FROM servicio_insumo_stock WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . (int) $inventory['id_insumo']
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-15',
                $restockAssignedIngredient['status'] === 200
                    && $stockAfterAssignedRestock === 3
                    && $serviceStockAfterAssignedRestock === 2,
                'La reposición suma al stock general y al del servicio cuando el insumo sí está asignado.'
            );

            $zeroServiceIngredient = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'asignar_insumo_servicio',
                'id_servicio' => (string) $service['id_servicio'],
                'id_insumo' => (string) $inventory['id_insumo'],
                'cantidad' => '0',
            ]);
            $quantityAfterZeroAttempt = (int) $pdo->query(
                'SELECT cantidad FROM servicio_insumos WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . (int) $inventory['id_insumo']
            )->fetchColumn();
            $serviceConfigured = (int) $pdo->query(
                'SELECT insumos_configurados FROM servicios WHERE id_servicio = ' . (int) $service['id_servicio']
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-12',
                $zeroServiceIngredient['status'] === 200
                    && str_contains($zeroServiceIngredient['body'], 'cantidad positiva')
                    && $quantityAfterZeroAttempt === 3
                    && $serviceConfigured === 1,
                'La asignación solo agrega insumos con cantidad positiva y no los elimina con cero.'
            );
        } else {
            recordResult($results, 'ADM-11', false, 'No se pudo preparar el insumo de prueba.');
            recordResult($results, 'ADM-12', false, 'No se pudo preparar el insumo de prueba.');
        }

        $updateService = postAdmin($baseUrl, $adminCookie, $csrfToken, [
            'accion' => 'actualizar_servicio',
            'id_servicio' => (string) $service['id_servicio'],
            'nombre_servicio' => $temporaryService . '_EDIT',
            'consumo_insumo' => '2',
        ]);
        $updatedConsumption = (int) $pdo->query(
            'SELECT consumo_insumo FROM servicios WHERE id_servicio = ' . (int) $service['id_servicio']
        )->fetchColumn();
        recordResult($results, 'ADM-13', $updateService['status'] === 200 && $updatedConsumption === 2, 'Se edita el servicio sin citas confirmadas.');
    } else {
        recordResult($results, 'ADM-11', false, 'No se pudo preparar el servicio de prueba.');
        recordResult($results, 'ADM-12', false, 'No se pudo preparar el servicio de prueba.');
        recordResult($results, 'ADM-13', false, 'No se pudo preparar el servicio de prueba.');
    }

    if ($inventory && $service) {
        $adminQuery = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo');
        $adminQuery->execute(['correo' => $correoAdmin]);
        $adminId = $adminQuery->fetchColumn();
        $fechaCitaPrueba = null;
        $horaCitaPrueba = null;
        for ($intento = 0; $intento < 10; $intento++) {
            $fechaCitaPrueba = (new DateTimeImmutable('+' . random_int(300, 5000) . ' days'))->format('Y-m-d');
            $horaCitaPrueba = sprintf('%02d:%02d:00', random_int(0, 23), random_int(0, 59));
            $buscarHorario = $pdo->prepare(
                'SELECT 1 FROM citas WHERE fecha_cita = :fecha AND hora_cita = :hora'
            );
            $buscarHorario->execute(['fecha' => $fechaCitaPrueba, 'hora' => $horaCitaPrueba]);
            if (!$buscarHorario->fetchColumn()) {
                break;
            }
            $fechaCitaPrueba = null;
            $horaCitaPrueba = null;
        }

        if ($adminId !== false && $fechaCitaPrueba !== null && $horaCitaPrueba !== null) {
            $crearCitaPrueba = $pdo->prepare(
                "INSERT INTO citas (id_cliente, id_servicio, fecha_cita, hora_cita, estado)
                 VALUES (:cliente, :servicio, :fecha, :hora, 'confirmada')"
            );
            $crearCitaPrueba->execute([
                'cliente' => $adminId,
                'servicio' => $service['id_servicio'],
                'fecha' => $fechaCitaPrueba,
                'hora' => $horaCitaPrueba,
            ]);
            $idCitaPrueba = (int) $pdo->lastInsertId();
            $idInsumoPrueba = (int) $inventory['id_insumo'];
            $pdo->prepare(
                'INSERT INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
                 VALUES (:servicio, :insumo, 2)'
            )->execute([
                'servicio' => $service['id_servicio'],
                'insumo' => $idInsumoPrueba,
            ]);
            if ($unassignedInventoryId !== false) {
                $pdo->prepare(
                    'INSERT INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
                     VALUES (:servicio, :insumo, 2)'
                )->execute([
                    'servicio' => $service['id_servicio'],
                    'insumo' => $unassignedInventoryId,
                ]);
                $pdo->prepare(
                    'INSERT INTO cita_insumos (id_cita, id_insumo, cantidad) VALUES (:cita, :insumo, 1)'
                )->execute([
                    'cita' => $idCitaPrueba,
                    'insumo' => $unassignedInventoryId,
                ]);
            }
            $registrarConsumoInicial = $pdo->prepare(
                'INSERT INTO cita_insumos (id_cita, id_insumo, cantidad) VALUES (:cita, :insumo, 1)'
            );
            $registrarConsumoInicial->execute(['cita' => $idCitaPrueba, 'insumo' => $idInsumoPrueba]);
            $pdo->prepare('UPDATE insumos SET stock_actual = 2 WHERE id_insumo = :id')
                ->execute(['id' => $idInsumoPrueba]);
            $dashboardWithSelectedAppointment = httpRequest(
                $baseUrl,
                '/admin/dashboard.php?cita=' . $idCitaPrueba,
                $adminCookie
            );
            $appointmentIngredientOption = 'value="' . $idInsumoPrueba . '"';
            $unassignedIngredientOption = 'value="' . (int) $unassignedInventoryId . '"';
            recordResult(
                $results,
                'ADM-18',
                $createUnassignedInventory['status'] === 200
                    && $unassignedInventoryId !== false
                    && str_contains($dashboardWithSelectedAppointment['body'], 'name="id_insumo"')
                    && str_contains($dashboardWithSelectedAppointment['body'], $appointmentIngredientOption)
                    && str_contains($dashboardWithSelectedAppointment['body'], $unassignedIngredientOption)
                    && str_contains($dashboardWithSelectedAppointment['body'], $temporaryInventory)
                    && str_contains($dashboardWithSelectedAppointment['body'], $temporaryUnassignedInventory),
                'La edición fija el servicio, muestra los consumos previos y permite elegir un insumo por vez.'
            );

            if ($unassignedInventoryId !== false) {
                $removePreviousUnassignedIngredient = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                    'accion' => 'actualizar_insumos_cita',
                    'id_cita' => (string) $idCitaPrueba,
                    'id_insumo' => (string) $unassignedInventoryId,
                    'cantidad' => '0',
                ]);
                $previousUnassignedConsumption = $pdo->prepare(
                    'SELECT cantidad FROM cita_insumos WHERE id_cita = :cita AND id_insumo = :insumo'
                );
                $previousUnassignedConsumption->execute([
                    'cita' => $idCitaPrueba,
                    'insumo' => $unassignedInventoryId,
                ]);
                $assignedConsumptionAfterRemoval = (int) $pdo->query(
                    'SELECT cantidad FROM cita_insumos WHERE id_cita = ' . $idCitaPrueba
                    . ' AND id_insumo = ' . $idInsumoPrueba
                )->fetchColumn();
                recordResult(
                    $results,
                    'ADM-20',
                    $removePreviousUnassignedIngredient['status'] === 200
                        && $previousUnassignedConsumption->fetchColumn() === false
                        && $assignedConsumptionAfterRemoval === 1,
                    'Quitar un insumo conserva los demás consumos registrados en la cita.'
                );
            } else {
                recordResult($results, 'ADM-20', false, 'No se pudo preparar el insumo previo de la cita.');
            }

            $increaseAppointmentConsumption = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'actualizar_insumos_cita',
                'id_cita' => (string) $idCitaPrueba,
                'id_insumo' => (string) $idInsumoPrueba,
                'cantidad' => '2',
            ]);
            $stockAfterIncrease = (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $serviceStockAfterIncrease = (int) $pdo->query(
                'SELECT stock_actual FROM servicio_insumo_stock WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $quantityAfterIncrease = (int) $pdo->query(
                'SELECT cantidad FROM cita_insumos WHERE id_cita = ' . $idCitaPrueba
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-14',
                $increaseAppointmentConsumption['status'] === 200
                    && $stockAfterIncrease === 1
                    && $serviceStockAfterIncrease === 1
                    && $quantityAfterIncrease === 2,
                'Aumentar el consumo descuenta del stock general y el saldo del servicio.'
            );

            $decreaseAppointmentConsumption = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'actualizar_insumos_cita',
                'id_cita' => (string) $idCitaPrueba,
                'id_insumo' => (string) $idInsumoPrueba,
                'cantidad' => '1',
            ]);
            $stockAfterDecrease = (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $serviceStockAfterDecrease = (int) $pdo->query(
                'SELECT stock_actual FROM servicio_insumo_stock WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $quantityAfterDecrease = (int) $pdo->query(
                'SELECT cantidad FROM cita_insumos WHERE id_cita = ' . $idCitaPrueba
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-15',
                $decreaseAppointmentConsumption['status'] === 200
                    && $stockAfterDecrease === 2
                    && $serviceStockAfterDecrease === 2
                    && $quantityAfterDecrease === 1,
                'Reducir el consumo repone la diferencia en ambos saldos de stock.'
            );

            $insufficientAppointmentStock = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                'accion' => 'actualizar_insumos_cita',
                'id_cita' => (string) $idCitaPrueba,
                'id_insumo' => (string) $idInsumoPrueba,
                'cantidad' => '1000000',
            ]);
            $stockAfterRejectedChange = (int) $pdo->query(
                'SELECT stock_actual FROM insumos WHERE id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $serviceStockAfterRejectedChange = (int) $pdo->query(
                'SELECT stock_actual FROM servicio_insumo_stock WHERE id_servicio = '
                . (int) $service['id_servicio'] . ' AND id_insumo = ' . $idInsumoPrueba
            )->fetchColumn();
            $quantityAfterRejectedChange = (int) $pdo->query(
                'SELECT cantidad FROM cita_insumos WHERE id_cita = ' . $idCitaPrueba
            )->fetchColumn();
            recordResult(
                $results,
                'ADM-16',
                $insufficientAppointmentStock['status'] === 200
                    && str_contains($insufficientAppointmentStock['body'], 'No hay suficientes unidades')
                    && $stockAfterRejectedChange === 2
                    && $serviceStockAfterRejectedChange === 2
                    && $quantityAfterRejectedChange === 1,
                'Un consumo mayor al stock del servicio se rechaza sin modificar la cita ni los saldos.'
            );

            if ($unassignedInventoryId !== false) {
                $unassignedAppointmentIngredient = postAdmin($baseUrl, $adminCookie, $csrfToken, [
                    'accion' => 'actualizar_insumos_cita',
                    'id_cita' => (string) $idCitaPrueba,
                    'id_insumo' => (string) $unassignedInventoryId,
                    'cantidad' => '1',
                ]);
                $stockAfterUnassignedAttempt = (int) $pdo->query(
                    'SELECT stock_actual FROM insumos WHERE id_insumo = ' . (int) $idInsumoPrueba
                )->fetchColumn();
                $appointmentQuantityAfterUnassignedAttempt = (int) $pdo->query(
                    'SELECT cantidad FROM cita_insumos WHERE id_cita = ' . $idCitaPrueba
                    . ' AND id_insumo = ' . $idInsumoPrueba
                )->fetchColumn();
                recordResult(
                    $results,
                    'ADM-19',
                    $unassignedAppointmentIngredient['status'] === 200
                        && str_contains($unassignedAppointmentIngredient['body'], 'no está asignado al servicio')
                        && $stockAfterUnassignedAttempt === 2
                        && $appointmentQuantityAfterUnassignedAttempt === 1,
                    'El servidor bloquea insumos no asignados sin modificar la cita ni su stock.'
                );
            } else {
                recordResult($results, 'ADM-19', false, 'No se pudo crear el insumo no asignado para la prueba.');
            }
        } else {
            recordResult($results, 'ADM-14', false, 'No se encontró un horario libre para la cita de prueba.');
            recordResult($results, 'ADM-15', false, 'No se encontró un horario libre para la cita de prueba.');
            recordResult($results, 'ADM-16', false, 'No se encontró un horario libre para la cita de prueba.');
        }
    } else {
        recordResult($results, 'ADM-14', false, 'No se pudo preparar la cita de prueba.');
        recordResult($results, 'ADM-15', false, 'No se pudo preparar la cita de prueba.');
        recordResult($results, 'ADM-16', false, 'No se pudo preparar la cita de prueba.');
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
            'ADM-17',
            $blockedConsumption['status'] === 200
                && str_contains($blockedConsumption['body'], 'No se puede cambiar el consumo')
                && $consumptionAfterAttempt === (int) $activeService['consumo_insumo'],
            'No permite alterar consumos con citas confirmadas.'
        );
    } else {
        recordResult($results, 'ADM-17', true, 'No había citas confirmadas; escenario no aplicable.');
    }
} catch (Throwable $exception) {
    recordResult($results, 'ADM-00', false, 'Error durante pruebas: ' . $exception->getMessage());
} finally {
    try {
        $pdo->beginTransaction();
        if ($idCitaPrueba !== null) {
            $deleteAppointment = $pdo->prepare('DELETE FROM citas WHERE id_cita = :id');
            $deleteAppointment->execute(['id' => $idCitaPrueba]);
        }
        $deleteService = $pdo->prepare('DELETE FROM servicios WHERE nombre_servicio IN (:nombre, :editado)');
        $deleteService->execute(['nombre' => $temporaryService, 'editado' => $temporaryService . '_EDIT']);
        $deleteInventory = $pdo->prepare('DELETE FROM insumos WHERE nombre_insumo = :nombre');
        $deleteInventory->execute(['nombre' => $temporaryInventory]);
        $deleteUnassignedInventory = $pdo->prepare('DELETE FROM insumos WHERE nombre_insumo = :nombre');
        $deleteUnassignedInventory->execute(['nombre' => $temporaryUnassignedInventory]);
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