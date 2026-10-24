<?php
declare(strict_types=1);

require __DIR__ . '/../../src/App.php';

$databasePath = $argv[1] ?? '';
$gatePath = $argv[2] ?? '';
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
    $app->createReservation(1, [
        'resource_type' => 'room',
        'resource_id' => 1,
        'reservation_date' => '2030-01-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);
    echo 'SUCCESS';
} catch (InvalidArgumentException) {
    echo 'CONFLICT';
} catch (Throwable $error) {
    echo 'ERROR:' . $error::class;
    exit(1);
}
