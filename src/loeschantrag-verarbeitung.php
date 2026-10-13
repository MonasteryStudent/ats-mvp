<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processAccountDeletionRequest(
    int $accountId,
    array $input
): array {
    $errors = [];
    $statusCode = 200;

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage konnte nicht überprüft werden. '
            . 'Bitte lade die Seite neu.'
        );

        $statusCode = 403;
    }

    if (($input['aufbewahrung_geprueft'] ?? '') !== '1') {
        $errors[] = (
            'Bitte bestätige die Prüfung des Aufbewahrungsbedarfs.'
        );
    }

    if ($errors !== []) {
        return [
            'deleted' => false,
            'fileCleanupFailed' => false,
            'statusCode' => $statusCode,
            'errors' => $errors,
        ];
    }

    $connection = database();
    $connection->beginTransaction();

    try {
        $account = findPendingAccountDeletionRequest(
            $connection,
            $accountId
        );

        if ($account === null) {
            $connection->rollBack();

            return [
                'deleted' => false,
                'fileCleanupFailed' => false,
                'statusCode' => 404,
                'errors' => [
                    'Es wurde kein offener Löschantrag gefunden.',
                ],
            ];
        }

        $storedFilenames = findStoredFilenamesForUser(
            $connection,
            $accountId
        );

        deleteAccountWithPendingDeletionRequest(
            $connection,
            $accountId
        );

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    $fileCleanupFailed = false;

    foreach ($storedFilenames as $storedFilename) {
        if (
            $storedFilename === ''
            || basename($storedFilename) !== $storedFilename
        ) {
            error_log(
                'Ungültiger Speicherdateiname bei geprüfter Kontolöschung: '
                . $storedFilename
            );

            $fileCleanupFailed = true;
            continue;
        }

        $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedFilename;

        if (is_file($path) && !@unlink($path)) {
            error_log(
                'Datei konnte bei geprüfter Kontolöschung nicht entfernt werden: '
                . $path
            );

            $fileCleanupFailed = true;
        }
    }

    return [
        'deleted' => true,
        'fileCleanupFailed' => $fileCleanupFailed,
        'statusCode' => 200,
        'errors' => [],
    ];
}