<?php

declare(strict_types=1);

require_once __DIR__ . '/stellen.php';
require_once dirname(__DIR__) . '/config/auth.php';

function emptyJobForm(): array
{
    return [
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
}

function processJobForm(
    array $input,
    ?int $jobId = null
): array {
    $form = emptyJobForm();
    $errors = [];

    foreach (array_keys($form) as $fieldName) {
        $form[$fieldName] = trim(
            (string) ($input[$fieldName] ?? '')
        );
    }

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
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

    if (!in_array(
        $form['beschaeftigungsgrad'],
        ['Vollzeit', 'Teilzeit', 'Minijob'],
        true
    )) {
        $errors[] = 'Bitte wähle einen Beschäftigungsgrad aus.';
    }

    if (!in_array(
        $form['befristung'],
        ['Unbefristet', 'Befristet'],
        true
    )) {
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
            || $date->format('Y-m-d') !== $form['eintrittsdatum']
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

    if (
        $errors === []
        && jobReferenceExists($form['kennziffer'], $jobId)
    ) {
        $errors[] = (
            'Die angegebene Kennziffer wird bereits verwendet.'
        );
    }

    if ($errors !== []) {
        return [
            'form' => $form,
            'errors' => $errors,
            'saved' => false,
        ];
    }

    $parameters = $form;

    foreach (
        ['eintrittsdatum', 'karrierestufe', 'verguetung']
        as $fieldName
    ) {
        if ($parameters[$fieldName] === '') {
            $parameters[$fieldName] = null;
        }
    }

    saveJob($parameters, $jobId);

    return [
        'form' => $form,
        'errors' => [],
        'saved' => true,
    ];
}