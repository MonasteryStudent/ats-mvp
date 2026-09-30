<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireRole('admin');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'POST') {
    http_response_code(405);
    exit('Diese Aktion ist nur über POST erlaubt.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Die Anfrage ist abgelaufen oder ungültig.');
}

$accountId = filter_input(
    INPUT_POST,
    'account_id',
    FILTER_VALIDATE_INT
);

$requestedAction = (string) ($_POST['action'] ?? '');

$newStatus = match ($requestedAction) {
    'activate' => 1,
    'deactivate' => 0,
    default => null,
};

if (
    $accountId === false
    || $accountId === null
    || $accountId < 1
    || $newStatus === null
) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

try {
    $statement = database()->prepare(
        'UPDATE benutzerkonten
         SET
            ist_aktiv = :ist_aktiv,
            aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
         AND rolle = :rolle'
    );

    $statement->execute([
        'ist_aktiv' => $newStatus,
        'id' => $accountId,
        'rolle' => 'recruiting',
    ]);

    if ($statement->rowCount() === 0) {
        http_response_code(404);
        exit('Das Recruitingkonto wurde nicht gefunden.');
    }

    $result = $newStatus === 1
        ? 'activated'
        : 'deactivated';

    header('Location: admin.php?status=' . $result);
    exit;
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Ändern des Recruitingkontostatus: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Der Kontostatus konnte nicht geändert werden.');
}