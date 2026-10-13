<?php

declare(strict_types=1);

require_once __DIR__ . '/bewerbungen.php';
require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processWithdrawnApplicationDeletion(
    int $applicationId,
    array $input
): array {
    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        return [
            'deleted' => false,
            'statusCode' => 403,
            'error' => (
                'Die Anfrage ist abgelaufen. '
                . 'Bitte lade die Seite neu und versuche es erneut.'
            ),
        ];
    }

    if (($input['aufbewahrung_geprueft'] ?? null) !== '1') {
        return [
            'deleted' => false,
            'statusCode' => 200,
            'error' => (
                'Bitte bestätige, dass kein weiterer '
                . 'Aufbewahrungsgrund besteht.'
            ),
        ];
    }

    $connection = database();
    $connection->beginTransaction();

    try {
        $application = findWithdrawnApplication(
            $connection,
            $applicationId
        );

        if ($application === null) {
            $connection->rollBack();

            return [
                'deleted' => false,
                'statusCode' => 404,
                'error' => (
                    'Diese Bewerbung ist nicht verfügbar '
                    . 'oder wurde nicht zurückgezogen.'
                ),
            ];
        }

        $storedFiles = findStoredFilenamesForApplication(
            $connection,
            $applicationId
        );

        deleteWithdrawnApplication(
            $connection,
            $applicationId
        );

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    $fileDeletionFailed = false;

    foreach ($storedFiles as $storedFilename) {
        $filename = (string) $storedFilename;

        if (basename($filename) !== $filename) {
            error_log(
                'Ungültiger Speicherdateiname bei Bewerbung '
                . $applicationId
                . ': '
                . $filename
            );

            $fileDeletionFailed = true;
            continue;
        }

        $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path) && !@unlink($path)) {
            error_log(
                'Datei nach Bewerbungslöschung nicht entfernt: '
                . $path
            );

            $fileDeletionFailed = true;
        }
    }

    if ($fileDeletionFailed) {
        return [
            'deleted' => true,
            'statusCode' => 500,
            'error' => (
                'Die Bewerbungsdaten wurden gelöscht. '
                . 'Mindestens eine Datei konnte jedoch nicht '
                . 'entfernt werden. Bitte informiere die '
                . 'technische Administration.'
            ),
        ];
    }

    return [
        'deleted' => true,
        'statusCode' => 200,
        'error' => null,
    ];
}