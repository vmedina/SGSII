<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/LoanService.php';
require __DIR__ . '/../src/Repositories/LoanRepository.php';

use Sgsii\Repositories\LoanRepository;
use Sgsii\Services\LoanService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO supplies (name,asset_code,category,quantity,status) VALUES ('Notebook','NB-1','Equipo',2,'operational')");
$service = new LoanService($database, new LoanRepository($database), new DateTimeImmutable('2030-01-01 08:00:00'));

$service->create(['supply_id' => 1, 'user_id' => 1, 'quantity' => 1, 'notes' => 'Prueba']);
assertSame(1, (int) $database->query('SELECT COUNT(*) FROM loans')->fetchColumn(), 'No se creó el préstamo.');
$loanId = (int) $database->lastInsertId();
$service->return($loanId);
assertTrue($database->query('SELECT returned_at FROM loans WHERE id=' . $loanId)->fetchColumn() !== false, 'No se registró la devolución.');

expectInvalid(static fn() => $service->create(['supply_id' => 1, 'user_id' => 1, 'quantity' => 3, 'notes' => '']));

echo "PASS: loan service\n";

function expectInvalid(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('FAIL: el préstamo sin stock fue aceptado.');
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}