<?php
declare(strict_types=1);

namespace Sgsii\Validation;

final class Validator
{
    public static function text(mixed $value, int $minimum, int $maximum, string $label): string
    {
        $text = trim((string) $value);
        if (strlen($text) < $minimum || strlen($text) > $maximum) {
            throw new \InvalidArgumentException("{$label}: longitud inválida.");
        }

        return $text;
    }

    public static function integer(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum || (int) $value > $maximum) {
            throw new \InvalidArgumentException("{$label}: valor inválido.");
        }

        return (int) $value;
    }

    public static function choice(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("{$label}: valor inválido.");
        }

        return $value;
    }

    public static function date(mixed $value): string
    {
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
        if (!is_string($value) || !preg_match('/^([01][0-9]|2[0-3]):00$/', $value)) {
            throw new \InvalidArgumentException('Las horas deben terminar en :00.');
        }

        return $value;
    }
}
