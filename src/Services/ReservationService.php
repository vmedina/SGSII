<?php
declare(strict_types=1);

namespace Sgsii\Services;

require_once __DIR__ . '/../Validation/Validator.php';
require_once __DIR__ . '/TransactionCoordinator.php';

use Sgsii\Repositories\ReservationRepository;
use Sgsii\Validation\Validator;

final class ReservationService
{
    private const RESOURCE_STORAGE = [
        'room' => ['table' => 'rooms', 'column' => 'room_id'],
        'supply' => ['table' => 'supplies', 'column' => 'supply_id'],
    ];

    public function __construct(
        private \PDO $database,
        private ReservationRepository $reservations,
        private ?\DateTimeImmutable $clock = null,
        ?TransactionCoordinator $transactions = null
    ) {
        $this->transactions = $transactions ?? new TransactionCoordinator($database);
    }

    private TransactionCoordinator $transactions;

    public function create(int $actorId, array $data, ?int $reservationId = null, bool $admin = false): void
    {
        [$type, $resourceId, $date, $start, $end, $quantity] = $this->reservationRequest($data);
        $today = $this->now()->format('Y-m-d');
        $storage = $this->resourceStorage($type);
        $table = $storage['table'];
        $column = $storage['column'];

        $transactionStarted = $this->beginWriteTransaction($table, $resourceId);
        try {
            $ownerId = $actorId;
            if ($reservationId) {
                $existing = $this->one('SELECT user_id, reservation_date FROM reservations WHERE id=?' . $this->forUpdateClause(), [$reservationId]);
                if (!$existing) throw new \InvalidArgumentException('La reserva no existe.');
                if ($existing['reservation_date'] < $today) throw new \InvalidArgumentException('Las reservas pasadas solo pueden cancelarse.');
                $ownerId = (int) $existing['user_id'];
                if ($admin && array_key_exists('user_id', $data)) $ownerId = $this->activeTeacherId($data['user_id']);
            } elseif ($admin) {
                if (!array_key_exists('user_id', $data)) throw new \InvalidArgumentException('Seleccione un docente responsable.');
                $ownerId = $this->activeTeacherId($data['user_id']);
            }

            $resource = $this->one("SELECT * FROM {$table} WHERE id=? AND active=1" . $this->forUpdateClause(), [$resourceId]);
            if (!$resource) throw new \InvalidArgumentException('El recurso no está disponible.');
            if ($type === 'supply' && $resource['status'] !== 'operational') throw new \InvalidArgumentException('El insumo no está operativo.');
            if (!$this->available($type, $resourceId, $date, $start, $end, $quantity, $reservationId)) {
                throw new \InvalidArgumentException('Existe un conflicto de disponibilidad para ese horario.');
            }

            if ($reservationId) {
                $this->execute(
                    'UPDATE reservations SET user_id=?, room_id=?, supply_id=?, quantity=?, reservation_date=?, start_time=?, end_time=? WHERE id=?',
                    [$ownerId, $type === 'room' ? $resourceId : null, $type === 'supply' ? $resourceId : null, $quantity, $date, $start, $end, $reservationId]
                );
            } else {
                $this->execute(
                    "INSERT INTO reservations (user_id, {$column}, quantity, reservation_date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)",
                    [$ownerId, $resourceId, $quantity, $date, $start, $end]
                );
            }

            $this->transactions->commit($transactionStarted);
        } catch (\Throwable $error) {
            $this->rollbackWriteTransaction($transactionStarted);
            throw $error;
        }
    }

