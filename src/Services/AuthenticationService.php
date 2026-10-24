<?php
declare(strict_types=1);

namespace Sgsii\Services;

use Sgsii\Repositories\UserRepository;

final class AuthenticationService
{
    /** Coordina autenticación, cambio de contraseña y validación de sesiones. */
    public function __construct(private UserRepository $users) {}

    public function login(string $username, string $password): ?array
    {
        // Solo los usuarios activos pueden iniciar sesión; la contraseña nunca
        // se compara en texto plano, sino mediante el hash almacenado.
        $user = $this->users->findActiveByUsername(trim($username));
        return ($user !== null && password_verify($password, $user['password_hash'])) ? $user : null;
    }

    public function changePassword(int $id, string $oldPassword, string $newPassword): void
    {
        // Exige la contraseña anterior antes de guardar un hash nuevo.
        $passwordHash = $this->users->findPasswordHash($id);
        if ($passwordHash === null || !password_verify($oldPassword, $passwordHash)) {
            throw new \InvalidArgumentException('La contraseña actual no es correcta.');
        }
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');
        }

        $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function activeSessionUser(int $id): ?array
    {
        // Devuelve únicamente los datos mínimos necesarios para reconstruir la sesión.
        return $this->users->findActiveSessionUser($id);
    }
}
