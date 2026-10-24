<?php
declare(strict_types=1);

namespace Sgsii\Validation;

final class Validator
{
    /**
     * Reglas pequeñas y reutilizables para validar datos de formularios y API.
     */
    public static function text(mixed $value, int $minimum, int $maximum, string $label): string
    {
        // Quita espacios exteriores y comprueba el rango de longitud.
        $text = trim((string) $value);
        if (strlen($text) < $minimum || strlen($text) > $maximum) {
            throw new \InvalidArgumentException("{$label}: longitud inválida.");
        }

        return $text;
    }

    public static function integer(mixed $value, int $minimum, int $maximum, string $label): int
    {
        // Rechaza valores no enteros y límites fuera del intervalo permitido.
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum || (int) $value > $maximum) {
            throw new \InvalidArgumentException("{$label}: valor inválido.");
        }

        return (int) $value;
    }

    public static function choice(mixed $value, array $allowed, string $label): string
    {
        // Comprueba que el valor sea texto y pertenezca exactamente al catálogo.
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("{$label}: valor inválido.");
        }

        return $value;
    }

    public static function date(mixed $value): string
    {
        // Valida fechas ISO completas, evitando aceptar fechas normalizadas.
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Fecha inválida.');
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Fecha inválida.');
        }

        return $value;
    }

    public static function hour(mixed $value): string
    {
        // El sistema trabaja con turnos de una hora; por eso solo admite :00.
        if (!is_string($value) || !preg_match('/^([01][0-9]|2[0-3]):00$/', $value)) {
            throw new \InvalidArgumentException('Las horas deben terminar en :00.');
        }

        return $value;
    }
}
