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

function redirigirAdmin(string $mensaje, string $tipo = 'success'): never
{
    $_SESSION['admin_notice'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    header('Location: ../admin/');
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
            redirigirAdmin('Insumo registrado.');

        case 'reponer_stock':
            $idInsumo = enteroFormulario('id_insumo', 1, PHP_INT_MAX);
            $cantidad = enteroFormulario('cantidad', 1, 1000000);
            if ($idInsumo === null || $cantidad === null) {
                redirigirAdmin('Selecciona un insumo y una cantidad positiva.', 'error');
            }

            $pdo->beginTransaction();
            $consulta = $pdo->prepare('SELECT stock_actual FROM insumos WHERE id_insumo = :id FOR UPDATE');
            $consulta->execute(['id' => $idInsumo]);
            $stock = $consulta->fetchColumn();
            if ($stock === false || (int) $stock > 2147483647 - $cantidad) {
                throw new DomainException('No se encontró el insumo o la cantidad supera el límite permitido.');
            }

            $actualizar = $pdo->prepare('UPDATE insumos SET stock_actual = stock_actual + :cantidad WHERE id_insumo = :id');
            $actualizar->execute(['cantidad' => $cantidad, 'id' => $idInsumo]);
            $pdo->commit();
            redirigirAdmin('Stock repuesto correctamente.');

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

        case 'crear_servicio':
            $nombre = trim((string) ($_POST['nombre_servicio'] ?? ''));
            $consumo = enteroFormulario('consumo_insumo', 0, 1000000);
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
            $consumo = enteroFormulario('consumo_insumo', 0, 1000000);
            if ($idServicio === null || $nombre === '' || mb_strlen($nombre) > 100 || $consumo === null) {
                redirigirAdmin('Revisa los datos del servicio.', 'error');
            }

            $pdo->beginTransaction();
            $buscar = $pdo->prepare('SELECT consumo_insumo FROM servicios WHERE id_servicio = :id FOR UPDATE');
            $buscar->execute(['id' => $idServicio]);
            $servicio = $buscar->fetch();
            if (!$servicio) {
                throw new DomainException('No se encontró el servicio seleccionado.');
            }

            if ((int) $servicio['consumo_insumo'] !== $consumo) {
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
            $actualizar->execute(['nombre' => $nombre, 'consumo' => $consumo, 'id' => $idServicio]);
            $pdo->commit();
            redirigirAdmin('Servicio actualizado.');

        default:
            redirigirAdmin('Acción administrativa no reconocida.', 'error');
    }
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirigirAdmin($exception->getMessage(), 'error');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SAGER admin action failed: ' . $exception->getMessage());
    redirigirAdmin('No se pudo guardar el cambio. Revisa los datos e inténtalo nuevamente.', 'error');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SAGER unexpected admin action failure: ' . $exception->getMessage());
    redirigirAdmin('Ocurrió un error inesperado. Inténtalo nuevamente.', 'error');
}