<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/recruitingkonto-verarbeitung.php';

requireRole('admin');

header('Cache-Control: no-store, no-cache, must-revalidate');

$recruitingAccounts = [];
$databaseError = false;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
];

$creationErrors = [];

$statusResult = filter_input(INPUT_GET, 'status');

$accountCreated = filter_input(INPUT_GET, 'created') === '1';
$accountActivated = $statusResult === 'activated';
$accountDeactivated = $statusResult === 'deactivated';

if ($requestMethod === 'POST') {
    $processingResult = processRecruitingAccountCreation(
        $_POST
    );

    $formData = $processingResult['formData'];
    $creationErrors = $processingResult['errors'];

    if ($processingResult['technicalError']) {
        http_response_code(500);
    }

    if ($processingResult['created']) {
        header(
            'Location: admin.php?created=1',
            true,
            303
        );
        exit;
    }
}

try {
    $recruitingAccounts = findRecruitingAccounts();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Adminübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Adminübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/admin.php';
require __DIR__ . '/includes/footer.php';