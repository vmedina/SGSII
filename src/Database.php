<?php
declare(strict_types=1);

final class Database
{
    /** Gestiona la configuración y conexión PDO para SQLite o MySQL. */
    public static function configuration(string $root): array
    {
        // Si no hay configuración local, el proyecto funciona con SQLite.
        $localConfig = $root . '/config.local.php';
        if (!file_exists($localConfig)) {
            return [
                'driver' => 'sqlite',
                'path' => $root . '/database/sgsii.sqlite',
            ];
        }

        $config = require $localConfig;
        if (!is_array($config)) {
            throw new RuntimeException('config.local.php debe devolver un arreglo de configuración.');
        }
        return self::validate($config, $root);
    }

    public static function connect(array $config): PDO
    {
        // PDO unifica ambos motores y las opciones fuerzan consultas seguras.
        $driver = $config['driver'] ?? null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $db = new PDO('sqlite:' . $config['path'], null, null, $options);
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA busy_timeout = 3000');
            return $db;
        }

        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );
            return new PDO($dsn, $config['username'], $config['password'], $options);
        }

        throw new RuntimeException('Motor de base de datos no soportado.');
    }

    public static function application(string $root): PDO
    {
        // Atajo usado por el punto de entrada de la aplicación.
        return self::connect(self::configuration($root));
    }

    private static function validate(array $config, string $root): array
    {
        // Normaliza valores y rechaza configuraciones incompletas o inseguras.
        $driver = $config['driver'] ?? null;
        if ($driver === 'sqlite') {
            $path = $config['path'] ?? ($root . '/database/sgsii.sqlite');
            if (!is_string($path) || $path === '') {
                throw new RuntimeException('La ruta SQLite no es válida.');
            }
            return ['driver' => 'sqlite', 'path' => $path];
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException('El driver debe ser sqlite o mysql.');
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? 'sgsii';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        if (!is_string($host) || $host === ''
            || filter_var($port, FILTER_VALIDATE_INT) === false || (int)$port < 1 || (int)$port > 65535
            || !is_string($database) || !preg_match('/^[A-Za-z0-9_]+$/', $database)
            || !is_string($username) || $username === '' || !is_string($password)) {
            throw new RuntimeException('La configuración MySQL no es válida.');
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int)$port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }
}
