<?php
declare(strict_types=1);

namespace Sgsii\Services;

require_once __DIR__ . '/../Validation/Validator.php';
require_once __DIR__ . '/TransactionCoordinator.php';

use Sgsii\Repositories\ResourceRepository;
use Sgsii\Validation\Validator;

final class ResourceService
{
    private const RESOURCE_STORAGE = [
        'room' => ['table' => 'rooms', 'column' => 'room_id'],
        'supply' => ['table' => 'supplies', 'column' => 'supply_id'],
    ];

    public function __construct(
        private \PDO $database,
        private ResourceRepository $resources,
        private ?\DateTimeImmutable $clock = null,
        ?TransactionCoordinator $transactions = null
    ) {
        $this->transactions = $transactions ?? new TransactionCoordinator($database);
    }

    private TransactionCoordinator $transactions;

    public function saveRoom(array $data, ?int $id = null): void
    {
        $values = [
            Validator::text($data['name'] ?? '', 2, 100, 'Nombre'),
            Validator::text($data['room_number'] ?? '', 1, 30, 'Número'),
            Validator::text($data['location'] ?? '', 2, 120, 'Ubicación'),
            Validator::integer($data['equipment_count'] ?? null, 0, 999, 'Cantidad de equipos'),
        ];
        if ($id) {
            $this->resources->updateRoom($id, $values);
            return;
        }
        $this->resources->createRoom($values);
    }

    public function saveSupply(array $data, ?int $id = null): void
    {
        $values = [
            Validator::text($data['name'] ?? '', 2, 100, 'Nombre'),
            Validator::text($data['asset_code'] ?? '', 1, 50, 'Código'),
            Validator::text($data['category'] ?? '', 2, 60, 'Categoría'),
            Validator::text($data['description'] ?? '', 0, 500, 'Descripción'),
            Validator::integer($data['quantity'] ?? null, 0, 9999, 'Cantidad'),
            Validator::choice($data['status'] ?? '', ['operational', 'repair', 'out_of_service'], 'Estado'),
        ];
        if ($id === null) {
            $this->resources->createSupply($values);
            return;
        }

        $transactionStarted = $this->beginWriteTransaction('supplies', $id);
        try {
            $supply = $this->one('SELECT id FROM supplies WHERE id=?' . $this->forUpdateClause(), [$id]);
            if (!$supply) throw new \InvalidArgumentException('El insumo no existe.');
            if ($this->incidentBlockerCount('supply', $id) > 0 && $values[5] !== 'repair') {
                throw new \InvalidArgumentException('El insumo debe permanecer en reparación mientras exista una incidencia bloqueante.');
            }
            $committed = $this->supplyCommittedQuantity($id);
            if ((int) $values[4] < $committed) {
                throw new \InvalidArgumentException("El stock no puede ser menor que las {$committed} unidades comprometidas.");
            }
            if ($values[5] !== 'operational' && $committed > 0) {
                throw new \InvalidArgumentException('No puede cambiar el estado mientras existan préstamos o reservas activas.');
            }

            $this->resources->updateSupply($id, $values);
            $this->transactions->commit($transactionStarted);
        } catch (\Throwable $error) {
            $this->rollbackWriteTransaction($transactionStarted);
            throw $error;
        }
    }

    public function deactivateRoom(int $id): void
    {
        if ($this->incidentBlockerCount('room', $id) > 0) {
            throw new \InvalidArgumentException('La sala ya está bloqueada por una incidencia; resuélvala antes de desactivarla.');
        }
        $this->resources->deactivateRoom($id);
    }

    public function deactivateSupply(int $id): void
    {
        $transactionStarted = $this->beginWriteTransaction('supplies', $id);
        try {
            if ($this->supplyCommittedQuantity($id) > 0) {
                throw new \InvalidArgumentException('No puede desactivar el insumo mientras existan préstamos o reservas activas.');
            }
            $this->resources->deactivateSupply($id);
            $this->transactions->commit($transactionStarted);
        } catch (\Throwable $error) {
            $this->rollbackWriteTransaction($transactionStarted);
            throw $error;
        }
    }

    private function incidentBlockerCount(string $type, int $resourceId): int
    {
        $column = self::RESOURCE_STORAGE[$type]['column'];
        return (int) $this->one(
            "SELECT COUNT(*) AS total FROM incidents WHERE {$column}=? AND status!='resolved' AND (urgency='high' OR status='in_progress')",
            [$resourceId]
        )['total'];
    }

    private function supplyCommittedQuantity(int $supplyId): int
    {
        $loaned = (int) $this->one(
            'SELECT COALESCE(SUM(quantity),0) AS used FROM loans WHERE supply_id=? AND returned_at IS NULL',
            [$supplyId]
        )['used'];
        return $loaned + $this->reservedSupplyPeak($supplyId);
    }

    private function reservedSupplyPeak(int $supplyId): int
    {
        $clock = $this->clock ?? new \DateTimeImmutable('now');
        $today = $clock->format('Y-m-d');
        $now = $clock->format('H:i');
        $reservations = $this->all(
            "SELECT reservation_date,start_time,end_time,quantity FROM reservations
             WHERE supply_id=? AND status='active'
             AND (reservation_date > ? OR (reservation_date=? AND end_time > ?))",
            [$supplyId, $today, $today, $now]
        );
        $events = [];
        foreach ($reservations as $reservation) {
            $date = (string) $reservation['reservation_date'];
            $start = (string) $reservation['start_time'];
            if ($date === $today && $start < $now) $start = $now;
            $end = (string) $reservation['end_time'];
            $quantity = (int) $reservation['quantity'];
            $events[$date][$start] = ($events[$date][$start] ?? 0) + $quantity;
            $events[$date][$end] = ($events[$date][$end] ?? 0) - $quantity;
        }

        $peak = 0;
        foreach ($events as $dayEvents) {
            ksort($dayEvents);
            $used = 0;
            foreach ($dayEvents as $change) {
                $used += $change;
                $peak = max($peak, $used);
            }
        }
        return $peak;
    }

    private function beginWriteTransaction(string $table, int $id): bool
    {
        return $this->transactions->begin($table, $id);
    }

    private function rollbackWriteTransaction(bool $started): void
    {
        $this->transactions->rollback($started);
    }

    private function forUpdateClause(): string
    {
        return $this->driverName() === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function driverName(): string
    {
        return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    private function all(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function one(string $query, array $parameters = []): array
    {
        $rows = $this->all($query, $parameters);
        return $rows[0] ?? [];
    }
}