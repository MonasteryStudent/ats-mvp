<?php

declare(strict_types=1);

require_once __DIR__ . '/bewerbungen.php';
require_once dirname(__DIR__) . '/config/session.php';

function processApplicationWithdrawal(
    int $applicationId,
    int $userId,
    array $input
): array {
    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        return [
            'withdrawn' => false,
            'statusCode' => 403,
            'error' => (
                'Die Anfrage ist abgelaufen. '
                . 'Bitte lade die Seite neu und versuche es erneut.'
            ),
        ];
    }

    if (!withdrawApplication($applicationId, $userId)) {
        return [
            'withdrawn' => false,
            'statusCode' => 409,
            'error' => (
                'Der Bewerbungsstatus wurde inzwischen geändert. '
                . 'Bitte kehre zu deinem Konto zurück.'
            ),
        ];
    }

    return [
        'withdrawn' => true,
        'statusCode' => 200,
        'error' => null,
    ];
}