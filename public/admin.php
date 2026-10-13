<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/benutzerkonten.php';

requireRole('admin');

header('Cache-Control: no-store, no-cache, must-revalidate');

$recruitingAccounts = [];
$databaseError = false;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
];

$creationErrors = [];

$statusResult = filter_input(INPUT_GET, 'status');

$accountCreated = filter_input(INPUT_GET, 'created') === '1';
$accountActivated = $statusResult === 'activated';
$accountDeactivated = $statusResult === 'deactivated';

if ($requestMethod === 'POST') {
    $formData['vorname'] = trim(
        (string) ($_POST['vorname'] ?? '')
    );

    $formData['nachname'] = trim(
        (string) ($_POST['nachname'] ?? '')
    );

    $formData['email'] = strtolower(trim(
        (string) ($_POST['email'] ?? '')
    ));

    $password = (string) ($_POST['passwort'] ?? '');

    $passwordConfirmation = (string) (
        $_POST['passwort_bestaetigung'] ?? ''
    );

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $creationErrors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if ($formData['vorname'] === '') {
        $creationErrors[] = 'Bitte gib einen Vornamen ein.';
    } elseif (strlen($formData['vorname']) > 100) {
        $creationErrors[] = (
            'Der Vorname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['nachname'] === '') {
        $creationErrors[] = 'Bitte gib einen Nachnamen ein.';
    } elseif (strlen($formData['nachname']) > 100) {
        $creationErrors[] = (
            'Der Nachname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['email'] === '') {
        $creationErrors[] = 'Bitte gib eine E-Mail-Adresse ein.';
    } elseif (
        filter_var(
            $formData['email'],
            FILTER_VALIDATE_EMAIL
        ) === false
    ) {
        $creationErrors[] = (
            'Bitte gib eine gültige E-Mail-Adresse ein.'
        );
    } elseif (strlen($formData['email']) > 254) {
        $creationErrors[] = (
            'Die E-Mail-Adresse darf höchstens 254 Zeichen enthalten.'
        );
    }

    if (strlen($password) < 8) {
        $creationErrors[] = (
            'Das Passwort muss mindestens 8 Zeichen enthalten.'
        );
    }

    if ($password !== $passwordConfirmation) {
        $creationErrors[] = (
            'Die Passwörter stimmen nicht überein.'
        );
    }

    if ($creationErrors === []) {
        try {

            if (userEmailExists($formData['email'])) {
                $creationErrors[] = (
                    'Für diese E-Mail-Adresse besteht bereits '
                    . 'ein Benutzerkonto.'
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

                createRecruitingAccount(
                    $formData['email'],
                    $passwordHash,
                    $formData['vorname'],
                    $formData['nachname']
                );

                header('Location: admin.php?created=1');
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler beim Anlegen eines Recruitingkontos: '
                . $exception->getMessage()
            );

            $creationErrors[] = (
                'Das Recruitingkonto konnte aus technischen '
                . 'Gründen nicht angelegt werden.'
            );

            http_response_code(500);
        }
    }
}

try {
    $recruitingAccounts = findRecruitingAccounts();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Adminübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Adminübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/admin.php';
require __DIR__ . '/includes/footer.php';