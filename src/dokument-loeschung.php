<?php

declare(strict_types=1);

require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processDocumentDeletion(
    int $documentId,
    int $userId,
    int $jobId,
    array $input
): bool {
    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        return false;
    }

    $connection = database();
    $connection->beginTransaction();

    try {
        $document = findOwnDraftDocument(
            $connection,
            $documentId,
            $userId,
            $jobId
        );

        if ($document === null) {
            $connection->rollBack();
            return false;
        }

        deleteApplicationDocumentRecord(
            $connection,
            $documentId,
            (int) $document['bewerbung_id']
        );

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    $storedFilename = basename(
        (string) $document['speicherdateiname']
    );

    $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedFilename;

    if (is_file($path) && !@unlink($path)) {
        error_log(
            'Die gespeicherte Dokumentdatei konnte nicht gelöscht werden: '
            . $storedFilename
        );
    }

    return true;
}