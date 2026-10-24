<?php
declare(strict_types=1);

require __DIR__ . '/../src/Security.php';

assertRejected('missing');
assertRejected('empty');
assertRejected('incorrect');
assertAccepted('valid');
assertRotation();

echo "PASS: validación CSRF\n";

function runProbe(string $case): string
{
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/fixtures/csrf_probe.php', $case],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar la prueba CSRF.');
    }

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $output . $error;
}

function assertRejected(string $case): void
{
    $output = runProbe($case);
    if (str_contains($output, 'ACCEPTED')) {
        throw new RuntimeException("FAIL: CSRF {$case} fue aceptado.");
    }
}

function assertAccepted(string $case): void
{
    $output = runProbe($case);
    if (!str_contains($output, 'ACCEPTED')) {
        throw new RuntimeException("FAIL: CSRF {$case} fue rechazado: {$output}");
    }
}

function assertRotation(): void
{
    $_SESSION['csrf'] = 'old-token';
    $newToken = rotate_csrf_token();

    if ($newToken === 'old-token' || strlen($newToken) !== 64) {
        throw new RuntimeException('FAIL: el token CSRF no fue rotado correctamente.');
    }
}
