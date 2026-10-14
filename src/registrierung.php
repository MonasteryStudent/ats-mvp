<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once dirname(__DIR__) . '/config/session.php';

function processRegistration(array $input): array
{
    $formData = [
        'vorname' => trim((string) ($input['vorname'] ?? '')),
        'nachname' => trim((string) ($input['nachname'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'email_bestaetigung' => trim(
            (string) ($input['email_bestaetigung'] ?? '')
        ),
    ];

    $password = (string) ($input['passwort'] ?? '');
    $passwordConfirmation = (string) (
        $input['passwort_bestaetigung'] ?? ''
    );

    $conditionsAccepted = (
        $input['bedingungen_akzeptiert'] ?? ''
    ) === '1';

    $errors = [];
    $registered = false;
    $technicalError = false;

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
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
            'Bitte akzeptiere die Nutzungsbedingungen.'
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

                $registered = true;
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

            $technicalError = true;
        }
    }

    return [
        'formData' => $formData,
        'conditionsAccepted' => $conditionsAccepted,
        'errors' => $errors,
        'registered' => $registered,
        'technicalError' => $technicalError,
    ];
}