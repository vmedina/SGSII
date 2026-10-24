<?php
declare(strict_types=1);

namespace Sgsii;

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
use Sgsii\Controllers\AuthController;

final class ApplicationContext
{
    public function __construct(
        public readonly \App $application,
        public readonly Request $request,
        public readonly Router $router,
        public readonly ViewRenderer $views,
        public readonly AuthenticationService $authentication,
        public readonly ResourceService $resourceService,
        public readonly ReservationService $reservationService,
        public readonly LoanService $loanService,
        public readonly IncidentService $incidentService,
        public readonly ReportService $reportService,
        public readonly TransactionCoordinator $transactions,
        public readonly AuthController $authController,
        public readonly DashboardRepository $dashboard,
        public readonly LoanRepository $loans,
        public readonly IncidentRepository $incidents,
        public readonly ReportRepository $reports,
        public readonly ResourceRepository $resources,
        public readonly UserRepository $users,
        public readonly ReservationRepository $reservations
    ) {}
}