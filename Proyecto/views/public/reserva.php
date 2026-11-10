<?php
if (!defined('SAGER_VIEW')) {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar cita | SAGER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header class="navbar navbar-dark">
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn active" href="index.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
        </nav>
    </header>
    <main class="main-container container">
        <section class="view-section active">
            <div class="view-heading">
                <div><p class="eyebrow">Agenda en línea</p><h2>Agenda tu cita</h2><p class="heading-note">Elige un servicio, una fecha y una hora. El sistema confirmará si el horario está libre.</p></div>
                <span class="flow-chip"><i class="fa-solid fa-calendar-check"></i> Confirmación en pantalla</span>
            </div>
            <?php if ($servicios === []): ?>
                <p class="login-error visible" role="alert">Aún no hay servicios disponibles. Contacta al administrador.</p>
            <?php else: ?>
                <form id="reservation-form" action="../controllers/CitasController.php" method="post" class="reservation-grid row g-4" aria-busy="false">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                    <div>
                        <div class="form-group">
                            <label for="service-select"><i class="fa-solid fa-scissors"></i> Servicio</label>
                            <select class="form-select" id="service-select" name="id_servicio" required>
                                <option value="">Selecciona un servicio</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <option value="<?= (int) $servicio['id_servicio'] ?>"><?= escapar($servicio['nombre_servicio']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date-select"><i class="fa-regular fa-calendar"></i> Fecha</label>
                            <input class="form-control" id="date-select" name="fecha_cita" type="date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="time-select"><i class="fa-regular fa-clock"></i> Hora</label>
                            <input class="form-control" id="time-select" name="hora_cita" type="time" required>
                        </div>
                    </div>
                    <div class="confirmation-card card border-0">
                        <h3>Datos del cliente</h3>
                        <p>Usaremos tu correo para asociar esta cita a tus datos anteriores.</p>
                        <div class="form-group">
                            <label for="client-name">Nombre completo</label>
                            <input class="form-control" id="client-name" name="nombre" type="text" maxlength="100" autocomplete="name" required>
                        </div>
                        <div class="form-group">
                            <label for="client-email">Correo electrónico</label>
                            <input class="form-control" id="client-email" name="correo" type="email" maxlength="100" autocomplete="email" required>
                        </div>
                        <button id="submit-button" class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="fa-solid fa-check"></i> Reservar cita
                        </button>
                        <div id="reservation-message" class="confirmation-status alert d-none" role="status" aria-live="polite"></div>
                        <p class="helper-text"><i class="fa-solid fa-circle-info"></i> Al confirmar la cita, se descontarán los insumos requeridos por el servicio.</p>
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
                form.setAttribute('aria-busy', 'true');
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Procesando reserva…';
                message.className = 'confirmation-status alert d-none';
                message.textContent = '';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    message.classList.remove('d-none', 'alert-success', 'alert-danger');
                    message.classList.add(result.ok ? 'alert-success' : 'alert-danger');
                    message.setAttribute('role', result.ok ? 'status' : 'alert');
                    message.textContent = result.mensaje || 'No se pudo procesar la reserva.';
                    if (result.ok) form.reset();
                } catch {
                    message.classList.remove('d-none', 'alert-success');
                    message.classList.add('alert-danger');
                    message.setAttribute('role', 'alert');
                    message.textContent = 'No fue posible conectar con el sistema. Inténtalo nuevamente.';
                } finally {
                    button.disabled = false;
                    button.innerHTML = buttonText;
                    form.setAttribute('aria-busy', 'false');
                }
            });
        }
    </script>
</body>
</html>