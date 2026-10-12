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
                <li>
                    <?= escape(
                        $job['karrierestufe'] ?: 'Nicht angegeben'
                    ) ?>
                </li>
                <li>
                    Eintritt: <?= escape(formatJobStartDate(
                        $job['eintrittsdatum']
                    )) ?>
                </li>
                <li>
                    Vergütung:
                    <?= escape(
                        $job['verguetung'] ?: 'Nach Vereinbarung'
                    ) ?>
                </li>
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
                <a
                    class="button"
                    href="bewerbung.php?stelle_id=<?= (int) $job['id'] ?>"
                >
                    Jetzt bewerben
                </a>
                <p class="hint">
                    Für die Bewerbung ist ein Benutzerkonto erforderlich.
                </p>
                <hr>
                <p><strong>Ansprechperson</strong><br><?= escape($job['ansprechperson_name']) ?><br><?= escape($job['ansprechperson_email']) ?></p>
            </aside>
        </div>
    <?php endif; ?>
</main>