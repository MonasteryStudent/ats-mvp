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

function findJobById(int $jobId): ?array
{
    $statement = database()->prepare(
        'SELECT *
         FROM stellen
         WHERE id = :id'
    );

    $statement->execute([
        'id' => $jobId,
    ]);

    $job = $statement->fetch();

    return $job === false ? null : $job;
}

function jobReferenceExists(
    string $reference,
    ?int $excludedJobId = null
): bool {
    $statement = database()->prepare(
        'SELECT id
         FROM stellen
         WHERE kennziffer = :kennziffer
           AND (:id IS NULL OR id <> :id)
         LIMIT 1'
    );

    $statement->execute([
        'kennziffer' => $reference,
        'id' => $excludedJobId,
    ]);

    return $statement->fetch() !== false;
}

function saveJob(array $parameters, ?int $jobId = null): void
{
    if ($jobId !== null) {
        $parameters['id'] = $jobId;

        $statement = database()->prepare(
            'UPDATE stellen
             SET
                titel = :titel,
                kennziffer = :kennziffer,
                arbeitsort = :arbeitsort,
                eintrittsdatum = :eintrittsdatum,
                karrierestufe = :karrierestufe,
                beschaeftigungsgrad = :beschaeftigungsgrad,
                befristung = :befristung,
                verguetung = :verguetung,
                wer_wir_sind = :wer_wir_sind,
                das_erwartet_dich = :das_erwartet_dich,
                aufgaben = :aufgaben,
                anforderungen = :anforderungen,
                leistungen = :leistungen,
                ansprechperson_name = :ansprechperson_name,
                ansprechperson_email = :ansprechperson_email,
                status = :status,
                aktualisiert_am = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute($parameters);
        return;
    }

    $statement = database()->prepare(
        'INSERT INTO stellen (
            titel,
            kennziffer,
            arbeitsort,
            eintrittsdatum,
            karrierestufe,
            beschaeftigungsgrad,
            befristung,
            verguetung,
            wer_wir_sind,
            das_erwartet_dich,
            aufgaben,
            anforderungen,
            leistungen,
            ansprechperson_name,
            ansprechperson_email,
            status
         ) VALUES (
            :titel,
            :kennziffer,
            :arbeitsort,
            :eintrittsdatum,
            :karrierestufe,
            :beschaeftigungsgrad,
            :befristung,
            :verguetung,
            :wer_wir_sind,
            :das_erwartet_dich,
            :aufgaben,
            :anforderungen,
            :leistungen,
            :ansprechperson_name,
            :ansprechperson_email,
            :status
         )'
    );

    $statement->execute($parameters);
}

function findJobForDeletion(int $jobId): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            titel,
            kennziffer,
            EXISTS (
                SELECT 1
                FROM bewerbungen
                WHERE stelle_id = stellen.id
            ) AS hat_bewerbungen
         FROM stellen
         WHERE id = :id'
    );

    $statement->execute([
        'id' => $jobId,
    ]);

    $job = $statement->fetch();

    return $job === false ? null : $job;
}

function deleteJobWithoutApplications(int $jobId): bool
{
    $statement = database()->prepare(
        'DELETE FROM stellen
         WHERE id = :id
           AND NOT EXISTS (
               SELECT 1
               FROM bewerbungen
               WHERE stelle_id = stellen.id
           )'
    );

    $statement->execute([
        'id' => $jobId,
    ]);

    return $statement->rowCount() === 1;
}