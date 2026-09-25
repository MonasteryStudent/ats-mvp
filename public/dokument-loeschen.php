<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Diese Aktion ist nur über POST erlaubt.');
}

$jobId = filter_input(
    INPUT_POST,
    'stelle_id',
    FILTER_VALIDATE_INT
);

$documentId = filter_input(
    INPUT_POST,
    'dokument_id',
    FILTER_VALIDATE_INT
);

if (
    $jobId === false
    || $jobId === null
    || $jobId <= 0
) {
    http_response_code(400);
    exit('Ungültige Stellen-ID.');
}

$applicationUrl = 'bewerbung.php?stelle_id=' . $jobId;

if (!userIsAuthenticated()) {
    header(
        'Location: anmelden.php?from=application&id=' . $jobId
    );
    exit;
}

if (authenticatedUserRole() !== 'bewerbend') {
    http_response_code(403);
    exit('Zugriff nicht erlaubt.');
}

if (
    $documentId === false
    || $documentId === null
    || $documentId <= 0
    || !isValidCsrfToken($_POST['csrf_token'] ?? null)
) {
    header('Location: ' . $applicationUrl . '&removal_failed=1');
    exit;
}

$connection = null;

try {
    $connection = database();
    $connection->beginTransaction();

    $documentStatement = $connection->prepare(
        'SELECT
            d.id,
            d.bewerbung_id,
            d.speicherdateiname
         FROM dokumente AS d
         INNER JOIN bewerbungen AS b
            ON b.id = d.bewerbung_id
         WHERE d.id = :dokument_id
           AND b.benutzerkonto_id = :benutzerkonto_id
           AND b.stelle_id = :stelle_id
           AND b.status = :status
         LIMIT 1'
    );

    $documentStatement->execute([
        'dokument_id' => $documentId,
        'benutzerkonto_id' => authenticatedUserId(),
        'stelle_id' => $jobId,
        'status' => 'entwurf',
    ]);

    $document = $documentStatement->fetch();

    if ($document === false) {
        $connection->rollBack();

        header('Location: ' . $applicationUrl . '&removal_failed=1');
        exit;
    }

    $deleteStatement = $connection->prepare(
        'DELETE FROM dokumente
         WHERE id = :dokument_id
           AND bewerbung_id = :bewerbung_id'
    );

    $deleteStatement->execute([
        'dokument_id' => $documentId,
        'bewerbung_id' => $document['bewerbung_id'],
    ]);

    if ($deleteStatement->rowCount() !== 1) {
        throw new RuntimeException(
            'Das Dokument konnte nicht gelöscht werden.'
        );
    }

    $connection->commit();

    $storedFilename = basename(
        (string) $document['speicherdateiname']
    );

    $path = UPLOAD_PATH . '/' . $storedFilename;

    if (is_file($path) && !unlink($path)) {
        error_log(
            'Die gespeicherte Dokumentdatei konnte nicht gelöscht werden: '
            . $storedFilename
        );
    }

    header('Location: ' . $applicationUrl . '&removed=1');
    exit;
} catch (Throwable $exception) {
    if (
        $connection instanceof PDO
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Fehler beim Löschen eines Bewerbungsdokuments: '
        . $exception->getMessage()
    );

    header('Location: ' . $applicationUrl . '&removal_failed=1');
    exit;
}