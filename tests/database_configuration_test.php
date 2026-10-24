<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';

$root = sys_get_temp_dir() . '/sgsii-config-' . bin2hex(random_bytes(4));
if (!mkdir($root) && !is_dir($root)) throw new RuntimeException('FAIL: no se creó el directorio temporal.');

try {
    $sqlite = Database::configuration($root);
    assertConfig($sqlite['driver'] === 'sqlite', 'La configuración por defecto no usa SQLite.');
    assertConfig($sqlite['path'] === $root . '/database/sgsii.sqlite', 'La ruta SQLite por defecto es incorrecta.');

    $mysqlConfig = [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'sgsii_test',
        'username' => 'root',
        'password' => '',
    ];
    writeConfig($root, $mysqlConfig);
    $mysql = Database::configuration($root);
    assertConfig($mysql['driver'] === 'mysql', 'No se cargó el driver MySQL.');
    assertConfig($mysql['port'] === 3306, 'El puerto MySQL no fue normalizado.');
    assertConfig($mysql['database'] === 'sgsii_test', 'El nombre MySQL cambió.');

    writeConfig($root, [...$mysqlConfig, 'database' => 'sgsii;DROP DATABASE mysql']);
    try {
        Database::configuration($root);
        throw new RuntimeException('FAIL: se aceptó un nombre de base inseguro.');
    } catch (RuntimeException $error) {
        if (str_starts_with($error->getMessage(), 'FAIL:')) throw $error;
    }

    $schema = file_get_contents(__DIR__ . '/../database/schema.mysql.sql');
    assertConfig($schema !== false, 'No se pudo leer el esquema MySQL.');
    foreach (['users', 'rooms', 'supplies', 'reservations', 'loans', 'incidents'] as $table) {
        assertConfig(str_contains($schema, "CREATE TABLE IF NOT EXISTS {$table}"), "Falta la tabla MySQL {$table}.");
    }
    assertConfig(substr_count($schema, 'ENGINE=InnoDB') === 6, 'No todas las tablas MySQL usan InnoDB.');
    assertConfig(str_contains($schema, 'utf8mb4_unicode_ci'), 'El esquema MySQL no usa utf8mb4.');

    echo "PASS: configuración dual SQLite/MySQL\n";
} finally {
    $configPath = $root . '/config.local.php';
    if (file_exists($configPath)) unlink($configPath);
    if (is_dir($root)) rmdir($root);
}

function writeConfig(string $root, array $config): void
{
    $contents = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($root . '/config.local.php', $contents) === false) {
        throw new RuntimeException('FAIL: no se pudo escribir la configuración temporal.');
    }
}

function assertConfig(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}
