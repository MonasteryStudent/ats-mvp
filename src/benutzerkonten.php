<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function findUserByEmail(string $email): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            email,
            passwort_hash,
            rolle,
            ist_aktiv
         FROM benutzerkonten
         WHERE email = :email
         LIMIT 1'
    );

    $statement->execute([
        'email' => $email,
    ]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function userEmailExists(string $email): bool
{
    $statement = database()->prepare(
        'SELECT id
         FROM benutzerkonten
         WHERE email = :email
         LIMIT 1'
    );

    $statement->execute([
        'email' => $email,
    ]);

    return $statement->fetch() !== false;
}

function createApplicantAccount(
    string $email,
    string $passwordHash,
    string $firstName,
    string $lastName
): void {
    $statement = database()->prepare(
        'INSERT INTO benutzerkonten (
            email,
            passwort_hash,
            vorname,
            nachname,
            rolle,
            ist_aktiv
        ) VALUES (
            :email,
            :passwort_hash,
            :vorname,
            :nachname,
            :rolle,
            :ist_aktiv
        )'
    );

    $statement->execute([
        'email' => strtolower($email),
        'passwort_hash' => $passwordHash,
        'vorname' => $firstName,
        'nachname' => $lastName,
        'rolle' => 'bewerbend',
        'ist_aktiv' => 1,
    ]);
}

function findActiveUserById(int $userId): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            email,
            passwort_hash,
            vorname,
            nachname,
            telefon,
            rolle
         FROM benutzerkonten
         WHERE id = :id
           AND ist_aktiv = 1'
    );

    $statement->execute([
        'id' => $userId,
    ]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function updateUserProfile(
    int $userId,
    string $firstName,
    string $lastName,
    ?string $phone
): void {
    $statement = database()->prepare(
        'UPDATE benutzerkonten
         SET vorname = :vorname,
             nachname = :nachname,
             telefon = :telefon,
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $statement->execute([
        'vorname' => $firstName,
        'nachname' => $lastName,
        'telefon' => $phone,
        'id' => $userId,
    ]);
}

function updateUserPassword(
    int $userId,
    string $passwordHash
): void {
    $statement = database()->prepare(
        'UPDATE benutzerkonten
         SET passwort_hash = :passwort_hash,
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $statement->execute([
        'passwort_hash' => $passwordHash,
        'id' => $userId,
    ]);
}