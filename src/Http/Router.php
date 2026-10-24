<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Router
{
    private const PAGES = [
        'login',
        'dashboard',
        'availability',
        'rooms',
        'supplies',
        'users',
        'reservations',
        'loans',
        'incidents',
        'reports',
        'password',
    ];

    private const ADMIN_PAGES = ['users', 'rooms', 'supplies', 'loans', 'reports'];

    public function page(Request $request, ?array $user): string
    {
        $page = $request->query('page');
        if (is_string($page) && $page !== '') {
            return $page;
        }

        return $user === null ? 'login' : 'dashboard';
    }

    public function pages(): array
    {
        return self::PAGES;
    }

    public function adminPages(): array
    {
        return self::ADMIN_PAGES;
    }

    public function isKnownPage(string $page): bool
    {
        return in_array($page, self::PAGES, true);
    }

    public function requiresLogin(string $page): bool
    {
        return $page !== 'login';
    }

    public function requiresAdmin(string $page): bool
    {
        return in_array($page, self::ADMIN_PAGES, true);
    }
}