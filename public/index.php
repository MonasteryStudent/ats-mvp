<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$databaseError = false;

try {
    $statement = database()->query(
        "SELECT id, kennziffer, titel, arbeitsort, beschaeftigungsgrad, befristung, das_erwartet_dich
         FROM stellen
         WHERE status = 'aktiv'
         ORDER BY titel"
    );
    $jobs = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Stellenübersicht: '
        . $exception->getMessage()
    );

    $jobs = [];
    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Karriere bei FiktivFit';
$headerLinkHref = 'anmelden.php?from=overview';

require __DIR__ . '/includes/header.php';

?>

<main>
    <section class="hero">
        <p class="eyebrow">Karriere bei FiktivFit</p>
        <h1>Finde die Stelle, die zu dir passt.</h1>
        <p>Entdecke unsere aktuellen Stellenangebote an verschiedenen Standorten.</p>
    </section>

    <section class="jobs" aria-labelledby="jobs-heading">
        <div class="section-heading">
            <h2 id="jobs-heading">Offene Stellen</h2>
            <span><?= count($jobs) ?> Angebote</span>
        </div>

        <?php if ($databaseError): ?>
            <div class="notice notice--error" role="alert">
                <strong>Die Stellenangebote konnten nicht geladen werden.</strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php elseif ($jobs === []): ?>
            <p class="notice">Zurzeit sind keine Stellen ausgeschrieben.</p>
        <?php else: ?>
            <div class="job-grid">
                <?php foreach ($jobs as $job): ?>
                    <article class="job-card">
                        <p class="job-card__reference"><?= escape($job['kennziffer']) ?></p>
                        <h3><?= escape($job['titel']) ?></h3>
                        <ul class="job-card__facts" aria-label="Stellendaten">
                            <li><?= escape($job['arbeitsort']) ?></li>
                            <li><?= escape($job['beschaeftigungsgrad']) ?></li>
                            <li><?= escape($job['befristung']) ?></li>
                        </ul>
                        <p><?= escape($job['das_erwartet_dich']) ?></p>
                        <a class="text-link" href="stelle.php?id=<?= (int) $job['id'] ?>">
                            Stellendetails ansehen
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>