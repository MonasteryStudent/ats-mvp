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

header('Cache-Control: no-store');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Diese Anfrage ist nicht erlaubt.');
}

$draftId = filter_input(
    $requestMethod === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$draft = null;
$error = null;
$connection = null;

try {
    $connection = database();

    // Der Aktivierungsstatus wird unabhängig von der Sitzung geprüft.
    $accountStatement = $connection->prepare(
        'SELECT id
         FROM benutzerkonten
         WHERE id = :id
           AND rolle = :rolle
           AND ist_aktiv = 1'
    );

    $accountStatement->execute([
        'id' => authenticatedUserId(),
        'rolle' => 'bewerbend',
    ]);

    if ($accountStatement->fetch() === false) {
        signOutUser();
        header('Location: anmelden.php?from=overview');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $error = 'Die Anfrage konnte nicht überprüft werden.';
        } else {
            $connection->beginTransaction();
        }
    }

    if ($error === null) {
        $statement = $connection->prepare(
            'SELECT b.id, s.titel
             FROM bewerbungen AS b
             INNER JOIN stellen AS s ON s.id = b.stelle_id
             WHERE b.id = :id
               AND b.benutzerkonto_id = :benutzerkonto_id
               AND b.status = :status'
        );

        $statement->execute([
            'id' => $draftId ?: 0,
            'benutzerkonto_id' => authenticatedUserId(),
            'status' => 'entwurf',
        ]);

        $draft = $statement->fetch();

        if ($draft === false) {
            $draft = null;
            http_response_code(404);
            $error = 'Der Bewerbungsentwurf ist nicht verfügbar.';
        } elseif ($requestMethod === 'POST') {
            $documentStatement = $connection->prepare(
                'SELECT speicherdateiname
                 FROM dokumente
                 WHERE bewerbung_id = :id'
            );

            $documentStatement->execute(['id' => $draft['id']]);
            $documents = $documentStatement->fetchAll();

            $deleteStatement = $connection->prepare(
                'DELETE FROM bewerbungen
                 WHERE id = :id
                   AND benutzerkonto_id = :benutzerkonto_id
                   AND status = :status'
            );

            $deleteStatement->execute([
                'id' => $draft['id'],
                'benutzerkonto_id' => authenticatedUserId(),
                'status' => 'entwurf',
            ]);

            if ($deleteStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'Der Entwurf konnte nicht gelöscht werden.'
                );
            }

            // Zugehörige Dokumentdatensätze werden durch
            // ON DELETE CASCADE entfernt.
            $connection->commit();

            $fileDeletionFailed = false;

            foreach ($documents as $document) {
                $filename = basename($document['speicherdateiname']);
                $path = UPLOAD_PATH . '/' . $filename;

                if (file_exists($path) && !unlink($path)) {
                    $fileDeletionFailed = true;

                    error_log(
                        'Datei eines gelöschten Entwurfs '
                        . 'konnte nicht entfernt werden: '
                        . $filename
                    );
                }
            }

            if (!$fileDeletionFailed) {
                header('Location: konto.php?deleted=draft');
                exit;
            }

            $draft = null;
            http_response_code(500);
            $error = (
                'Der Entwurf wurde aus dem Konto entfernt. '
                . 'Die zugehörigen Dateien konnten nicht vollständig '
                . 'gelöscht werden. Bitte kontaktiere die Personalverwaltung.'
            );
        }
    }

    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
} catch (Throwable $exception) {
    if (
        $connection instanceof PDO
        && $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Fehler beim Löschen eines Bewerbungsentwurfs: '
        . $exception->getMessage()
    );

    http_response_code(500);
    $error = 'Die Löschung konnte nicht abgeschlossen werden.';
}

$pageTitle = 'Entwurf löschen | FiktivFit Karriere';
$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Entwurf löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="konto.php">Zurück zu Mein Konto</a>
        <?php else: ?>
            <p>
                Möchtest du deinen Entwurf für
                <strong><?= escape($draft['titel']) ?></strong>
                einschließlich aller hochgeladenen Unterlagen löschen?
                Diese Aktion kann nicht rückgängig gemacht werden.
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
                    value="<?= (int) $draft['id'] ?>"
                >

                <button class="button" type="submit">
                    Entwurf endgültig löschen
                </button>

                <a class="text-link" href="konto.php">Abbrechen</a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>