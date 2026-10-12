<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function findActiveJobs(): array
{
    $statement = database()->query(
        "SELECT
            id,
            kennziffer,
            titel,
            arbeitsort,
            beschaeftigungsgrad,
            befristung,
            das_erwartet_dich
         FROM stellen
         WHERE status = 'aktiv'
         ORDER BY titel"
    );

    return $statement->fetchAll();
}