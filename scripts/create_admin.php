<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Database.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 5) { fwrite(STDERR, "Uso: php scripts/create_admin.php usuario nombre apellido contraseña\n"); exit(1); }
[$script,$username,$first,$last,$password] = $argv;
if (strlen($username) < 3 || strlen($password) < 8) { fwrite(STDERR, "Usuario (3+) y contraseña (8+) requeridos.\n"); exit(1); }
$db = Database::application(dirname(__DIR__));
$s = $db->prepare("INSERT INTO users (username,first_name,last_name,password_hash,role) VALUES (?,?,?,?, 'admin')");
$s->execute([$username,$first,$last,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrador creado.\n";
