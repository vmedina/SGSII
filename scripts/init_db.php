<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__); $path = $root . '/database/sgsii.sqlite';
if (file_exists($path)) { fwrite(STDERR, "La base ya existe. Elimínela manualmente solo si desea reinicializarla.\n"); exit(1); }
$db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents($root . '/database/schema.sql'));
echo "Base de datos creada en database/sgsii.sqlite\n";
