<?php
declare(strict_types=1);

namespace Sgsii\Services;

final class TransactionCoordinator
{
    public function __construct(private \PDO $database) {}

    public function begin(string $table, int $id): bool
    {
        if ($this->database->inTransaction()) return false;
        $this->database->beginTransaction();
        try {
            if ($this->driverName() === 'sqlite') {
                $statement = $this->database->prepare("UPDATE {$table} SET id=id WHERE id=?");
                $statement->execute([$id]);
            }
            return true;
        } catch (\Throwable $error) {
            $this->database->rollBack();
            throw $error;
        }
    }

    public function commit(bool $started): void
    {
        if ($started && $this->database->inTransaction()) $this->database->commit();
    }

    public function rollback(bool $started): void
    {
        if (!$started || !$this->database->inTransaction()) return;
        try { $this->database->rollBack(); } catch (\Throwable) {}
    }

    private function driverName(): string
    {
        return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}