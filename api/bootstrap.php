<?php
declare(strict_types=1);

/**
 * Arranque común de todos los endpoints JSON:
 * cabeceras, sesión, CSRF, manejo de errores y funciones auxiliares.
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e): void {
    error_log('[halloween] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'error'  => 'Error interno del servidor. Verifica que MySQL esté encendido y que la base "halloween" esté importada.',
        'codigo' => 'interno',
    ], JSON_UNESCAPED_UNICODE);
});

// ---------- Sesión ----------
session_name('HALLOWEENSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ---------- Respuestas ----------
function json_out(mixed $datos, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error_out(string $mensaje, int $estado = 400, ?string $codigo = null): never
{
    json_out(['error' => $mensaje, 'codigo' => $codigo], $estado);
}

// ---------- Petición ----------
/** Método HTTP real. Permite POST + _method=PUT|DELETE (los formularios con archivos no pueden usar PUT). */
function metodo(): string
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($m === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper((string) $_POST['_method']);
        if ($override === 'PUT' || $override === 'DELETE') {
            return $override;
        }
    }
    return $m;
}

/** Cuerpo de la petición como arreglo (JSON, formulario o multipart). */
function cuerpo(): array
{
    static $datos = null;
    if ($datos !== null) {
        return $datos;
    }
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    $crudo = null;
    if (stripos($tipo, 'application/json') !== false) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $datos = is_array($json) ? $json : [];
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $datos = $_POST;
    } else {
        parse_str((string) file_get_contents('php://input'), $crudo);
        $datos = is_array($crudo) ? $crudo : [];
    }
    return $datos;
}

/** Id del recurso: /endpoint.php/3  o  /endpoint.php?id=3 */
function id_recurso(string $parametro = 'id'): ?int
{
    $path = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
    if ($path !== '' && ctype_digit($path)) {
        return (int) $path;
    }
    $valor = $_GET[$parametro] ?? null;
    if (is_string($valor) && ctype_digit($valor) && (int) $valor > 0) {
        return (int) $valor;
    }
    return null;
}

/** Valida método permitido y token CSRF en operaciones que modifican datos. */
function iniciar_endpoint(array $permitidos): void
{
    $m = metodo();
    if (!in_array($m, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        error_out('Método no permitido.', 405);
    }
    if ($m !== 'GET' && $m !== 'HEAD') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            error_out('Sesión de seguridad inválida. Recarga la página e inténtalo de nuevo.', 403, 'csrf');
        }
    }
}

// ---------- Autenticación ----------
function usuario_actual(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $nombre = (string) $_SESSION['unombre'];
    return [
        'id'     => (int) $_SESSION['uid'],
        'nombre' => $nombre,
        'admin'  => strcasecmp($nombre, ADMIN_USER) === 0,
    ];
}

function iniciar_sesion(array $usuario): void
{
    session_regenerate_id(true);                 // evita fijación de sesión
    $_SESSION['uid']     = (int) $usuario['id'];
    $_SESSION['unombre'] = (string) $usuario['nombre'];
    $_SESSION['csrf']    = bin2hex(random_bytes(32));
}

function requerir_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        error_out('Debes iniciar sesión para hacer esto.', 401, 'no_autenticado');
    }
    return $u;
}

function requerir_admin(): array
{
    $u = requerir_login();
    if (!$u['admin']) {
        error_out('Solo el administrador puede hacer esto.', 403, 'no_admin');
    }
    return $u;
}
