<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$expectedMethods = [
    'connect',
    'db',
    'all',
    'one',
    'execute',
    'id',
    'login',
    'saveUser',
    'changePassword',
    'saveRoom',
    'saveSupply',
    'deactivate',
    'createReservation',
    'checkAvailability',
    'available',
    'cancelReservation',
    'loan',
    'returnLoan',
    'incident',
    'updateIncident',
    'incidentsFor',
    'reportSummary',
];

$reflection = new ReflectionClass(App::class);
foreach ($expectedMethods as $method) {
    assertTrue($reflection->hasMethod($method), "La fachada perdió el método {$method}.");
    assertTrue($reflection->getMethod($method)->isPublic(), "El método {$method} dejó de ser público.");
}

$application = App::connect(':memory:');
assertTrue($application instanceof App, 'App::connect dejó de devolver la fachada App.');
assertTrue($application->db() instanceof PDO, 'La fachada dejó de exponer su conexión PDO.');

echo "PASS: contrato de fachada App\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}