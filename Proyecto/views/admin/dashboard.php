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
<body>
    <header class="navbar navbar-dark">
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER · Panel administrativo</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn" href="../public/index.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
            <a class="tab-btn active" href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Panel</a>
            <a class="tab-btn" href="../controllers/LogoutController.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</a>
        </nav>
    </header>
    <main class="main-container container">
        <section class="view-section active">
            <div class="view-heading">
                <div><p class="eyebrow">Menú principal · Administrador</p><h2>Panel de control</h2><p class="heading-note">Hola, <?= escapar($_SESSION['usuario']['nombre']) ?>. Consulta citas e inventario.</p></div>
                <div class="dashboard-topline"><span class="status-dot"></span> Sistema operativo</div>
            </div>
            <?php if (is_array($notificacion ?? null)): ?>
                <p class="admin-notice <?= escapar((string) ($notificacion['tipo'] ?? 'success')) ?>" role="status"><?= escapar((string) ($notificacion['mensaje'] ?? '')) ?></p>
            <?php endif; ?>
            <?php $stockTotal = array_sum(array_column($insumos, 'stock_actual')); ?>
            <?php $alertas = count(array_filter($insumos, static fn (array $insumo): bool => (int) $insumo['stock_actual'] <= (int) $insumo['stock_minimo'])); ?>
            <div class="dashboard-stats">
                <div class="stat-card card"><h3>Citas programadas</h3><div class="number"><?= $totalCitas ?></div><p class="stat-caption">Todas las citas registradas</p></div>
                <div class="stat-card card"><h3>Insumos en stock</h3><div class="number"><?= (int) $stockTotal ?> <small>un.</small></div><p class="stat-caption">Existencias actuales</p></div>
                <div class="stat-card card"><h3>Alertas de inventario</h3><div class="number"><?= $alertas ?></div><p class="stat-caption">En o por debajo del mínimo</p></div>
            </div>
            <div class="admin-content">
                <div class="panel card">
                    <div class="panel-heading"><h3>Agenda de citas</h3><span><?= $totalCitas ?> registros</span></div>
                    <div class="table-wrap table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>ID cita</th><th>Cliente</th><th>Servicio</th><th>Fecha y hora</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php if ($citas === []): ?>
                                    <tr><td class="text-center text-body-secondary py-4" colspan="5">No hay citas registradas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $cita): ?>
                                        <tr>
                                            <td>#<?= (int) $cita['id_cita'] ?></td>
                                            <td><?= escapar($cita['cliente']) ?></td>
                                            <td><?= escapar($cita['nombre_servicio']) ?></td>
                                            <td><?= escapar($cita['fecha_cita']) ?> · <?= escapar(substr($cita['hora_cita'], 0, 5)) ?></td>
                                            <td><span class="badge <?= $cita['estado'] === 'confirmada' ? 'success' : 'warning' ?>"><?= escapar(ucfirst($cita['estado'])) ?></span></td>
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
                                    <li class="page-item disabled"><span class="page-link"><?= $pagina ?> / <?= $totalPaginas ?></span></li>
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
                    <div class="panel-heading"><h3>Inventario</h3><span><?= $alertas ?> alertas</span></div>
                    <div class="inventory-list">
                        <?php if ($insumos === []): ?>
                            <p class="helper-text">No hay insumos registrados.</p>
                        <?php else: ?>
                            <?php foreach ($insumos as $insumo): ?>
                                <?php $critico = (int) $insumo['stock_actual'] <= (int) $insumo['stock_minimo']; ?>
                                <div class="inventory-item">
                                    <div><strong><?= escapar($insumo['nombre_insumo']) ?></strong><small>Mínimo recomendado: <?= (int) $insumo['stock_minimo'] ?> un.</small></div>
                                    <?php if ($critico): ?>
                                        <span class="badge warning">Stock Crítico · <?= (int) $insumo['stock_actual'] ?></span>
                                    <?php else: ?>
                                        <span class="badge success"><?= (int) $insumo['stock_actual'] ?> un.</span>
                                    <?php endif; ?>
                                    <div class="inventory-actions">
                                        <form action="../controllers/AdminActionsController.php" method="post" class="admin-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="reponer_stock">
                                            <input type="hidden" name="id_insumo" value="<?= (int) $insumo['id_insumo'] ?>">
                                            <label class="visually-hidden" for="restock-<?= (int) $insumo['id_insumo'] ?>">Unidades a reponer de <?= escapar($insumo['nombre_insumo']) ?></label>
                                            <input class="form-control" id="restock-<?= (int) $insumo['id_insumo'] ?>" name="cantidad" type="number" min="1" max="1000000" value="1" required>
                                            <button class="btn btn-primary btn-small" type="submit"><i class="fa-solid fa-plus"></i> Reponer</button>
                                        </form>
                                        <form action="../controllers/AdminActionsController.php" method="post" class="admin-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="actualizar_minimo">
                                            <input type="hidden" name="id_insumo" value="<?= (int) $insumo['id_insumo'] ?>">
                                            <label class="visually-hidden" for="minimum-<?= (int) $insumo['id_insumo'] ?>">Stock mínimo para <?= escapar($insumo['nombre_insumo']) ?></label>
                                            <input class="form-control" id="minimum-<?= (int) $insumo['id_insumo'] ?>" name="stock_minimo" type="number" min="0" max="1000000" value="<?= (int) $insumo['stock_minimo'] ?>" required>
                                            <button class="btn btn-outline-secondary btn-small secondary" type="submit">Guardar mínimo</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-management-grid">
                <section class="panel card management-panel">
                    <div class="panel-heading"><h3>Registrar insumo</h3><span><?= count($insumos) ?> registrados</span></div>
                    <form action="../controllers/AdminActionsController.php" method="post" class="management-form">
                        <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                        <input type="hidden" name="accion" value="crear_insumo">
                        <div class="form-group">
                            <label for="new-inventory-name">Nombre del insumo</label>
                            <input class="form-control" id="new-inventory-name" name="nombre_insumo" type="text" maxlength="100" required>
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label for="new-inventory-stock">Stock inicial</label>
                                <input class="form-control" id="new-inventory-stock" name="stock_actual" type="number" min="0" max="1000000" value="0" required>
                            </div>
                            <div class="form-group">
                                <label for="new-inventory-minimum">Stock mínimo</label>
                                <input class="form-control" id="new-inventory-minimum" name="stock_minimo" type="number" min="0" max="1000000" value="5" required>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-primary-custom" type="submit"><i class="fa-solid fa-boxes-stacked"></i> Agregar insumo</button>
                    </form>
                </section>

                <section class="panel card management-panel">
                    <div class="panel-heading"><h3>Servicios</h3><span><?= count($servicios) ?> registrados</span></div>
                    <div class="service-list">
                        <?php foreach ($servicios as $servicio): ?>
                            <form action="../controllers/AdminActionsController.php" method="post" class="service-edit-form">
                                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                                <input type="hidden" name="accion" value="actualizar_servicio">
                                <input type="hidden" name="id_servicio" value="<?= (int) $servicio['id_servicio'] ?>">
                                <div class="form-group">
                                    <label for="service-name-<?= (int) $servicio['id_servicio'] ?>">Nombre del servicio</label>
                                    <input class="form-control" id="service-name-<?= (int) $servicio['id_servicio'] ?>" name="nombre_servicio" type="text" maxlength="100" value="<?= escapar($servicio['nombre_servicio']) ?>" required>
                                </div>
                                <div class="form-columns">
                                    <div class="form-group">
                                        <label for="service-consumption-<?= (int) $servicio['id_servicio'] ?>">Consumo de insumos</label>
                                        <input class="form-control" id="service-consumption-<?= (int) $servicio['id_servicio'] ?>" name="consumo_insumo" type="number" min="0" max="1000000" value="<?= (int) $servicio['consumo_insumo'] ?>" required>
                                    </div>
                                    <div class="form-group form-action">
                                        <button class="btn btn-primary btn-small" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                                    </div>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <form action="../controllers/AdminActionsController.php" method="post" class="management-form new-service-form">
                        <h4>Agregar servicio</h4>
                        <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                        <input type="hidden" name="accion" value="crear_servicio">
                        <div class="form-group">
                            <label for="new-service-name">Nombre del servicio</label>
                            <input class="form-control" id="new-service-name" name="nombre_servicio" type="text" maxlength="100" required>
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label for="new-service-consumption">Consumo de insumos</label>
                                <input class="form-control" id="new-service-consumption" name="consumo_insumo" type="number" min="0" max="1000000" value="1" required>
                            </div>
                            <div class="form-group form-action">
                                <button class="btn btn-primary btn-primary-custom" type="submit"><i class="fa-solid fa-plus"></i> Agregar servicio</button>
                            </div>
                        </div>
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