<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$job = null;
$databaseError = false;

if ($jobId !== false && $jobId !== null) {
    try {
        $statement = database()->prepare(
            "SELECT * FROM stellen WHERE id = :id AND status = 'aktiv'"
        );
        $statement->execute(['id' => $jobId]);
        $job = $statement->fetch();
    } catch (Throwable $exception) {
        error_log(
            'Fehler beim Laden der Stellendetails: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if (!$databaseError && ($job === false || $job === null)) {
    http_response_code(404);
}

if ($databaseError) {
    $pageTitle = 'Technischer Fehler | FiktivFit Karriere';
} elseif ($job) {
    $pageTitle = $job['titel'] . ' | FiktivFit Karriere';
} else {
    $pageTitle = 'Stelle nicht gefunden | FiktivFit Karriere';
}

$headerLinkHref = $job
    ? 'anmelden.php?from=job&id=' . (int) $job['id']
    : 'anmelden.php?from=overview';

require __DIR__ . '/includes/header.php';

?>

<main class="job-detail">
    <?php if ($databaseError): ?>
        <section class="notice notice--error" role="alert">
            <h1>Stellendetails konnten nicht geladen werden</h1>
            <p>Bitte versuche es später erneut.</p>
            <a class="text-link" href="index.php">Zur Stellenübersicht</a>
        </section>
    <?php elseif (!$job): ?>
        <section class="notice notice--error">
            <h1>Stelle nicht gefunden</h1>
            <p>Die gewünschte Stelle ist nicht verfügbar.</p>
            <a class="text-link" href="index.php">Zur Stellenübersicht</a>
        </section>
    <?php else: ?>
        <a class="back-link" href="index.php">&larr; Zur Stellenübersicht</a>

        <header class="job-detail__header">
            <p class="eyebrow"><?= escape($job['kennziffer']) ?></p>
            <h1><?= escape($job['titel']) ?></h1>
            <ul class="job-card__facts" aria-label="Stellendaten">
                <li><?= escape($job['arbeitsort']) ?></li>
                <li><?= escape($job['beschaeftigungsgrad']) ?></li>
                <li><?= escape($job['befristung']) ?></li>
            </ul>
        </header>

        <div class="job-detail__layout">
            <article class="job-description">
                <section>
                    <h2>Wer wir sind</h2>
                    <p><?= escape($job['wer_wir_sind']) ?></p>
                </section>
                <section>
                    <h2>Das erwartet dich</h2>
                    <p><?= escape($job['das_erwartet_dich']) ?></p>
                </section>
                <section>
                    <h2>Deine Aufgaben</h2>
                    <p><?= escape($job['aufgaben']) ?></p>
                </section>
                <section>
                    <h2>Das bringst du mit</h2>
                    <p><?= escape($job['anforderungen']) ?></p>
                </section>
                <section>
                    <h2>Das bieten wir dir</h2>
                    <p><?= escape($job['leistungen']) ?></p>
                </section>
            </article>

            <aside class="application-card">
                <h2>Interesse geweckt?</h2>
                <p>Bewirb dich auf die Stelle <?= escape($job['titel']) ?>.</p>
                <span class="button button--disabled" aria-disabled="true">Jetzt bewerben</span>
                <p class="hint">Die Anmeldung und das Bewerbungsformular folgen im nächsten Entwicklungsschritt.</p>
                <hr>
                <p><strong>Ansprechperson</strong><br><?= escape($job['ansprechperson_name']) ?><br><?= escape($job['ansprechperson_email']) ?></p>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>