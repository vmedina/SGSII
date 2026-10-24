<?php
declare(strict_types=1);

require_once __DIR__ . '/Validation/Validator.php';
require_once __DIR__ . '/Repositories/ReportRepository.php';
require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/ResourceRepository.php';
require_once __DIR__ . '/Repositories/ReservationRepository.php';
require_once __DIR__ . '/Repositories/LoanRepository.php';
require_once __DIR__ . '/Repositories/IncidentRepository.php';
require_once __DIR__ . '/Services/AuthenticationService.php';
require_once __DIR__ . '/Services/ResourceService.php';
require_once __DIR__ . '/Services/ReservationService.php';
require_once __DIR__ . '/Services/LoanService.php';
require_once __DIR__ . '/Services/IncidentService.php';
require_once __DIR__ . '/Services/ReportService.php';

use Sgsii\Repositories\IncidentRepository;
use Sgsii\Repositories\ReportRepository;
use Sgsii\Repositories\ResourceRepository;
use Sgsii\Repositories\UserRepository;
use Sgsii\Services\AuthenticationService;
use Sgsii\Services\IncidentService;
use Sgsii\Services\LoanService;
use Sgsii\Services\ReportService;
use Sgsii\Services\ReservationService;
use Sgsii\Services\ResourceService;
use Sgsii\Validation\Validator;

final class App
{
    private ?AuthenticationService $authentication = null;
    private ?ResourceService $resourceService = null;
    private ?ReservationService $reservationService = null;
    private ?LoanService $loanService = null;
    private ?IncidentService $incidentService = null;
    private ?ReportService $reportService = null;

    public function __construct(private PDO $db, private ?DateTimeImmutable $clock = null) {}

    public static function connect(string $path, ?DateTimeImmutable $clock = null): self
    {
        $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 3000');
        return new self($db, $clock);
    }

    public function db(): PDO { return $this->db; }
    public function all(string $sql, array $params = []): array { $s = $this->db->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public function one(string $sql, array $params = []): ?array { $rows = $this->all($sql, $params); return $rows[0] ?? null; }
    public function execute(string $sql, array $params = []): void { $s = $this->db->prepare($sql); $s->execute($params); }
    public function id(): int { return (int) $this->db->lastInsertId(); }

    public function login(string $username, string $password): ?array { return $this->authentication()->login($username, $password); }

    public function saveUser(array $data, ?int $id = null): void
    {
        $username = Validator::text($data['username'] ?? '', 3, 50, 'Usuario');
        $first = Validator::text($data['first_name'] ?? '', 1, 80, 'Nombre');
        $last = Validator::text($data['last_name'] ?? '', 1, 80, 'Apellido');
        $subject = Validator::text($data['subject'] ?? '', 0, 120, 'Asignatura');
        $role = Validator::choice($data['role'] ?? 'user', ['admin', 'user'], 'Rol');
        $repository = new UserRepository($this->db);
        if ($id === null) {
            $password = (string) ($data['password'] ?? '');
            if (strlen($password) < 8) throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
            $repository->create([$username, $first, $last, $subject, password_hash($password, PASSWORD_DEFAULT), $role]);
            return;
        }
        $repository->update($id, [$username, $first, $last, $subject, $role]);
    }

    public function changePassword(int $id, string $old, string $new): void { $this->authentication()->changePassword($id, $old, $new); }
    public function saveRoom(array $data, ?int $id = null): void { $this->resourceService()->saveRoom($data, $id); }
    public function saveSupply(array $data, ?int $id = null): void { $this->resourceService()->saveSupply($data, $id); }

    public function deactivate(string $table, int $id): void
    {
        if (!in_array($table, ['users', 'rooms', 'supplies'], true)) throw new InvalidArgumentException('Entidad inválida.');
        if ($table === 'users') { (new UserRepository($this->db))->deactivate($id); return; }
        if ($table === 'rooms') { $this->resourceService()->deactivateRoom($id); return; }
        $this->resourceService()->deactivateSupply($id);
    }

    public function createReservation(int $actorId, array $data, ?int $reservationId = null, bool $admin = false): void { $this->reservationService()->create($actorId, $data, $reservationId, $admin); }
    public function checkAvailability(array $data): array { return $this->reservationService()->checkAvailability($data); }
    public function available(string $type, int $id, string $date, string $start, string $end, int $quantity, ?int $exclude = null): bool { return $this->reservationService()->available($type, $id, $date, $start, $end, $quantity, $exclude); }
    public function cancelReservation(int $id, int $actorId, bool $admin): void { $this->reservationService()->cancel($id, $actorId, $admin); }
    public function loan(array $data): void { $this->loanService()->create($data); }
    public function returnLoan(int $id): void { $this->loanService()->return($id); }
    public function incident(int $reporter, array $data): void { $this->incidentService()->report($reporter, $data); }
    public function updateIncident(int $id, array $data): bool { return $this->incidentService()->update($id, $data); }
    public function incidentsFor(int $viewerId, bool $admin): array { return (new IncidentRepository($this->db))->findForViewer($viewerId, $admin); }
    public function reportSummary(mixed $fromValue, mixed $toValue): array { return $this->reportService()->summary($fromValue, $toValue); }

    private function authentication(): AuthenticationService { return $this->authentication ??= new AuthenticationService(new UserRepository($this->db)); }
    private function resourceService(): ResourceService { return $this->resourceService ??= new ResourceService($this->db, new ResourceRepository($this->db), $this->clock); }
    private function reservationService(): ReservationService { return $this->reservationService ??= new ReservationService($this->db, new \Sgsii\Repositories\ReservationRepository($this->db), $this->clock); }
    private function loanService(): LoanService { return $this->loanService ??= new LoanService($this->db, new \Sgsii\Repositories\LoanRepository($this->db), $this->clock); }
    private function incidentService(): IncidentService { return $this->incidentService ??= new IncidentService($this->db); }
    private function reportService(): ReportService { return $this->reportService ??= new ReportService(new ReportRepository($this->db)); }
}