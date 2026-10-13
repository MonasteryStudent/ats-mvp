<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/src/dokument-loeschung.php';

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

$userId = authenticatedUserId();

if (
    $userId === null
    || $documentId === false
    || $documentId === null
    || $documentId <= 0
) {
    header(
        'Location: ' . $applicationUrl . '&removal_failed=1',
        true,
        303
    );
    exit;
}

try {
    $removed = processDocumentDeletion(
        $documentId,
        $userId,
        $jobId,
        $_POST
    );
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Löschen eines Bewerbungsdokuments: '
        . $exception->getMessage()
    );

    $removed = false;
}

header(
    'Location: '
    . $applicationUrl
    . ($removed ? '&removed=1' : '&removal_failed=1'),
    true,
    303
);

exit;