<?php
declare(strict_types=1);

require __DIR__ . '/../../src/App.php';

$databasePath = $argv[1] ?? '';
$gatePath = $argv[2] ?? '';
$operation = $argv[3] ?? '';
$deadline = microtime(true) + 5;

while (!file_exists($gatePath) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!file_exists($gatePath)) {
    echo 'ERROR:gate';
    exit(1);
}

try {
    $app = App::connect($databasePath);
    if ($operation === 'loan') {
        $app->loan([
            'supply_id' => 1,
            'user_id' => 1,
            'quantity' => 2,
            'notes' => 'Carrera de inventario',
        ]);
    } elseif ($operation === 'update') {
        $app->saveSupply([
            'name' => 'Notebook',
            'asset_code' => 'NB-1',
            'category' => 'Informática',
            'description' => 'Equipo',
            'quantity' => 1,
            'status' => 'operational',
        ], 1);
    } else {
        throw new RuntimeException('Operación desconocida.');
    }
    echo 'SUCCESS';
} catch (InvalidArgumentException) {
    echo 'CONFLICT';
} catch (Throwable $error) {
    echo 'ERROR:' . $error::class;
    exit(1);
}
