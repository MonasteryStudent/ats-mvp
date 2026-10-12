<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/uploads.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_once dirname(__DIR__) . '/src/stellen.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';
require_once dirname(__DIR__) . '/src/dokumente.php';

$jobId = filter_input(
    INPUT_GET,
    'stelle_id',
    FILTER_VALIDATE_INT
);

$job = null;
$user = null;
$application = null;
$databaseError = false;
$accessDenied = false;
$errors = [];
$documents = [];

$documentsByType = [];

$documentFields = [
    [
        'type' => 'lebenslauf',
        'name' => 'lebenslauf',
        'id' => 'resume',
        'label' => 'Lebenslauf',
        'optional' => false,
    ],
    [
        'type' => 'anschreiben',
        'name' => 'anschreiben',
        'id' => 'cover-letter',
        'label' => 'Anschreiben',
        'optional' => false,
    ],
    [
        'type' => 'zeugnis',
        'name' => 'zeugnisse',
        'id' => 'certificates',
        'label' => 'Zeugnisse als zusammengefasste PDF',
        'optional' => true,
    ],
    [
        'type' => 'anlage',
        'name' => 'anlagen',
        'id' => 'attachments',
        'label' => 'Weitere Anlagen als zusammengefasste PDF',
        'optional' => true,
    ],
];

$earliestStartDate = '';
$message = '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$formAction = (string) ($_POST['action'] ?? 'save_draft');

$submissionAttempted = (
    $requestMethod === 'POST'
    && $formAction === 'submit_application'
);

$draftSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === 'draft'
);

$documentRemoved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'removed') === '1'
);

$documentRemovalFailed = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'removal_failed') === '1'
);

if (
    $jobId === false
    || $jobId === null
    || $jobId <= 0
) {
    http_response_code(404);
} elseif (!userIsAuthenticated()) {
    header(
        'Location: anmelden.php?from=application&id=' . $jobId
    );
    exit;
} elseif (authenticatedUserRole() !== 'bewerbend') {
    $accessDenied = true;
    http_response_code(403);
} else {
    header('Cache-Control: no-store, no-cache, must-revalidate');

    try {
        $job = findActiveJobById($jobId);

        if ($job === null) {
            http_response_code(404);
        }

        if ($job !== null) {
            $userId = authenticatedUserId();

            $user = $userId === null
                ? null
                : findActiveApplicantById($userId);

            if ($user === null) {
                signOutUser();

                header(
                    'Location: anmelden.php?from=application&id='
                    . $jobId
                );
                exit;
            }

            $application = findApplicationForUserAndJob(
                $userId,
                $jobId
            );

            if ($application !== null) {
                $earliestStartDate = (string) (
                    $application['fruehestmoegliches_eintrittsdatum'] ?? ''
                );

                $message = (string) (
                    $application['nachricht'] ?? ''
                );

                $documents = findDocumentsForApplication(
                    (int) $application['id']
                );
            }
        }

        if (
            $job !== null
            && $user !== null
            && $requestMethod === 'POST'
            && ($application === null
                || $application['status'] === 'entwurf')
        ) {
            $earliestStartDate = trim(
                (string) (
                    $_POST[
                        'fruehestmoegliches_eintrittsdatum'
                    ] ?? ''
                )
            );

            $message = trim(
                (string) ($_POST['nachricht'] ?? '')
            );

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
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
                    $errors[] = (
                        'Bitte gib ein gültiges Eintrittsdatum an.'
                    );
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
                        $application === null ? null : (int) $application['id'],
                        $userId,
                        $jobId,
                        $earliestStartDate,
                        $message
                    );

                    $uploadFields = [
                        'lebenslauf' => [
                            'type' => 'lebenslauf',
                            'label' => 'Lebenslauf',
                            'replace' => true,
                        ],
                        'anschreiben' => [
                            'type' => 'anschreiben',
                            'label' => 'Anschreiben',
                            'replace' => true,
                        ],
                        'zeugnisse' => [
                            'type' => 'zeugnis',
                            'label' => 'Zeugnis',
                            'replace' => true,
                        ],
                        'anlagen' => [
                            'type' => 'anlage',
                            'label' => 'Weitere Anlage',
                            'replace' => true,
                        ],
                    ];

                    foreach ($uploadFields as $fieldName => $configuration) {
                        foreach (uploadedFiles($fieldName) as $uploadedFile) {
                            try {
                                $storedFile = storeUploadedPdf(
                                    $uploadedFile
                                );
                            } catch (UploadValidationException $exception) {
                                throw new UploadValidationException(
                                    $configuration['label']
                                    . ': '
                                    . $exception->getMessage()
                                );
                            }

                            $newStoredFiles[] =
                                $storedFile['speicherdateiname'];

                            if ($configuration['replace']) {
                                $oldFilenames = replaceApplicationDocumentRecords(
                                    $connection,
                                    $applicationId,
                                    $configuration['type']
                                );

                                foreach ($oldFilenames as $storedFilename) {
                                    $replacedStoredFiles[] = $storedFilename;
                                }
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

                    foreach ($replacedStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    if ($submissionAttempted) {
                        header('Location: konto.php?submitted=1');
                    } else {
                        header(
                            'Location: bewerbung.php?stelle_id='
                            . $jobId
                            . '&saved=draft'
                        );
                    }

                    exit;
                } catch (UploadValidationException $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }

                    foreach ($newStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    $errors[] = $exception->getMessage();
                } catch (Throwable $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }

                    foreach ($newStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    throw $exception;
                }
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler beim Bearbeiten der Bewerbung: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

foreach ($documents as $document) {
    $documentsByType[$document['dokumenttyp']] = $document;
}

$headerLinkLabel = 'Mein Konto';

if (userIsAuthenticated()) {
    $userRole = (string) authenticatedUserRole();
    $headerLinkHref = startPageForRole($userRole);
} else {
    $headerLinkHref = 'anmelden.php?from=overview';
}

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/bewerbung.php';
require __DIR__ . '/includes/footer.php';
