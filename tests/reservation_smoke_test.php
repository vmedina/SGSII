<?php
declare(strict_types=1);

require __DIR__ . '/../src/App.php';

$path = temporaryDatabasePath();
try {
    runReservationScenario($path);
} finally {
    removeTemporaryDatabase($path);
}
assertSmoke(!file_exists($path), 'La base temporal no se eliminó después del escenario exitoso');

$failurePath = temporaryDatabasePath();
try {
    try {
        runFailingScenario($failurePath);
    } finally {
        removeTemporaryDatabase($failurePath);
    }
    throw new RuntimeException('No se produjo el fallo de control');
} catch (RuntimeException $exception) {
    assertSmoke($exception->getMessage() === 'fallo esperado para validar limpieza', 'Se ocultó el fallo original durante la limpieza');
}
assertSmoke(!file_exists($failurePath), 'La base temporal no se eliminó después del escenario fallido');

echo "PASS: smoke de reservas\n";

function runReservationScenario(string $path): void
{
    $db = openTemporaryDatabase($path);
    $app = new App($db);
    try {
        $db->prepare("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES (?,?,?,?,?)")
            ->execute(['teacher','Ana','Docente',password_hash('password123', PASSWORD_DEFAULT),'user']);
        $db->exec("INSERT INTO rooms (name,room_number,location,equipment_count) VALUES ('Laboratorio 1','L1','Piso 1',20)");
        $db->exec("INSERT INTO supplies (name,asset_code,category,quantity,status) VALUES ('Laptops','LAP-01','Laptop',2,'operational')");
        $user = 1;
        $app->createReservation($user, ['resource_type'=>'room','resource_id'=>1,'reservation_date'=>'2030-01-10','start_time'=>'09:00','end_time'=>'10:00']);
        assertSmoke(!$app->available('room',1,'2030-01-10','09:00','10:00',1), 'La misma sala no queda disponible en el mismo intervalo');
        assertSmoke($app->available('room',1,'2030-01-10','10:00','11:00',1), 'Las reservas consecutivas son válidas');
        assertSmoke(!$app->available('room',1,'2030-01-10','09:30','10:00',1), 'Un intervalo superpuesto se rechaza');
        assertSmoke($app->available('supply',1,'2030-01-10','09:00','10:00',2), 'El stock permite dos insumos');
        $app->createReservation($user, ['resource_type'=>'supply','resource_id'=>1,'reservation_date'=>'2030-01-10','start_time'=>'09:00','end_time'=>'10:00','quantity'=>2]);
        assertSmoke(!$app->available('supply',1,'2030-01-10','09:00','10:00',1), 'El stock reservado bloquea más solicitudes');
        try {
            $app->createReservation($user, ['resource_type'=>'room','resource_id'=>1,'reservation_date'=>'2030-01-10','start_time'=>'09:30','end_time'=>'10:00']);
            throw new RuntimeException('Se aceptó una superposición');
        } catch (InvalidArgumentException) {
        }
        try {
            $app->createReservation($user, ['resource_type'=>'room','resource_id'=>1,'reservation_date'=>'2030-01-10','start_time'=>'10:30','end_time'=>'10:00']);
            throw new RuntimeException('Se aceptó un intervalo inválido');
        } catch (InvalidArgumentException) {
        }
    } finally {
        $app = null;
        $db = null;
    }
}

function runFailingScenario(string $path): void
{
    $db = openTemporaryDatabase($path);
    $app = new App($db);
    try {
        throw new RuntimeException('fallo esperado para validar limpieza');
    } finally {
        $app = null;
        $db = null;
    }
}

function openTemporaryDatabase(string $path): PDO
{
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    return $db;
}

function temporaryDatabasePath(): string
{
    return sys_get_temp_dir() . '/sgsii-test-' . bin2hex(random_bytes(4)) . '.sqlite';
}

function removeTemporaryDatabase(string $path): void
{
    if (file_exists($path) && !@unlink($path)) {
        throw new RuntimeException("No se pudo eliminar la base temporal: {$path}");
    }
}

function assertSmoke(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}
