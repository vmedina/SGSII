<?php
declare(strict_types=1);

namespace Sgsii\Services;

require_once __DIR__ . '/../Validation/Validator.php';

use Sgsii\Repositories\ReportRepository;
use Sgsii\Validation\Validator;

final class ReportService
{
    public function __construct(private ReportRepository $reports) {}

    public function summary(mixed $fromValue, mixed $toValue): array
    {
        $from = Validator::date($fromValue);
        $to = Validator::date($toValue);
        if ($from > $to) {
            throw new \InvalidArgumentException('La fecha desde no puede ser posterior a la fecha hasta.');
        }

        return [
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->summary($from, $to),
        ];
    }
}