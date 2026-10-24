<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class UserRepository
{
    /** Encapsula las consultas de usuarios y nunca expone SQL al controlador. */
    public function __construct(private \PDO $database) {}

    public function findById(mixed $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE id=?', [$id]);
    }

    public function findActiveByUsername(string $username): ?array
    {
        // El inicio de sesión solo considera cuentas activas.
        return $this->fetchOne('SELECT * FROM users WHERE username=? AND active=1', [$username]);
    }

    public function findActiveSessionUser(int $id): ?array
    {
        // Consulta el subconjunto mínimo necesario para revalidar una sesión.
        return $this->fetchOne(
            'SELECT id, first_name, role FROM users WHERE id=? AND active=1',
            [$id]
        );
    }

    public function findPasswordHash(int $id): ?string
    {
        // Recupera el hash sin devolver otros datos sensibles al servicio.
        $user = $this->fetchOne('SELECT password_hash FROM users WHERE id=?', [$id]);
        return $user['password_hash'] ?? null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->execute('UPDATE users SET password_hash=? WHERE id=?', [$passwordHash, $id]);
    }

    public function findAll(): array
    {
        return $this->fetchAll('SELECT * FROM users ORDER BY active DESC,last_name');
    }

    public function findActiveTeachers(): array
    {
        // Los administradores pueden asignar reservas solo a docentes activos.
        return $this->fetchAll(
            "SELECT id,first_name,last_name FROM users WHERE active=1 AND role='user' ORDER BY last_name,first_name"
        );
    }

    public function create(array $values): void
    {
        $this->execute(
            'INSERT INTO users (username, first_name, last_name, subject, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)',
            $values
        );
    }

    public function update(int $id, array $values): void
    {
        $this->execute(
            'UPDATE users SET username=?, first_name=?, last_name=?, subject=?, role=? WHERE id=?',
            [...$values, $id]
        );
    }

    public function deactivate(int $id): void
    {
        $this->execute('UPDATE users SET active=0 WHERE id=?', [$id]);
    }

    private function fetchAll(string $query, array $parameters = []): array
    {
        // Ejecuta lecturas parametrizadas y devuelve filas asociativas.
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function fetchOne(string $query, array $parameters = []): ?array
    {
        // Convierte una consulta de una fila en null cuando no encuentra datos.
        $rows = $this->fetchAll($query, $parameters);
        return $rows[0] ?? null;
    }

    private function execute(string $query, array $parameters = []): void
    {
        // Ejecuta escrituras sin concatenar valores recibidos del usuario.
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
    }
}
