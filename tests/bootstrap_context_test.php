<?php
declare(strict_types=1);

require __DIR__ . '/../src/Bootstrap.php';

use Sgsii\ApplicationContext;
use Sgsii\Http\Request;
use Sgsii\Http\Router;
use Sgsii\Repositories\ReservationRepository;
use Sgsii\Repositories\DashboardRepository;
use Sgsii\Repositories\LoanRepository;
use Sgsii\Repositories\IncidentRepository;
use Sgsii\Repositories\ReportRepository;
use Sgsii\Repositories\ResourceRepository;
use Sgsii\Repositories\UserRepository;
use Sgsii\Views\ViewRenderer;
use Sgsii\Services\AuthenticationService;
use Sgsii\Services\ResourceService;
use Sgsii\Services\ReservationService;
use Sgsii\Services\LoanService;
use Sgsii\Services\IncidentService;
use Sgsii\Services\ReportService;
use Sgsii\Services\TransactionCoordinator;

$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['page' => 'dashboard'];
$_POST = [];

$application = App::connect(':memory:');
$context = Bootstrap::createContextForApplication($application, dirname(__DIR__));

assertTrue($context instanceof ApplicationContext, 'Bootstrap no construyó el contexto.');
assertTrue($context->application === $application, 'El contexto no conservó la aplicación.');
assertTrue($context->request instanceof Request, 'El contexto no construyó Request.');
assertTrue($context->router instanceof Router, 'El contexto no construyó Router.');
assertTrue($context->views instanceof ViewRenderer, 'El contexto no construyó ViewRenderer.');
assertTrue($context->authentication instanceof AuthenticationService, 'El contexto no construyó AuthenticationService.');
assertTrue($context->resourceService instanceof ResourceService, 'El contexto no construyó ResourceService.');
assertTrue($context->reservationService instanceof ReservationService, 'El contexto no construyó ReservationService.');
assertTrue($context->loanService instanceof LoanService, 'El contexto no construyó LoanService.');
assertTrue($context->incidentService instanceof IncidentService, 'El contexto no construyó IncidentService.');
assertTrue($context->reportService instanceof ReportService, 'El contexto no construyó ReportService.');
assertTrue($context->transactions instanceof TransactionCoordinator, 'El contexto no construyó TransactionCoordinator.');
assertTrue($context->dashboard instanceof DashboardRepository, 'El contexto no construyó DashboardRepository.');
assertTrue($context->loans instanceof LoanRepository, 'El contexto no construyó LoanRepository.');
assertTrue($context->incidents instanceof IncidentRepository, 'El contexto no construyó IncidentRepository.');
assertTrue($context->reports instanceof ReportRepository, 'El contexto no construyó ReportRepository.');
assertTrue($context->resources instanceof ResourceRepository, 'El contexto no construyó ResourceRepository.');
assertTrue($context->users instanceof UserRepository, 'El contexto no construyó UserRepository.');
assertTrue($context->reservations instanceof ReservationRepository, 'El contexto no construyó ReservationRepository.');
assertSame('dashboard', $context->router->page($context->request, null), 'El contexto no conectó Request con Router.');

echo "PASS: composition root\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL: {$message}");
    }
}