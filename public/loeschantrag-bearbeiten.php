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

$accountId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$account = null;
$errors = [];
$databaseError = false;

try {
    $connection = database();

    if ($requestMethod === 'POST') {
        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = (
                'Die Anfrage konnte nicht überprüft werden. '
                . 'Bitte lade die Seite neu.'
            );

            http_response_code(403);
        }

        if (($_POST['aufbewahrung_geprueft'] ?? '') !== '1') {
            $errors[] = (
                'Bitte bestätige die Prüfung des Aufbewahrungsbedarfs.'
            );
        }

        if ($errors === []) {
            $connection->beginTransaction();
        }
    }

    if (
        $accountId !== false
        && $accountId !== null
        && $accountId > 0
    ) {
        $accountStatement = $connection->prepare(
            'SELECT
                id,
                vorname,
                nachname,
                email
             FROM benutzerkonten
             WHERE id = :id
               AND rolle = :rolle
               AND ist_aktiv = 0
               AND loeschung_angefordert_am IS NOT NULL'
        );

        $accountStatement->execute([
            'id' => $accountId,
            'rolle' => 'bewerbend',
        ]);

        $account = $accountStatement->fetch();
    }

    if (!$account) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if ($errors === []) {
            http_response_code(404);
        }
    } elseif (
        $requestMethod === 'POST'
        && $errors === []
    ) {
        $documentStatement = $connection->prepare(
            'SELECT dokumente.speicherdateiname
             FROM dokumente
             INNER JOIN bewerbungen
                 ON bewerbungen.id = dokumente.bewerbung_id
             WHERE bewerbungen.benutzerkonto_id =
                 :benutzerkonto_id'
        );

        $documentStatement->execute([
            'benutzerkonto_id' => $accountId,
        ]);

        $storedFilenames = $documentStatement->fetchAll(
            PDO::FETCH_COLUMN
        );

        $deleteStatement = $connection->prepare(
            'DELETE FROM benutzerkonten
             WHERE id = :id
               AND rolle = :rolle
               AND ist_aktiv = 0
               AND loeschung_angefordert_am IS NOT NULL'
        );

        $deleteStatement->execute([
            'id' => $accountId,
            'rolle' => 'bewerbend',
        ]);

        if ($deleteStatement->rowCount() !== 1) {
            throw new RuntimeException(
                'Das Benutzerkonto konnte nicht gelöscht werden.'
            );
        }

        $connection->commit();

        $fileCleanupFailed = false;

        foreach ($storedFilenames as $storedFilename) {
            if (
                $storedFilename === ''
                || basename($storedFilename) !== $storedFilename
            ) {
                error_log(
                    'Ungültiger Speicherdateiname bei geprüfter Kontolöschung: '
                    . $storedFilename
                );

                $fileCleanupFailed = true;
                continue;
            }

            $path = UPLOAD_PATH . '/' . $storedFilename;

            if (is_file($path) && !unlink($path)) {
                error_log(
                    'Datei konnte bei geprüfter Kontolöschung nicht entfernt werden: '
                    . $path
                );

                $fileCleanupFailed = true;
            }
        }

        $destination = $fileCleanupFailed
            ? 'loeschantraege.php?cleanup=files'
            : 'loeschantraege.php?deleted=account';

        header('Location: ' . $destination, true, 303);
        exit;
    }
} catch (Throwable $exception) {
    if (
        isset($connection)
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Fehler bei der Bearbeitung eines Kontolöschantrags: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Löschantrag bearbeiten | FiktivFit Karriere';
$headerLinkLabel = 'Zur Übersicht der Löschanträge';
$headerLinkHref = 'loeschantraege.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section
        class="auth-card auth-card--confirmation"
        aria-labelledby="deletion-heading"
    >
        <p class="eyebrow">Recruitingbereich</p>
        <h1 id="deletion-heading">Kontolöschung bestätigen</h1>

        <?php if ($databaseError): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Der Löschantrag konnte nicht verarbeitet werden.
                </strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php elseif (!$account): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Es wurde kein offener Löschantrag gefunden.
                </strong>
            </div>
        <?php else: ?>
            <p>
                Das folgende Bewerbendenkonto soll endgültig
                gelöscht werden:
            </p>

            <p>
                <strong>
                    <?= escape(
                        $account['vorname']
                        . ' '
                        . $account['nachname']
                    ) ?>
                </strong>
                <br>
                <?= escape($account['email']) ?>
            </p>

            <p>
                Die Löschung umfasst das Konto, sämtliche Bewerbungen
                und Entwürfe, Bewertungen, Recruitingnotizen sowie
                alle zugehörigen Unterlagen. Sie kann nicht
                rückgängig gemacht werden.
            </p>

            <?php if ($errors !== []): ?>
                <div
                    class="notice notice--error form-errors"
                    role="alert"
                >
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $account['id'] ?>"
                >

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="aufbewahrung_geprueft"
                        value="1"
                        required
                    >
                    <span>
                        Ich habe sämtliche zugehörigen Bewerbungen
                        geprüft. Es besteht kein weiterer
                        Aufbewahrungsgrund, insbesondere kein
                        konkreter Rechtsstreit.
                    </span>
                </label>

                <button class="button" type="submit">
                    Konto und zugehörige Daten endgültig löschen
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-card__footer">
            <a class="text-link" href="loeschantraege.php">
                Zurück zur Übersicht der Löschanträge
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>