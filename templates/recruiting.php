<main class="account-page recruiting-page">
    <section
        class="account-intro"
        aria-labelledby="recruiting-heading"
    >
        <p class="eyebrow">Recruitingbereich</p>
        <h1 id="recruiting-heading">Recruitingübersicht</h1>

        <p>
            In diesem Bereich werden Stellen und eingegangene
            Bewerbungen verwaltet.
        </p>
    </section>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>
                Die Recruitingübersicht konnte nicht geladen werden.
            </strong>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php else: ?>
        <?php if ($applicationDeleted): ?>
            <div class="notice notice--success" role="status">
                <strong>
                    Die Bewerbung und ihre Unterlagen wurden gelöscht.
                </strong>
            </div>
        <?php endif; ?>
        
        <?php if ($jobCreated): ?>
            <div class="notice notice--success" role="status">
                <strong>Die neue Stelle wurde angelegt.</strong>
            </div>
        <?php elseif ($jobUpdated): ?>
            <div class="notice notice--success" role="status">
                <strong>Die Änderungen wurden gespeichert.</strong>
            </div>
        <?php elseif ($jobDeleted): ?>
            <div class="notice notice--success" role="status">
                <strong>Die Stelle wurde gelöscht.</strong>
            </div>
        <?php endif; ?>

        <div class="recruiting-sections">
            <section
                class="recruiting-section"
                aria-labelledby="jobs-heading"
            >
                <div class="recruiting-section__header">
                    <h2 id="jobs-heading">Stellen</h2>

                    <a
                        class="button button--fit"
                        href="stelle-verwalten.php"
                    >
                        Neue Stelle anlegen
                    </a>
                </div>

                <?php if ($jobs === []): ?>
                    <p class="empty-state">
                        Es wurden noch keine Stellen angelegt.
                    </p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th scope="col">Stellenbezeichnung</th>
                                    <th scope="col">Kennziffer</th>
                                    <th scope="col">Arbeitsort</th>
                                    <th scope="col">Stellenstatus</th>
                                    <th scope="col">Aktion</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <?= escape($job['titel']) ?>
                                        </td>
                                        <td>
                                            <?= escape($job['kennziffer']) ?>
                                        </td>
                                        <td>
                                            <?= escape($job['arbeitsort']) ?>
                                        </td>
                                        <td>
                                            <span
                                                class="status-label status-label--<?= escape($job['status']) ?>"
                                            >
                                                <?= $job['status'] === 'aktiv'
                                                    ? 'Aktiv'
                                                    : 'Inaktiv' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                class="text-link"
                                                href="stelle-verwalten.php?id=<?= (int) $job['id'] ?>"
                                            >
                                                Bearbeiten
                                            </a>

                                            <br>

                                            <a
                                                class="account-table__action account-table__action--danger"
                                                href="stelle-loeschen.php?id=<?= (int) $job['id'] ?>"
                                            >
                                                Löschen
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php foreach ($applicationGroups as $group): ?>
                <section
                    class="recruiting-section"
                    aria-labelledby="<?= escape($group['id']) ?>-heading"
                >
                    <?php if ($group['collapsible']): ?>
                        <details class="recruiting-archive">
                            <summary>
                                <h2 id="<?= escape($group['id']) ?>-heading">
                                    <?= escape($group['title']) ?>
                                    (<?= count($group['applications']) ?>)
                                </h2>
                            </summary>

                            <div class="recruiting-archive__body">
                    <?php else: ?>
                        <div class="recruiting-section__header">
                            <h2 id="<?= escape($group['id']) ?>-heading">
                                <?= escape($group['title']) ?>
                                (<?= count($group['applications']) ?>)
                            </h2>
                        </div>
                    <?php endif; ?>

                    <?php if ($group['applications'] === []): ?>
                        <p class="empty-state">
                            <?= escape($group['emptyMessage']) ?>
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stellenbezeichnung</th>
                                        <th scope="col">
                                            Name der bewerbenden Person
                                        </th>
                                        <th scope="col">Einreichungsdatum</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Aktion</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach (
                                        $group['applications'] as $application
                                    ): ?>
                                        <tr>
                                            <td>
                                                <?= escape($application['titel']) ?>
                                            </td>
                                            <td>
                                                <?= escape(
                                                    $application['vorname']
                                                    . ' '
                                                    . $application['nachname']
                                                ) ?>
                                            </td>
                                            <td>
                                                <?= escape(formatDate(
                                                    $application['eingereicht_am']
                                                )) ?>
                                            </td>
                                            <td>
                                                <?= escape(statusLabel(
                                                    $application['status']
                                                )) ?>
                                            </td>
                                            <td>
                                                <a
                                                    class="text-link"
                                                    href="bewerbung-sichten.php?id=<?= (int) $application['id'] ?>"
                                                >
                                                    Bearbeiten
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($group['collapsible']): ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <section class="recruiting-section">
                <h2>Kontolöschanträge</h2>
                <p>
                    Prüfe Löschanträge von Bewerbendenkonten
                    mit bereits eingereichten Bewerbungen.
                </p>
                <a
                    class="text-link"
                    href="loeschantraege.php"
                >
                    Löschanträge anzeigen
                </a>
            </section>
        </div>

        <form
            class="account-logout"
            action="abmelden.php"
            method="post"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(csrfToken()) ?>"
            >

            <button class="button" type="submit">
                Abmelden
            </button>
        </form>
    <?php endif; ?>
</main>