<?php

declare(strict_types=1);

require_once __DIR__ . '/bewerbungen.php';
require_once dirname(__DIR__) . '/config/auth.php';

function recruitingStatusOptions(): array
{
    return [
        'eingegangen' => 'Eingegangen',
        'vorauswahl' => 'Vorauswahl',
        'interview' => 'Interview',
        'angebot' => 'Angebot',
        'abgelehnt' => 'Abgelehnt',
    ];
}

function recruitingRatingOptions(): array
{
    return [
        '1' => '1 – sehr gut',
        '2' => '2 – gut',
        '3' => '3 – befriedigend',
        '4' => '4 – ausreichend',
        '5' => '5 – unzureichend',
    ];
}

function processApplicationReview(
    array $application,
    array $input
): array {
    $selectedStatus = trim(
        (string) ($input['status'] ?? '')
    );

    $selectedRating = trim(
        (string) ($input['bewertung'] ?? '')
    );

    $recruitingNote = trim(
        (string) ($input['recruitingnotiz'] ?? '')
    );

    $errors = [];
    $rating = null;

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if (
        $application['status'] !== 'zurueckgezogen'
        && !array_key_exists(
            $selectedStatus,
            recruitingStatusOptions()
        )
    ) {
        $errors[] = (
            'Bitte wähle einen gültigen Bewerbungsstatus aus.'
        );
    }

    if (
        $application['status'] === 'zurueckgezogen'
        && $selectedStatus !== 'zurueckgezogen'
    ) {
        $errors[] = (
            'Eine zurückgezogene Bewerbung kann nicht '
            . 'erneut in das Auswahlverfahren aufgenommen werden.'
        );
    }

    if ($selectedRating !== '') {
        $validatedRating = filter_var(
            $selectedRating,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 5,
                ],
            ]
        );

        if ($validatedRating === false) {
            $errors[] = (
                'Die Bewertung muss zwischen 1 und 5 liegen.'
            );
        } else {
            $rating = (int) $validatedRating;
        }
    }

    if (strlen($recruitingNote) > 5000) {
        $errors[] = (
            'Die interne Notiz darf höchstens '
            . '5000 Zeichen enthalten.'
        );
    }

    if ($errors === []) {
        updateApplicationReview(
            (int) $application['id'],
            $selectedStatus,
            $rating,
            $recruitingNote === '' ? null : $recruitingNote
        );
    }

    return [
        'selectedStatus' => $selectedStatus,
        'selectedRating' => $selectedRating,
        'recruitingNote' => $recruitingNote,
        'errors' => $errors,
        'saved' => $errors === [],
    ];
}