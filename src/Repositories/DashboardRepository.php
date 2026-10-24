<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class DashboardRepository
{
    /** Proporciona los indicadores resumidos mostrados en el panel principal. */
    public function __construct(private \PDO $database) {}

    public function findActiveReservationsForDate(string $date): array
    {
        return $this->fetchAll(
            "SELECT r.*, COALESCE(ro.name,s.name) resource_name,
                    CASE WHEN r.room_id IS NULL THEN 'Insumo' ELSE 'Sala' END resource_type
             FROM reservations r
             LEFT JOIN rooms ro ON ro.id=r.room_id
             LEFT JOIN supplies s ON s.id=r.supply_id
             WHERE r.status='active' AND r.reservation_date=?
             ORDER BY r.start_time",
            [$date]
        );
    }

    public function summaryForDate(string $date, string $time): array
    {
        $statement = $this->database->prepare(
            "SELECT
                (SELECT COUNT(*) FROM rooms ro
                 WHERE ro.active=1 AND NOT EXISTS (
                    SELECT 1 FROM reservations rr
                    WHERE rr.room_id=ro.id AND rr.status='active'
                      AND rr.reservation_date=? AND rr.start_time<=? AND rr.end_time>?
                 )) AS available_rooms,
                (SELECT COUNT(*) FROM reservations rr
                 WHERE rr.status='active' AND rr.reservation_date=?) AS active_reservations,
                (SELECT COUNT(*) FROM supplies sp
                 WHERE sp.active=1 AND sp.status IN ('repair','out_of_service')) AS maintenance,
                (SELECT COUNT(*) FROM incidents ii
                 WHERE ii.status IN ('open','in_progress')) AS open_incidents"
        );
        $statement->execute([$date, $time, $time, $date]);
        $summary = $statement->fetch() ?: [];

        return array_map('intval', [
            'available_rooms' => $summary['available_rooms'] ?? 0,
            'active_reservations' => $summary['active_reservations'] ?? 0,
            'maintenance' => $summary['maintenance'] ?? 0,
            'open_incidents' => $summary['open_incidents'] ?? 0,
        ]);
    }
    private function fetchAll(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }
}
