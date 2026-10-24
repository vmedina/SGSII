<?php
declare(strict_types=1);

$entryPoint = file_get_contents(__DIR__ . '/../public/index.php');
$dispatcher = file_get_contents(__DIR__ . '/../src/Http/ActionDispatcher.php');

$pages = [
    'login',
    'dashboard',
    'availability',
    'rooms',
    'supplies',
    'users',
    'reservations',
    'loans',
    'incidents',
    'reports',
    'password',
];

foreach ($pages as $page) {
    assertContains("page === '{$page}'", $entryPoint, "La ruta page={$page} desapareció.");
}

$actions = [
    'login',
    'logout',
    'password',
    'save_user',
    'deactivate_user',
    'save_room',
    'deactivate_room',
    'save_supply',
    'deactivate_supply',
    'reservation',
    'cancel_reservation',
    'loan',
    'return_loan',
    'incident',
    'update_incident',
];

foreach ($actions as $action) {
    assertContains("action === '{$action}'", $dispatcher, "La acción {$action} dejó de ser reconocida.");
    assertContains("name=\"action\" value=\"{$action}\"", $entryPoint, "La acción {$action} no tiene formulario HTTP.");
}

foreach (['users', 'rooms', 'supplies', 'loans', 'reports'] as $adminPage) {
    assertContains("'{$adminPage}'", $entryPoint, "La protección administrativa de {$adminPage} desapareció.");
}

echo "PASS: contrato HTTP de rutas y acciones\n";

function assertContains(string $expected, string $content, string $message): void
{
    if (!str_contains($content, $expected)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}