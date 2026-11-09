<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (esAdministrador()) {
    header('Location: ../controllers/DashboardController.php');
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header>
        <div class="brand"><i class="fa-solid fa-calendar-check"></i><h1>SAGER</h1></div>
        <nav class="nav-tabs" aria-label="Navegación principal">
            <a class="tab-btn" href="../controllers/ReservaController.php"><i class="fa-solid fa-calendar-plus"></i> Reservar cita</a>
        </nav>
    </header>
    <main class="main-container">
        <section class="view-section active">
            <div class="auth-layout">
                <div class="auth-intro">
                    <p class="eyebrow">Entorno privado</p>
                    <h2>Acceso de administrador</h2>
                    <p class="heading-note">Ingresa para consultar la agenda y supervisar el inventario.</p>
                    <div class="auth-points"><span><i class="fa-solid fa-calendar-day"></i> Citas programadas</span><span><i class="fa-solid fa-triangle-exclamation"></i> Stock crítico</span></div>
                </div>
                <form class="auth-card" action="../controllers/LoginController.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                    <div class="auth-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <h3>Iniciar sesión</h3>
                    <p>Usa las credenciales de tu cuenta administradora.</p>
                    <label for="admin-email">Correo electrónico</label>
                    <input id="admin-email" name="correo" type="email" maxlength="100" autocomplete="username" required>
                    <label for="admin-password">Contraseña</label>
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    <button class="btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Ingresar al panel</button>
                    <?php if ($mensaje !== ''): ?>
                        <p class="login-error visible" role="alert"><?= escapar($mensaje) ?></p>
                    <?php endif; ?>
                </form>
            </div>
        </section>
    </main>
</body>
</html>