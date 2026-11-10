<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (esAdministrador()) {
	require __DIR__ . '/../controllers/DashboardController.php';
	exit;
}

header('Location: login.php');
exit;
