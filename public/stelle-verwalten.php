<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen-verarbeitung.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$jobIdProvided = array_key_exists('id', $_GET);

$isEditMode = (
    $jobIdProvided
    && $jobId !== false
    && $jobId !== null
    && $jobId > 0
);

$jobNotFound = $jobIdProvided && !$isEditMode;
$databaseError = false;
$errors = [];
$form = emptyJobForm();

try {
    if ($isEditMode) {
        $job = findJobById($jobId);

        if ($job === null) {
            $jobNotFound = true;
        } else {
            foreach (array_keys($form) as $fieldName) {
                $form[$fieldName] = (string) (
                    $job[$fieldName] ?? ''
                );
            }
        }
    }

    if ($requestMethod === 'POST' && !$jobNotFound) {
        $processingResult = processJobForm(
            $_POST,
            $isEditMode ? $jobId : null
        );

        $form = $processingResult['form'];
        $errors = $processingResult['errors'];

        if ($processingResult['saved']) {
            header(
                'Location: recruiting.php?saved='
                . ($isEditMode ? 'updated' : 'created'),
                true,
                303
            );
            exit;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler bei der Stellenverwaltung: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

if ($jobNotFound && !$databaseError) {
    http_response_code(404);
}

$pageTitle = $isEditMode
    ? 'Stelle bearbeiten | FiktivFit Karriere'
    : 'Neue Stelle anlegen | FiktivFit Karriere';

$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/stelle-verwalten.php';
require __DIR__ . '/includes/footer.php';