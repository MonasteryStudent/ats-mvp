<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$deletionRequests = [];
$applicationsByAccount = [];
$databaseError = false;

$accountDeleted = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'deleted') === 'account'
);

$fileCleanupPending = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'cleanup') === 'files'
);

try {
    $deletionRequests = findPendingAccountDeletionRequests();

    $applications = findApplicationsForPendingDeletionRequests();

    foreach ($applications as $application) {
        $accountId = (int) $application['benutzerkonto_id'];
        $applicationsByAccount[$accountId][] = $application;
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Kontolöschanträge: '
        . $exception->getMessage()
    );

    $deletionRequests = [];
    $applicationsByAccount = [];
    $databaseError = true;

    http_response_code(500);
}

$pageTitle = 'Kontolöschanträge | FiktivFit Karriere';
$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/loeschantraege.php';
require __DIR__ . '/includes/footer.php';