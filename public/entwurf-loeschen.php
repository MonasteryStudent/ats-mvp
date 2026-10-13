<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/src/entwurf-loeschung.php';

requireRole('bewerbend');

header('Cache-Control: no-store');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Diese Anfrage ist nicht erlaubt.');
}

$draftId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$draft = null;
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

    $validatedDraftId = (
        $draftId !== false
        && $draftId !== null
        && $draftId > 0
    ) ? $draftId : 0;

    if ($requestMethod === 'POST') {
        $result = processDraftDeletion(
            $validatedDraftId,
            $userId,
            $_POST
        );

        if ($result['deleted'] && $result['error'] === null) {
            header('Location: konto.php?deleted=draft', true, 303);
            exit;
        }

        http_response_code($result['statusCode']);
        $error = $result['error'];
    } else {
        $draft = findOwnDraft(
            database(),
            $validatedDraftId,
            $userId
        );

        if ($draft === null) {
            http_response_code(404);
            $error = 'Der Bewerbungsentwurf ist nicht verfügbar.';
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Löschen eines Bewerbungsentwurfs: '
        . $exception->getMessage()
    );

    http_response_code(500);
    $error = 'Die Löschung konnte nicht abgeschlossen werden.';
}

$pageTitle = 'Entwurf löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/entwurf-loeschen.php';
require __DIR__ . '/includes/footer.php';