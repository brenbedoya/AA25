<?php
declare(strict_types=1);

/**
 * GET /api/imagen.php?id=3   -> foto del disfraz (archivo de /uploads o, si falta, la miniatura de la BD)
 * Las imágenes se sirven siempre a través de este script; /uploads no es accesible directamente.
 */

require_once __DIR__ . '/db.php';

$id = (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(400);
    exit;
}

$st = db()->prepare('SELECT foto, foto_blob FROM disfraces WHERE id = ? AND eliminado = 0');
$st->execute([$id]);
$fila = $st->fetch();

if ($fila === false || $fila['foto'] === '') {
    http_response_code(404);
    exit;
}

$tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($fila['foto'], PATHINFO_EXTENSION));
$ruta = dirname(__DIR__) . '/uploads/' . basename($fila['foto']);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');

if (isset($tipos[$ext]) && is_file($ruta)) {
    header('Content-Type: ' . $tipos[$ext]);
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
} elseif ($fila['foto_blob'] !== '' && $fila['foto_blob'] !== null) {
    header('Content-Type: image/jpeg');
    echo $fila['foto_blob'];
} else {
    http_response_code(404);
}
