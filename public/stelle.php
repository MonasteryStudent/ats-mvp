<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen.php';

$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$job = null;
$databaseError = false;

if ($jobId !== false && $jobId !== null) {
    try {
        $job = findActiveJobById($jobId);
    } catch (Throwable $exception) {
        error_log(
            'Fehler beim Laden der Stellendetails: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if (!$databaseError && $job === null) {
    http_response_code(404);
}

if ($databaseError) {
    $pageTitle = 'Technischer Fehler | FiktivFit Karriere';
} elseif ($job) {
    $pageTitle = $job['titel'] . ' | FiktivFit Karriere';
} else {
    $pageTitle = 'Stelle nicht gefunden | FiktivFit Karriere';
}

$headerLinkHref = $job !== null
    ? 'anmelden.php?from=job&id=' . (int) $job['id']
    : 'anmelden.php?from=overview';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/stellendetails.php';
require __DIR__ . '/includes/footer.php';