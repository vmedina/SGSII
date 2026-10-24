<?php
declare(strict_types=1);

require __DIR__ . '/../src/Views/ViewContext.php';
require __DIR__ . '/../src/Views/ViewRenderer.php';

use Sgsii\Views\ViewRenderer;

$renderer = new ViewRenderer(__DIR__ . '/fixtures/views');
$html = $renderer->render('greeting', [
    'title' => '<Título>',
    'message' => 'Contenido seguro',
]);

assertContains('&lt;Título&gt;', $html, 'El contexto no escapó el título.');
assertContains('Contenido seguro', $html, 'El contexto no entregó el mensaje.');
assertNotContains('<Título>', $html, 'La vista permitió HTML sin escapar.');

expectInvalidView(static fn() => $renderer->render('../greeting'));
expectInvalidView(static fn() => $renderer->render('missing'));

echo "PASS: renderer de vistas\n";

function expectInvalidView(callable $render): void
{
    try {
        $render();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('FAIL: se aceptó una vista inválida.');
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}