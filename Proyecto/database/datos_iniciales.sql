USE sistema_reservas;

INSERT INTO servicios (nombre_servicio, consumo_insumo) VALUES
    ('Consultoría especializada', 2),
    ('Mantenimiento de equipo', 3);

INSERT INTO insumos (nombre_insumo, stock_actual, stock_minimo) VALUES
    ('Material de consumo', 48, 5);

INSERT INTO servicio_insumos (id_servicio, id_insumo, cantidad)
SELECT s.id_servicio, i.id_insumo, s.consumo_insumo
FROM servicios s
JOIN insumos i ON i.nombre_insumo = 'Material de consumo'
WHERE s.nombre_servicio IN ('Consultoría especializada', 'Mantenimiento de equipo');

INSERT INTO servicio_insumo_stock (id_servicio, id_insumo, stock_actual)
SELECT
    s.id_servicio,
    i.id_insumo,
    CASE s.nombre_servicio
        WHEN 'Consultoría especializada' THEN 24
        WHEN 'Mantenimiento de equipo' THEN 24
    END
FROM servicios s
JOIN insumos i ON i.nombre_insumo = 'Material de consumo'
WHERE s.nombre_servicio IN ('Consultoría especializada', 'Mantenimiento de equipo');

UPDATE servicios
SET insumos_configurados = 1
WHERE nombre_servicio IN ('Consultoría especializada', 'Mantenimiento de equipo');
