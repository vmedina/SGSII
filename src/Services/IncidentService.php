<?php
declare(strict_types=1);

namespace Sgsii\Services;

require_once __DIR__ . '/../Validation/Validator.php';
require_once __DIR__ . '/TransactionCoordinator.php';

use Sgsii\Validation\Validator;

final class IncidentService
{
    /** Registra incidencias y sincroniza su efecto sobre la disponibilidad. */
    private const RESOURCE_STORAGE = [
        'room' => ['table' => 'rooms', 'column' => 'room_id'],
        'supply' => ['table' => 'supplies', 'column' => 'supply_id'],
    ];

    public function __construct(
        private \PDO $database,
        ?TransactionCoordinator $transactions = null,
        private ?\DateTimeImmutable $clock = null
    ) {
        $this->transactions = $transactions ?? new TransactionCoordinator($database);
    }

    private TransactionCoordinator $transactions;

    public function report(int $reporter, array $data): void
    {
        // Una incidencia urgente bloquea el recurso dentro de la misma transacción
        // en la que se registra, para que el estado no quede desincronizado.
        $type = Validator::choice($data['resource_type'] ?? '', ['room', 'supply'], 'Tipo de recurso');
        $id = Validator::integer($data['resource_id'] ?? null, 1, PHP_INT_MAX, 'Recurso');
        $description = Validator::text($data['description'] ?? '', 5, 1000, 'Descripción');
        $urgency = Validator::choice($data['urgency'] ?? '', ['low', 'medium', 'high'], 'Urgencia');
        $table = $this->resourceStorage($type)['table'];
        $transactionStarted = $this->beginWriteTransaction($table, $id);
        try {
            if (!$this->one("SELECT id FROM {$table} WHERE id=?" . $this->forUpdateClause(), [$id])) {
                throw new \InvalidArgumentException('El recurso indicado no existe.');
            }
            if ($urgency === 'high' && $this->incidentBlockerCount($type, $id) === 0 && !$this->resourceAvailable($type, $id)) {
                throw new \InvalidArgumentException('El recurso ya no está disponible para registrar un nuevo bloqueo.');
            }
            $this->execute(
                'INSERT INTO incidents (reporter_id, room_id, supply_id, description, urgency) VALUES (?, ?, ?, ?, ?)',
                [$reporter, $type === 'room' ? $id : null, $type === 'supply' ? $id : null, $description, $urgency]
            );
            if ($urgency === 'high') $this->setResourceAvailable($type, $id, false);
            $this->transactions->commit($transactionStarted);
        } catch (\Throwable $error) {
            $this->rollback($transactionStarted);
            throw $error;
        }
    }

    public function update(int $id, array $data): bool
    {
        // Al resolver una incidencia, restaura el recurso solo si no quedan otros
        // bloqueos activos que deban mantenerlo indisponible.
        $status = Validator::choice($data['status'] ?? '', ['open', 'in_progress', 'resolved'], 'Estado');
        $notes = Validator::text($data['admin_notes'] ?? '', 0, 1000, 'Notas');
        $transactionStarted = $this->beginWriteTransaction('incidents', $id);
        try {
            $incident = $this->one('SELECT * FROM incidents WHERE id=?' . $this->forUpdateClause(), [$id]);
            if (!$incident) throw new \InvalidArgumentException('La incidencia no existe.');
            [$type, $resourceId] = $this->incidentResource($incident);
            $table = $this->resourceStorage($type)['table'];
            if (!$this->one("SELECT id FROM {$table} WHERE id=?" . $this->forUpdateClause(), [$resourceId])) {
                throw new \InvalidArgumentException('El recurso asociado ya no existe.');
            }
            $wasBlocking = $this->incidentBlocks((string) $incident['urgency'], (string) $incident['status']);
            $willBlock = $this->incidentBlocks((string) $incident['urgency'], $status);
            if (!$wasBlocking && $willBlock && $this->incidentBlockerCount($type, $resourceId, $id) === 0 && !$this->resourceAvailable($type, $resourceId)) {
                throw new \InvalidArgumentException('El recurso ya no está disponible para iniciar este bloqueo.');
            }
            $this->execute(
                "UPDATE incidents SET status=?, admin_notes=?, resolved_at=CASE WHEN ?='resolved' THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id=?",
                [$status, $notes, $status, $id]
            );
            $blockers = $this->incidentBlockerCount($type, $resourceId);
            if ($blockers > 0) $this->setResourceAvailable($type, $resourceId, false);
            elseif ($wasBlocking && !$willBlock) $this->setResourceAvailable($type, $resourceId, true);
            $available = $this->resourceAvailable($type, $resourceId);
            $this->transactions->commit($transactionStarted);
            return $available;
        } catch (\Throwable $error) {
            $this->rollback($transactionStarted);
            throw $error;
        }
    }

