<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/benutzerkonten.php';
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
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if (
        $email === ''
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    }

    if ($password === '') {
        $errors[] = 'Bitte gib dein Passwort ein.';
    }

    if ($errors === []) {
        try {
            $user = findUserByEmail($email);

            if (
                $user === null
                || !password_verify(
                    $password,
                    $user['passwort_hash']
                )
            ) {
                $errors[] = (
                    'E-Mail-Adresse oder Passwort ist nicht korrekt.'
                );
            } elseif ((int) $user['ist_aktiv'] !== 1) {
                $errors[] = 'Dieses Benutzerkonto ist deaktiviert.';
            } else {
                $role = (string) $user['rolle'];

                signInUser(
                    (int) $user['id'],
                    $role
                );

                $destination = $role === 'bewerbend'
                    ? $redirectAfterLogin
                    : startPageForRole($role);

                header('Location: ' . $destination);
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler bei der Anmeldung: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Die Anmeldung ist aus technischen Gründen '
                . 'nicht möglich. Bitte versuche es erneut.'
            );

            http_response_code(500);
        }
    }
}

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/anmelden.php';
require __DIR__ . '/includes/footer.php';