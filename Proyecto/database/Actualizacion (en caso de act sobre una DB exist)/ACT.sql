USE sistema_reservas;

CREATE TABLE IF NOT EXISTS servicio_insumos (
    id_servicio INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    PRIMARY KEY (id_servicio, id_insumo),
    FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES insumos(id_insumo) ON DELETE CASCADE
);

SET @columna_insumos_configurados = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'servicios'
      AND column_name = 'insumos_configurados'
);
SET @sql_agregar_configuracion = IF(
    @columna_insumos_configurados = 0,
    'ALTER TABLE servicios ADD COLUMN insumos_configurados TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE migracion_configuracion FROM @sql_agregar_configuracion;
EXECUTE migracion_configuracion;
DEALLOCATE PREPARE migracion_configuracion;

INSERT IGNORE INTO servicio_insumos (id_servicio, id_insumo, cantidad)
SELECT s.id_servicio, inventario.id_insumo, s.consumo_insumo
FROM servicios s
CROSS JOIN (
    SELECT MIN(id_insumo) AS id_insumo
    FROM insumos
) inventario
WHERE s.insumos_configurados = 0
  AND s.consumo_insumo > 0
  AND inventario.id_insumo IS NOT NULL;

UPDATE servicios SET insumos_configurados = 1;

CREATE TABLE IF NOT EXISTS cita_insumos (
    id_cita INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    PRIMARY KEY (id_cita, id_insumo),
    FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES insumos(id_insumo) ON DELETE CASCADE
);

INSERT IGNORE INTO cita_insumos (id_cita, id_insumo, cantidad)
SELECT c.id_cita, si.id_insumo, si.cantidad
FROM citas c
INNER JOIN servicio_insumos si ON si.id_servicio = c.id_servicio
WHERE NOT EXISTS (
    SELECT 1
    FROM cita_insumos ci
    WHERE ci.id_cita = c.id_cita
);

CREATE TABLE IF NOT EXISTS servicio_insumo_stock (
    id_servicio INT NOT NULL,
    id_insumo INT NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    PRIMARY KEY (id_servicio, id_insumo),
    FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES insumos(id_insumo) ON DELETE CASCADE
);

INSERT IGNORE INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
SELECT
    si.id_servicio,
    si.id_insumo,
    CASE
        WHEN asignado.id_insumo IS NOT NULL THEN 0
        ELSE FLOOR(i.stock_actual * si.cantidad / consumo.total_consumo)
            + CASE
                WHEN si.id_servicio = consumo.primer_servicio THEN
                    i.stock_actual - (
                        SELECT SUM(FLOOR(i.stock_actual * otra.cantidad / consumo.total_consumo))
                        FROM servicio_insumos otra
                        WHERE otra.id_insumo = si.id_insumo
                    )
                ELSE 0
            END
    END
FROM servicio_insumos si
INNER JOIN insumos i ON i.id_insumo = si.id_insumo
INNER JOIN (
    SELECT id_insumo, SUM(cantidad) AS total_consumo, MIN(id_servicio) AS primer_servicio
    FROM servicio_insumos
    GROUP BY id_insumo
) consumo ON consumo.id_insumo = si.id_insumo AND consumo.total_consumo > 0
LEFT JOIN (
    SELECT DISTINCT id_insumo
    FROM servicio_insumo_stock
) asignado ON asignado.id_insumo = si.id_insumo;
