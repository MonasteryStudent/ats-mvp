<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/src/recruitingkonto-verarbeitung.php';

requireRole('admin');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'POST') {
    header('Allow: POST');
    http_response_code(405);

    exit('Diese Aktion ist nur über POST erlaubt.');
}

try {
    $processingResult = processRecruitingAccountStatusChange(
        $_POST
    );
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Ändern des Recruitingkontostatus: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Der Kontostatus konnte nicht geändert werden.');
}

if (!$processingResult['changed']) {
    http_response_code($processingResult['statusCode']);
    exit($processingResult['error']);
}

header(
    'Location: admin.php?status=' . $processingResult['result'],
    true,
    303
);

exit;