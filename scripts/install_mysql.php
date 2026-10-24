<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este instalador solo puede ejecutarse desde una terminal.\n");
    exit(1);
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Falta la extensión pdo_mysql de PHP.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    $defaults = [
        /*'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'sgsii',
        'username' => 'root',
        'password' => '',*/

        'driver' => 'mysql',
        'host' => 'sql207.infinityfree.com',
        'port' => 3306,
        'database' => 'if0_42936421_sgsii',
        'username' => 'if0_42936421',
        'password' => '4mV9GJClq0bV16',
    ];
    if (file_exists($root . '/config.local.php')) {
        $existingConfig = Database::configuration($root);
        if (($existingConfig['driver'] ?? null) === 'mysql') $defaults = $existingConfig;
    }

    $config = [
        'driver' => 'mysql',
        'host' => envValue('SGSII_DB_HOST', $defaults['host']),
        'port' => (int)envValue('SGSII_DB_PORT', (string)$defaults['port']),
        'database' => envValue('SGSII_DB_NAME', $defaults['database']),
        'username' => envValue('SGSII_DB_USER', $defaults['username']),
        'password' => envValue('SGSII_DB_PASSWORD', $defaults['password']),
    ];

    $adminWasProvided = getenv('SGSII_ADMIN_USERNAME') !== false;
    $generatedPassword = generatePassword();
    $admin = [
        'username' => envValue('SGSII_ADMIN_USERNAME', 'admin'),
        'first_name' => envValue('SGSII_ADMIN_FIRST_NAME', 'Administrador'),
        'last_name' => envValue('SGSII_ADMIN_LAST_NAME', 'SGSII'),
        'password' => envValue('SGSII_ADMIN_PASSWORD', $generatedPassword),
    ];
    validateInstallInput($config, $admin);

    $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $server = new PDO($serverDsn, $config['username'], $config['password'], $options);
    $databaseName = $config['database'];
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $server = null;

    $db = Database::connect($config);
    $schema = file_get_contents($root . '/database/schema.mysql.sql');
    if ($schema === false) throw new RuntimeException('No se pudo leer el esquema MySQL.');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $db->exec($statement);
    }

    if ($adminWasProvided) {
        $query = $db->prepare('SELECT id, username, role FROM users WHERE username=?');
        $query->execute([$admin['username']]);
        $existingAdmin = $query->fetch();
        if ($existingAdmin && $existingAdmin['role'] !== 'admin') {
            throw new RuntimeException('El usuario administrador ya existe con otro rol.');
        }
    } else {
        $existingAdmin = $db->query(
            "SELECT id, username, role FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1"
        )->fetch();
    }

    $adminCreated = false;
    if (!$existingAdmin) {
        $insert = $db->prepare(
            "INSERT INTO users (username, first_name, last_name, password_hash, role) VALUES (?, ?, ?, ?, 'admin')"
        );
        $insert->execute([
            $admin['username'],
            $admin['first_name'],
            $admin['last_name'],
            password_hash($admin['password'], PASSWORD_DEFAULT),
        ]);
        $adminCreated = true;
    }

    $configFile = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($root . '/config.local.php', $configFile, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar config.local.php.');
    }

    echo "Base MySQL preparada: {$config['database']}\n";
    echo "Configuración guardada en config.local.php\n";
    if ($adminCreated) {
        echo "\nCREDENCIALES INICIALES\n";
        echo "Usuario: {$admin['username']}\n";
        echo "Contraseña: {$admin['password']}\n";
        echo "Guárdelas y cambie la contraseña después de ingresar.\n\n";
    } else {
        echo "Administrador existente conservado: {$existingAdmin['username']}\n";
    }
} catch (PDOException $error) {
    fwrite(STDERR, "No se pudo instalar MySQL: {$error->getMessage()}\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, "Instalación incompleta: {$error->getMessage()}\n");
    exit(1);
}

function envValue(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function generatePassword(): string
{
    return 'Sg!' . bin2hex(random_bytes(7)) . '9a';
}

function validateInstallInput(array $config, array $admin): void
{
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $config['host'])
        || $config['port'] < 1 || $config['port'] > 65535
        || !preg_match('/^[A-Za-z0-9_]+$/', $config['database'])
        || $config['username'] === '') {
        throw new InvalidArgumentException('La configuración de conexión MySQL no es válida.');
    }
    if (strlen($admin['username']) < 3 || strlen($admin['username']) > 50
        || trim($admin['first_name']) === '' || strlen($admin['first_name']) > 80
        || trim($admin['last_name']) === '' || strlen($admin['last_name']) > 80
        || strlen($admin['password']) < 8) {
        throw new InvalidArgumentException('Los datos del administrador no son válidos.');
    }
}
