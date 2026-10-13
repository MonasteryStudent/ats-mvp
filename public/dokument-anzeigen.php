<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/src/dokument-anzeige.php';

requireRole('recruiting');

$documentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $documentId === false
    || $documentId === null
    || $documentId < 1
) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

try {
    $document = prepareRecruitingDocument($documentId);
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden eines Bewerbungsdokuments: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Das Dokument konnte nicht geladen werden.');
}

if ($document === null) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

$filePath = $document['filePath'];
$downloadFilename = $document['downloadFilename'];

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header(
    'Content-Disposition: inline; filename="'
    . $downloadFilename
    . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($filePath);
exit;