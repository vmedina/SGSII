<?php
declare(strict_types=1);

namespace Sgsii\Controllers;

use Sgsii\Services\AuthenticationService;

final class AuthController
{
    /** Traduce acciones HTTP de autenticación a operaciones del servicio. */
    public function __construct(private AuthenticationService $authentication) {}

    public function login(array $request): never
    {
        // Regenera la sesión después de autenticar para evitar fijación de sesión.
        $user = $this->authentication->login(
            (string) ($request['username'] ?? ''),
            (string) ($request['password'] ?? '')
        );
        if ($user === null) throw new \InvalidArgumentException('Usuario o contraseña incorrectos.');

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

    public function logout(): never
    {
        // Descarta todos los datos de sesión y crea una sesión limpia para el flash.
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'Sesión cerrada.');
        go('login');
    }

    public function changePassword(array $request, array $user): never
    {
        // Comprueba la confirmación antes de delegar el cambio al servicio.
        $newPassword = (string) ($request['new_password'] ?? '');
        if ($newPassword !== (string) ($request['new_password_confirm'] ?? '')) {
            throw new \InvalidArgumentException('La confirmación no coincide.');
        }
        $this->authentication->changePassword(
            (int) $user['id'],
            (string) ($request['old_password'] ?? ''),
            $newPassword
        );
        flash('success', 'Contraseña actualizada.');
        go('password');
    }
}
