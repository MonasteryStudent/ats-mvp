<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/registrierung.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSession();

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$loginHref = 'anmelden.php?from=overview';

if (
    in_array($from, ['job', 'application'], true)
    && $returnJobId !== false
    && $returnJobId !== null
    && $returnJobId > 0
) {
    $loginHref = (
        'anmelden.php?from='
        . $from
        . '&id='
        . $returnJobId
    );
}

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
    'email_bestaetigung' => '',
];

$conditionsAccepted = false;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = processRegistration($_POST);

    $formData = $result['formData'];
    $conditionsAccepted = $result['conditionsAccepted'];
    $errors = $result['errors'];

    if ($result['technicalError']) {
        http_response_code(500);
    }

    if ($result['registered']) {
        header(
            'Location: '
            . $loginHref
            . '&registered=1'
        );
        exit;
    }
}

$pageTitle = 'Registrieren | FiktivFit Karriere';
$headerLinkLabel = 'Zur Anmeldung';
$headerLinkHref = $loginHref;

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/registrieren.php';
require __DIR__ . '/includes/footer.php';