<?php

declare(strict_types=1);

require_once __DIR__ . '/bewerbungen.php';
require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/session.php';

function processDraftDeletion(
    int $draftId,
    int $userId,
    array $input
): array {
    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        return [
            'deleted' => false,
            'statusCode' => 403,
            'error' => 'Die Anfrage konnte nicht überprüft werden.',
        ];
    }

    $connection = database();
    $connection->beginTransaction();

    try {
        $draft = findOwnDraft(
            $connection,
            $draftId,
            $userId
        );

        if ($draft === null) {
            $connection->rollBack();

            return [
                'deleted' => false,
                'statusCode' => 404,
                'error' => 'Der Bewerbungsentwurf ist nicht verfügbar.',
            ];
        }

        $storedFilenames = findStoredFilenamesForApplication(
            $connection,
            (int) $draft['id']
        );

        deleteOwnDraft(
            $connection,
            (int) $draft['id'],
            $userId
        );

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    $fileDeletionFailed = false;

    foreach ($storedFilenames as $storedFilename) {
        $filename = basename($storedFilename);
        $path = UPLOAD_PATH . '/' . $filename;

        if (file_exists($path) && !@unlink($path)) {
            $fileDeletionFailed = true;

            error_log(
                'Datei eines gelöschten Entwurfs '
                . 'konnte nicht entfernt werden: '
                . $filename
            );
        }
    }

    if ($fileDeletionFailed) {
        return [
            'deleted' => true,
            'statusCode' => 500,
            'error' => (
                'Der Entwurf wurde aus dem Konto entfernt. '
                . 'Die zugehörigen Dateien konnten nicht vollständig '
                . 'gelöscht werden. Bitte kontaktiere die Personalverwaltung.'
            ),
        ];
    }

    return [
        'deleted' => true,
        'statusCode' => 200,
        'error' => null,
    ];
}