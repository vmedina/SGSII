<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class ReservationRepository
{
    public function __construct(private \PDO $database) {}

    public function findById(mixed $id): ?array
    {
        return $this->fetchOne('SELECT * FROM reservations WHERE id=?', [$id]);
    }

    public function findOwnerAndStatus(int $id): ?array
    {
        return $this->fetchOne('SELECT user_id,status FROM reservations WHERE id=?', [$id]);
    }

    public function findActiveOwner(int $id): ?array
    {
        return $this->fetchOne("SELECT user_id FROM reservations WHERE id=? AND status='active'", [$id]);
    }

    public function cancel(int $id): void
    {
        $statement = $this->database->prepare("UPDATE reservations SET status='cancelled' WHERE id=?");
        $statement->execute([$id]);
    }

    public function findAllForAdmin(): array
    {
        return $this->fetchAll(
            "SELECT r.*,u.first_name,u.last_name,COALESCE(ro.name,s.name) resource_name
             FROM reservations r
             JOIN users u ON u.id=r.user_id
             LEFT JOIN rooms ro ON ro.id=r.room_id
             LEFT JOIN supplies s ON s.id=r.supply_id
             ORDER BY r.reservation_date DESC,r.start_time DESC"
        );
    }

    public function findAllForUser(int $userId): array
    {
        return $this->fetchAll(
            "SELECT r.*,COALESCE(ro.name,s.name) resource_name
             FROM reservations r
             LEFT JOIN rooms ro ON ro.id=r.room_id
             LEFT JOIN supplies s ON s.id=r.supply_id
             WHERE r.user_id=?
             ORDER BY r.reservation_date DESC,r.start_time DESC",
            [$userId]
        );
    }

    private function fetchAll(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function fetchOne(string $query, array $parameters = []): ?array
    {
        $rows = $this->fetchAll($query, $parameters);
        return $rows[0] ?? null;
    }
}
