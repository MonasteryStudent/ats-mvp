<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';
require_once dirname(__DIR__) . '/src/dokumente.php';
require_once dirname(__DIR__) . '/src/bewerbung-verarbeitung.php';

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
            $result = processApplication(
                $userId,
                $jobId,
                $application,
                $documents,
                $_POST
            );

            $earliestStartDate = $result['earliestStartDate'];
            $message = $result['message'];
            $errors = $result['errors'];

            if ($result['saved']) {
                if ($result['submitted']) {
                    header('Location: konto.php?submitted=1');
                } else {
                    header(
                        'Location: bewerbung.php?stelle_id='
                        . $jobId
                        . '&saved=draft'
                    );
                }

                exit;
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
