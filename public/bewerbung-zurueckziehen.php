<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

requireRole('bewerbend');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Diese Anfrage ist nicht erlaubt.');
}

$applicationId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$error = null;

try {
    $connection = database();

    $accountStatement = $connection->prepare(
        "SELECT id
         FROM benutzerkonten
         WHERE id = :id
         AND rolle = 'bewerbend'
         AND ist_aktiv = 1"
    );

    $accountStatement->execute([
        'id' => authenticatedUserId(),
    ]);

    if ($accountStatement->fetch() === false) {
        signOutUser();
        header('Location: anmelden.php?from=overview');
        exit;
    }

    if (
        $applicationId === false
        || $applicationId === null
        || $applicationId <= 0
    ) {
        http_response_code(404);
        $error = 'Diese Bewerbung kann nicht zurückgezogen werden.';
    } else {
        $statement = $connection->prepare(
            "SELECT bewerbungen.id, stellen.titel
             FROM bewerbungen
             INNER JOIN stellen
                ON stellen.id = bewerbungen.stelle_id
             WHERE bewerbungen.id = :id
             AND bewerbungen.benutzerkonto_id = :benutzerkonto_id
             AND bewerbungen.status IN (
                'eingegangen',
                'vorauswahl',
                'interview',
                'angebot'
             )"
        );

        $statement->execute([
            'id' => $applicationId,
            'benutzerkonto_id' => authenticatedUserId(),
        ]);

        $application = $statement->fetch();

        if ($application === false) {
            http_response_code(404);
            $error = (
                'Diese Bewerbung ist nicht verfügbar '
                . 'oder kann nicht mehr zurückgezogen werden.'
            );
        } elseif ($requestMethod === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                $error = (
                    'Die Anfrage ist abgelaufen. '
                    . 'Bitte lade die Seite neu und versuche es erneut.'
                );
            } else {
                $updateStatement = $connection->prepare(
                    "UPDATE bewerbungen
                     SET
                        status = 'zurueckgezogen',
                        aktualisiert_am = CURRENT_TIMESTAMP
                     WHERE id = :id
                     AND benutzerkonto_id = :benutzerkonto_id
                     AND status IN (
                        'eingegangen',
                        'vorauswahl',
                        'interview',
                        'angebot'
                     )"
                );

                $updateStatement->execute([
                    'id' => $applicationId,
                    'benutzerkonto_id' => authenticatedUserId(),
                ]);

                if ($updateStatement->rowCount() === 1) {
                    header('Location: konto.php?withdrawn=1', true, 303);
                    exit;
                }

                http_response_code(409);
                $error = (
                    'Der Bewerbungsstatus wurde inzwischen geändert. '
                    . 'Bitte kehre zu deinem Konto zurück.'
                );
            }
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Zurückziehen einer Bewerbung: '
        . $exception->getMessage()
    );

    http_response_code(500);
    $error = (
        'Die Bewerbung konnte nicht zurückgezogen werden. '
        . 'Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Bewerbung zurückziehen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Bewerbung zurückziehen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="konto.php">
                Zurück zu Mein Konto
            </a>
        <?php else: ?>
            <p>
                Möchtest du deine Bewerbung für die Stelle
                <strong><?= escape($application['titel']) ?></strong>
                wirklich zurückziehen?
            </p>

            <p>
                Damit beendest du deine Teilnahme am Auswahlverfahren.
                Deine Unterlagen werden dadurch nicht sofort gelöscht.
            </p>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $application['id'] ?>"
                >

                <button class="button" type="submit">
                    Bewerbung zurückziehen
                </button>

                <a class="text-link" href="konto.php">
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>