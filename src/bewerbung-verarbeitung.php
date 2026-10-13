<?php

declare(strict_types=1);

require_once __DIR__ . '/bewerbungen.php';
require_once __DIR__ . '/dokumente.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/uploads.php';

function removeStoredApplicationFiles(array $storedFilenames): void
{
    foreach (array_unique($storedFilenames) as $storedFilename) {
        $path = UPLOAD_PATH . '/' . $storedFilename;

        if (is_file($path) && !@unlink($path)) {
            error_log(
                'Bewerbungsdatei konnte nicht entfernt werden: '
                . $storedFilename
            );
        }
    }
}

function processApplication(
    int $userId,
    int $jobId,
    ?array $application,
    array $documents,
    array $input
): array {
    $earliestStartDate = trim(
        (string) ($input['fruehestmoegliches_eintrittsdatum'] ?? '')
    );
    $message = trim((string) ($input['nachricht'] ?? ''));
    $formAction = (string) ($input['action'] ?? 'save_draft');
    $submissionAttempted = $formAction === 'submit_application';

    $errors = [];
    $saved = false;

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage konnte nicht überprüft werden. '
            . 'Bitte lade die Seite neu.'
        );
    }

    if (
        !in_array(
            $formAction,
            ['save_draft', 'submit_application'],
            true
        )
    ) {
        $errors[] = 'Die ausgewählte Aktion ist ungültig.';
    }

    if (
        $application !== null
        && $application['status'] !== 'entwurf'
    ) {
        $errors[] = (
            'Eine eingereichte Bewerbung kann nicht mehr '
            . 'als Entwurf bearbeitet werden.'
        );
    }

    if ($earliestStartDate === '') {
        if ($submissionAttempted) {
            $errors[] = (
                'Das frühestmögliche Eintrittsdatum '
                . 'ist zum Einreichen erforderlich.'
            );
        }
    } else {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $earliestStartDate
        );

        if (
            $date === false
            || $date->format('Y-m-d') !== $earliestStartDate
        ) {
            $errors[] = 'Bitte gib ein gültiges Eintrittsdatum an.';
        }
    }

    if (strlen($message) > 2000) {
        $errors[] = (
            'Die Nachricht darf höchstens '
            . '2000 Zeichen enthalten.'
        );
    }

    if ($submissionAttempted) {
        $existingDocumentTypes = array_column(
            $documents,
            'dokumenttyp'
        );

        $hasResume = (
            in_array('lebenslauf', $existingDocumentTypes, true)
            || uploadedFiles('lebenslauf') !== []
        );

        $hasCoverLetter = (
            in_array('anschreiben', $existingDocumentTypes, true)
            || uploadedFiles('anschreiben') !== []
        );

        if (!$hasResume) {
            $errors[] = (
                'Ein Lebenslauf ist zum Einreichen erforderlich.'
            );
        }

        if (!$hasCoverLetter) {
            $errors[] = (
                'Ein Anschreiben ist zum Einreichen erforderlich.'
            );
        }
    }

    if ($errors === []) {
        $connection = database();
        $newStoredFiles = [];
        $replacedStoredFiles = [];

        $connection->beginTransaction();

        try {
            $applicationId = saveApplicationDraft(
                $connection,
                $application === null
                    ? null
                    : (int) $application['id'],
                $userId,
                $jobId,
                $earliestStartDate,
                $message
            );

            $uploadFields = [
                'lebenslauf' => [
                    'type' => 'lebenslauf',
                    'label' => 'Lebenslauf',
                ],
                'anschreiben' => [
                    'type' => 'anschreiben',
                    'label' => 'Anschreiben',
                ],
                'zeugnisse' => [
                    'type' => 'zeugnis',
                    'label' => 'Zeugnis',
                ],
                'anlagen' => [
                    'type' => 'anlage',
                    'label' => 'Weitere Anlage',
                ],
            ];

            foreach ($uploadFields as $fieldName => $configuration) {
                foreach (uploadedFiles($fieldName) as $uploadedFile) {
                    try {
                        $storedFile = storeUploadedPdf($uploadedFile);
                    } catch (UploadValidationException $exception) {
                        throw new UploadValidationException(
                            $configuration['label']
                            . ': '
                            . $exception->getMessage()
                        );
                    }

                    $newStoredFiles[] =
                        $storedFile['speicherdateiname'];

                    $oldFilenames = replaceApplicationDocumentRecords(
                        $connection,
                        $applicationId,
                        $configuration['type']
                    );

                    foreach ($oldFilenames as $storedFilename) {
                        $replacedStoredFiles[] = $storedFilename;
                    }

                    insertApplicationDocument(
                        $connection,
                        $applicationId,
                        $configuration['type'],
                        $storedFile
                    );
                }
            }

            if ($submissionAttempted) {
                submitApplication(
                    $connection,
                    $applicationId,
                    $userId
                );
            }

            $connection->commit();
            $saved = true;
        } catch (UploadValidationException $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            removeStoredApplicationFiles($newStoredFiles);
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            removeStoredApplicationFiles($newStoredFiles);
            throw $exception;
        }

        if ($saved) {
            removeStoredApplicationFiles($replacedStoredFiles);
        }
    }

    return [
        'earliestStartDate' => $earliestStartDate,
        'message' => $message,
        'errors' => $errors,
        'saved' => $saved,
        'submitted' => $saved && $submissionAttempted,
    ];
}