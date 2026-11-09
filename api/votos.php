<?php
declare(strict_types=1);

/**
 * Recurso: votos (requiere sesión iniciada)
 *   GET    /api/votos.php                 -> ids de los disfraces por los que votó el usuario
 *   POST   /api/votos.php                 -> votar          { id_disfraz }
 *   DELETE /api/votos.php?id_disfraz=3    -> retirar voto
 *
 * Un usuario solo puede votar una vez por cada disfraz (clave única en la tabla votos).
 */

require __DIR__ . '/bootstrap.php';

iniciar_endpoint(['GET', 'POST', 'DELETE']);
$usuario = requerir_login();
$pdo = db();

switch (metodo()) {
    case 'GET':
        $st = $pdo->prepare('SELECT id_disfraz FROM votos WHERE id_usuario = ?');
        $st->execute([$usuario['id']]);
        json_out(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));

    case 'POST':
        $idDisfraz = filter_var(cuerpo()['id_disfraz'] ?? null, FILTER_VALIDATE_INT);
        if ($idDisfraz === false || $idDisfraz < 1) {
            error_out('Disfraz inválido.', 422);
        }

        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT id FROM disfraces WHERE id = ? AND eliminado = 0 FOR UPDATE');
        $st->execute([$idDisfraz]);
        if ($st->fetchColumn() === false) {
            $pdo->rollBack();
            error_out('Ese disfraz no existe.', 404);
        }

        try {
            $pdo->prepare('INSERT INTO votos (id_usuario, id_disfraz) VALUES (?, ?)')
                ->execute([$usuario['id'], $idDisfraz]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (($e->errorInfo[1] ?? 0) === 1062) {
                error_out('Ya votaste por este disfraz.', 409);
            }
            throw $e;
        }

        $pdo->prepare('UPDATE disfraces SET votos = votos + 1 WHERE id = ?')->execute([$idDisfraz]);
        $total = (int) $pdo->query('SELECT votos FROM disfraces WHERE id = ' . $idDisfraz)->fetchColumn();
        $pdo->commit();

        json_out(['ok' => true, 'id_disfraz' => $idDisfraz, 'votos' => $total], 201);

    case 'DELETE':
        $idDisfraz = id_recurso('id_disfraz');
        $idDisfraz !== null || error_out('Falta id_disfraz.', 400);

        $pdo->beginTransaction();
        $st = $pdo->prepare('DELETE FROM votos WHERE id_usuario = ? AND id_disfraz = ?');
        $st->execute([$usuario['id'], $idDisfraz]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();
            error_out('No habías votado por este disfraz.', 404);
        }
        $pdo->prepare('UPDATE disfraces SET votos = votos - 1 WHERE id = ? AND votos > 0')->execute([$idDisfraz]);
        $total = (int) $pdo->query('SELECT votos FROM disfraces WHERE id = ' . (int) $idDisfraz)->fetchColumn();
        $pdo->commit();

        json_out(['ok' => true, 'id_disfraz' => $idDisfraz, 'votos' => $total]);
}
