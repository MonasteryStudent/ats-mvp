<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/anmeldung.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSession();

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$registrationSuccessful = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'registered') === '1'
);

$deletionResult = $requestMethod === 'GET'
    ? filter_input(INPUT_GET, 'deletion')
    : null;

$pageTitle = 'Anmelden | FiktivFit Karriere';
$headerLinkLabel = 'Zur Stellenübersicht';
$headerLinkHref = 'index.php';
$registrationHref = 'registrieren.php?from=overview';
$redirectAfterLogin = 'konto.php';

if (
    $returnJobId !== false
    && $returnJobId !== null
    && $returnJobId > 0
) {
    if ($from === 'application') {
        $headerLinkLabel = 'Zur Stelle';
        $headerLinkHref = 'stelle.php?id=' . $returnJobId;
        $registrationHref = (
            'registrieren.php?from=application&id=' . $returnJobId
        );
        $redirectAfterLogin = (
            'bewerbung.php?stelle_id=' . $returnJobId
        );
    } elseif ($from === 'job') {
        $headerLinkLabel = 'Zur Stelle';
        $headerLinkHref = 'stelle.php?id=' . $returnJobId;
        $registrationHref = (
            'registrieren.php?from=job&id=' . $returnJobId
        );
    }
}

if (userIsAuthenticated()) {
    $authenticatedRole = (string) authenticatedUserRole();

    $destination = $authenticatedRole === 'bewerbend'
        ? $redirectAfterLogin
        : startPageForRole($authenticatedRole);

    header('Location: ' . $destination);
    exit;
}

$email = '';
$errors = [];

if ($requestMethod === 'POST') {
    $result = processLogin($_POST);

    $email = $result['email'];
    $errors = $result['errors'];

    if ($result['technicalError']) {
        http_response_code(500);
    }

    if ($result['role'] !== null) {
        $role = $result['role'];

        $destination = $role === 'bewerbend'
            ? $redirectAfterLogin
            : startPageForRole($role);

        header('Location: ' . $destination);
        exit;
    }
}

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/anmelden.php';
require __DIR__ . '/includes/footer.php';