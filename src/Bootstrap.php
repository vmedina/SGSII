<?php
declare(strict_types=1);

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Http/Input.php';
require_once __DIR__ . '/Http/Request.php';
require_once __DIR__ . '/Http/Response.php';
require_once __DIR__ . '/Http/Router.php';
require_once __DIR__ . '/Views/ViewContext.php';
require_once __DIR__ . '/Views/ViewRenderer.php';
require_once __DIR__ . '/ApplicationContext.php';
require_once __DIR__ . '/Services/AuthenticationService.php';
require_once __DIR__ . '/Services/ResourceService.php';
require_once __DIR__ . '/Services/ReservationService.php';
require_once __DIR__ . '/Services/LoanService.php';
require_once __DIR__ . '/Services/IncidentService.php';
require_once __DIR__ . '/Services/ReportService.php';
require_once __DIR__ . '/Services/TransactionCoordinator.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Http/ActionDispatcher.php';
require_once __DIR__ . '/Repositories/ResourceRepository.php';
require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/ReservationRepository.php';
require_once __DIR__ . '/Repositories/DashboardRepository.php';
require_once __DIR__ . '/Repositories/LoanRepository.php';
require_once __DIR__ . '/Repositories/IncidentRepository.php';
require_once __DIR__ . '/Repositories/ReportRepository.php';

require_once __DIR__ . '/App.php';

use Sgsii\Repositories\ResourceRepository;
use Sgsii\Repositories\UserRepository;
use Sgsii\Repositories\ReservationRepository;
use Sgsii\Repositories\DashboardRepository;
use Sgsii\Repositories\LoanRepository;
use Sgsii\Repositories\IncidentRepository;
use Sgsii\Repositories\ReportRepository;
use Sgsii\ApplicationContext;
use Sgsii\Http\Request;
use Sgsii\Http\Router;
use Sgsii\Views\ViewRenderer;
use Sgsii\Services\AuthenticationService;
use Sgsii\Services\ResourceService;
use Sgsii\Services\ReservationService;
use Sgsii\Services\LoanService;
use Sgsii\Services\IncidentService;
use Sgsii\Services\ReportService;
use Sgsii\Services\TransactionCoordinator;
use Sgsii\Controllers\AuthController;


final class Bootstrap
{
    public static function createReservationRepository(App $application): ReservationRepository
    {
        return new ReservationRepository($application->db());
    }

    public static function createAuthenticationService(App $application): AuthenticationService
    {
        return new AuthenticationService(self::createUserRepository($application));
    }

    public static function createAuthController(App $application): AuthController
    {
        return new AuthController(self::createAuthenticationService($application));
    }

    public static function createResourceService(App $application, ?TransactionCoordinator $transactions = null): ResourceService
    {
        return new ResourceService($application->db(), self::createResourceRepository($application), null, $transactions);
    }

    public static function createReservationService(App $application, ?TransactionCoordinator $transactions = null): ReservationService
    {
        return new ReservationService($application->db(), self::createReservationRepository($application), null, $transactions);
    }

    public static function createLoanService(App $application, ?TransactionCoordinator $transactions = null): LoanService
    {
        return new LoanService($application->db(), self::createLoanRepository($application), null, $transactions);
    }

    public static function createIncidentService(App $application, ?TransactionCoordinator $transactions = null): IncidentService
    {
        return new IncidentService($application->db(), $transactions);
    }

    public static function createReportService(App $application): ReportService
    {
        return new ReportService(self::createReportRepository($application));
    }

    public static function createUserRepository(App $application): UserRepository
    {
        return new UserRepository($application->db());
    }

    public static function createResourceRepository(App $application): ResourceRepository
    {
        return new ResourceRepository($application->db());
    }

    public static function createDashboardRepository(App $application): DashboardRepository
    {
        return new DashboardRepository($application->db());
    }

    public static function createLoanRepository(App $application): LoanRepository
    {
        return new LoanRepository($application->db());
    }

    public static function createIncidentRepository(App $application): IncidentRepository
    {
        return new IncidentRepository($application->db());
    }

    public static function createReportRepository(App $application): ReportRepository
    {
        return new ReportRepository($application->db());
    }

    public static function createApplication(string $root): App
    {
        start_session();

        $application = new App(Database::application($root));
        revalidate_session_user($application);

        return $application;
    }

    public static function createContext(string $root): ApplicationContext
    {
        return self::createContextForApplication(self::createApplication($root), $root);
    }

    public static function createContextForApplication(App $application, string $root): ApplicationContext
    {
        start_session();
        revalidate_session_user($application);
        $transactions = new TransactionCoordinator($application->db());

        return new ApplicationContext(
            $application,
            Request::fromGlobals(),
            new Router(),
            new ViewRenderer($root . '/src/Views'),
            self::createAuthenticationService($application),
            self::createResourceService($application, $transactions),
            self::createReservationService($application, $transactions),
            self::createLoanService($application, $transactions),
            self::createIncidentService($application, $transactions),
            self::createReportService($application),
            $transactions,
            self::createAuthController($application),
            self::createDashboardRepository($application),
            self::createLoanRepository($application),
            self::createIncidentRepository($application),
            self::createReportRepository($application),
            self::createResourceRepository($application),
            self::createUserRepository($application),
            self::createReservationRepository($application)
        );
    }
}
