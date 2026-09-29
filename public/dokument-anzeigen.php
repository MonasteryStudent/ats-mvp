<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireRole('recruiting');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (
    $documentId === false
    || $documentId === null
    || $documentId < 1
) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

try {
    $statement = database()->prepare(
        "SELECT
            dokumente.originaldateiname,
            dokumente.speicherdateiname
         FROM dokumente
         INNER JOIN bewerbungen
            ON bewerbungen.id = dokumente.bewerbung_id
         WHERE dokumente.id = :id
         AND bewerbungen.status <> 'entwurf'
         LIMIT 1"
    );

    $statement->execute([
        'id' => $documentId,
    ]);

    $document = $statement->fetch();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden eines Bewerbungsdokuments: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Das Dokument konnte nicht geladen werden.');
}

if (!$document) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

$storedFilename = basename($document['speicherdateiname']);

if ($storedFilename !== $document['speicherdateiname']) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

$uploadDirectory = realpath(UPLOAD_PATH);
$filePath = realpath(
    UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedFilename
);

if (
    $uploadDirectory === false
    || $filePath === false
    || dirname($filePath) !== $uploadDirectory
    || !is_file($filePath)
    || !is_readable($filePath)
) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

$downloadFilename = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $document['originaldateiname']
);

if (
    $downloadFilename === null
    || $downloadFilename === ''
) {
    $downloadFilename = 'dokument.pdf';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $downloadFilename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($filePath);
exit;