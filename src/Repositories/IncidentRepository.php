<?php
declare(strict_types=1);

namespace Sgsii\Repositories;

final class IncidentRepository
{
    public function __construct(private \PDO $database) {}

    public function findForViewer(int $viewerId, bool $admin): array
    {
        $resourceFields = "COALESCE(ro.name,s.name) resource_name,
            CASE WHEN i.room_id IS NOT NULL THEN ro.active
                WHEN s.active=1 AND s.status='operational' THEN 1 ELSE 0 END resource_available";
        $joins = 'LEFT JOIN rooms ro ON ro.id=i.room_id LEFT JOIN supplies s ON s.id=i.supply_id';

        if ($admin) {
            return $this->fetchAll(
                "SELECT i.id,i.reporter_id,i.room_id,i.supply_id,i.description,i.urgency,i.status,
                    i.admin_notes,i.created_at,i.resolved_at,u.first_name,u.last_name,{$resourceFields}
                FROM incidents i JOIN users u ON u.id=i.reporter_id {$joins}
                ORDER BY i.status='resolved',i.created_at DESC,i.id DESC"
            );
        }

        return $this->fetchAll(
            "SELECT i.id,i.room_id,i.supply_id,i.description,i.urgency,i.status,
                i.created_at,i.resolved_at,{$resourceFields}
            FROM incidents i {$joins}
            WHERE i.reporter_id=?
            ORDER BY i.status='resolved',i.created_at DESC,i.id DESC",
            [$viewerId]
        );
    }

    private function fetchAll(string $query, array $parameters = []): array
    {
        $statement = $this->database->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }
}