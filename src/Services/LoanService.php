<?php
declare(strict_types=1);

namespace Sgsii\Services;

require_once __DIR__ . '/../Validation/Validator.php';
require_once __DIR__ . '/TransactionCoordinator.php';

use Sgsii\Repositories\LoanRepository;
use Sgsii\Validation\Validator;

final class LoanService
{
    public function __construct(
        private \PDO $database,
        private LoanRepository $loans,
        private ?\DateTimeImmutable $clock = null,
        ?TransactionCoordinator $transactions = null
    ) {
        $this->transactions = $transactions ?? new TransactionCoordinator($database);
    }

    private TransactionCoordinator $transactions;

    public function create(array $data): void
    {
        $supply = Validator::integer($data['supply_id'] ?? null, 1, PHP_INT_MAX, 'Insumo');
        $user = Validator::integer($data['user_id'] ?? null, 1, PHP_INT_MAX, 'Docente');
        $quantity = Validator::integer($data['quantity'] ?? null, 1, 9999, 'Cantidad');
        $notes = Validator::text($data['notes'] ?? '', 0, 300, 'Notas');

        $transactionStarted = $this->beginWriteTransaction($supply);
        try {
            $item = $this->one("SELECT * FROM supplies WHERE id=? AND active=1 AND status='operational'" . $this->forUpdateClause(), [$supply]);
            if (!$item) throw new \InvalidArgumentException('El insumo no se puede prestar.');
            $committed = $this->supplyCommittedQuantity($supply);
            if ($committed + $quantity > (int) $item['quantity']) {
                throw new \InvalidArgumentException('No hay stock suficiente para el préstamo considerando las reservas activas.');
            }

            $this->loans->create($supply, $user, $quantity, $notes);
            $this->transactions->commit($transactionStarted);
        } catch (\Throwable $error) {
            $this->rollbackWriteTransaction($transactionStarted);
            throw $error;
        }
    }

    public function return(int $id): void
    {
        $this->loans->returnLoan($id);
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

    private function beginWriteTransaction(int $supplyId): bool
    {
        return $this->transactions->begin('supplies', $supplyId);
    }

    private function rollbackWriteTransaction(bool $started): void
    {
        $this->transactions->rollback($started);
    }

    private function forUpdateClause(): string { return $this->driverName() === 'mysql' ? ' FOR UPDATE' : ''; }
    private function driverName(): string { return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME); }

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