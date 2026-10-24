<?php
declare(strict_types=1);

$entryPoint = file_get_contents(__DIR__ . '/../public/index.php');
$dispatcher = file_get_contents(__DIR__ . '/../src/Http/ActionDispatcher.php');

assertNotContains('SELECT ', $entryPoint, 'public/index.php volvió a contener SQL.');
assertNotContains('SELECT ', $dispatcher, 'ActionDispatcher volvió a contener SQL.');
assertNotContains('INSERT ', $entryPoint, 'public/index.php volvió a contener INSERT.');
assertNotContains('UPDATE ', $dispatcher, 'ActionDispatcher volvió a contener UPDATE.');
assertNotContains('DELETE ', $dispatcher, 'ActionDispatcher volvió a contener DELETE.');

echo "PASS: frontera SQL HTTP\n";

function assertNotContains(string $needle, string $content, string $message): void
{
    if (str_contains($content, $needle)) {
        throw new RuntimeException("FAIL: {$message}");
    }
}