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

function replaceApplicationDocumentRecords(
    PDO $connection,
    int $applicationId,
    string $documentType
): array {
    $statement = $connection->prepare(
        'SELECT speicherdateiname
         FROM dokumente
         WHERE bewerbung_id = :bewerbung_id
           AND dokumenttyp = :dokumenttyp'
    );

    $statement->execute([
        'bewerbung_id' => $applicationId,
        'dokumenttyp' => $documentType,
    ]);

    $storedFilenames = $statement->fetchAll(PDO::FETCH_COLUMN);

    $deleteStatement = $connection->prepare(
        'DELETE FROM dokumente
         WHERE bewerbung_id = :bewerbung_id
           AND dokumenttyp = :dokumenttyp'
    );

    $deleteStatement->execute([
        'bewerbung_id' => $applicationId,
        'dokumenttyp' => $documentType,
    ]);

    return $storedFilenames;
}

function insertApplicationDocument(
    PDO $connection,
    int $applicationId,
    string $documentType,
    array $storedFile
): void {
    $statement = $connection->prepare(
        'INSERT INTO dokumente (
            bewerbung_id,
            dokumenttyp,
            originaldateiname,
            speicherdateiname,
            mime_typ,
            dateigroesse
        ) VALUES (
            :bewerbung_id,
            :dokumenttyp,
            :originaldateiname,
            :speicherdateiname,
            :mime_typ,
            :dateigroesse
        )'
    );

    $statement->execute([
        'bewerbung_id' => $applicationId,
        'dokumenttyp' => $documentType,
        'originaldateiname' => $storedFile['originaldateiname'],
        'speicherdateiname' => $storedFile['speicherdateiname'],
        'mime_typ' => $storedFile['mime_typ'],
        'dateigroesse' => $storedFile['dateigroesse'],
    ]);
}