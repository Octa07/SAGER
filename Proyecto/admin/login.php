<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguridad.php';

if (esAdministrador()) {
    header('Location: dashboard.php');
    exit;
}

define('SAGER_VIEW', true);
require __DIR__ . '/../views/admin/login.php';
