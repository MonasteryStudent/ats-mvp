<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/benutzerkonten.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSession();

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$loginHref = 'anmelden.php?from=overview';

if (
    in_array($from, ['job', 'application'], true)
    && $returnJobId !== false
    && $returnJobId !== null
    && $returnJobId > 0
) {
    $loginHref = (
        'anmelden.php?from='
        . $from
        . '&id='
        . $returnJobId
    );
}

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
    'email_bestaetigung' => '',
];

$conditionsAccepted = false;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formData['vorname'] = trim(
        (string) ($_POST['vorname'] ?? '')
    );
    $formData['nachname'] = trim(
        (string) ($_POST['nachname'] ?? '')
    );
    $formData['email'] = trim(
        (string) ($_POST['email'] ?? '')
    );
    $formData['email_bestaetigung'] = trim(
        (string) ($_POST['email_bestaetigung'] ?? '')
    );

    $password = (string) ($_POST['passwort'] ?? '');
    $passwordConfirmation = (string) (
        $_POST['passwort_bestaetigung'] ?? ''
    );

    $conditionsAccepted = (
        $_POST['bedingungen_akzeptiert'] ?? ''
    ) === '1';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if ($formData['vorname'] === '') {
        $errors[] = 'Bitte gib deinen Vornamen ein.';
    } elseif (strlen($formData['vorname']) > 100) {
        $errors[] = 'Der Vorname darf höchstens 100 Zeichen enthalten.';
    }

    if ($formData['nachname'] === '') {
        $errors[] = 'Bitte gib deinen Nachnamen ein.';
    } elseif (strlen($formData['nachname']) > 100) {
        $errors[] = 'Der Nachname darf höchstens 100 Zeichen enthalten.';
    }

    if ($formData['email'] === '') {
        $errors[] = 'Bitte gib deine E-Mail-Adresse ein.';
    } elseif (
        filter_var($formData['email'], FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    }

    if (
        strcasecmp(
            $formData['email'],
            $formData['email_bestaetigung']
        ) !== 0
    ) {
        $errors[] = 'Die E-Mail-Adressen stimmen nicht überein.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen enthalten.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if (!$conditionsAccepted) {
        $errors[] = (
            'Bitte akzeptiere die Nutzungsbedingungen '
            . 'und bestätige die Datenschutzhinweise.'
        );
    }

    if ($errors === []) {
        try {
            if (userEmailExists($formData['email'])) {
                $errors[] = (
                    'Für diese E-Mail-Adresse besteht bereits ein Konto.'
                );
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($passwordHash === false) {
                    throw new RuntimeException(
                        'Das Passwort konnte nicht verarbeitet werden.'
                    );
                }

                createApplicantAccount(
                    $formData['email'],
                    $passwordHash,
                    $formData['vorname'],
                    $formData['nachname']
                );

                header(
                    'Location: '
                    . $loginHref
                    . '&registered=1'
                );
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler bei der Registrierung: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Das Konto konnte aus technischen Gründen '
                . 'nicht angelegt werden. Bitte versuche es erneut.'
            );

            http_response_code(500);
        }
    }
}

$pageTitle = 'Registrieren | FiktivFit Karriere';
$headerLinkLabel = 'Zur Anmeldung';
$headerLinkHref = $loginHref;

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/registrieren.php';
require __DIR__ . '/includes/footer.php';