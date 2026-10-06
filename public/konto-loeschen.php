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

$userId = authenticatedUserId();
$user = null;
$errors = [];
$databaseError = false;
$hasSubmittedApplications = false;
$storedFilenames = [];

try {
    $connection = database();

    if ($requestMethod === 'POST') {
        $connection->beginTransaction();
    }

    $userStatement = $connection->prepare(
        'SELECT id, passwort_hash
         FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 1'
    );

    $userStatement->execute([
        'id' => $userId,
        'rolle' => 'bewerbend',
    ]);

    $user = $userStatement->fetch();

    if ($user === false) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        signOutUser();

        header('Location: anmelden.php?from=overview', true, 303);
        exit;
    }

    $applicationStatement = $connection->prepare(
        'SELECT COUNT(*)
         FROM bewerbungen
         WHERE benutzerkonto_id = :benutzerkonto_id
           AND status <> :status'
    );

    $applicationStatement->execute([
        'benutzerkonto_id' => $userId,
        'status' => 'entwurf',
    ]);

    $hasSubmittedApplications = (
        (int) $applicationStatement->fetchColumn() > 0
    );

    $deletionMode = $hasSubmittedApplications
        ? 'request'
        : 'delete';

    if ($requestMethod === 'POST') {
        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = (
                'Die Anfrage konnte nicht überprüft werden. '
                . 'Bitte lade die Seite neu.'
            );

            http_response_code(403);
        }

        $password = (string) ($_POST['passwort'] ?? '');

        if (!password_verify(
            $password,
            $user['passwort_hash']
        )) {
            $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
        }

        if (($_POST['deletion_mode'] ?? '') !== $deletionMode) {
            $errors[] = (
                'Der Bewerbungsstand hat sich geändert. '
                . 'Bitte prüfe den Hinweis und bestätige erneut.'
            );

            http_response_code(409);
        }

        if ($errors === []) {
            if ($hasSubmittedApplications) {
                $updateStatement = $connection->prepare(
                    'UPDATE benutzerkonten
                     SET loeschung_angefordert_am = CURRENT_TIMESTAMP,
                         ist_aktiv = 0,
                         aktualisiert_am = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND rolle = :rolle
                       AND ist_aktiv = 1'
                );

                $updateStatement->execute([
                    'id' => $userId,
                    'rolle' => 'bewerbend',
                ]);

                if ($updateStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Der Löschantrag konnte nicht gespeichert werden.'
                    );
                }

                $result = 'requested';
            } else {
                $documentStatement = $connection->prepare(
                    'SELECT dokumente.speicherdateiname
                     FROM dokumente
                     INNER JOIN bewerbungen
                         ON bewerbungen.id = dokumente.bewerbung_id
                     WHERE bewerbungen.benutzerkonto_id =
                         :benutzerkonto_id'
                );

                $documentStatement->execute([
                    'benutzerkonto_id' => $userId,
                ]);

                $storedFilenames = $documentStatement->fetchAll(
                    PDO::FETCH_COLUMN
                );

                $deleteStatement = $connection->prepare(
                    'DELETE FROM benutzerkonten
                     WHERE id = :id
                       AND rolle = :rolle
                       AND ist_aktiv = 1
                       AND NOT EXISTS (
                           SELECT 1
                           FROM bewerbungen
                           WHERE benutzerkonto_id = :bewerbungskonto_id
                             AND status <> :entwurfsstatus
                       )'
                );

                $deleteStatement->execute([
                    'id' => $userId,
                    'rolle' => 'bewerbend',
                    'bewerbungskonto_id' => $userId,
                    'entwurfsstatus' => 'entwurf',
                ]);

                if ($deleteStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Das Benutzerkonto konnte nicht gelöscht werden.'
                    );
                }

                $result = 'deleted';
            }

            $connection->commit();
            signOutUser();

            foreach ($storedFilenames as $storedFilename) {
                if (
                    $storedFilename === ''
                    || basename($storedFilename) !== $storedFilename
                ) {
                    error_log(
                        'Ungültiger Speicherdateiname bei Kontolöschung: '
                        . $storedFilename
                    );

                    $result = 'cleanup_pending';
                    continue;
                }

                $path = UPLOAD_PATH . '/' . $storedFilename;

                if (is_file($path) && !unlink($path)) {
                    error_log(
                        'Datei konnte bei Kontolöschung nicht entfernt werden: '
                        . $path
                    );

                    $result = 'cleanup_pending';
                }
            }

            header(
                'Location: anmelden.php?from=overview&deletion='
                . $result,
                true,
                303
            );
            exit;
        }

        $connection->rollBack();
    }
} catch (Throwable $exception) {
    if (
        isset($connection)
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Fehler bei der Kontolöschung: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Kontolöschung | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section
        class="auth-card auth-card--confirmation"
        aria-labelledby="deletion-heading"
    >
        <p class="eyebrow">Kontoeinstellungen</p>
        <h1 id="deletion-heading">Kontolöschung</h1>

        <?php if ($databaseError): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Die Kontolöschung konnte nicht verarbeitet werden.
                </strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php else: ?>
            <?php if ($hasSubmittedApplications): ?>
                <p>
                    Es sind bereits eingereichte Bewerbungen vorhanden.
                    Nach deiner Bestätigung wird dein Kontozugang
                    gesperrt und du wirst abgemeldet.
                </p>
                <p>
                    Das Recruiting prüft anschließend deinen Löschantrag
                    und einen möglichen weiteren Aufbewahrungsbedarf
                    deiner Bewerbungsdaten.
                </p>
            <?php else: ?>
                <p>
                    Dein Konto sowie vorhandene Bewerbungsentwürfe
                    und ihre Unterlagen werden nach deiner Bestätigung
                    endgültig gelöscht. Dieser Vorgang kann nicht
                    rückgängig gemacht werden.
                </p>
            <?php endif; ?>

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
                    name="deletion_mode"
                    value="<?= escape($deletionMode) ?>"
                >

                <div class="form-field">
                    <label for="deletion-password">
                        Aktuelles Passwort zur Bestätigung
                    </label>
                    <input
                        type="password"
                        id="deletion-password"
                        name="passwort"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="button" type="submit">
                    <?= $hasSubmittedApplications
                        ? 'Kontolöschung anfordern'
                        : 'Konto endgültig löschen' ?>
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-card__footer">
            <a class="text-link" href="konto.php">
                Abbrechen und zurück zum Konto
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>