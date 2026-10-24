<?php
declare(strict_types=1);

namespace Sgsii\Services;

final class TransactionCoordinator
{
    /** Centraliza el inicio, confirmación y reversión de transacciones de escritura. */
    public function __construct(private \PDO $database) {}

    public function begin(string $table, int $id): bool
    {
        // Si ya existe una transacción, el llamador conserva su control y no se
        // crea una transacción anidada que PDO no soporta de forma uniforme.
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
        // Solo confirma la transacción que este coordinador inició.
        if ($started && $this->database->inTransaction()) $this->database->commit();
    }

    public function rollback(bool $started): void
    {
        // Revierte de forma segura cuando la operación actual abrió la transacción.
        if (!$started || !$this->database->inTransaction()) return;
        try { $this->database->rollBack(); } catch (\Throwable) {}
    }

    private function driverName(): string
    {
        // SQLite necesita un bloqueo explícito de la fila para coordinar escritores.
        return (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
