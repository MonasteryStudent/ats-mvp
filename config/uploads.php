<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const MAX_PDF_UPLOAD_SIZE = 5 * 1024 * 1024;

final class UploadValidationException extends RuntimeException
{
}

function uploadedFiles(string $fieldName): array
{
    $upload = $_FILES[$fieldName] ?? null;

    if (!is_array($upload)) {
        return [];
    }

    foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
        if (!array_key_exists($key, $upload)) {
            return [];
        }
    }

    if (!is_array($upload['name'])) {
        return (int) $upload['error'] === UPLOAD_ERR_NO_FILE
            ? []
            : [$upload];
    }

    $files = [];

    foreach (array_keys($upload['name']) as $index) {
        $error = (int) ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $upload['name'][$index] ?? '',
            'tmp_name' => $upload['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $upload['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function storeUploadedPdf(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        if (
            $error === UPLOAD_ERR_INI_SIZE
            || $error === UPLOAD_ERR_FORM_SIZE
        ) {
            throw new UploadValidationException(
                'Die PDF-Datei überschreitet die zulässige Größe.'
            );
        }

        throw new UploadValidationException(
            'Die PDF-Datei konnte nicht vollständig hochgeladen werden.'
        );
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $fileSize = (int) ($file['size'] ?? 0);

    if (
        $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new UploadValidationException(
            'Die hochgeladene Datei ist ungültig.'
        );
    }

    if ($fileSize <= 0) {
        throw new UploadValidationException(
            'Die hochgeladene Datei ist leer.'
        );
    }

    if ($fileSize > MAX_PDF_UPLOAD_SIZE) {
        throw new UploadValidationException(
            'PDF-Dateien dürfen höchstens 5 MB groß sein.'
        );
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);

    if ($mimeType !== 'application/pdf') {
        throw new UploadValidationException(
            'Es sind ausschließlich PDF-Dateien zulässig.'
        );
    }

    $handle = fopen($temporaryPath, 'rb');
    $signature = $handle === false
        ? false
        : fread($handle, 5);

    if ($handle !== false) {
        fclose($handle);
    }

    if ($signature !== '%PDF-') {
        throw new UploadValidationException(
            'Die ausgewählte Datei ist keine gültige PDF-Datei.'
        );
    }

    $originalFilename = basename(
        str_replace(
            '\\',
            '/',
            (string) ($file['name'] ?? '')
        )
    );

    $originalFilename = preg_replace(
        '/[\x00-\x1F\x7F]/u',
        '',
        $originalFilename
    );

    if (
        !is_string($originalFilename)
        || $originalFilename === ''
    ) {
        $originalFilename = 'dokument.pdf';
    }

    if (strlen($originalFilename) > 255) {
        throw new UploadValidationException(
            'Der Dateiname darf höchstens 255 Zeichen enthalten.'
        );
    }

    if (
        !is_dir(UPLOAD_PATH)
        && !mkdir(UPLOAD_PATH, 0775, true)
        && !is_dir(UPLOAD_PATH)
    ) {
        throw new RuntimeException(
            'Das Uploadverzeichnis konnte nicht angelegt werden.'
        );
    }

    $storedFilename = bin2hex(random_bytes(16)) . '.pdf';
    $destination = UPLOAD_PATH . '/' . $storedFilename;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException(
            'Die PDF-Datei konnte nicht gespeichert werden.'
        );
    }

    return [
        'originaldateiname' => $originalFilename,
        'speicherdateiname' => $storedFilename,
        'mime_typ' => 'application/pdf',
        'dateigroesse' => $fileSize,
    ];
}