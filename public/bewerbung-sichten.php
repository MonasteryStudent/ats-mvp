<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/bewerbung-bewertung.php';
require_once dirname(__DIR__) . '/src/dokumente.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errors = [];

$changesSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === '1'
);

$statusOptions = recruitingStatusOptions();
$ratingOptions = recruitingRatingOptions();

$selectedStatus = '';
$selectedRating = '';
$recruitingNote = '';

$applicationId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$documents = [];
$databaseError = false;

if (
    $applicationId !== false
    && $applicationId !== null
    && $applicationId > 0
) {
    try {
        $application = findSubmittedApplicationById(
            $applicationId
        );

        if ($application !== null) {
            $selectedStatus = (string) $application['status'];

            $selectedRating = $application['bewertung'] === null
                ? ''
                : (string) $application['bewertung'];

            $recruitingNote = (string) (
                $application['recruitingnotiz'] ?? ''
            );

            if ($requestMethod === 'POST') {
                $processingResult = processApplicationReview(
                    $application,
                    $_POST
                );

                $selectedStatus = (
                    $processingResult['selectedStatus']
                );

                $selectedRating = (
                    $processingResult['selectedRating']
                );

                $recruitingNote = (
                    $processingResult['recruitingNote']
                );

                $errors = $processingResult['errors'];

                if ($processingResult['saved']) {
                    header(
                        'Location: bewerbung-sichten.php?id='
                        . $applicationId
                        . '&saved=1',
                        true,
                        303
                    );
                    exit;
                }
            }

            $documents = findDocumentsForRecruiting(
                $applicationId
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler bei der Verarbeitung '
            . 'der Bewerbungsdetails: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if (!$databaseError && $application === null) {
    http_response_code(404);
}

if ($databaseError) {
    $pageTitle = 'Technischer Fehler | FiktivFit Karriere';
} elseif ($application !== null) {
    $pageTitle = 'Bewerbungsdetails | FiktivFit Karriere';
} else {
    $pageTitle = 'Bewerbung nicht gefunden | FiktivFit Karriere';
}

$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

if (filter_input(INPUT_GET, 'from') === 'deletion_requests') {
    $headerLinkLabel = 'Zur Übersicht der Löschanträge';
    $headerLinkHref = 'loeschantraege.php';
}

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/bewerbung-sichten.php';
require __DIR__ . '/includes/footer.php';