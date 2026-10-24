<?php
declare(strict_types=1);

namespace Sgsii\Http;

use Sgsii\Repositories\LoanRepository;
use Sgsii\Repositories\ReservationRepository;
use Sgsii\Controllers\AuthController;
use Sgsii\Repositories\UserRepository;
use Sgsii\Services\AuthenticationService;

final class ActionDispatcher
{
    private const ADMIN_ACTIONS = [
        'save_user',
        'deactivate_user',
        'save_room',
        'deactivate_room',
        'save_supply',
        'deactivate_supply',
        'loan',
        'return_loan',
        'update_incident',
    ];

    public static function dispatch(\App $app, array $request, ?LoanRepository $loans = null, ?ReservationRepository $reservations = null, ?AuthController $authController = null): void
    {
        $action = (string) ($request['action'] ?? '');
        $user = current_user();
        $authController ??= new AuthController(new AuthenticationService(new UserRepository($app->db())));

        if ($action === 'login') {
            $authController->login($request);
            return;
        }

        if ($action === 'logout') {
            $authController->logout();
            return;
        }

        require_login();
        if (in_array($action, self::ADMIN_ACTIONS, true)) {
            require_admin();
        }

        self::dispatchAuthenticatedAction($app, $action, $request, $user, $loans, $reservations, $authController);
    }

    private static function login(\App $app, array $request): void
    {
        $username = (string) ($request['username'] ?? '');
        $password = (string) ($request['password'] ?? '');
        $user = $app->login($username, $password);

        if ($user === null) {
            throw new \InvalidArgumentException('Usuario o contraseña incorrectos.');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'name' => $user['first_name'],
        ];
        rotate_csrf_token();
        flash('success', 'Sesión iniciada.');
        go('dashboard');
    }

    private static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'Sesión cerrada.');
        go('login');
    }

    private static function dispatchAuthenticatedAction(\App $app, string $action, array $request, ?array $user, ?LoanRepository $loans, ?ReservationRepository $reservations, AuthController $authController): void
    {
        if ($user === null) {
            throw new \LogicException('No hay un usuario autenticado.');
        }

        if ($action === 'password') {
            $authController->changePassword($request, $user);
            return;
        }

        if ($action === 'save_user') {
            $id = self::optionalId($request);
            $app->saveUser($request, $id);
            self::redirectWithMessage('users', 'Usuario guardado.');
        }

        if ($action === 'deactivate_user') {
            $id = Input::postInt('id');
            if ($id === (int) $user['id']) {
                throw new \InvalidArgumentException('No puede desactivar su propia cuenta.');
            }
            $app->deactivate('users', $id);
            self::redirectWithMessage('users', 'Usuario desactivado.');
        }

        if ($action === 'save_room') {
            $app->saveRoom($request, self::optionalId($request));
            self::redirectWithMessage('rooms', 'Sala guardada.');
        }

        if ($action === 'deactivate_room') {
            $app->deactivate('rooms', Input::postInt('id'));
            self::redirectWithMessage('rooms', 'Sala desactivada.');
        }

        if ($action === 'save_supply') {
            $app->saveSupply($request, self::optionalId($request));
            self::redirectWithMessage('supplies', 'Insumo guardado.');
        }

        if ($action === 'deactivate_supply') {
            $app->deactivate('supplies', Input::postInt('id'));
            self::redirectWithMessage('supplies', 'Insumo desactivado.');
        }

        if ($action === 'reservation') {
            self::saveReservation($app, $request, $user, $reservations);
            return;
        }

        if ($action === 'cancel_reservation') {
            $app->cancelReservation(Input::postInt('id'), (int) $user['id'], is_admin());
            self::redirectWithMessage('reservations', 'Reserva cancelada.');
        }

        if ($action === 'loan') {
            $app->loan($request);
            self::redirectWithMessage('loans', 'Préstamo registrado.');
        }

        if ($action === 'return_loan') {
            ($loans ?? new LoanRepository($app->db()))->returnLoan(Input::postInt('id'));
            self::redirectWithMessage('loans', 'Devolución registrada.');
        }

        if ($action === 'incident') {
            $resource = Input::resource((string) ($request['resource'] ?? ''));
            $request['resource_type'] = $resource[0];
            $request['resource_id'] = $resource[1];
            $app->incident((int) $user['id'], $request);
            self::redirectWithMessage('incidents', 'Incidencia reportada.');
        }

        if ($action === 'update_incident') {
            $available = $app->updateIncident(Input::postInt('id'), $request);
            $message = 'Incidencia actualizada. Recurso ' . ($available ? 'disponible.' : 'no disponible.');
            self::redirectWithMessage('incidents', $message);
        }

        throw new \InvalidArgumentException('Acción inválida.');
    }

    private static function changePassword(\App $app, array $request, array $user): void
    {
        $newPassword = (string) ($request['new_password'] ?? '');
        $confirmation = (string) ($request['new_password_confirm'] ?? '');
        if ($newPassword !== $confirmation) {
            throw new \InvalidArgumentException('La confirmación no coincide.');
        }

        $app->changePassword((int) $user['id'], (string) ($request['old_password'] ?? ''), $newPassword);
        self::redirectWithMessage('password', 'Contraseña actualizada.');
    }

    private static function saveReservation(\App $app, array $request, array $user, ?ReservationRepository $reservations): void
    {
        $resource = Input::resource((string) ($request['resource'] ?? ''));
        $request['resource_type'] = $resource[0];
        $request['resource_id'] = $resource[1];
        $reservationId = self::optionalId($request);

        if ($reservationId !== null) {
            self::ensureReservationCanBeEdited($app, $reservationId, $user, $reservations);
        }

        $app->createReservation((int) $user['id'], $request, $reservationId, is_admin());
        $message = $reservationId === null ? 'Reserva creada.' : 'Reserva modificada.';
        self::redirectWithMessage('reservations', $message);
    }

    private static function ensureReservationCanBeEdited(\App $app, int $reservationId, array $user, ?ReservationRepository $reservations): void
    {
        $reservation = ($reservations ?? new ReservationRepository($app->db()))->findOwnerAndStatus($reservationId);
        $isOwner = $reservation !== null && (int) $reservation['user_id'] === (int) $user['id'];
        if ($reservation === null || $reservation['status'] !== 'active' || (!is_admin() && !$isOwner)) {
            throw new \InvalidArgumentException('No puede editar esa reserva.');
        }
    }

    private static function optionalId(array $request): ?int
    {
        return isset($request['id']) && $request['id'] !== ''
            ? self::postIntFromRequest($request['id'])
            : null;
    }

    private static function postIntFromRequest(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new \InvalidArgumentException('Identificador inválido.');
        }

        return (int) $value;
    }

    private static function redirectWithMessage(string $page, string $message): void
    {
        flash('success', $message);
        go($page);
    }
}
