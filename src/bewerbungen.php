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