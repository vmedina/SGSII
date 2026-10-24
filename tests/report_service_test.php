<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/ReportService.php';
require __DIR__ . '/../src/Repositories/ReportRepository.php';

use Sgsii\Repositories\ReportRepository;
use Sgsii\Services\ReportService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$service = new ReportService(new ReportRepository($database));

$summary = $service->summary('2030-01-01', '2030-01-31');
assertSame('2030-01-01', $summary['from'], 'El servicio alteró la fecha inicial.');
assertSame(['Reservas' => 0, 'Préstamos' => 0, 'Incidencias' => 0], $summary['summary'], 'El servicio no coordinó el reporte vacío.');
expectInvalid(static fn() => $service->summary('2030-02-01', '2030-01-31'));
expectInvalid(static fn() => $service->summary('2030-02-30', '2030-03-01'));

echo "PASS: report service\n";

function expectInvalid(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: se aceptó un filtro de reporte inválido.');
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}