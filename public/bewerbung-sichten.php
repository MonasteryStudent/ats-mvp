<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/src/bewerbungen.php';
require_once dirname(__DIR__) . '/src/dokumente.php';

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errors = [];

$changesSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === '1'
);

$statusOptions = [
    'eingegangen' => 'Eingegangen',
    'vorauswahl' => 'Vorauswahl',
    'interview' => 'Interview',
    'angebot' => 'Angebot',
    'abgelehnt' => 'Abgelehnt',
];

$ratingOptions = [
    '1' => '1 – sehr gut',
    '2' => '2 – gut',
    '3' => '3 – befriedigend',
    '4' => '4 – ausreichend',
    '5' => '5 – unzureichend',
];

$selectedStatus = '';
$selectedRating = '';
$recruitingNote = '';

$applicationId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$documents = [];
$databaseError = false;

if (
    $applicationId !== false
    && $applicationId !== null
    && $applicationId > 0
) {
    try {
        $application = findSubmittedApplicationById(
            $applicationId
        );

        if ($application) {
            $selectedStatus = (string) $application['status'];

            $selectedRating = $application['bewertung'] === null
                ? ''
                : (string) $application['bewertung'];

            $recruitingNote = (string) (
                $application['recruitingnotiz'] ?? ''
            );

            if ($requestMethod === 'POST') {
                $selectedStatus = trim(
                    (string) ($_POST['status'] ?? '')
                );

                $selectedRating = trim(
                    (string) ($_POST['bewertung'] ?? '')
                );

                $recruitingNote = trim(
                    (string) ($_POST['recruitingnotiz'] ?? '')
                );

                if (
                    !isValidCsrfToken(
                        $_POST['csrf_token'] ?? null
                    )
                ) {
                    $errors[] = (
                        'Die Anfrage ist abgelaufen. '
                        . 'Bitte lade die Seite neu und '
                        . 'versuche es erneut.'
                    );
                }

                if (
                    $application['status'] !== 'zurueckgezogen'
                    && !array_key_exists($selectedStatus, $statusOptions)
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

                $rating = null;

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
                            'Die Bewertung muss zwischen '
                            . '1 und 5 liegen.'
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
                    $updateStatement = database()->prepare(
                        "UPDATE bewerbungen
                         SET
                            status = CASE
                                WHEN status = 'zurueckgezogen' THEN status
                                ELSE :status
                            END,
                            bewertung = :bewertung,
                            recruitingnotiz =
                                :recruitingnotiz,
                            aktualisiert_am =
                                CURRENT_TIMESTAMP
                         WHERE id = :id
                         AND status <> 'entwurf'"
                    );

                    $updateStatement->execute([
                        'status' => $selectedStatus,
                        'bewertung' => $rating,
                        'recruitingnotiz' =>
                            $recruitingNote === ''
                                ? null
                                : $recruitingNote,
                        'id' => $applicationId,
                    ]);

                    header(
                        'Location: bewerbung-sichten.php?id='
                        . $applicationId
                        . '&saved=1'
                    );
                    exit;
                }
            }

            $documents = findDocumentsForRecruiting(
                $applicationId
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler bei der Verarbeitung '
            . 'der Bewerbungsdetails: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if (!$databaseError && !$application) {
    http_response_code(404);
}

if ($databaseError) {
    $pageTitle = (
        'Technischer Fehler | FiktivFit Karriere'
    );
} elseif ($application) {
    $pageTitle = (
        'Bewerbungsdetails | FiktivFit Karriere'
    );
} else {
    $pageTitle = (
        'Bewerbung nicht gefunden | FiktivFit Karriere'
    );
}

$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

if (filter_input(INPUT_GET, 'from') === 'deletion_requests') {
    $headerLinkLabel = 'Zur Übersicht der Löschanträge';
    $headerLinkHref = 'loeschantraege.php';
}

require __DIR__ . '/includes/header.php';
require dirname(__DIR__) . '/templates/bewerbung-sichten.php';
require __DIR__ . '/includes/footer.php';