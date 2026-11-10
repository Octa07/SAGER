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
                    <p class="eyebrow">Administración</p>
                    <h2>Panel de control</h2>
                    <p class="heading-note">
                        Hola, <?= escapar($_SESSION['usuario']['nombre']) ?>. Consulta las citas, su consumo y el stock de cada servicio.
                    </p>
                </div>
                <div class="dashboard-topline">
                    <span class="status-dot"></span>
                    Administrador conectado
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

            <h3 class="dashboard-section-heading">Resumen</h3>
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
            </div>

            <h3 class="dashboard-section-heading">Stock por servicio</h3>
            <div class="service-stock-grid">
                <?php foreach ($servicios as $servicio): ?>
                    <?php
                    $idServicio = (int) $servicio['id_servicio'];
                    $cantidadesServicio = $insumosPorServicio[$idServicio] ?? [];
                    $configurado = (int) $servicio['insumos_configurados'] === 1;
                    ?>
                    <div class="stat-card card service-stock-card" id="service-stock-<?= $idServicio ?>">
                        <h3><?= escapar($servicio['nombre_servicio']) ?></h3>
                        <?php if (!$configurado): ?>
                            <p class="stat-caption">Falta configurar los insumos de este servicio.</p>
                        <?php elseif ($cantidadesServicio === []): ?>
                            <p class="stat-caption">No hay insumos asignados a este servicio.</p>
                        <?php else: ?>
                            <ul class="service-stock-list">
                                <?php foreach (array_keys($cantidadesServicio) as $idInsumo): ?>
                                    <?php $insumoServicio = $insumosPorId[$idInsumo] ?? null; ?>
                                    <?php if ($insumoServicio !== null): ?>
                                        <li>
                                            <span><?= escapar($insumoServicio['nombre_insumo']) ?></span>
                                            <strong>
                                                <?= (int) ($stockPorServicio[$idServicio][$idInsumo] ?? 0) ?> un.
                                            </strong>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- La agenda ocupa su propia fila; la reposición queda debajo. -->
            <div class="admin-content">
                <div class="panel card">
                    <div class="panel-heading">
                        <div class="panel-heading-copy">
                            <h3>Agenda de citas</h3>
                            <p>Consulta el cliente, el servicio y el estado de cada cita.</p>
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
            </div>

            <section class="panel card stock-add-panel" id="agregar-stock">
                <div class="panel-heading">
                    <div class="panel-heading-copy">
                        <h3>Agregar al stock</h3>
                        <p>Selecciona un servicio y uno de sus insumos. Las unidades se sumarán al stock general y al del servicio.</p>
                    </div>
                </div>
                <div class="service-ingredient-list">
                    <?php if ($insumos === []): ?>
                        <p class="helper-text">Registra un insumo para agregar existencias.</p>
                    <?php elseif ($servicios === []): ?>
                        <p class="helper-text">Registra un servicio para asignarle existencias.</p>
                    <?php else: ?>
                        <form
                            action="../controllers/AdminActionsController.php"
                            method="post"
                            class="management-form stock-add-form"
                        >
                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                            <input type="hidden" name="accion" value="reponer_stock">
                            <div class="form-group">
                                <label for="stock-service">Servicio</label>
                                <select class="form-select" id="stock-service" name="id_servicio" required>
                                    <option value="">Selecciona un servicio</option>
                                    <?php foreach ($servicios as $servicio): ?>
                                        <option value="<?= (int) $servicio['id_servicio'] ?>">
                                            <?= escapar($servicio['nombre_servicio']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="stock-ingredient">Insumo del servicio</label>
                                <select class="form-select" id="stock-ingredient" name="id_insumo" required disabled>
                                    <option value="">Primero selecciona un servicio</option>
                                    <?php foreach ($insumos as $insumo): ?>
                                        <?php
                                        $idInsumo = (int) $insumo['id_insumo'];
                                        $serviciosDelInsumo = [];
                                        foreach ($insumosPorServicio as $idServicio => $cantidadesServicio) {
                                            if (array_key_exists($idInsumo, $cantidadesServicio)) {
                                                $serviciosDelInsumo[] = (int) $idServicio;
                                            }
                                        }
                                        ?>
                                        <option
                                            value="<?= $idInsumo ?>"
                                            data-servicios="<?= escapar(implode(',', $serviciosDelInsumo)) ?>"
                                            disabled
                                        >
                                            <?= escapar($insumo['nombre_insumo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-help" id="stock-ingredient-availability" aria-live="polite">
                                    Selecciona un servicio para ver sus insumos disponibles.
                                </p>
                            </div>
                            <div class="form-group">
                                <label for="stock-add">Unidades compradas</label>
                                <input
                                    class="form-control"
                                    id="stock-add"
                                    name="cantidad"
                                    type="number"
                                    min="1"
                                    max="1000000"
                                    value="1"
                                    required
                                >
                            </div>
                            <button class="btn btn-primary btn-small" type="submit" disabled>
                                <i class="fa-solid fa-plus"></i> Agregar compra
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

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
                        <?php
                        $idServicioCita = (int) $citaSeleccionada['id_servicio'];
                        $insumosEditables = array_unique(array_merge(
                            array_keys($insumosPorServicio[$idServicioCita] ?? []),
                            array_keys($insumosCitaSeleccionada)
                        ));
                        $insumoSeleccionado = null;
                        if (
                            $insumoCitaSeleccionado !== null
                            && in_array($insumoCitaSeleccionado, $insumosEditables, true)
                        ) {
                            $insumoSeleccionado = $insumoCitaSeleccionado;
                        } elseif ($insumosEditables !== []) {
                            $insumoSeleccionado = (int) reset($insumosEditables);
                        }
                        ?>
                        <?php if ($consumosCitaSeleccionada !== []): ?>
                            <h4 class="appointment-ingredients-heading">Insumos usados en esta cita</h4>
                            <ul class="appointment-edit-consumption-list">
                                <?php foreach ($consumosCitaSeleccionada as $consumo): ?>
                                    <li>
                                        <span>
                                            <?= escapar($consumo['nombre_insumo']) ?>
                                            <strong><?= (int) $consumo['cantidad'] ?> un.</strong>
                                        </span>
                                        <div class="appointment-ingredient-actions">
                                            <a
                                                class="btn btn-outline-primary btn-sm"
                                                href="?page=<?= $pagina ?>&amp;cita=<?= (int) $citaSeleccionada['id_cita'] ?>&amp;insumo=<?= (int) $consumo['id_insumo'] ?>#insumos-cita"
                                            >
                                                Editar
                                            </a>
                                            <form action="../controllers/AdminActionsController.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="actualizar_insumos_cita">
                                                <input type="hidden" name="id_cita" value="<?= (int) $citaSeleccionada['id_cita'] ?>">
                                                <input type="hidden" name="id_insumo" value="<?= (int) $consumo['id_insumo'] ?>">
                                                <input type="hidden" name="cantidad" value="0">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">
                                                    Quitar
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="helper-text">Esta cita todavía no tiene insumos registrados.</p>
                        <?php endif; ?>

                        <?php if ($insumosEditables === []): ?>
                            <p class="helper-text">No hay insumos asignados al servicio de esta cita.</p>
                        <?php else: ?>
                            <?php
                            $cantidadInicial = $insumoSeleccionado === null
                                ? 1
                                : ($insumosCitaSeleccionada[$insumoSeleccionado] ?? 1);
                            ?>
                            <form
                                action="../controllers/AdminActionsController.php"
                                method="post"
                                class="service-ingredient-form"
                            >
                                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                <input type="hidden" name="accion" value="actualizar_insumos_cita">
                                <input type="hidden" name="id_cita" value="<?= (int) $citaSeleccionada['id_cita'] ?>">
                                <p class="form-help">
                                    El servicio está fijado por la cita. Selecciona un insumo de ese servicio y guarda su cantidad;
                                    los demás insumos usados no se modifican.
                                </p>
                                <div class="service-ingredient-fields appointment-ingredient-editor">
                                    <div class="form-group">
                                        <label for="appointment-ingredient">Insumo de <?= escapar($citaSeleccionada['nombre_servicio']) ?></label>
                                        <select
                                            class="form-select"
                                            id="appointment-ingredient"
                                            name="id_insumo"
                                            data-servicio="<?= $idServicioCita ?>"
                                            required
                                        >
                                            <?php foreach ($insumos as $insumo): ?>
                                                <?php $idInsumo = (int) $insumo['id_insumo']; ?>
                                                <?php if (!in_array($idInsumo, $insumosEditables, true)) continue; ?>
                                                <?php $estaAsignado = array_key_exists($idInsumo, $insumosPorServicio[$idServicioCita] ?? []); ?>
                                                <option
                                                    value="<?= $idInsumo ?>"
                                                    data-cantidad="<?= (int) ($insumosCitaSeleccionada[$idInsumo] ?? 0) ?>"
                                                    data-asignado="<?= $estaAsignado ? '1' : '0' ?>"
                                                    data-stock="<?= (int) ($stockPorServicio[$idServicioCita][$idInsumo] ?? 0) ?>"
                                                    data-stock-general="<?= (int) $insumo['stock_actual'] ?>"
                                                    <?= $insumoSeleccionado === $idInsumo ? 'selected' : '' ?>
                                                >
                                                    <?= escapar($insumo['nombre_insumo']) ?>
                                                    <?= !$estaAsignado ? ' (consumo anterior)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="appointment-ingredient-quantity">Cantidad usada</label>
                                        <input
                                            class="form-control"
                                            id="appointment-ingredient-quantity"
                                            name="cantidad"
                                            type="number"
                                            min="0"
                                            max="1000000"
                                            value="<?= $cantidadInicial ?>"
                                            required
                                        >
                                    </div>
                                </div>
                                <p class="form-help" id="appointment-ingredient-stock" aria-live="polite"></p>
                                <button class="btn btn-primary btn-small" type="submit">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar este insumo
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel card service-ingredients-panel" id="insumos-por-servicio">
                <div class="panel-heading">
                    <div class="panel-heading-copy">
                        <h3>Consumo de insumos por servicio</h3>
                        <p>Selecciona un servicio y un insumo del catálogo. Los insumos nuevos y existentes están disponibles para asignarse.</p>
                    </div>
                </div>
                <div class="service-ingredient-list">
                    <?php if ($servicios === [] || $insumos === []): ?>
                        <p class="helper-text">
                            <?= $servicios === [] ? 'Registra un servicio' : 'Registra un insumo' ?>
                            para poder asignarlo.
                        </p>
                    <?php else: ?>
                        <form
                            action="../controllers/AdminActionsController.php"
                            method="post"
                            class="management-form service-assignment-form"
                        >
                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                            <input type="hidden" name="accion" value="asignar_insumo_servicio">
                            <div class="service-assignment-fields">
                                <div class="form-group">
                                    <label for="assignment-service">Servicio</label>
                                    <select class="form-select" id="assignment-service" name="id_servicio" required>
                                        <option value="">Selecciona un servicio</option>
                                        <?php foreach ($servicios as $servicio): ?>
                                            <option value="<?= (int) $servicio['id_servicio'] ?>">
                                                <?= escapar($servicio['nombre_servicio']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="assignment-ingredient">Insumo</label>
                                    <select class="form-select" id="assignment-ingredient" name="id_insumo" required>
                                        <option value="">Selecciona un insumo</option>
                                        <?php foreach ($insumos as $insumo): ?>
                                            <option value="<?= (int) $insumo['id_insumo'] ?>">
                                                <?= escapar($insumo['nombre_insumo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="assignment-quantity">Unidades usadas por cita</label>
                                    <input
                                        class="form-control"
                                        id="assignment-quantity"
                                        name="cantidad"
                                        type="number"
                                        min="1"
                                        max="1000000"
                                        value="5"
                                        required
                                    >
                                </div>
                            </div>
                            <p class="form-help">El valor inicial es 5 unidades. Ajústalo al consumo habitual por cita.</p>
                            <button class="btn btn-primary btn-small" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar consumo por servicio
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
                            <p>Registra un insumo y define su stock inicial.</p>
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
                            <p>Edita los nombres de los servicios. Configura sus insumos en la sección superior.</p>
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
        const stockService = document.getElementById('stock-service');
        const stockIngredient = document.getElementById('stock-ingredient');
        const stockAvailability = document.getElementById('stock-ingredient-availability');
        const stockSubmit = stockIngredient
            ? stockIngredient.form.querySelector('button[type="submit"]')
            : null;

        if (stockService && stockIngredient && stockAvailability && stockSubmit) {
            const updateStockIngredients = () => {
                const serviceId = stockService.value;
                let availableCount = 0;

                Array.from(stockIngredient.options).forEach((option, index) => {
                    if (index === 0) return;

                    const serviceIds = option.dataset.servicios.split(',').filter(Boolean);
                    option.disabled = !serviceId || !serviceIds.includes(serviceId);
                    if (!option.disabled) availableCount += 1;
                });

                if (!serviceId) {
                    stockIngredient.value = '';
                    stockIngredient.disabled = true;
                    stockSubmit.disabled = true;
                    stockIngredient.options[0].textContent = 'Primero selecciona un servicio';
                    stockAvailability.textContent = 'Selecciona un servicio para ver sus insumos disponibles.';
                    return;
                }

                stockIngredient.disabled = availableCount === 0;
                stockIngredient.options[0].textContent = availableCount > 0
                    ? 'Selecciona un insumo'
                    : 'Este servicio no tiene insumos asignados';
                if (stockIngredient.value && stockIngredient.selectedOptions[0].disabled) {
                    stockIngredient.value = '';
                }
                const selectedOption = stockIngredient.selectedOptions[0];
                stockSubmit.disabled = !selectedOption || !selectedOption.value || selectedOption.disabled;
                stockAvailability.textContent = availableCount > 0
                    ? 'Solo puedes elegir insumos asignados al servicio seleccionado.'
                    : 'Asigna insumos a este servicio antes de agregar existencias.';
            };

            stockService.addEventListener('change', updateStockIngredients);
            stockIngredient.addEventListener('change', () => {
                stockSubmit.disabled = !stockIngredient.value || stockIngredient.selectedOptions[0].disabled;
            });
        }

        const appointmentIngredient = document.getElementById('appointment-ingredient');
        const appointmentQuantity = document.getElementById('appointment-ingredient-quantity');
        const appointmentIngredientStock = document.getElementById('appointment-ingredient-stock');

        if (appointmentIngredient && appointmentQuantity && appointmentIngredientStock) {
            const updateAppointmentIngredient = () => {
                const option = appointmentIngredient.selectedOptions[0];
                if (!option) return;

                const previousQuantity = Number(option.dataset.cantidad);
                const serviceStock = Number(option.dataset.stock);
                const generalStock = Number(option.dataset.stockGeneral);
                const assigned = option.dataset.asignado === '1';
                appointmentQuantity.value = previousQuantity > 0 ? previousQuantity : 1;
                appointmentQuantity.max = assigned
                    ? String(previousQuantity + Math.min(serviceStock, generalStock))
                    : String(previousQuantity);
                appointmentIngredientStock.textContent = assigned
                    ? `Stock disponible para este servicio: ${serviceStock} un.`
                    : 'Este insumo ya no está asignado al servicio; solo puedes reducirlo o quitarlo.';
            };

            appointmentIngredient.addEventListener('change', updateAppointmentIngredient);
            updateAppointmentIngredient();
        }

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