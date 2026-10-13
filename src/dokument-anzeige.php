<?php

declare(strict_types=1);

require_once __DIR__ . '/dokumente.php';

function prepareRecruitingDocument(int $documentId): ?array
{
    $document = findSubmittedDocumentById($documentId);

    if ($document === null) {
        return null;
    }

    $storedFilename = basename(
        $document['speicherdateiname']
    );

    if ($storedFilename !== $document['speicherdateiname']) {
        return null;
    }

    $uploadDirectory = realpath(UPLOAD_PATH);

    $filePath = realpath(
        UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedFilename
    );

    if (
        $uploadDirectory === false
        || $filePath === false
        || dirname($filePath) !== $uploadDirectory
        || !is_file($filePath)
        || !is_readable($filePath)
    ) {
        return null;
    }

    $downloadFilename = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $document['originaldateiname']
    );

    if (
        $downloadFilename === null
        || $downloadFilename === ''
    ) {
        $downloadFilename = 'dokument.pdf';
    }

    return [
        'filePath' => $filePath,
        'downloadFilename' => $downloadFilename,
    ];
}