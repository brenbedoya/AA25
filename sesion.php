<?php
declare(strict_types=1);

/**
 * Recurso: sesión
 *   GET    /api/sesion.php   -> estado de la sesión + token CSRF
 *   POST   /api/sesion.php   -> iniciar sesión   { nombre, clave }
 *   DELETE /api/sesion.php   -> cerrar sesión
 */

require __DIR__ . '/bootstrap.php';

iniciar_endpoint(['GET', 'POST', 'DELETE']);

// Hash de relleno: se usa cuando el usuario no existe para que el tiempo de respuesta sea parecido.
const HASH_RELLENO = '$2y$10$PGAMviESca4Y/4m.gFi/g.W30v1Vg3cnoeOYFw/udgprr6KCUfcZi';

switch (metodo()) {
    case 'GET':
        $u = usuario_actual();
        json_out([
            'autenticado' => $u !== null,
            'usuario'     => $u,
            'csrf'        => $_SESSION['csrf'],
        ]);

    case 'POST':
        $d = cuerpo();
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $clave  = (string) ($d['clave'] ?? '');

        if ($nombre === '' || $clave === '' || strlen($clave) > 200) {
            error_out('Escribe tu usuario y tu contraseña.', 422);
        }

        // Freno básico contra fuerza bruta: 5 fallos => 30 segundos de espera.
        if (($_SESSION['bloqueo_hasta'] ?? 0) > time()) {
            error_out('Demasiados intentos fallidos. Espera unos segundos.', 429);
        }

        $st = db()->prepare('SELECT id, nombre, clave FROM usuarios WHERE nombre = ? LIMIT 1');
        $st->execute([$nombre]);
        $usuario = $st->fetch() ?: null;

        $hash = $usuario !== null ? $usuario['clave'] : HASH_RELLENO;
        $ok = password_verify($clave, $hash) && $usuario !== null;

        if (!$ok) {
            $_SESSION['fallos'] = ($_SESSION['fallos'] ?? 0) + 1;
            if ($_SESSION['fallos'] >= 5) {
                $_SESSION['fallos'] = 0;
                $_SESSION['bloqueo_hasta'] = time() + 30;
            }
            error_out('Usuario o contraseña incorrectos.', 401);
        }

        // Si PHP cambia el algoritmo por defecto, se actualiza el hash de forma transparente.
        if (password_needs_rehash($usuario['clave'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE usuarios SET clave = ? WHERE id = ?')
                ->execute([password_hash($clave, PASSWORD_DEFAULT), $usuario['id']]);
        }

        $_SESSION['fallos'] = 0;
        iniciar_sesion($usuario);
        json_out([
            'autenticado' => true,
            'usuario'     => usuario_actual(),
            'csrf'        => $_SESSION['csrf'],
        ]);

    case 'DELETE':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $p['path'],
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $p['secure'],
            ]);
        }
        session_destroy();
        json_out(['ok' => true]);
}
