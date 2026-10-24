<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/TransactionCoordinator.php';

use Sgsii\Services\TransactionCoordinator;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec('CREATE TABLE resources (id INTEGER PRIMARY KEY, name TEXT)');
$database->exec("INSERT INTO resources (id,name) VALUES (1,'Original')");
$coordinator = new TransactionCoordinator($database);

$started = $coordinator->begin('resources', 1);
$database->exec("UPDATE resources SET name='Committed' WHERE id=1");
$coordinator->commit($started);
assertSame('Committed', $database->query('SELECT name FROM resources WHERE id=1')->fetchColumn(), 'El coordinador no confirmó.');

$started = $coordinator->begin('resources', 1);
$database->exec("UPDATE resources SET name='Rolled back' WHERE id=1");
$coordinator->rollback($started);
assertSame('Committed', $database->query('SELECT name FROM resources WHERE id=1')->fetchColumn(), 'El coordinador no revirtió.');

echo "PASS: transaction coordinator\n";

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException("FAIL: {$message}");
}