<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/konto-loeschung.php';

requireRole('bewerbend');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);

    exit('Diese Anfrage ist nicht erlaubt.');
}

$userId = authenticatedUserId();
$errors = [];
$databaseError = false;
$hasSubmittedApplications = false;
$deletionMode = 'delete';

try {
    $user = $userId === null
        ? null
        : findActiveUserById($userId);

    if ($user === null || $user['rolle'] !== 'bewerbend') {
        signOutUser();

        header(
            'Location: anmelden.php?from=overview',
            true,
            303
        );
        exit;
    }

    if ($requestMethod === 'POST') {
        $processingResult = processAccountDeletion(
            $userId,
            $_POST
        );

        if ($processingResult['accountMissing']) {
            signOutUser();

            header(
                'Location: anmelden.php?from=overview',
                true,
                303
            );
            exit;
        }

        $hasSubmittedApplications = (
            $processingResult['hasSubmittedApplications']
        );

        $errors = $processingResult['errors'];

        http_response_code($processingResult['statusCode']);

        if ($processingResult['result'] !== null) {
            signOutUser();

            header(
                'Location: anmelden.php?from=overview&deletion='
                . $processingResult['result'],
                true,
                303
            );
            exit;
        }
    } else {
        $hasSubmittedApplications = userHasSubmittedApplications(
            database(),
            $userId
        );
    }

    $deletionMode = $hasSubmittedApplications
        ? 'request'
        : 'delete';
} catch (Throwable $exception) {
    error_log(
        'Fehler bei der Kontolöschung: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Kontolöschung | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/konto-loeschen.php';
require __DIR__ . '/includes/footer.php';