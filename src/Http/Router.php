<?php
declare(strict_types=1);

namespace Sgsii\Http;

final class Router
{
    /** Define las páginas públicas y las páginas restringidas del sistema. */
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
        // Respeta la página solicitada; si falta, decide según la sesión.
        $page = $request->query('page');
        if (is_string($page) && $page !== '') {
            return $page;
        }

        return $user === null ? 'login' : 'dashboard';
    }

    public function pages(): array
    {
        // Se expone para que el controlador pueda validar la ruta solicitada.
        return self::PAGES;
    }

    public function adminPages(): array
    {
        // Lista de rutas que requieren rol administrador.
        return self::ADMIN_PAGES;
    }

    public function isKnownPage(string $page): bool
    {
        // Evita cargar plantillas para nombres de página no registrados.
        return in_array($page, self::PAGES, true);
    }

    public function requiresLogin(string $page): bool
    {
        // Todas las páginas salvo login necesitan una sesión autenticada.
        return $page !== 'login';
    }

    public function requiresAdmin(string $page): bool
    {
        // Comprueba si la página pertenece al conjunto exclusivo del administrador.
        return in_array($page, self::ADMIN_PAGES, true);
    }
}
