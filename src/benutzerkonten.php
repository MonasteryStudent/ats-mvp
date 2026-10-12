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