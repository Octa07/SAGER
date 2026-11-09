<?php
declare(strict_types=1);

final class Sistema
{
    public static function servicios(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT id_servicio, nombre_servicio, consumo_insumo FROM servicios ORDER BY nombre_servicio'
        )->fetchAll();
    }

    public static function datosDashboard(PDO $pdo): array
    {
        $citas = $pdo->query(
            "SELECT c.id_cita, u.nombre AS cliente, s.nombre_servicio, c.fecha_cita, c.hora_cita, c.estado
             FROM citas c
             INNER JOIN usuarios u ON u.id_usuario = c.id_cliente
             INNER JOIN servicios s ON s.id_servicio = c.id_servicio
             ORDER BY c.fecha_cita DESC, c.hora_cita DESC"
        )->fetchAll();

        $insumos = $pdo->query(
            'SELECT id_insumo, nombre_insumo, stock_actual, stock_minimo FROM insumos ORDER BY nombre_insumo'
        )->fetchAll();

        return ['citas' => $citas, 'insumos' => $insumos];
    }
}