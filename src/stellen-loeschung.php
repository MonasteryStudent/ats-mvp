<?php

declare(strict_types=1);

require_once __DIR__ . '/stellen.php';
require_once dirname(__DIR__) . '/config/auth.php';

function processJobDeletion(
    int $jobId,
    array $input
): array {
    $job = findJobForDeletion($jobId);

    if ($job === null) {
        return [
            'deleted' => false,
            'statusCode' => 404,
            'error' => 'Diese Stelle ist nicht verfügbar.',
        ];
    }

    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        return [
            'deleted' => false,
            'statusCode' => 403,
            'error' => (
                'Die Anfrage ist abgelaufen. '
                . 'Bitte lade die Seite neu und versuche es erneut.'
            ),
        ];
    }

    if (($input['loeschung_bestaetigt'] ?? null) !== '1') {
        return [
            'deleted' => false,
            'statusCode' => 200,
            'error' => 'Bitte bestätige die endgültige Löschung.',
        ];
    }

    if ((int) $job['hat_bewerbungen'] === 1) {
        return [
            'deleted' => false,
            'statusCode' => 409,
            'error' => (
                'Dieser Stelle sind Bewerbungen oder Entwürfe '
                . 'zugeordnet. Sie kann nur deaktiviert werden.'
            ),
        ];
    }

    if (!deleteJobWithoutApplications($jobId)) {
        return [
            'deleted' => false,
            'statusCode' => 409,
            'error' => (
                'Die Stelle konnte nicht gelöscht werden. '
                . 'Sie wurde bereits entfernt oder besitzt '
                . 'inzwischen eine Bewerbung oder einen Entwurf.'
            ),
        ];
    }

    return [
        'deleted' => true,
        'statusCode' => 200,
        'error' => null,
    ];
}