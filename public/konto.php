<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/konto.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';

requireRole('bewerbend');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$user = null;
$databaseError = false;

$applications = [];
$drafts = [];
$errors = [];
$passwordErrors = [];

$passwordSaved = filter_input(INPUT_GET, 'saved') === 'password';
$profileSaved = filter_input(INPUT_GET, 'saved') === 'profile';
$applicationSubmitted = filter_input(INPUT_GET, 'submitted') === '1';

$applicationWithdrawn = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'withdrawn') === '1'
);

$draftDeleted = filter_input(INPUT_GET, 'deleted') === 'draft';

try {
    $userId = authenticatedUserId();

    if ($userId === null) {
        signOutUser();
        header('Location: anmelden.php?from=overview');
        exit;
    }

    $user = findActiveUserById($userId);

    if ($user === null) {
        signOutUser();
        header('Location: anmelden.php?from=overview');
        exit;
    }

    if ($requestMethod === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $result = processProfileUpdate($user, $_POST);

            $user = $result['user'];
            $errors = $result['errors'];

            if ($result['saved']) {
                header('Location: konto.php?saved=profile');
                exit;
            }
        } elseif ($action === 'change_password') {
            $result = processPasswordChange($user, $_POST);

            $passwordErrors = $result['errors'];

            if ($result['saved']) {
                header('Location: konto.php?saved=password');
                exit;
            }
        }
    }

    foreach (findApplicationsForUser($userId) as $application) {
        if ($application['status'] === 'entwurf') {
            $drafts[] = $application;
        } else {
            $applications[] = $application;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden des Benutzerkontos: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Mein Konto | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/konto.php';
require __DIR__ . '/includes/footer.php';