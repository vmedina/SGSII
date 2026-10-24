<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Security.php';

$case = $argv[1] ?? '';
$_SESSION = [];
if ($case === 'admin') $_SESSION['user'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
if ($case === 'user') $_SESSION['user'] = ['id' => 2, 'role' => 'user', 'name' => 'User'];

require_admin();
echo 'ACCEPTED';
