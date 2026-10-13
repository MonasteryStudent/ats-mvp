<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$jobs = [];
$applications = [];
$databaseError = false;

$jobCreated = filter_input(INPUT_GET, 'saved') === 'created';
$jobUpdated = filter_input(INPUT_GET, 'saved') === 'updated';
$jobDeleted = filter_input(INPUT_GET, 'deleted') === 'job';

$applicationDeleted = (
    filter_input(INPUT_GET, 'deleted') === 'application'
);

try {
    $jobs = findJobsForRecruiting();
    $applications = findSubmittedApplicationsForRecruiting();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Recruitingübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$ongoingApplications = [];
$completedApplications = [];

foreach ($applications as $application) {
    if (
        in_array(
            $application['status'],
            ['abgelehnt', 'zurueckgezogen'],
            true
        )
    ) {
        $completedApplications[] = $application;
    } else {
        $ongoingApplications[] = $application;
    }
}

$applicationGroups = [
    [
        'id' => 'ongoing-applications',
        'title' => 'Laufende Bewerbungen',
        'applications' => $ongoingApplications,
        'collapsible' => false,
        'emptyMessage' =>
            'Es liegen keine laufenden Bewerbungen vor.',
    ],
    [
        'id' => 'completed-applications',
        'title' => 'Abgeschlossene Bewerbungen',
        'applications' => $completedApplications,
        'collapsible' => true,
        'emptyMessage' =>
            'Es liegen keine abgeschlossenen Bewerbungen vor.',
    ],
];

$pageTitle = 'Recruitingübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/recruiting.php';
require __DIR__ . '/includes/footer.php';