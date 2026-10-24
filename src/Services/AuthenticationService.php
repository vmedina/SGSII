<?php
declare(strict_types=1);

namespace Sgsii\Services;

use Sgsii\Repositories\UserRepository;

final class AuthenticationService
{
    public function __construct(private UserRepository $users) {}

    public function login(string $username, string $password): ?array
    {
        $user = $this->users->findActiveByUsername(trim($username));
        return ($user !== null && password_verify($password, $user['password_hash'])) ? $user : null;
    }

    public function changePassword(int $id, string $oldPassword, string $newPassword): void
    {
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
        return $this->users->findActiveSessionUser($id);
    }
}