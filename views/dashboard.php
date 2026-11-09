<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrativo | SAGER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header>
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER · Panel administrativo</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn" href="ReservaController.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
            <a class="tab-btn active" href="DashboardController.php"><i class="fa-solid fa-chart-line"></i> Panel</a>
            <a class="tab-btn" href="LogoutController.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</a>
        </nav>
    </header>
    <main class="main-container">
        <section class="view-section active">
            <div class="view-heading">
                <div><p class="eyebrow">Menú principal · Administrador</p><h2>Panel de control</h2><p class="heading-note">Hola, <?= $_SESSION['usuario']['nombre'] ?>. Consulta citas e inventario.</p></div>
                <div class="dashboard-topline"><span class="status-dot"></span> Sistema operativo</div>
            </div>
            <?php $stockTotal = array_sum(array_column($insumos, 'stock_actual')); ?>
            <?php $alertas = count(array_filter($insumos, static fn (array $insumo): bool => (int) $insumo['stock_actual'] <= (int) $insumo['stock_minimo'])); ?>
            <div class="dashboard-stats">
                <div class="stat-card"><h3>Citas programadas</h3><div class="number"><?= count($citas) ?></div><p class="stat-caption">Todas las citas registradas</p></div>
                <div class="stat-card"><h3>Insumos en stock</h3><div class="number"><?= (int) $stockTotal ?> <small>un.</small></div><p class="stat-caption">Existencias actuales</p></div>
                <div class="stat-card"><h3>Alertas de inventario</h3><div class="number"><?= $alertas ?></div><p class="stat-caption">En o por debajo del mínimo</p></div>
            </div>
            <div class="admin-content">
                <div class="panel">
                    <div class="panel-heading"><h3>Agenda de citas</h3><span><?= count($citas) ?> registros</span></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID cita</th><th>Cliente</th><th>Servicio</th><th>Fecha y hora</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php if ($citas === []): ?>
                                    <tr><td colspan="5">No hay citas registradas.</td></tr>
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
                </div>
                <div class="panel">
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
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>