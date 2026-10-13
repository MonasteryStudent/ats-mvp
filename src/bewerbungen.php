<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function findApplicationsForUser(int $userId): array
{
    $statement = database()->prepare(
        'SELECT
            bewerbungen.id,
            bewerbungen.stelle_id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.kennziffer,
            stellen.titel,
            stellen.arbeitsort
         FROM bewerbungen
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         WHERE bewerbungen.benutzerkonto_id = :benutzerkonto_id
         ORDER BY bewerbungen.aktualisiert_am DESC'
    );

    $statement->execute([
        'benutzerkonto_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function findApplicationForUserAndJob(
    int $userId,
    int $jobId
): ?array {
    $statement = database()->prepare(
        'SELECT
            id,
            status,
            fruehestmoegliches_eintrittsdatum,
            nachricht
         FROM bewerbungen
         WHERE benutzerkonto_id = :benutzerkonto_id
           AND stelle_id = :stelle_id
         LIMIT 1'
    );

    $statement->execute([
        'benutzerkonto_id' => $userId,
        'stelle_id' => $jobId,
    ]);

    $application = $statement->fetch();

    return $application === false ? null : $application;
}

function saveApplicationDraft(
    PDO $connection,
    ?int $applicationId,
    int $userId,
    int $jobId,
    string $earliestStartDate,
    string $message
): int {
    if ($applicationId === null) {
        $statement = $connection->prepare(
            'INSERT INTO bewerbungen (
                benutzerkonto_id,
                stelle_id,
                fruehestmoegliches_eintrittsdatum,
                nachricht
            ) VALUES (
                :benutzerkonto_id,
                :stelle_id,
                :eintrittsdatum,
                :nachricht
            )'
        );

        $statement->execute([
            'benutzerkonto_id' => $userId,
            'stelle_id' => $jobId,
            'eintrittsdatum' => $earliestStartDate === ''
                ? null
                : $earliestStartDate,
            'nachricht' => $message === '' ? null : $message,
        ]);

        return (int) $connection->lastInsertId();
    }

    $statement = $connection->prepare(
        'UPDATE bewerbungen
         SET fruehestmoegliches_eintrittsdatum = :eintrittsdatum,
             nachricht = :nachricht,
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
           AND benutzerkonto_id = :benutzerkonto_id
           AND status = :status'
    );

    $statement->execute([
        'eintrittsdatum' => $earliestStartDate === ''
            ? null
            : $earliestStartDate,
        'nachricht' => $message === '' ? null : $message,
        'id' => $applicationId,
        'benutzerkonto_id' => $userId,
        'status' => 'entwurf',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Der Bewerbungsentwurf konnte nicht gespeichert werden.'
        );
    }

    return $applicationId;
}

function submitApplication(
    PDO $connection,
    int $applicationId,
    int $userId
): void {
    $statement = $connection->prepare(
        'UPDATE bewerbungen
         SET status = :neuer_status,
             eingereicht_am = CURRENT_TIMESTAMP,
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
           AND benutzerkonto_id = :benutzerkonto_id
           AND status = :alter_status'
    );

    $statement->execute([
        'neuer_status' => 'eingegangen',
        'id' => $applicationId,
        'benutzerkonto_id' => $userId,
        'alter_status' => 'entwurf',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Die Bewerbung konnte nicht eingereicht werden.'
        );
    }
}

function findWithdrawableApplication(
    int $applicationId,
    int $userId
): ?array {
    $statement = database()->prepare(
        "SELECT bewerbungen.id, stellen.titel
         FROM bewerbungen
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         WHERE bewerbungen.id = :id
           AND bewerbungen.benutzerkonto_id = :benutzerkonto_id
           AND bewerbungen.status IN (
               'eingegangen',
               'vorauswahl',
               'interview',
               'angebot'
           )"
    );

    $statement->execute([
        'id' => $applicationId,
        'benutzerkonto_id' => $userId,
    ]);

    $application = $statement->fetch();

    return $application === false ? null : $application;
}

function withdrawApplication(
    int $applicationId,
    int $userId
): bool {
    $statement = database()->prepare(
        "UPDATE bewerbungen
         SET status = 'zurueckgezogen',
             aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
           AND benutzerkonto_id = :benutzerkonto_id
           AND status IN (
               'eingegangen',
               'vorauswahl',
               'interview',
               'angebot'
           )"
    );

    $statement->execute([
        'id' => $applicationId,
        'benutzerkonto_id' => $userId,
    ]);

    return $statement->rowCount() === 1;
}

function findOwnDraft(
    PDO $connection,
    int $draftId,
    int $userId
): ?array {
    $statement = $connection->prepare(
        'SELECT b.id, s.titel
         FROM bewerbungen AS b
         INNER JOIN stellen AS s ON s.id = b.stelle_id
         WHERE b.id = :id
           AND b.benutzerkonto_id = :benutzerkonto_id
           AND b.status = :status'
    );

    $statement->execute([
        'id' => $draftId,
        'benutzerkonto_id' => $userId,
        'status' => 'entwurf',
    ]);

    $draft = $statement->fetch();

    return $draft === false ? null : $draft;
}

function deleteOwnDraft(
    PDO $connection,
    int $draftId,
    int $userId
): void {
    $statement = $connection->prepare(
        'DELETE FROM bewerbungen
         WHERE id = :id
           AND benutzerkonto_id = :benutzerkonto_id
           AND status = :status'
    );

    $statement->execute([
        'id' => $draftId,
        'benutzerkonto_id' => $userId,
        'status' => 'entwurf',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Der Entwurf konnte nicht gelöscht werden.'
        );
    }
}

function userHasSubmittedApplications(
    PDO $connection,
    int $userId
): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM bewerbungen
         WHERE benutzerkonto_id = :benutzerkonto_id
           AND status <> :status'
    );

    $statement->execute([
        'benutzerkonto_id' => $userId,
        'status' => 'entwurf',
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function findSubmittedApplicationsForRecruiting(): array
{
    $statement = database()->query(
        "SELECT
            bewerbungen.id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.titel,
            benutzerkonten.vorname,
            benutzerkonten.nachname
         FROM bewerbungen
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         INNER JOIN benutzerkonten
            ON benutzerkonten.id =
                bewerbungen.benutzerkonto_id
         WHERE bewerbungen.status <> 'entwurf'
         ORDER BY
            bewerbungen.eingereicht_am DESC,
            bewerbungen.id DESC"
    );

    return $statement->fetchAll();
}

function findSubmittedApplicationById(
    int $applicationId
): ?array {
    $statement = database()->prepare(
        "SELECT
            bewerbungen.id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            bewerbungen.fruehestmoegliches_eintrittsdatum,
            bewerbungen.nachricht,
            bewerbungen.bewertung,
            bewerbungen.recruitingnotiz,
            benutzerkonten.vorname,
            benutzerkonten.nachname,
            benutzerkonten.email,
            benutzerkonten.telefon,
            stellen.titel,
            stellen.kennziffer
         FROM bewerbungen
         INNER JOIN benutzerkonten
            ON benutzerkonten.id =
                bewerbungen.benutzerkonto_id
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         WHERE bewerbungen.id = :id
           AND bewerbungen.status <> 'entwurf'
         LIMIT 1"
    );

    $statement->execute([
        'id' => $applicationId,
    ]);

    $application = $statement->fetch();

    return $application === false ? null : $application;
}

function updateApplicationReview(
    int $applicationId,
    string $status,
    ?int $rating,
    ?string $note
): void {
    $statement = database()->prepare(
        "UPDATE bewerbungen
         SET
            status = CASE
                WHEN status = 'zurueckgezogen' THEN status
                ELSE :status
            END,
            bewertung = :bewertung,
            recruitingnotiz = :recruitingnotiz,
            aktualisiert_am = CURRENT_TIMESTAMP
         WHERE id = :id
           AND status <> 'entwurf'"
    );

    $statement->execute([
        'status' => $status,
        'bewertung' => $rating,
        'recruitingnotiz' => $note,
        'id' => $applicationId,
    ]);
}

function findWithdrawnApplication(
    PDO $connection,
    int $applicationId
): ?array {
    $statement = $connection->prepare(
        "SELECT
            bewerbungen.id,
            stellen.titel,
            benutzerkonten.vorname,
            benutzerkonten.nachname
         FROM bewerbungen
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         INNER JOIN benutzerkonten
            ON benutzerkonten.id = bewerbungen.benutzerkonto_id
         WHERE bewerbungen.id = :id
           AND bewerbungen.status = 'zurueckgezogen'"
    );

    $statement->execute([
        'id' => $applicationId,
    ]);

    $application = $statement->fetch();

    return $application === false ? null : $application;
}

function deleteWithdrawnApplication(
    PDO $connection,
    int $applicationId
): void {
    $statement = $connection->prepare(
        "DELETE FROM bewerbungen
         WHERE id = :id
           AND status = 'zurueckgezogen'"
    );

    $statement->execute([
        'id' => $applicationId,
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Die Bewerbung konnte nicht gelöscht werden.'
        );
    }
}

function findApplicationsForPendingDeletionRequests(): array
{
    $statement = database()->prepare(
        'SELECT
            bewerbungen.id,
            bewerbungen.benutzerkonto_id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.titel,
            stellen.kennziffer
         FROM bewerbungen
         INNER JOIN stellen
             ON stellen.id = bewerbungen.stelle_id
         INNER JOIN benutzerkonten
             ON benutzerkonten.id = bewerbungen.benutzerkonto_id
         WHERE benutzerkonten.rolle = :rolle
           AND benutzerkonten.ist_aktiv = 0
           AND benutzerkonten.loeschung_angefordert_am IS NOT NULL
           AND bewerbungen.status <> :entwurfsstatus
         ORDER BY bewerbungen.eingereicht_am, bewerbungen.id'
    );

    $statement->execute([
        'rolle' => 'bewerbend',
        'entwurfsstatus' => 'entwurf',
    ]);

    return $statement->fetchAll();
}