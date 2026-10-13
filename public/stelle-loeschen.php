<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen-loeschung.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);

    exit('Diese Anfrage ist nicht erlaubt.');
}

$jobId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$job = null;
$error = null;
$hasApplications = false;

try {
    if (
        $jobId === false
        || $jobId === null
        || $jobId <= 0
    ) {
        http_response_code(404);
        $error = 'Diese Stelle ist nicht verfügbar.';
    } else {
        $job = findJobForDeletion($jobId);

        if ($job === null) {
            http_response_code(404);
            $error = 'Diese Stelle ist nicht verfügbar.';
        } else {
            $hasApplications = (
                (int) $job['hat_bewerbungen'] === 1
            );

            if ($requestMethod === 'POST') {
                $processingResult = processJobDeletion(
                    $jobId,
                    $_POST
                );

                if ($processingResult['deleted']) {
                    header(
                        'Location: recruiting.php?deleted=job',
                        true,
                        303
                    );
                    exit;
                }

                http_response_code(
                    $processingResult['statusCode']
                );

                $error = $processingResult['error'];
            }
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Löschen einer Stelle: '
        . $exception->getMessage()
    );

    http_response_code(500);

    $error = (
        'Die Stelle konnte aus technischen Gründen nicht gelöscht '
        . 'werden. Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Stelle löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/stelle-loeschen.php';
require __DIR__ . '/includes/footer.php';