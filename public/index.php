<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen.php';

$jobs = [];
$databaseError = false;

try {
    $jobs = findActiveJobs();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Stellenübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Karriere bei FiktivFit';
$headerLinkHref = 'anmelden.php?from=overview';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/stellenuebersicht.php';
require __DIR__ . '/includes/footer.php';