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

function findActiveApplicantById(int $userId): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            email,
            vorname,
            nachname,
            telefon
         FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 1'
    );

    $statement->execute([
        'id' => $userId,
        'rolle' => 'bewerbend',
    ]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function requestApplicantAccountDeletion(
    PDO $connection,
    int $userId
): void {
    $statement = $connection->prepare(
        'UPDATE benutzerkonten
         SET loeschung_angefordert_am = CURRENT_TIMESTAMP,
             ist_aktiv = 0,
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 1'
    );

    $statement->execute([
        'id' => $userId,
        'rolle' => 'bewerbend',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Der Löschantrag konnte nicht gespeichert werden.'
        );
    }
}

function deleteApplicantAccountWithoutSubmittedApplications(
    PDO $connection,
    int $userId
): void {
    $statement = $connection->prepare(
        'DELETE FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 1
           AND NOT EXISTS (
               SELECT 1
               FROM bewerbungen
               WHERE benutzerkonto_id = :bewerbungskonto_id
                 AND status <> :entwurfsstatus
           )'
    );

    $statement->execute([
        'id' => $userId,
        'rolle' => 'bewerbend',
        'bewerbungskonto_id' => $userId,
        'entwurfsstatus' => 'entwurf',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Das Benutzerkonto konnte nicht gelöscht werden.'
        );
    }
}

function findPendingAccountDeletionRequests(): array
{
    $statement = database()->prepare(
        'SELECT
            id,
            vorname,
            nachname,
            email,
            loeschung_angefordert_am
         FROM benutzerkonten
         WHERE rolle = :rolle
           AND ist_aktiv = 0
           AND loeschung_angefordert_am IS NOT NULL
         ORDER BY loeschung_angefordert_am, id'
    );

    $statement->execute([
        'rolle' => 'bewerbend',
    ]);

    return $statement->fetchAll();
}

function findPendingAccountDeletionRequest(
    PDO $connection,
    int $accountId
): ?array {
    $statement = $connection->prepare(
        'SELECT
            id,
            vorname,
            nachname,
            email
         FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 0
           AND loeschung_angefordert_am IS NOT NULL'
    );

    $statement->execute([
        'id' => $accountId,
        'rolle' => 'bewerbend',
    ]);

    $account = $statement->fetch();

    return $account === false ? null : $account;
}

function deleteAccountWithPendingDeletionRequest(
    PDO $connection,
    int $accountId
): void {
    $statement = $connection->prepare(
        'DELETE FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 0
           AND loeschung_angefordert_am IS NOT NULL'
    );

    $statement->execute([
        'id' => $accountId,
        'rolle' => 'bewerbend',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Das Benutzerkonto konnte nicht gelöscht werden.'
        );
    }
}

function findRecruitingAccounts(): array
{
    $statement = database()->prepare(
        'SELECT
            id,
            vorname,
            nachname,
            email,
            ist_aktiv
         FROM benutzerkonten
         WHERE rolle = :rolle
         ORDER BY nachname, vorname'
    );

    $statement->execute([
        'rolle' => 'recruiting',
    ]);

    return $statement->fetchAll();
}

function createRecruitingAccount(
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
        'email' => $email,
        'passwort_hash' => $passwordHash,
        'vorname' => $firstName,
        'nachname' => $lastName,
        'rolle' => 'recruiting',
        'ist_aktiv' => 1,
    ]);
}