<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processLogin(array $input): array
{
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $errors = [];

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
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

    $role = null;
    $technicalError = false;

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
                $userRole = (string) $user['rolle'];

                signInUser(
                    (int) $user['id'],
                    $userRole
                );

                $role = $userRole;
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

            $technicalError = true;
        }
    }

    return [
        'email' => $email,
        'errors' => $errors,
        'role' => $role,
        'technicalError' => $technicalError,
    ];
}