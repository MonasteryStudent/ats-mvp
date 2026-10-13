<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/bewerbung-rueckzug.php';

requireRole('bewerbend');

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

    if (
        $userId === null
        || findActiveApplicantById($userId) === null
    ) {
        signOutUser();
        header('Location: anmelden.php?from=overview');
        exit;
    }

    if (
        $applicationId === false
        || $applicationId === null
        || $applicationId <= 0
    ) {
        http_response_code(404);
        $error = 'Diese Bewerbung kann nicht zurückgezogen werden.';
    } else {
        $application = findWithdrawableApplication(
            $applicationId,
            $userId
        );

        if ($application === null) {
            http_response_code(404);

            $error = (
                'Diese Bewerbung ist nicht verfügbar '
                . 'oder kann nicht mehr zurückgezogen werden.'
            );
        } elseif ($requestMethod === 'POST') {
            $result = processApplicationWithdrawal(
                $applicationId,
                $userId,
                $_POST
            );

            if ($result['withdrawn']) {
                header('Location: konto.php?withdrawn=1', true, 303);
                exit;
            }

            http_response_code($result['statusCode']);
            $error = $result['error'];
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Zurückziehen einer Bewerbung: '
        . $exception->getMessage()
    );

    http_response_code(500);

    $error = (
        'Die Bewerbung konnte nicht zurückgezogen werden. '
        . 'Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Bewerbung zurückziehen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/bewerbung-zurueckziehen.php';
require __DIR__ . '/includes/footer.php';