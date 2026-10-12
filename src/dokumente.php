<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function findDocumentsForApplication(int $applicationId): array
{
    $statement = database()->prepare(
        'SELECT
            id,
            dokumenttyp,
            originaldateiname,
            speicherdateiname,
            dateigroesse
         FROM dokumente
         WHERE bewerbung_id = :bewerbung_id
         ORDER BY hochgeladen_am, id'
    );

    $statement->execute([
        'bewerbung_id' => $applicationId,
    ]);

    return $statement->fetchAll();
}