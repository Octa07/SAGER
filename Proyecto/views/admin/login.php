<?php
declare(strict_types=1);

if (!defined('SAGER_VIEW')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/seguridad.php';

if (esAdministrador()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$mensaje = (string) ($_SESSION['login_error'] ?? '');
unset($_SESSION['login_error']);
$csrfToken = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso administrador | SAGER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header class="navbar navbar-dark">
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn" href="../public/index.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
        </nav>
    </header>
    <main class="main-container container">
        <section class="view-section active">
            <div class="auth-layout">
                <div class="auth-intro">
                    <p class="eyebrow">Entorno privado</p>
                    <h2>Acceso de administrador</h2>
                    <p class="heading-note">Ingresa para consultar la agenda y supervisar el inventario.</p>
                    <div class="auth-points"><span><i class="fa-solid fa-calendar-day"></i> Citas programadas</span><span><i class="fa-solid fa-triangle-exclamation"></i> Stock crítico</span></div>
                </div>
                <form class="auth-card card" action="../controllers/LoginController.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                    <div class="auth-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <h3>Iniciar sesión</h3>
                    <p>Usa las credenciales de tu cuenta administradora.</p>
                    <label for="admin-email">Correo electrónico</label>
                    <input class="form-control" id="admin-email" name="correo" type="email" maxlength="100" autocomplete="username" required>
                    <label for="admin-password">Contraseña</label>
                    <input class="form-control" id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    <button class="btn btn-primary w-100 mt-3" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Ingresar al panel</button>
                    <?php if ($mensaje !== ''): ?>
                        <p class="login-error visible" role="alert"><?= escapar($mensaje) ?></p>
                    <?php endif; ?>
                </form>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                if (!button || !form.reportValidity()) return;

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Validando acceso…';
            });
        });
    </script>
</body>
</html>