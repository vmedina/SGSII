<?php
declare(strict_types=1);

require __DIR__ . '/../src/Services/ReservationService.php';
require __DIR__ . '/../src/Repositories/ReservationRepository.php';

use Sgsii\Repositories\ReservationRepository;
use Sgsii\Services\ReservationService;

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$database->exec("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES ('teacher','Ana','Docente','hash','user')");
$database->exec("INSERT INTO rooms (name,room_number,location) VALUES ('Laboratorio','L1','Piso 1')");
$service = new ReservationService($database, new ReservationRepository($database), new DateTimeImmutable('2030-01-01 08:00:00'));
$data = [
    'resource_type' => 'room',
    'resource_id' => 1,
    'reservation_date' => '2030-01-10',
    'start_time' => '09:00',
    'end_time' => '10:00',
];

$service->create(1, $data);
assertTrue(!$service->available('room', 1, '2030-01-10', '09:30', '10:00', 1), 'La reserva creada no bloqueó el solapamiento.');
assertTrue($service->available('room', 1, '2030-01-10', '10:00', '11:00', 1), 'Las reservas consecutivas dejaron de ser válidas.');
$availability = $service->checkAvailability([...$data, 'start_time' => '11:00', 'end_time' => '12:00']);
assertTrue($availability['available'], 'La disponibilidad no usó el mismo contrato que la creación.');
$service->cancel(1, 1, false);
assertTrue($service->available('room', 1, '2030-01-10', '09:00', '10:00', 1), 'La cancelación no liberó la sala.');

echo "PASS: reservation service\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException("FAIL: {$message}");
}