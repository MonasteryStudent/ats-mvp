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