    public function checkAvailability(array $data): array
    {
        [$type, $resourceId, $date, $start, $end, $quantity] = $this->reservationRequest($data);
        $table = $this->resourceStorage($type)['table'];
        $resource = $this->one("SELECT * FROM {$table} WHERE id=?", [$resourceId]);
        if (!$resource) throw new \InvalidArgumentException('El recurso no existe.');

        $result = [
            'available' => false,
            'status' => 'maintenance',
            'title' => 'No disponible',
            'message' => 'El recurso está inactivo.',
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'resource_name' => (string) $resource['name'],
            'reservation_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'quantity' => $quantity,
        ];
        if (!(bool) $resource['active']) return $result;
        if ($type === 'supply' && $resource['status'] !== 'operational') {
            if ($resource['status'] === 'repair') {
                $result['title'] = 'En mantenimiento';
                $result['message'] = 'El insumo está en reparación.';
            } else {
                $result['title'] = 'Fuera de servicio';
                $result['message'] = 'El insumo está fuera de servicio.';
            }
            return $result;
        }
        if ($this->available($type, $resourceId, $date, $start, $end, $quantity)) {
            $result['available'] = true;
            $result['status'] = 'available';
            $result['title'] = 'Disponible';
            $result['message'] = $type === 'room' ? 'La sala está disponible en ese horario.' : 'Hay stock suficiente para la cantidad solicitada.';
            return $result;
        }
        if ($type === 'room') {
            $result['status'] = 'reserved';
            $result['title'] = 'Reservado';
            $result['message'] = 'La sala ya está reservada en ese horario.';
        } else {
            $result['status'] = 'insufficient_stock';
            $result['title'] = 'Stock insuficiente';
            $result['message'] = 'No hay stock suficiente para la cantidad solicitada.';
        }
        return $result;
    }

    public function available(string $type, int $id, string $date, string $start, string $end, int $quantity, ?int $exclude = null): bool
    {
        $column = $this->resourceStorage($type)['column'];
        $sql = "SELECT COALESCE(SUM(quantity),0) AS used FROM reservations WHERE {$column}=? AND reservation_date=? AND status='active' AND start_time < ? AND end_time > ?";
        $params = [$id, $date, $end, $start];
        if ($exclude) { $sql .= ' AND id <> ?'; $params[] = $exclude; }
        $used = (int) $this->one($sql, $params)['used'];
        if ($type === 'room') return $used === 0;
        $supply = $this->one('SELECT quantity FROM supplies WHERE id=?', [$id]);
        $loaned = (int) $this->one('SELECT COALESCE(SUM(quantity),0) AS used FROM loans WHERE supply_id=? AND returned_at IS NULL', [$id])['used'];
        return $used + $loaned + $quantity <= (int) $supply['quantity'];
    }

    public function cancel(int $id, int $actorId, bool $admin): void
    {
        $reservation = $this->reservations->findActiveOwner($id);
        if (!$reservation || (!$admin && (int) $reservation['user_id'] !== $actorId)) {
            throw new \InvalidArgumentException('No puede cancelar esa reserva.');
        }
        $this->reservations->cancel($id);
    }

    private function reservationRequest(array $data): array
    {
        $type = Validator::choice($data['resource_type'] ?? '', ['room', 'supply'], 'Tipo de recurso');
        $resourceId = Validator::integer($data['resource_id'] ?? null, 1, PHP_INT_MAX, 'Recurso');
        $date = Validator::date($data['reservation_date'] ?? '');
        if ($date < $this->now()->format('Y-m-d')) throw new \InvalidArgumentException('La fecha de reserva no puede ser anterior a hoy.');
        $start = Validator::hour($data['start_time'] ?? '');
        $end = Validator::hour($data['end_time'] ?? '');
        if ($end <= $start) throw new \InvalidArgumentException('La hora final debe ser posterior a la inicial.');
        $quantity = $type === 'supply' ? Validator::integer($data['quantity'] ?? null, 1, 9999, 'Cantidad') : 1;
        return [$type, $resourceId, $date, $start, $end, $quantity];
    }

    private function activeTeacherId(mixed $value): int
    {
        $userId = Validator::integer($value, 1, PHP_INT_MAX, 'Docente');
        $teacher = $this->one("SELECT id FROM users WHERE id=? AND active=1 AND role='user'", [$userId]);
        if (!$teacher) throw new \InvalidArgumentException('El docente responsable no está disponible.');
        return $userId;
    }

    private function resourceStorage(string $type): array
    {
        return self::RESOURCE_STORAGE[$type] ?? throw new \InvalidArgumentException('Tipo de recurso inválido.');
    }

    private function beginWriteTransaction(string $table, int $id): bool
    {
        return $this->transactions->begin($table, $id);
    }

    private function rollbackWriteTransaction(bool $started): void
    {
        $this->transactions->rollback($started);
    }

    private function forUpdateClause(): string { return $this->driverName() === 'mysql' ? ' FOR UPDATE' : ''; }
    private function driverName(): string { return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME); }
    private function now(): \DateTimeImmutable { return $this->clock ?? new \DateTimeImmutable('now'); }

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