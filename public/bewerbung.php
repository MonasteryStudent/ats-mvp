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

$jobId = filter_input(
    INPUT_GET,
    'stelle_id',
    FILTER_VALIDATE_INT
);

$job = null;
$databaseError = false;
$accessDenied = false;

if (
    $jobId === false
    || $jobId === null
    || $jobId <= 0
) {
    http_response_code(404);
} elseif (!userIsAuthenticated()) {
    header(
        'Location: anmelden.php?from=application&id=' . $jobId
    );
    exit;
} elseif (authenticatedUserRole() !== 'bewerbend') {
    $accessDenied = true;
    http_response_code(403);
} else {
    try {
        $statement = database()->prepare(
            "SELECT
                id,
                kennziffer,
                titel,
                arbeitsort,
                beschaeftigungsgrad,
                befristung
             FROM stellen
             WHERE id = :id
               AND status = 'aktiv'"
        );

        $statement->execute([
            'id' => $jobId,
        ]);

        $job = $statement->fetch();

        if ($job === false) {
            $job = null;
            http_response_code(404);
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler beim Laden des Bewerbungsformulars: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if ($databaseError) {
    $pageTitle = 'Technischer Fehler | FiktivFit Karriere';
} elseif ($accessDenied) {
    $pageTitle = 'Zugriff nicht erlaubt | FiktivFit Karriere';
} elseif ($job) {
    $pageTitle = 'Bewerbung | ' . $job['titel'];
} else {
    $pageTitle = 'Stelle nicht gefunden | FiktivFit Karriere';
}

$headerLinkLabel = 'Mein Konto';
$headerLinkHref = 'konto.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page">
    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <h1>Bewerbungsformular nicht verfügbar</h1>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php elseif ($accessDenied): ?>
        <div class="notice notice--error" role="alert">
            <h1>Zugriff nicht erlaubt</h1>
            <p>
                Bewerbungen können nur mit einem Bewerbendenkonto
                angelegt werden.
            </p>
        </div>
    <?php elseif (!$job): ?>
        <div class="notice notice--error">
            <h1>Stelle nicht gefunden</h1>
            <p>Die gewünschte Stelle ist nicht verfügbar.</p>
            <a class="text-link" href="index.php">
                Zur Stellenübersicht
            </a>
        </div>
    <?php else: ?>
        <a
            class="back-link"
            href="stelle.php?id=<?= (int) $job['id'] ?>"
        >
            &larr; Zur Stelle
        </a>

        <section
            class="account-intro"
            aria-labelledby="application-heading"
        >
            <p class="eyebrow">
                Bewerbung · <?= escape($job['kennziffer']) ?>
            </p>

            <h1 id="application-heading">
                Bewerbung auf <?= escape($job['titel']) ?>
            </h1>

            <ul class="job-card__facts" aria-label="Stellendaten">
                <li><?= escape($job['arbeitsort']) ?></li>
                <li><?= escape($job['beschaeftigungsgrad']) ?></li>
                <li><?= escape($job['befristung']) ?></li>
            </ul>

            <p>
                Im nächsten Schritt werden hier die Bewerbungsdaten
                und Unterlagen erfasst.
            </p>
        </section>

        <div class="notice">
            <strong>Der Einstieg in die Bewerbung funktioniert.</strong>
            <p>
                Das eigentliche Bewerbungsformular wird im
                nächsten Implementierungsschritt ergänzt.
            </p>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>