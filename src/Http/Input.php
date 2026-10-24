<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Input
{
    public static function postInt(string $name): int
    {
        $value = filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < 1) {
            throw new \InvalidArgumentException('Identificador inválido.');
        }

        return $value;
    }

    /**
     * Convierte el valor del formulario en un recurso válido.
     *
     * Este formato permite distinguir salas e insumos sin mezclar consultas
     * SQL ni reglas de persistencia con el procesamiento HTTP.
     */
    public static function resource(string $value): array
    {
        if (!preg_match('/^(room|supply):([1-9][0-9]*)$/', $value, $matches)) {
            throw new \InvalidArgumentException('Recurso inválido.');
        }

        return [$matches[1], (int) $matches[2]];
    }
}
