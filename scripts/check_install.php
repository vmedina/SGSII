<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Database.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
try {
    $config = Database::configuration($root);
    $db = Database::connect($config);
    foreach (['users', 'rooms', 'supplies', 'reservations', 'loans', 'incidents'] as $table) {
        $db->query("SELECT 1 FROM {$table} LIMIT 1");
    }
    $admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();
    if ($admins < 1) throw new RuntimeException('No existe un administrador activo.');
    echo "PASS: instalación {$config['driver']} lista; 6 tablas y administrador verificados.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exit(1);
}
