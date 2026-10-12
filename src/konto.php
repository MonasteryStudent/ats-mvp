<?php

declare(strict_types=1);

require_once __DIR__ . '/benutzerkonten.php';
require_once dirname(__DIR__) . '/config/session.php';

function processProfileUpdate(array $user, array $input): array
{
    $firstName = trim((string) ($input['vorname'] ?? ''));
    $lastName = trim((string) ($input['nachname'] ?? ''));
    $phone = trim((string) ($input['telefon'] ?? ''));

    $errors = [];

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = 'Die Anfrage konnte nicht überprüft werden.';
    }

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'Vorname und Nachname sind erforderlich.';
    }

    if (
        strlen($firstName) > 100
        || strlen($lastName) > 100
    ) {
        $errors[] = (
            'Vorname und Nachname dürfen höchstens '
            . '100 Zeichen enthalten.'
        );
    }

    if (strlen($phone) > 50) {
        $errors[] = (
            'Die Telefonnummer darf höchstens '
            . '50 Zeichen enthalten.'
        );
    }

    $user['vorname'] = $firstName;
    $user['nachname'] = $lastName;
    $user['telefon'] = $phone;

    if ($errors === []) {
        updateUserProfile(
            (int) $user['id'],
            $firstName,
            $lastName,
            $phone === '' ? null : $phone
        );
    }

    return [
        'user' => $user,
        'errors' => $errors,
        'saved' => $errors === [],
    ];
}

function processPasswordChange(array $user, array $input): array
{
    $currentPassword = (string) (
        $input['aktuelles_passwort'] ?? ''
    );
    $newPassword = (string) (
        $input['neues_passwort'] ?? ''
    );
    $passwordConfirmation = (string) (
        $input['passwort_bestaetigung'] ?? ''
    );

    $errors = [];

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = 'Die Anfrage konnte nicht überprüft werden.';
    }

    if (
        !password_verify(
            $currentPassword,
            $user['passwort_hash']
        )
    ) {
        $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
    }

    if (strlen($newPassword) < 8) {
        $errors[] = (
            'Das neue Passwort muss mindestens '
            . '8 Zeichen lang sein.'
        );
    }

    if ($newPassword !== $passwordConfirmation) {
        $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
    }

    if ($errors === []) {
        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'Das Passwort konnte nicht verarbeitet werden.'
            );
        }

        updateUserPassword(
            (int) $user['id'],
            $passwordHash
        );
    }

    return [
        'errors' => $errors,
        'saved' => $errors === [],
    ];
}