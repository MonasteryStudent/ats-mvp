<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once __DIR__ . '/bewerbungen.php';
require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processAccountDeletion(
    int $userId,
    array $input
): array {
    $connection = database();
    $connection->beginTransaction();

    $errors = [];
    $statusCode = 200;
    $storedFilenames = [];
    $result = null;

    try {
        $user = findActiveUserById($userId);

        if ($user === null || $user['rolle'] !== 'bewerbend') {
            $connection->rollBack();

            return [
                'accountMissing' => true,
                'hasSubmittedApplications' => false,
                'errors' => [],
                'statusCode' => 200,
                'result' => null,
            ];
        }

        $hasSubmittedApplications = userHasSubmittedApplications(
            $connection,
            $userId
        );

        $deletionMode = $hasSubmittedApplications
            ? 'request'
            : 'delete';

        if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
            $errors[] = (
                'Die Anfrage konnte nicht überprüft werden. '
                . 'Bitte lade die Seite neu.'
            );

            $statusCode = 403;
        }

        $password = (string) ($input['passwort'] ?? '');

        if (!password_verify($password, $user['passwort_hash'])) {
            $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
        }

        if (($input['deletion_mode'] ?? '') !== $deletionMode) {
            $errors[] = (
                'Der Bewerbungsstand hat sich geändert. '
                . 'Bitte prüfe den Hinweis und bestätige erneut.'
            );

            $statusCode = 409;
        }

        if ($errors !== []) {
            $connection->rollBack();

            return [
                'accountMissing' => false,
                'hasSubmittedApplications' =>
                    $hasSubmittedApplications,
                'errors' => $errors,
                'statusCode' => $statusCode,
                'result' => null,
            ];
        }

        if ($hasSubmittedApplications) {
            requestApplicantAccountDeletion(
                $connection,
                $userId
            );

            $result = 'requested';
        } else {
            $storedFilenames = findStoredFilenamesForUser(
                $connection,
                $userId
            );

            deleteApplicantAccountWithoutSubmittedApplications(
                $connection,
                $userId
            );

            $result = 'deleted';
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    foreach ($storedFilenames as $storedFilename) {
        if (
            $storedFilename === ''
            || basename($storedFilename) !== $storedFilename
        ) {
            error_log(
                'Ungültiger Speicherdateiname bei Kontolöschung: '
                . $storedFilename
            );

            $result = 'cleanup_pending';
            continue;
        }

        $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedFilename;

        if (is_file($path) && !@unlink($path)) {
            error_log(
                'Datei konnte bei Kontolöschung nicht entfernt werden: '
                . $path
            );

            $result = 'cleanup_pending';
        }
    }

    return [
        'accountMissing' => false,
        'hasSubmittedApplications' => $hasSubmittedApplications,
        'errors' => [],
        'statusCode' => 200,
        'result' => $result,
    ];
}