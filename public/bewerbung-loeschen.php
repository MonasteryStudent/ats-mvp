<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/bewerbung-loeschung.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);

    exit('Diese Anfrage ist nicht erlaubt.');
}

$applicationId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$error = null;

try {
    $userId = authenticatedUserId();

    $user = $userId === null
        ? null
        : findActiveUserById($userId);

    if ($user === null || $user['rolle'] !== 'recruiting') {
        signOutUser();

        header(
            'Location: anmelden.php?from=overview',
            true,
            303
        );
        exit;
    }

    if (
        $applicationId === false
        || $applicationId === null
        || $applicationId <= 0
    ) {
        http_response_code(404);
        $error = 'Diese Bewerbung ist nicht verfügbar.';
    } else {
        $application = findWithdrawnApplication(
            database(),
            $applicationId
        );

        if ($application === null) {
            http_response_code(404);

            $error = (
                'Diese Bewerbung ist nicht verfügbar '
                . 'oder wurde nicht zurückgezogen.'
            );
        } elseif ($requestMethod === 'POST') {
            $processingResult = processWithdrawnApplicationDeletion(
                $applicationId,
                $_POST
            );

            if (
                $processingResult['deleted']
                && $processingResult['error'] === null
            ) {
                header(
                    'Location: recruiting.php?deleted=application',
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
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Löschen einer Bewerbung: '
        . $exception->getMessage()
    );

    http_response_code(500);

    $error = (
        'Die Löschung konnte nicht vollständig durchgeführt werden. '
        . 'Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Bewerbung löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/bewerbung-loeschen.php';
require __DIR__ . '/includes/footer.php';