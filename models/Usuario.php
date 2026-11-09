<?php
declare(strict_types=1);

final class Usuario
{
    public static function buscarPorCorreo(PDO $pdo, string $correo): ?array
    {
        $consulta = $pdo->query(
            "SELECT id_usuario, nombre, correo, password_hash, rol FROM usuarios WHERE correo = '$correo' LIMIT 1"
        );
        $usuario = $consulta->fetch();

        return $usuario ?: null;
    }

    public static function obtenerOCrearCliente(PDO $pdo, string $nombre, string $correo): int
    {
        $consulta = $pdo->prepare(
            'SELECT id_usuario, rol FROM usuarios WHERE correo = :correo LIMIT 1 FOR UPDATE'
        );
        $consulta->execute(['correo' => $correo]);
        $usuario = $consulta->fetch();

        if ($usuario) {
            if ($usuario['rol'] !== 'cliente') {
                throw new DomainException('El correo no está disponible para una reserva.');
            }

            return (int) $usuario['id_usuario'];
        }

        $hashAleatorio = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $insertar = $pdo->prepare(
            "INSERT INTO usuarios (nombre, correo, password_hash, rol) VALUES (:nombre, :correo, :password_hash, 'cliente')"
        );
        $insertar->execute([
            'nombre' => $nombre,
            'correo' => $correo,
            'password_hash' => $hashAleatorio,
        ]);

        return (int) $pdo->lastInsertId();
    }
}