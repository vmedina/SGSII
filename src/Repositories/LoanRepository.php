<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class LoanRepository
{
    /** Persiste préstamos y consultas relacionadas con su estado de devolución. */
    public function __construct(private \PDO $database) {}

    public function findAll(): array
    {
        return $this->fetchAll(
            'SELECT l.*,s.name supply_name,u.first_name,u.last_name
             FROM loans l
             JOIN supplies s ON s.id=l.supply_id
             JOIN users u ON u.id=l.user_id
             ORDER BY l.returned_at IS NULL DESC,l.loaned_at DESC'
        );
    }

    public function returnLoan(int $id): void
    {
        $statement = $this->database->prepare(
            'UPDATE loans SET returned_at=CURRENT_TIMESTAMP WHERE id=? AND returned_at IS NULL'
        );
        $statement->execute([$id]);
    }

    public function create(int $supplyId, int $userId, int $quantity, string $notes): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO loans (supply_id, user_id, quantity, notes) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$supplyId, $userId, $quantity, $notes]);
    }

    private function fetchAll(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }
}
