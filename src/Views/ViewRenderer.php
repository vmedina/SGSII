<?php
declare(strict_types=1);

namespace Sgsii\Views;

final class ViewRenderer
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $path = realpath($viewsPath);
        if ($path === false || !is_dir($path)) {
            throw new \InvalidArgumentException('La raíz de vistas no existe.');
        }

        $this->viewsPath = $path;
    }

    public function render(string $view, array $values = []): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $view)) {
            throw new \InvalidArgumentException('Nombre de vista inválido.');
        }

        $path = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($path)) {
            throw new \InvalidArgumentException('La vista no existe.');
        }

        $context = new ViewContext($values);
        ob_start();
        try {
            require $path;
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}