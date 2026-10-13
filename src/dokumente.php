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

function findStoredFilenamesForApplication(
    PDO $connection,
    int $applicationId
): array {
    $statement = $connection->prepare(
        'SELECT speicherdateiname
         FROM dokumente
         WHERE bewerbung_id = :id'
    );

    $statement->execute([
        'id' => $applicationId,
    ]);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function findOwnDraftDocument(
    PDO $connection,
    int $documentId,
    int $userId,
    int $jobId
): ?array {
    $statement = $connection->prepare(
        'SELECT
            d.id,
            d.bewerbung_id,
            d.speicherdateiname
         FROM dokumente AS d
         INNER JOIN bewerbungen AS b
             ON b.id = d.bewerbung_id
         WHERE d.id = :dokument_id
           AND b.benutzerkonto_id = :benutzerkonto_id
           AND b.stelle_id = :stelle_id
           AND b.status = :status
         LIMIT 1'
    );

    $statement->execute([
        'dokument_id' => $documentId,
        'benutzerkonto_id' => $userId,
        'stelle_id' => $jobId,
        'status' => 'entwurf',
    ]);

    $document = $statement->fetch();

    return $document === false ? null : $document;
}

function deleteApplicationDocumentRecord(
    PDO $connection,
    int $documentId,
    int $applicationId
): void {
    $statement = $connection->prepare(
        'DELETE FROM dokumente
         WHERE id = :dokument_id
           AND bewerbung_id = :bewerbung_id'
    );

    $statement->execute([
        'dokument_id' => $documentId,
        'bewerbung_id' => $applicationId,
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'Das Dokument konnte nicht gelöscht werden.'
        );
    }
}

function findStoredFilenamesForUser(
    PDO $connection,
    int $userId
): array {
    $statement = $connection->prepare(
        'SELECT d.speicherdateiname
         FROM dokumente AS d
         INNER JOIN bewerbungen AS b
             ON b.id = d.bewerbung_id
         WHERE b.benutzerkonto_id = :benutzerkonto_id'
    );

    $statement->execute([
        'benutzerkonto_id' => $userId,
    ]);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function findDocumentsForRecruiting(
    int $applicationId
): array {
    $statement = database()->prepare(
        "SELECT
            id,
            dokumenttyp,
            originaldateiname
         FROM dokumente
         WHERE bewerbung_id = :bewerbung_id
         ORDER BY
            CASE dokumenttyp
                WHEN 'lebenslauf' THEN 1
                WHEN 'anschreiben' THEN 2
                WHEN 'zeugnis' THEN 3
                WHEN 'anlage' THEN 4
                ELSE 5
            END"
    );

    $statement->execute([
        'bewerbung_id' => $applicationId,
    ]);

    return $statement->fetchAll();
}