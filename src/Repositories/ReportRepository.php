<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class ReportRepository
{
    public function __construct(private \PDO $database) {}

    public function summary(string $from, string $to): array
    {
        return [
            'Reservas' => (int) $this->one(
                "SELECT COUNT(*) AS total FROM reservations
                 WHERE reservation_date BETWEEN ? AND ? AND status='active'",
                [$from, $to]
            )['total'],
            'Préstamos' => (int) $this->one(
                'SELECT COUNT(*) AS total FROM loans WHERE date(loaned_at) BETWEEN ? AND ?',
                [$from, $to]
            )['total'],
            'Incidencias' => (int) $this->one(
                'SELECT COUNT(*) AS total FROM incidents WHERE date(created_at) BETWEEN ? AND ?',
                [$from, $to]
            )['total'],
        ];
    }

    private function one(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return $row === false ? ['total' => 0] : $row;
    }
}