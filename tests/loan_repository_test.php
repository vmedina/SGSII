<?php
declare(strict_types=1);

require __DIR__ . '/../src/Repositories/LoanRepository.php';

use Sgsii\Repositories\LoanRepository;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO supplies (name,asset_code,category) VALUES ('Notebook','NB-1','Informática')");
$insert = $database->prepare('INSERT INTO loans (supply_id,user_id,quantity,loaned_at,notes) VALUES (1,1,?,?,?)');
$insert->execute([2, '2030-01-01 10:00:00', 'Activo']);
$insert->execute([1, '2030-01-02 10:00:00', 'Devuelto']);
$database->exec("UPDATE loans SET returned_at='2030-01-02 12:00:00' WHERE id=2");

$repository = new LoanRepository($database);
$loans = $repository->findAll();
assertSame(2, count($loans), 'El listado no devolvió todos los préstamos.');
assertSame(1, (int) $loans[0]['id'], 'Los préstamos activos no aparecen primero.');
assertSame('Notebook', $loans[0]['supply_name'], 'El nombre del insumo no se resolvió.');
assertSame('Ana Docente', $loans[0]['first_name'] . ' ' . $loans[0]['last_name'], 'El docente no se resolvió.');

$repository->returnLoan(1);
assertTrue($database->query('SELECT returned_at FROM loans WHERE id=1')->fetchColumn() !== false, 'La devolución no actualizó el préstamo.');
$returnedAt = $database->query('SELECT returned_at FROM loans WHERE id=1')->fetchColumn();
assertTrue(is_string($returnedAt) && $returnedAt !== '', 'La devolución no registró timestamp.');

echo "PASS: loan repository\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}