    private function incidentResource(array $incident): array
    {
        // Determina si la incidencia apunta a una sala o a un insumo.
        if ($incident['room_id'] !== null) return ['room', (int) $incident['room_id']];
        if ($incident['supply_id'] !== null) return ['supply', (int) $incident['supply_id']];
        throw new \InvalidArgumentException('La incidencia no tiene un recurso válido.');
    }

    private function incidentBlocks(string $urgency, string $status): bool
    {
        // Los bloqueos provienen de urgencia alta o de una incidencia en progreso.
        return $status !== 'resolved' && ($urgency === 'high' || $status === 'in_progress');
    }

    private function incidentBlockerCount(string $type, int $resourceId, ?int $excludeId = null): int
    {
        // Permite excluir la incidencia que se está editando al recalcular bloqueos.
        $column = $this->resourceStorage($type)['column'];
        $sql = "SELECT COUNT(*) AS total FROM incidents WHERE {$column}=? AND status!='resolved' AND (urgency='high' OR status='in_progress')";
        $params = [$resourceId];
        if ($excludeId !== null) { $sql .= ' AND id!=?'; $params[] = $excludeId; }
        return (int) $this->one($sql, $params)['total'];
    }

    private function resourceAvailable(string $type, int $id): bool
    {
        // Comprueba disponibilidad manual y estado operativo del recurso.
        $resource = $type === 'room'
            ? $this->one('SELECT active FROM rooms WHERE id=?', [$id])
            : $this->one('SELECT active,status FROM supplies WHERE id=?', [$id]);
        if (!$resource || (int) $resource['active'] !== 1) return false;
        return $type === 'room' || $resource['status'] === 'operational';
    }

    private function setResourceAvailable(string $type, int $id, bool $available): void
    {
        // Mapea disponibilidad a active en salas y a status en insumos.
        if ($type === 'room') {
            $this->execute('UPDATE rooms SET active=? WHERE id=?', [$available ? 1 : 0, $id]);
            return;
        }
        $this->execute('UPDATE supplies SET status=? WHERE id=?', [$available ? 'operational' : 'repair', $id]);
    }

    private function resourceStorage(string $type): array
    {
        return self::RESOURCE_STORAGE[$type] ?? throw new \InvalidArgumentException('Tipo de recurso inválido.');
    }

    private function beginWriteTransaction(string $table, int $id): bool
    {
        return $this->transactions->begin($table, $id);
    }

    private function rollback(bool $started): void
    {
        $this->transactions->rollback($started);
    }

    /** Solicita bloqueo de fila en MySQL para actualizar sin carreras. */
    private function forUpdateClause(): string { return $this->driverName() === 'mysql' ? ' FOR UPDATE' : ''; }
    /** Devuelve el motor usado por la conexión PDO. */
    private function driverName(): string { return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME); }

    private function execute(string $query, array $parameters = []): void
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
    }

    private function one(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return $row === false ? [] : $row;
    }
}
