<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class ResourceRepository
{
    /** Persiste y consulta salas e insumos mediante consultas parametrizadas. */
    public function __construct(private \PDO $database) {}

    public function findActiveRooms(): array
    {
        // Las salas activas se ordenan por nombre para mostrarlas en formularios.
        return $this->fetchAll('SELECT * FROM rooms WHERE active=1 ORDER BY name');
    }

    public function findActiveSupplies(): array
    {
        // Solo devuelve insumos utilizables en nuevas reservas o préstamos.
        return $this->fetchAll('SELECT * FROM supplies WHERE active=1 ORDER BY name');
    }

    public function findRoomById(mixed $id): ?array
    {
        return $this->fetchOne('SELECT * FROM rooms WHERE id=?', [$id]);
    }

    public function findSupplyById(mixed $id): ?array
    {
        return $this->fetchOne('SELECT * FROM supplies WHERE id=?', [$id]);
    }

    public function findAllRooms(): array
    {
        return $this->fetchAll('SELECT * FROM rooms ORDER BY active DESC,name');
    }

    public function findAllSupplies(): array
    {
        return $this->fetchAll('SELECT * FROM supplies ORDER BY active DESC,name');
    }

    public function createRoom(array $values): void
    {
        // Inserta los campos ya validados por ResourceService.
        $this->execute(
            'INSERT INTO rooms (name, room_number, location, equipment_count) VALUES (?, ?, ?, ?)',
            $values
        );
    }

    public function updateRoom(int $id, array $values): void
    {
        // Actualiza una sala existente conservando su identificador.
        $this->execute(
            'UPDATE rooms SET name=?, room_number=?, location=?, equipment_count=? WHERE id=?',
            [...$values, $id]
        );
    }

    public function createSupply(array $values): void
    {
        // Registra un insumo con su stock y estado operativo inicial.
        $this->execute(
            'INSERT INTO supplies (name, asset_code, category, description, quantity, status) VALUES (?, ?, ?, ?, ?, ?)',
            $values
        );
    }

    public function updateSupply(int $id, array $values): void
    {
        // Guarda los datos editables de un insumo existente.
        $this->execute(
            'UPDATE supplies SET name=?, asset_code=?, category=?, description=?, quantity=?, status=? WHERE id=?',
            [...$values, $id]
        );
    }

    public function deactivateRoom(int $id): void
    {
        $this->execute('UPDATE rooms SET active=0 WHERE id=?', [$id]);
    }

    public function deactivateSupply(int $id): void
    {
        $this->execute('UPDATE supplies SET active=0 WHERE id=?', [$id]);
    }

    private function fetchAll(string $query, array $parameters = []): array
    {
        // Todas las lecturas pasan por prepare/execute para evitar interpolación.
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function fetchOne(string $query, array $parameters = []): ?array
    {
        // Reutiliza fetchAll y devuelve null cuando no hay coincidencias.
        $rows = $this->fetchAll($query, $parameters);
        return $rows[0] ?? null;
    }

    private function execute(string $query, array $parameters = []): void
    {
        // Punto común para inserciones, actualizaciones y desactivaciones.
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
    }
}
