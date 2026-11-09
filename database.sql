CREATE DATABASE IF NOT EXISTS sistema_reservas;
USE sistema_reservas;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'cliente') DEFAULT 'cliente'
);

CREATE TABLE servicios (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio VARCHAR(100) NOT NULL,
    consumo_insumo INT NOT NULL
);

CREATE TABLE insumos (
    id_insumo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_insumo VARCHAR(100) NOT NULL,
    stock_actual INT NOT NULL CHECK (stock_actual >= 0),
    stock_minimo INT DEFAULT 5
);

CREATE TABLE citas (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_servicio INT NOT NULL,
    fecha_cita DATE NOT NULL,
    hora_cita TIME NOT NULL,
    estado ENUM('confirmada', 'cancelada') DEFAULT 'confirmada',
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio),
    UNIQUE (fecha_cita, hora_cita)
);

INSERT INTO servicios (nombre_servicio, consumo_insumo) VALUES
    ('Consultoría especializada', 2),
    ('Mantenimiento de equipo', 3);

INSERT INTO insumos (nombre_insumo, stock_actual, stock_minimo) VALUES
    ('Insumo general', 48, 5);

    GRANT ALL PRIVILEGES ON sistema_reservas.* TO 'usuarioClases'@'localhost';
FLUSH PRIVILEGES;