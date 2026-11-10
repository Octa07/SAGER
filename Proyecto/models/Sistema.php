<?php
declare(strict_types=1);

final class Sistema
{
    public static function servicios(PDO $pdo, bool $soloConfigurados = false): array
    {
        $filtro = $soloConfigurados ? 'WHERE insumos_configurados = 1' : '';

        return $pdo->query(
            'SELECT id_servicio, nombre_servicio, consumo_insumo, insumos_configurados
             FROM servicios
             ' . $filtro . '
             ORDER BY nombre_servicio'
        )->fetchAll();
    }

    public static function datosDashboard(PDO $pdo, int $pagina = 1, ?int $idCitaSeleccionada = null): array
    {
        $porPagina = 25;
        $totalCitas = (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn();
        $totalPaginas = max(1, (int) ceil($totalCitas / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);
        $offset = ($pagina - 1) * $porPagina;

        $consultaCitas = $pdo->prepare(
            "SELECT c.id_cita, u.nombre AS cliente, s.nombre_servicio, c.fecha_cita, c.hora_cita, c.estado
             FROM citas c
             INNER JOIN usuarios u ON u.id_usuario = c.id_cliente
             INNER JOIN servicios s ON s.id_servicio = c.id_servicio
             ORDER BY c.fecha_cita DESC, c.hora_cita DESC
             LIMIT :limite OFFSET :desplazamiento"
        );
        $consultaCitas->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $consultaCitas->bindValue('desplazamiento', $offset, PDO::PARAM_INT);
        $consultaCitas->execute();
        $citas = $consultaCitas->fetchAll();

        $insumosPorCita = [];
        if ($citas !== []) {
            $parametrosCitas = [];
            $marcadoresCitas = [];
            foreach ($citas as $indice => $cita) {
                $marcador = ':cita_' . $indice;
                $marcadoresCitas[] = $marcador;
                $parametrosCitas['cita_' . $indice] = (int) $cita['id_cita'];
            }

            $consultaInsumosCitas = $pdo->prepare(
                'SELECT ci.id_cita, i.nombre_insumo, ci.cantidad
                 FROM cita_insumos ci
                 INNER JOIN insumos i ON i.id_insumo = ci.id_insumo
                 WHERE ci.id_cita IN (' . implode(', ', $marcadoresCitas) . ')
                 ORDER BY ci.id_cita, i.nombre_insumo'
            );
            $consultaInsumosCitas->execute($parametrosCitas);
            foreach ($consultaInsumosCitas->fetchAll() as $relacion) {
                $insumosPorCita[(int) $relacion['id_cita']][] = [
                    'nombre_insumo' => $relacion['nombre_insumo'],
                    'cantidad' => (int) $relacion['cantidad'],
                ];
            }
        }

        $insumos = $pdo->query(
            'SELECT id_insumo, nombre_insumo, stock_actual, stock_minimo FROM insumos ORDER BY nombre_insumo'
        )->fetchAll();

        $servicios = self::servicios($pdo);
        $insumosPorServicio = [];
        $relacionesInsumos = $pdo->query(
            'SELECT id_servicio, id_insumo, cantidad FROM servicio_insumos ORDER BY id_servicio, id_insumo'
        )->fetchAll();
        foreach ($relacionesInsumos as $relacion) {
            $idServicio = (int) $relacion['id_servicio'];
            $insumosPorServicio[$idServicio][(int) $relacion['id_insumo']] = (int) $relacion['cantidad'];
        }

        $citaSeleccionada = null;
        $insumosCitaSeleccionada = [];
        if ($idCitaSeleccionada !== null) {
            $consultaCitaSeleccionada = $pdo->prepare(
                'SELECT c.id_cita, c.id_servicio, c.estado, s.nombre_servicio
                 FROM citas c
                 INNER JOIN servicios s ON s.id_servicio = c.id_servicio
                 WHERE c.id_cita = :id'
            );
            $consultaCitaSeleccionada->execute(['id' => $idCitaSeleccionada]);
            $citaSeleccionada = $consultaCitaSeleccionada->fetch() ?: null;

            if ($citaSeleccionada !== null) {
                $consultaInsumosCita = $pdo->prepare(
                    'SELECT id_insumo, cantidad FROM cita_insumos WHERE id_cita = :id'
                );
                $consultaInsumosCita->execute(['id' => $idCitaSeleccionada]);
                foreach ($consultaInsumosCita->fetchAll() as $relacion) {
                    $insumosCitaSeleccionada[(int) $relacion['id_insumo']] = (int) $relacion['cantidad'];
                }
            }
        }

        return [
            'citas' => $citas,
            'insumosPorCita' => $insumosPorCita,
            'insumos' => $insumos,
            'servicios' => $servicios,
            'insumosPorServicio' => $insumosPorServicio,
            'citaSeleccionada' => $citaSeleccionada,
            'insumosCitaSeleccionada' => $insumosCitaSeleccionada,
            'totalCitas' => $totalCitas,
            'pagina' => $pagina,
            'totalPaginas' => $totalPaginas,
            'porPagina' => $porPagina,
        ];
    }
}