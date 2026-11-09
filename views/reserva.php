<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar cita | SAGER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header>
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn active" href="ReservaController.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
            <a class="tab-btn" href="../views/login.php"><i class="fa-solid fa-lock"></i> Acceso administrador</a>
        </nav>
    </header>
    <main class="main-container">
        <section class="view-section active">
            <div class="view-heading">
                <div><p class="eyebrow">Agenda en línea</p><h2>Agenda tu cita</h2><p class="heading-note">Selecciona un servicio y un horario disponible.</p></div>
                <span class="flow-chip"><i class="fa-solid fa-shield-heart"></i> Reserva segura</span>
            </div>
            <?php if ($servicios === []): ?>
                <p class="login-error visible" role="alert">Aún no hay servicios disponibles. Contacta al administrador.</p>
            <?php else: ?>
                <form id="reservation-form" action="../controllers/CitasController.php" method="post" class="reservation-grid">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                    <div>
                        <div class="form-group">
                            <label for="service-select"><i class="fa-solid fa-scissors"></i> Servicio</label>
                            <select id="service-select" name="id_servicio" required>
                                <option value="">Selecciona un servicio</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <option value="<?= (int) $servicio['id_servicio'] ?>"><?= escapar($servicio['nombre_servicio']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date-select"><i class="fa-regular fa-calendar"></i> Fecha</label>
                            <input id="date-select" name="fecha_cita" type="date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="time-select"><i class="fa-regular fa-clock"></i> Hora</label>
                            <input id="time-select" name="hora_cita" type="time" required>
                        </div>
                    </div>
                    <div class="confirmation-card">
                        <h3>Datos del cliente</h3>
                        <p>El correo identifica tus reservas anteriores. El horario se confirma al completar el envío.</p>
                        <div class="form-group">
                            <label for="client-name">Nombre completo</label>
                            <input id="client-name" name="nombre" type="text" maxlength="100" autocomplete="name" required>
                        </div>
                        <div class="form-group">
                            <label for="client-email">Correo electrónico</label>
                            <input id="client-email" name="correo" type="email" maxlength="100" autocomplete="email" required>
                        </div>
                        <button id="submit-button" class="btn-primary" type="submit"><i class="fa-solid fa-check"></i> Reservar cita</button>
                        <p id="reservation-message" class="confirmation-status" role="status" aria-live="polite"></p>
                        <p class="helper-text"><i class="fa-solid fa-circle-info"></i> El inventario se actualiza al confirmar la cita.</p>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script>
        const form = document.getElementById('reservation-form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!form.reportValidity()) return;

                const button = document.getElementById('submit-button');
                const message = document.getElementById('reservation-message');
                const buttonText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cargando...';
                message.classList.remove('visible');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    message.textContent = result.mensaje || 'No se pudo procesar la reserva.';
                    message.classList.add('visible');
                    message.style.background = result.ok ? '#ccfbf1' : '#fff1f2';
                    message.style.color = result.ok ? '#115e59' : '#b42318';
                    if (result.ok) form.reset();
                } catch (error) {
                    message.textContent = 'No fue posible conectar con el sistema. Inténtalo nuevamente.';
                    message.classList.add('visible');
                    message.style.background = '#fff1f2';
                    message.style.color = '#b42318';
                } finally {
                    button.disabled = false;
                    button.innerHTML = buttonText;
                }
            });
        }
    </script>
</body>
</html>