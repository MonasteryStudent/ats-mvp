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

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Diese Anfrage ist nicht erlaubt.');
}

$jobId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$job = null;
$error = null;
$hasApplications = false;

try {
    if ($jobId === false || $jobId === null || $jobId <= 0) {
        http_response_code(404);
        $error = 'Diese Stelle ist nicht verfügbar.';
    } else {
        $connection = database();

        $statement = $connection->prepare(
            'SELECT
                id,
                titel,
                kennziffer,
                EXISTS (
                    SELECT 1
                    FROM bewerbungen
                    WHERE stelle_id = stellen.id
                ) AS hat_bewerbungen
             FROM stellen
             WHERE id = :id'
        );

        $statement->execute(['id' => $jobId]);
        $job = $statement->fetch();

        if ($job === false) {
            http_response_code(404);
            $error = 'Diese Stelle ist nicht verfügbar.';
        } else {
            $hasApplications = (int) $job['hat_bewerbungen'] === 1;

            if ($requestMethod === 'POST') {
                if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                    http_response_code(403);
                    $error = (
                        'Die Anfrage ist abgelaufen. '
                        . 'Bitte lade die Seite neu und versuche es erneut.'
                    );
                } elseif (
                    ($_POST['loeschung_bestaetigt'] ?? null) !== '1'
                ) {
                    $error = 'Bitte bestätige die endgültige Löschung.';
                } elseif ($hasApplications) {
                    http_response_code(409);
                    $error = (
                        'Dieser Stelle sind Bewerbungen oder Entwürfe '
                        . 'zugeordnet. Sie kann nur deaktiviert werden.'
                    );
                } else {
                    $deleteStatement = $connection->prepare(
                        'DELETE FROM stellen
                         WHERE id = :id
                         AND NOT EXISTS (
                             SELECT 1
                             FROM bewerbungen
                             WHERE stelle_id = stellen.id
                         )'
                    );

                    $deleteStatement->execute(['id' => $jobId]);

                    if ($deleteStatement->rowCount() === 1) {
                        header(
                            'Location: recruiting.php?deleted=job',
                            true,
                            303
                        );
                        exit;
                    }

                    http_response_code(409);
                    $error = (
                        'Die Stelle konnte nicht gelöscht werden. '
                        . 'Sie wurde bereits entfernt oder besitzt '
                        . 'inzwischen eine Bewerbung oder einen Entwurf.'
                    );
                }
            }
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Löschen einer Stelle: '
        . $exception->getMessage()
    );

    http_response_code(500);
    $error = (
        'Die Stelle konnte aus technischen Gründen nicht gelöscht '
        . 'werden. Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Stelle löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Stelle löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        <?php elseif ($hasApplications): ?>
            <p>
                Die Stelle
                <strong><?= escape($job['titel']) ?></strong>
                kann nicht gelöscht werden, da Bewerbungen oder
                Bewerbungsentwürfe zugeordnet sind.
            </p>

            <p>
                Über die Bearbeitung kann die Stelle deaktiviert werden.
            </p>

            <a
                class="text-link"
                href="stelle-verwalten.php?id=<?= (int) $job['id'] ?>"
            >
                Stelle bearbeiten
            </a>
        <?php else: ?>
            <p>
                Möchtest du die Stelle
                <strong><?= escape($job['titel']) ?></strong>
                mit der Kennziffer
                <strong><?= escape($job['kennziffer']) ?></strong>
                endgültig löschen?
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
                    value="<?= (int) $job['id'] ?>"
                >

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="loeschung_bestaetigt"
                        value="1"
                        required
                    >

                    <span>
                        Ich bestätige die endgültige Löschung dieser Stelle.
                    </span>
                </label>

                <button class="button" type="submit">
                    Endgültig löschen
                </button>

                <a class="text-link" href="recruiting.php">
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>