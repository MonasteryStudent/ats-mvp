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

function findActiveJobById(int $jobId): ?array
{
    $statement = database()->prepare(
        "SELECT *
         FROM stellen
         WHERE id = :id AND status = 'aktiv'"
    );

    $statement->execute([
        'id' => $jobId,
    ]);

    $job = $statement->fetch();

    return $job === false ? null : $job;
}

function findJobsForRecruiting(): array
{
    $statement = database()->query(
        "SELECT
            id,
            titel,
            kennziffer,
            arbeitsort,
            status
         FROM stellen
         ORDER BY
            CASE status
                WHEN 'aktiv' THEN 0
                ELSE 1
            END,
            titel"
    );

    return $statement->fetchAll();
}