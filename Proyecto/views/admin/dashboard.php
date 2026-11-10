<?php
if (!defined('SAGER_VIEW') || !esAdministrador()) {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrativo | SAGER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="admin-dashboard">
    <header class="navbar navbar-dark">
        <div class="brand">
            <i class="fa-solid fa-calendar-check"></i>
            <h1>SAGER · Panel administrativo</h1>
        </div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn" href="../public/index.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
            <a class="tab-btn active" href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Panel</a>
            <a class="tab-btn" href="../controllers/LogoutController.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</a>
        </nav>
    </header>
    <main class="main-container container">
        <section class="view-section active">
            <!-- Título del panel y mensajes de las acciones administrativas. -->
            <div class="view-heading">
                <div>
                    <p class="eyebrow">Menú principal · Administrador</p>
                    <h2>Panel de control</h2>
                    <p class="heading-note">
                        Hola, <?= escapar($_SESSION['usuario']['nombre']) ?>. Consulta citas y existencias.
                    </p>
                </div>
                <div class="dashboard-topline">
                    <span class="status-dot"></span>
                    Sesión de administrador
                </div>
            </div>

            <?php if (is_array($notificacion ?? null)): ?>
                <p class="admin-notice <?= escapar((string) ($notificacion['tipo'] ?? 'success')) ?>" role="status"><?= escapar((string) ($notificacion['mensaje'] ?? '')) ?></p>
            <?php endif; ?>

            <?php $alertas = count(array_filter($insumos, static fn (array $insumo): bool => (int) $insumo['stock_actual'] <= (int) $insumo['stock_minimo'])); ?>
            <?php
            $insumosPorId = [];
            foreach ($insumos as $insumo) {
                $insumosPorId[(int) $insumo['id_insumo']] = $insumo;
            }
            ?>

            <!-- Resumen de citas y capacidad actual de cada servicio. -->
            <div class="dashboard-stats">
                <div class="stat-card card">
                    <h3>Citas programadas</h3>
                    <div class="number"><?= $totalCitas ?></div>
                    <p class="stat-caption">Citas registradas en el sistema</p>
                </div>
                <div class="stat-card card">
                    <h3>Alertas de stock</h3>
                    <div class="number"><?= $alertas ?></div>
                    <p class="stat-caption">Insumos que necesitan revisión</p>
                </div>
                <?php foreach ($servicios as $servicio): ?>
                    <?php
                    $idServicio = (int) $servicio['id_servicio'];
                    $cantidadesServicio = $insumosPorServicio[$idServicio] ?? [];
                    $configurado = (int) $servicio['insumos_configurados'] === 1;
                    $capacidadServicio = null;
                    if ($configurado && $cantidadesServicio !== []) {
                        $capacidadServicio = PHP_INT_MAX;
                        foreach ($cantidadesServicio as $idInsumo => $cantidadRequerida) {
                            $stockDisponible = (int) ($insumosPorId[$idInsumo]['stock_actual'] ?? 0);
                            $capacidadServicio = min(
                                $capacidadServicio,
                                intdiv($stockDisponible, $cantidadRequerida)
                            );
                        }
                    }
                    ?>
                    <div class="stat-card card service-stock-card">
                        <h3><?= escapar($servicio['nombre_servicio']) ?></h3>
                        <?php if (!$configurado): ?>
                            <p class="stat-caption">Falta configurar los insumos de este servicio.</p>
                        <?php elseif ($cantidadesServicio === []): ?>
                            <div class="number">Sin insumos</div>
                            <p class="stat-caption">Este servicio no requiere insumos configurados.</p>
                        <?php else: ?>
                            <div class="number"><?= $capacidadServicio ?> <small>servicios posibles</small></div>
                            <ul class="service-stock-list">
                                <?php foreach ($cantidadesServicio as $idInsumo => $cantidadRequerida): ?>
                                    <?php $insumoServicio = $insumosPorId[$idInsumo] ?? null; ?>
                                    <?php if ($insumoServicio !== null): ?>
                                        <li>
                                            <span><?= escapar($insumoServicio['nombre_insumo']) ?></span>
                                            <strong>
                                                <?= (int) $insumoServicio['stock_actual'] ?> un.
                                                <small>(<?= $cantidadRequerida ?> por servicio)</small>
                                            </strong>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Agenda y controles para revisar o actualizar existencias. -->
            <div class="admin-content">
                <div class="panel card">
                    <div class="panel-heading">
                        <div class="panel-heading-copy">
                            <h3>Agenda de citas</h3>
                            <p>Revisa quién tiene cita, el servicio y su estado.</p>
                        </div>
                        <span><?= $totalCitas ?> registros</span>
                    </div>
                    <div class="table-wrap table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID cita</th>
                                    <th>Cliente</th>
                                    <th>Servicio</th>
                                    <th>Fecha y hora</th>
                                    <th>Estado</th>
                                    <th>Insumos usados en esta cita</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($citas === []): ?>
                                    <tr>
                                        <td class="text-center text-body-secondary py-4" colspan="7">
                                            No hay citas registradas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $cita): ?>
                                        <tr>
                                            <td>#<?= (int) $cita['id_cita'] ?></td>
                                            <td><?= escapar($cita['cliente']) ?></td>
                                            <td><?= escapar($cita['nombre_servicio']) ?></td>
                                            <td>
                                                <?= escapar($cita['fecha_cita']) ?>
                                                · <?= escapar(substr($cita['hora_cita'], 0, 5)) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $cita['estado'] === 'confirmada' ? 'success' : 'warning' ?>">
                                                    <?= escapar(ucfirst($cita['estado'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $insumosDeEstaCita = $insumosPorCita[(int) $cita['id_cita']] ?? []; ?>
                                                <?php if ($insumosDeEstaCita === []): ?>
                                                    <span class="text-body-secondary">Sin consumo registrado</span>
                                                <?php else: ?>
                                                    <ul class="appointment-consumption-list">
                                                        <?php foreach ($insumosDeEstaCita as $consumo): ?>
                                                            <li>
                                                                <?= escapar($consumo['nombre_insumo']) ?>:
                                                                <?= (int) $consumo['cantidad'] ?> un.
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a
                                                    class="btn btn-outline-primary btn-sm"
                                                    href="?page=<?= $pagina ?>&amp;cita=<?= (int) $cita['id_cita'] ?>#insumos-cita"
                                                >
                                                    Editar consumo
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPaginas > 1): ?>
                        <?php $desdeCita = (($pagina - 1) * $porPagina) + 1; ?>
                        <?php $hastaCita = min($pagina * $porPagina, $totalCitas); ?>
                        <div class="panel-pagination">
                            <span>Mostrando <?= $desdeCita ?>–<?= $hastaCita ?> de <?= $totalCitas ?></span>
                            <nav aria-label="Paginación de citas">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                        <?php if ($pagina <= 1): ?>
                                            <span class="page-link">Anterior</span>
                                        <?php else: ?>
                                            <a class="page-link" href="?page=<?= $pagina - 1 ?>">Anterior</a>
                                        <?php endif; ?>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><?= $pagina ?> / <?= $totalPaginas ?></span>
                                    </li>
                                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                                        <?php if ($pagina >= $totalPaginas): ?>
                                            <span class="page-link">Siguiente</span>
                                        <?php else: ?>
                                            <a class="page-link" href="?page=<?= $pagina + 1 ?>">Siguiente</a>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="panel card">
                    <div class="panel-heading">
                        <div class="panel-heading-copy">
                            <h3>Existencias de insumos</h3>
                            <p>Revisa existencias, agrega unidades o cambia el mínimo.</p>
                            <p class="inventory-note">
                                Cada cita conserva por separado las cantidades que utilizó.
                            </p>
                        </div>
                        <span><?= $alertas ?> alertas</span>
                    </div>
                    <div class="inventory-list">
                        <?php if ($insumos === []): ?>
                            <p class="helper-text">No hay insumos registrados.</p>
                        <?php else: ?>
                            <?php foreach ($insumos as $insumo): ?>
                                <?php $critico = (int) $insumo['stock_actual'] <= (int) $insumo['stock_minimo']; ?>
                                <div class="inventory-item">
                                    <div>
                                        <strong><?= escapar($insumo['nombre_insumo']) ?></strong>
                                        <small>
                                            Mínimo recomendado: <?= (int) $insumo['stock_minimo'] ?> un.
                                        </small>
                                    </div>
                                    <?php if ($critico): ?>
                                        <span class="badge warning">
                                            Stock crítico · <?= (int) $insumo['stock_actual'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge success">
                                            <?= (int) $insumo['stock_actual'] ?> un.
                                        </span>
                                    <?php endif; ?>
                                    <div class="inventory-actions">
                                        <?php if ($servicios === []): ?>
                                            <p class="form-help">Registra un servicio antes de asignar este insumo.</p>
                                        <?php else: ?>
                                        <form action="../controllers/AdminActionsController.php" method="post" class="admin-inline-form service-restock-form">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="reponer_stock_servicio">
                                            <input type="hidden" name="id_insumo" value="<?= (int) $insumo['id_insumo'] ?>">
                                            <label for="restock-service-<?= (int) $insumo['id_insumo'] ?>">Servicio al que se asigna</label>
                                            <select
                                                class="form-select"
                                                id="restock-service-<?= (int) $insumo['id_insumo'] ?>"
                                                name="id_servicio"
                                                required
                                            >
                                                <option value="">Selecciona un servicio</option>
                                                <?php foreach ($servicios as $servicio): ?>
                                                    <option value="<?= (int) $servicio['id_servicio'] ?>">
                                                        <?= escapar($servicio['nombre_servicio']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="restock-<?= (int) $insumo['id_insumo'] ?>">Unidades para agregar al stock</label>
                                            <input
                                                class="form-control"
                                                id="restock-<?= (int) $insumo['id_insumo'] ?>"
                                                name="cantidad_reponer"
                                                type="number"
                                                min="1"
                                                max="1000000"
                                                value="1"
                                                required
                                            >
                                            <label for="service-quantity-<?= (int) $insumo['id_insumo'] ?>">Unidades usadas por cita</label>
                                            <input
                                                class="form-control"
                                                id="service-quantity-<?= (int) $insumo['id_insumo'] ?>"
                                                name="cantidad_por_servicio"
                                                type="number"
                                                min="1"
                                                max="1000000"
                                                value="1"
                                                required
                                            >
                                            <button class="btn btn-primary btn-small" type="submit">
                                                <i class="fa-solid fa-plus"></i> Reponer y asignar
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form action="../controllers/AdminActionsController.php" method="post" class="admin-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="actualizar_minimo">
                                            <input type="hidden" name="id_insumo" value="<?= (int) $insumo['id_insumo'] ?>">
                                            <label for="minimum-<?= (int) $insumo['id_insumo'] ?>">Nuevo mínimo</label>
                                            <input
                                                class="form-control"
                                                id="minimum-<?= (int) $insumo['id_insumo'] ?>"
                                                name="stock_minimo"
                                                type="number"
                                                min="0"
                                                max="1000000"
                                                value="<?= (int) $insumo['stock_minimo'] ?>"
                                                required
                                            >
                                            <button class="btn btn-outline-secondary btn-small secondary" type="submit">
                                                Guardar mínimo
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <section class="panel card service-ingredients-panel" id="insumos-cita">
                <div class="panel-heading">
                    <div class="panel-heading-copy">
                        <h3>Insumos de la cita</h3>
                        <p>Las cantidades guardadas aquí corresponden únicamente a esta cita.</p>
                        <?php if (is_array($citaSeleccionada)): ?>
                            <p class="selected-appointment-note">
                                Cita #<?= (int) $citaSeleccionada['id_cita'] ?>:
                                <?= escapar($citaSeleccionada['nombre_servicio']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="service-ingredient-list">
                    <?php if (!is_array($citaSeleccionada)): ?>
                        <p class="helper-text">Selecciona “Ver / editar” en una cita para revisar su consumo de insumos.</p>
                    <?php elseif ($citaSeleccionada['estado'] !== 'confirmada'): ?>
                        <p class="helper-text">Solo se pueden ajustar los insumos de citas confirmadas.</p>
                    <?php else: ?>
                        <form
                            action="../controllers/AdminActionsController.php"
                            method="post"
                            class="service-ingredient-form"
                        >
                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                            <input type="hidden" name="accion" value="actualizar_insumos_cita">
                            <input type="hidden" name="id_cita" value="<?= (int) $citaSeleccionada['id_cita'] ?>">
                            <p class="form-help">
                                Si cambias cantidades, el stock se ajustará solo por la diferencia de esta cita.
                                Usa 0 si no se utilizó ese insumo.
                            </p>
                            <?php if ($insumos === []): ?>
                                <p class="helper-text">No hay insumos registrados.</p>
                            <?php else: ?>
                                <div class="service-ingredient-fields">
                                    <?php foreach ($insumos as $insumo): ?>
                                        <?php $idInsumo = (int) $insumo['id_insumo']; ?>
                                        <div class="service-ingredient-field">
                                            <label for="cita-<?= (int) $citaSeleccionada['id_cita'] ?>-insumo-<?= $idInsumo ?>">
                                                <?= escapar($insumo['nombre_insumo']) ?>
                                            </label>
                                            <div class="service-ingredient-stock">
                                                Disponible: <?= (int) $insumo['stock_actual'] ?> un.
                                            </div>
                                            <input
                                                class="form-control"
                                                id="cita-<?= (int) $citaSeleccionada['id_cita'] ?>-insumo-<?= $idInsumo ?>"
                                                name="insumos[<?= $idInsumo ?>]"
                                                type="number"
                                                min="0"
                                                max="1000000"
                                                value="<?= (int) ($insumosCitaSeleccionada[$idInsumo] ?? 0) ?>"
                                                required
                                            >
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-small" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar consumo de esta cita
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Formularios para registrar insumos y administrar servicios. -->
            <div class="admin-management-grid">
                <section class="panel card management-panel">
                    <div class="panel-heading">
                        <div class="panel-heading-copy">
                            <h3>Registrar insumo</h3>
                            <p>Agrega un material y define su existencia inicial.</p>
                        </div>
                        <span><?= count($insumos) ?> registrados</span>
                    </div>
                    <form action="../controllers/AdminActionsController.php" method="post" class="management-form">
                        <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                        <input type="hidden" name="accion" value="crear_insumo">
                        <div class="form-group">
                            <label for="new-inventory-name">Nombre del insumo</label>
                            <input
                                class="form-control"
                                id="new-inventory-name"
                                name="nombre_insumo"
                                type="text"
                                maxlength="100"
                                required
                            >
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label for="new-inventory-stock">Stock inicial</label>
                                <input
                                    class="form-control"
                                    id="new-inventory-stock"
                                    name="stock_actual"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    value="0"
                                    required
                                >
                            </div>
                            <div class="form-group">
                                <label for="new-inventory-minimum">Stock mínimo</label>
                                <input
                                    class="form-control"
                                    id="new-inventory-minimum"
                                    name="stock_minimo"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    value="5"
                                    required
                                >
                            </div>
                        </div>
                        <button class="btn btn-primary btn-primary-custom" type="submit">
                            <i class="fa-solid fa-boxes-stacked"></i> Agregar insumo
                        </button>
                    </form>
                </section>

                <section class="panel card management-panel">
                    <div class="panel-heading">
                        <div class="panel-heading-copy">
                            <h3>Servicios</h3>
                            <p>Administra los nombres. Los insumos se configuran en el panel superior.</p>
                        </div>
                        <span><?= count($servicios) ?> registrados</span>
                    </div>
                    <div class="service-list">
                        <?php foreach ($servicios as $servicio): ?>
                            <form action="../controllers/AdminActionsController.php" method="post" class="service-edit-form">
                                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                <input type="hidden" name="accion" value="actualizar_servicio">
                                <input type="hidden" name="id_servicio" value="<?= (int) $servicio['id_servicio'] ?>">
                                <div class="form-group">
                                    <label for="service-name-<?= (int) $servicio['id_servicio'] ?>">Nombre del servicio</label>
                                    <input
                                        class="form-control"
                                        id="service-name-<?= (int) $servicio['id_servicio'] ?>"
                                        name="nombre_servicio"
                                        type="text"
                                        maxlength="100"
                                        value="<?= escapar($servicio['nombre_servicio']) ?>"
                                        required
                                    >
                                </div>
                                <button class="btn btn-primary btn-small" type="submit">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar nombre del servicio
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <form action="../controllers/AdminActionsController.php" method="post" class="management-form new-service-form">
                        <h4>Agregar servicio</h4>
                        <p class="form-help">Después de crearlo, asígnale los insumos que necesita.</p>
                        <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                        <input type="hidden" name="accion" value="crear_servicio">
                        <div class="form-group">
                            <label for="new-service-name">Nombre del servicio</label>
                            <input
                                class="form-control"
                                id="new-service-name"
                                name="nombre_servicio"
                                type="text"
                                maxlength="100"
                                required
                            >
                        </div>
                        <button class="btn btn-primary btn-primary-custom" type="submit">
                            <i class="fa-solid fa-plus"></i> Agregar servicio
                        </button>
                    </form>
                </section>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                if (!button || !form.reportValidity()) return;

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando…';
            });
        });
    </script>
</body>
</html>
