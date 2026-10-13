<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/stellen.php';

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

$form = [
    'titel' => '',
    'kennziffer' => '',
    'arbeitsort' => '',
    'eintrittsdatum' => '',
    'karrierestufe' => '',
    'beschaeftigungsgrad' => '',
    'befristung' => '',
    'verguetung' => '',
    'wer_wir_sind' => '',
    'das_erwartet_dich' => '',
    'aufgaben' => '',
    'anforderungen' => '',
    'leistungen' => '',
    'ansprechperson_name' => '',
    'ansprechperson_email' => '',
    'status' => 'aktiv',
];

try {
    if ($isEditMode) {
        $job = findJobById($jobId);

        if ($job === null) {
            $jobNotFound = true;
            http_response_code(404);
        } else {
            foreach (array_keys($form) as $fieldName) {
                $form[$fieldName] = (string) (
                    $job[$fieldName] ?? ''
                );
            }
        }
    }

    if ($requestMethod === 'POST' && !$jobNotFound) {
        foreach (array_keys($form) as $fieldName) {
            $form[$fieldName] = trim(
                (string) ($_POST[$fieldName] ?? '')
            );
        }

        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = (
                'Die Anfrage ist abgelaufen. '
                . 'Bitte lade die Seite neu und versuche es erneut.'
            );
        }

        $requiredFields = [
            'titel' => 'Stellenbezeichnung',
            'kennziffer' => 'Kennziffer',
            'arbeitsort' => 'Arbeitsort',
            'beschaeftigungsgrad' => 'Beschäftigungsgrad',
            'befristung' => 'Befristung',
            'wer_wir_sind' => 'Wer wir sind',
            'das_erwartet_dich' => 'Das erwartet dich',
            'aufgaben' => 'Deine Aufgaben',
            'anforderungen' => 'Das bringst du mit',
            'leistungen' => 'Das bieten wir dir',
            'ansprechperson_name' => 'Ansprechperson',
            'ansprechperson_email' => 'E-Mail-Adresse',
            'status' => 'Stellenstatus',
        ];

        foreach ($requiredFields as $fieldName => $label) {
            if ($form[$fieldName] === '') {
                $errors[] = $label . ' ist erforderlich.';
            }
        }

        if (
            $form['ansprechperson_email'] !== ''
            && filter_var(
                $form['ansprechperson_email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $errors[] = (
                'Bitte gib eine gültige E-Mail-Adresse '
                . 'für die Ansprechperson ein.'
            );
        }

        $allowedEmploymentTypes = [
            'Vollzeit',
            'Teilzeit',
            'Minijob',
        ];

        if (
            !in_array(
                $form['beschaeftigungsgrad'],
                $allowedEmploymentTypes,
                true
            )
        ) {
            $errors[] = 'Bitte wähle einen Beschäftigungsgrad aus.';
        }

        $allowedDurations = [
            'Unbefristet',
            'Befristet',
        ];

        if (
            !in_array(
                $form['befristung'],
                $allowedDurations,
                true
            )
        ) {
            $errors[] = 'Bitte wähle eine Befristung aus.';
        }

        if (!in_array($form['status'], ['aktiv', 'inaktiv'], true)) {
            $errors[] = 'Der ausgewählte Stellenstatus ist ungültig.';
        }

        if ($form['eintrittsdatum'] !== '') {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $form['eintrittsdatum']
            );

            if (
                $date === false
                || $date->format('Y-m-d')
                    !== $form['eintrittsdatum']
            ) {
                $errors[] = 'Das Eintrittsdatum ist ungültig.';
            }
        }

        $maximumLengths = [
            'titel' => 200,
            'kennziffer' => 50,
            'arbeitsort' => 150,
            'karrierestufe' => 100,
            'beschaeftigungsgrad' => 50,
            'befristung' => 50,
            'verguetung' => 150,
            'ansprechperson_name' => 200,
            'ansprechperson_email' => 254,
        ];

        foreach ($maximumLengths as $fieldName => $maximumLength) {
            if (strlen($form[$fieldName]) > $maximumLength) {
                $errors[] = (
                    $requiredFields[$fieldName]
                    ?? ucfirst($fieldName)
                ) . ' ist zu lang.';
            }
        }

        if ($errors === []) {
            if (jobReferenceExists(
                $form['kennziffer'],
                $isEditMode ? $jobId : null
            )) {
                $errors[] = (
                    'Die angegebene Kennziffer wird bereits verwendet.'
                );
            }
        }

        if ($errors === []) {
            $parameters = [
                'titel' => $form['titel'],
                'kennziffer' => $form['kennziffer'],
                'arbeitsort' => $form['arbeitsort'],
                'eintrittsdatum' =>
                    $form['eintrittsdatum'] === ''
                        ? null
                        : $form['eintrittsdatum'],
                'karrierestufe' =>
                    $form['karrierestufe'] === ''
                        ? null
                        : $form['karrierestufe'],
                'beschaeftigungsgrad' =>
                    $form['beschaeftigungsgrad'],
                'befristung' => $form['befristung'],
                'verguetung' =>
                    $form['verguetung'] === ''
                        ? null
                        : $form['verguetung'],
                'wer_wir_sind' => $form['wer_wir_sind'],
                'das_erwartet_dich' =>
                    $form['das_erwartet_dich'],
                'aufgaben' => $form['aufgaben'],
                'anforderungen' => $form['anforderungen'],
                'leistungen' => $form['leistungen'],
                'ansprechperson_name' =>
                    $form['ansprechperson_name'],
                'ansprechperson_email' =>
                    $form['ansprechperson_email'],
                'status' => $form['status'],
            ];

            if ($isEditMode) {
                $parameters['id'] = $jobId;

                $saveStatement = database()->prepare(
                    'UPDATE stellen
                     SET
                        titel = :titel,
                        kennziffer = :kennziffer,
                        arbeitsort = :arbeitsort,
                        eintrittsdatum = :eintrittsdatum,
                        karrierestufe = :karrierestufe,
                        beschaeftigungsgrad =
                            :beschaeftigungsgrad,
                        befristung = :befristung,
                        verguetung = :verguetung,
                        wer_wir_sind = :wer_wir_sind,
                        das_erwartet_dich =
                            :das_erwartet_dich,
                        aufgaben = :aufgaben,
                        anforderungen = :anforderungen,
                        leistungen = :leistungen,
                        ansprechperson_name =
                            :ansprechperson_name,
                        ansprechperson_email =
                            :ansprechperson_email,
                        status = :status,
                        aktualisiert_am = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );

                $saveStatement->execute($parameters);

                header(
                    'Location: recruiting.php?saved=updated'
                );
                exit;
            }

            $saveStatement = database()->prepare(
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

            $saveStatement->execute($parameters);

            header('Location: recruiting.php?saved=created');
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

if ($jobNotFound) {
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