<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Security.php';

$_SESSION = [];
$_POST = [];

$case = $argv[1] ?? '';

if ($case === 'empty') {
    $_SESSION['csrf'] = 'known-token';
    $_POST['csrf'] = '';
} elseif ($case === 'incorrect') {
    $_SESSION['csrf'] = 'known-token';
    $_POST['csrf'] = 'different-token';
} elseif ($case === 'valid') {
    $_SESSION['csrf'] = 'known-token';
    $_POST['csrf'] = 'known-token';
}

verify_csrf();
echo 'ACCEPTED';
