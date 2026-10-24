<?php
declare(strict_types=1);

$groups = [
    'Infraestructura' => [
        'database_configuration_test.php',
    ],
    'Seguridad' => [
        'security_test.php',
        'error_handling_test.php',
        'csrf_test.php',
        'input_security_test.php',
    ],
    'Autenticación y autorización' => [
        'authentication_test.php',
        'session_authorization_test.php',
    ],
    'Contrato HTTP' => [
        'http_contract_test.php',
        'http_response_contract_test.php',
        'http_request_test.php',
        'http_response_test.php',
        'http_router_test.php',
        'view_renderer_test.php',
        'bootstrap_context_test.php',
        'app_facade_contract_test.php',
        'http_sql_boundary_test.php',
        'authentication_service_test.php',
        'resource_service_test.php',
        'reservation_service_test.php',
        'loan_service_test.php',
        'incident_service_test.php',
        'report_service_test.php',
        'transaction_coordinator_test.php',
    ],
    'CRUD' => [
        'crud_validation_test.php',
        'dashboard_repository_test.php',
        'crud_repository_test.php',
    ],
    'Reservas y disponibilidad' => [
        'reservation_transaction_test.php',
        'reservation_date_test.php',
        'availability_test.php',
        'admin_reservation_assignment_test.php',
        'resource_mapping_test.php',
    ],
    'Inventario y préstamos' => [
        'loan_reservation_consistency_test.php',
        'supply_invariants_test.php',
        'loan_repository_test.php',
    ],
    'Incidencias y reportes' => [
        'incident_resource_lifecycle_test.php',
        'incident_visibility_test.php',
        'incident_repository_test.php',
        'report_filters_test.php',
        'report_repository_test.php',
    ],
    'Regresión integral' => [
        'reservation_smoke_test.php',
    ],
];

foreach (['pdo_sqlite', 'sqlite3'] as $requiredExtension) {
    if (!extension_loaded($requiredExtension)) {
        fwrite(STDERR, "FAIL: falta la extensión PHP {$requiredExtension}.\n");
        exit(1);
    }
}

$phpCommand = isolatedPhpCommand();
$failures = [];
$passed = 0;
$total = 0;

foreach ($groups as $group => $tests) {
    echo "\n== {$group} ==\n";
    foreach ($tests as $test) {
        $total++;
        $path = __DIR__ . DIRECTORY_SEPARATOR . $test;
        if (!is_file($path)) {
            echo "[FAIL] {$test}: archivo ausente.\n";
            $failures[] = $test;
            continue;
        }
        [$status, $output, $error] = runTest([...$phpCommand, $path]);
        $cleanError = trim($error);
        if ($status === 0 && $cleanError === '') {
            $passed++;
            echo "[PASS] {$test}\n";
            if (trim($output) !== '') echo indentOutput($output);
            continue;
        }
        $failures[] = $test;
        echo "[FAIL] {$test} (exit {$status})\n";
        if (trim($output) !== '') echo indentOutput($output);
        if ($cleanError !== '') echo indentOutput($error);
    }
}

echo "\nResultado: {$passed}/{$total} pruebas aprobadas.\n";
if ($failures !== []) {
    echo 'Fallaron: ' . implode(', ', $failures) . "\n";
    exit(1);
}
echo "PASS: suite completa\n";

function isolatedPhpCommand(): array
{
    $command = [PHP_BINARY, '-n', '-d', 'display_errors=stderr', '-d', 'error_reporting=-1'];
    $extensionDir = (string)ini_get('extension_dir');
    if ($extensionDir !== '' && !isAbsolutePath($extensionDir)) {
        $extensionDir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $extensionDir;
    }
    $extensionFiles = [];
    foreach (['pdo_sqlite', 'sqlite3'] as $extension) {
        foreach (["php_{$extension}." . PHP_SHLIB_SUFFIX, "{$extension}." . PHP_SHLIB_SUFFIX] as $candidate) {
            $path = $extensionDir . DIRECTORY_SEPARATOR . $candidate;
            if (is_file($path)) {
                $extensionFiles[] = $path;
                break;
            }
        }
    }
    if ($extensionFiles !== []) {
        $command[] = '-d';
        $command[] = 'extension_dir=' . $extensionDir;
        foreach ($extensionFiles as $file) {
            $command[] = '-d';
            $command[] = 'extension=' . basename($file);
        }
    }
    return $command;
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function runTest(array $command): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) return [1, '', 'No se pudo iniciar el proceso de prueba.'];
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}

function indentOutput(string $output): string
{
    $trimmed = rtrim($output);
    if ($trimmed === '') return '';
    return '  ' . str_replace("\n", "\n  ", $trimmed) . "\n";
}
