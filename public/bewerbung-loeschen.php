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

$applicationId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$error = null;
$connection = null;

try {
    $connection = database();

    $accountStatement = $connection->prepare(
        "SELECT id
         FROM benutzerkonten
         WHERE id = :id
         AND rolle = 'recruiting'
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
        $error = 'Diese Bewerbung ist nicht verfügbar.';
    } else {
        if ($requestMethod === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                $error = (
                    'Die Anfrage ist abgelaufen. '
                    . 'Bitte lade die Seite neu und versuche es erneut.'
                );
            } elseif (
                ($_POST['aufbewahrung_geprueft'] ?? null) !== '1'
            ) {
                $error = (
                    'Bitte bestätige, dass kein weiterer '
                    . 'Aufbewahrungsgrund besteht.'
                );
            }

            if ($error === null) {
                $connection->beginTransaction();
            }
        }

        $statement = $connection->prepare(
            "SELECT
                bewerbungen.id,
                stellen.titel,
                benutzerkonten.vorname,
                benutzerkonten.nachname
             FROM bewerbungen
             INNER JOIN stellen
                ON stellen.id = bewerbungen.stelle_id
             INNER JOIN benutzerkonten
                ON benutzerkonten.id = bewerbungen.benutzerkonto_id
             WHERE bewerbungen.id = :id
             AND bewerbungen.status = 'zurueckgezogen'"
        );

        $statement->execute([
            'id' => $applicationId,
        ]);

        $application = $statement->fetch();

        if ($application === false) {
            http_response_code(404);
            $error = (
                'Diese Bewerbung ist nicht verfügbar '
                . 'oder wurde nicht zurückgezogen.'
            );
        } elseif ($requestMethod === 'POST' && $error === null) {
            $documentStatement = $connection->prepare(
                'SELECT speicherdateiname
                 FROM dokumente
                 WHERE bewerbung_id = :id'
            );

            $documentStatement->execute([
                'id' => $applicationId,
            ]);

            $storedFiles = $documentStatement->fetchAll(
                PDO::FETCH_COLUMN
            );

            $deleteStatement = $connection->prepare(
                "DELETE FROM bewerbungen
                 WHERE id = :id
                 AND status = 'zurueckgezogen'"
            );

            $deleteStatement->execute([
                'id' => $applicationId,
            ]);

            if ($deleteStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'Die Bewerbung konnte nicht gelöscht werden.'
                );
            }

            $connection->commit();

            $fileDeletionFailed = false;

            foreach ($storedFiles as $storedFilename) {
                $filename = (string) $storedFilename;

                if (basename($filename) !== $filename) {
                    error_log(
                        'Ungültiger Speicherdateiname bei Bewerbung '
                        . $applicationId
                        . ': '
                        . $filename
                    );

                    $fileDeletionFailed = true;
                    continue;
                }

                $path = UPLOAD_PATH . '/' . $filename;

                if (is_file($path) && !unlink($path)) {
                    error_log(
                        'Datei nach Bewerbungslöschung nicht entfernt: '
                        . $path
                    );

                    $fileDeletionFailed = true;
                }
            }

            if ($fileDeletionFailed) {
                http_response_code(500);
                $error = (
                    'Die Bewerbungsdaten wurden gelöscht. '
                    . 'Mindestens eine Datei konnte jedoch nicht '
                    . 'entfernt werden. Bitte informiere die '
                    . 'technische Administration.'
                );
            } else {
                header(
                    'Location: recruiting.php?deleted=application',
                    true,
                    303
                );
                exit;
            }
        }

        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
} catch (Throwable $exception) {
    if (
        $connection instanceof PDO
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Fehler beim Löschen einer Bewerbung: '
        . $exception->getMessage()
    );

    http_response_code(500);
    $error = (
        'Die Löschung konnte nicht vollständig durchgeführt werden. '
        . 'Bitte versuche es später erneut.'
    );
}

$pageTitle = 'Bewerbung löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Bewerbung löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        <?php else: ?>
            <p>
                Möchtest du die zurückgezogene Bewerbung von
                <strong><?= escape(
                    $application['vorname']
                    . ' '
                    . $application['nachname']
                ) ?></strong>
                für die Stelle
                <strong><?= escape($application['titel']) ?></strong>
                endgültig löschen?
            </p>

            <p>
                Dabei werden die Bewerbung, die interne Bewertung,
                die Recruitingnotiz und sämtliche zugehörigen
                Bewerbungsunterlagen entfernt.
                Das Benutzerkonto bleibt bestehen.
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

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="aufbewahrung_geprueft"
                        value="1"
                        required
                    >

                    <span>
                        Ich habe geprüft, dass kein weiterer
                        Aufbewahrungsgrund besteht, insbesondere
                        kein konkreter Rechtsstreit.
                    </span>
                </label>

                <button class="button" type="submit">
                    Endgültig löschen
                </button>

                <a
                    class="text-link"
                    href="bewerbung-sichten.php?id=<?= (int) $application['id'] ?>"
                >
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>