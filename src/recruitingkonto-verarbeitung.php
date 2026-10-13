<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processRecruitingAccountCreation(array $input): array
{
    $formData = [
        'vorname' => trim(
            (string) ($input['vorname'] ?? '')
        ),
        'nachname' => trim(
            (string) ($input['nachname'] ?? '')
        ),
        'email' => strtolower(trim(
            (string) ($input['email'] ?? '')
        )),
    ];

    $password = (string) ($input['passwort'] ?? '');

    $passwordConfirmation = (string) (
        $input['passwort_bestaetigung'] ?? ''
    );

    $errors = [];
    $created = false;
    $technicalError = false;

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if ($formData['vorname'] === '') {
        $errors[] = 'Bitte gib einen Vornamen ein.';
    } elseif (strlen($formData['vorname']) > 100) {
        $errors[] = (
            'Der Vorname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['nachname'] === '') {
        $errors[] = 'Bitte gib einen Nachnamen ein.';
    } elseif (strlen($formData['nachname']) > 100) {
        $errors[] = (
            'Der Nachname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['email'] === '') {
        $errors[] = 'Bitte gib eine E-Mail-Adresse ein.';
    } elseif (
        filter_var(
            $formData['email'],
            FILTER_VALIDATE_EMAIL
        ) === false
    ) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    } elseif (strlen($formData['email']) > 254) {
        $errors[] = (
            'Die E-Mail-Adresse darf höchstens 254 Zeichen enthalten.'
        );
    }

    if (strlen($password) < 8) {
        $errors[] = (
            'Das Passwort muss mindestens 8 Zeichen enthalten.'
        );
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if ($errors === []) {
        try {
            if (userEmailExists($formData['email'])) {
                $errors[] = (
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

                $created = true;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler beim Anlegen eines Recruitingkontos: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Das Recruitingkonto konnte aus technischen '
                . 'Gründen nicht angelegt werden.'
            );

            $technicalError = true;
        }
    }

    return [
        'formData' => $formData,
        'errors' => $errors,
        'created' => $created,
        'technicalError' => $technicalError,
    ];
}