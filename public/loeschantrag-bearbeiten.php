<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/loeschantrag-verarbeitung.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);

    exit('Diese Anfrage ist nicht erlaubt.');
}

$accountId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$account = null;
$errors = [];
$databaseError = false;

try {
    if (
        $accountId !== false
        && $accountId !== null
        && $accountId > 0
    ) {
        $account = findPendingAccountDeletionRequest(
            database(),
            $accountId
        );
    }

    if ($account === null) {
        http_response_code(404);
    } elseif ($requestMethod === 'POST') {
        $processingResult = processAccountDeletionRequest(
            $accountId,
            $_POST
        );

        $errors = $processingResult['errors'];

        http_response_code(
            $processingResult['statusCode']
        );

        if ($processingResult['deleted']) {
            $destination = $processingResult['fileCleanupFailed']
                ? 'loeschantraege.php?cleanup=files'
                : 'loeschantraege.php?deleted=account';

            header('Location: ' . $destination, true, 303);
            exit;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler bei der Bearbeitung eines Kontolöschantrags: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Löschantrag bearbeiten | FiktivFit Karriere';
$headerLinkLabel = 'Zur Übersicht der Löschanträge';
$headerLinkHref = 'loeschantraege.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/loeschantrag-bearbeiten.php';
require __DIR__ . '/includes/footer.php';