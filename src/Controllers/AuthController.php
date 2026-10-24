<?php
declare(strict_types=1);

namespace Sgsii\Controllers;

use Sgsii\Services\AuthenticationService;

final class AuthController
{
    public function __construct(private AuthenticationService $authentication) {}

    public function login(array $request): never
    {
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
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'Sesión cerrada.');
        go('login');
    }

    public function changePassword(array $request, array $user): never
    {
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