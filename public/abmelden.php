<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Diese Anfrage ist nicht zulässig.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Die Anfrage konnte nicht überprüft werden.');
}

signOutUser();

header('Location: index.php');
exit;