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

function statusLabel(string $status): string
{
    $labels = [
        'eingegangen' => 'Eingegangen',
        'vorauswahl' => 'Vorauswahl',
        'interview' => 'Interview',
        'angebot' => 'Angebot',
        'abgelehnt' => 'Abgelehnt',
        'zurueckgezogen' => 'Zurückgezogen',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function formatDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '–';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? '–'
        : date('d.m.Y', $timestamp);
}

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$jobs = [];
$applications = [];
$databaseError = false;
$jobCreated = filter_input(INPUT_GET, 'saved') === 'created';
$jobUpdated = filter_input(INPUT_GET, 'saved') === 'updated';

try {
    $jobStatement = database()->query(
        "SELECT
            id,
            titel,
            kennziffer,
            arbeitsort,
            status
         FROM stellen
         ORDER BY
            CASE status
                WHEN 'aktiv' THEN 0
                ELSE 1
            END,
            titel"
    );

    $jobs = $jobStatement->fetchAll();

    $applicationStatement = database()->query(
        "SELECT
            bewerbungen.id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.titel,
            benutzerkonten.vorname,
            benutzerkonten.nachname
         FROM bewerbungen
         INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
         INNER JOIN benutzerkonten
            ON benutzerkonten.id =
                bewerbungen.benutzerkonto_id
         WHERE bewerbungen.status <> 'entwurf'
         ORDER BY
            bewerbungen.eingereicht_am DESC,
            bewerbungen.id DESC"
    );

    $applications = $applicationStatement->fetchAll();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Recruitingübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Recruitingübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

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
        <?php if ($jobCreated): ?>
            <div class="notice notice--success" role="status">
                <strong>Die neue Stelle wurde angelegt.</strong>
            </div>
        <?php elseif ($jobUpdated): ?>
            <div class="notice notice--success" role="status">
                <strong>Die Änderungen wurden gespeichert.</strong>
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
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section
                class="recruiting-section"
                aria-labelledby="applications-heading"
            >
                <div class="recruiting-section__header">
                    <h2 id="applications-heading">Bewerbungen</h2>
                </div>

                <?php if ($applications === []): ?>
                    <p class="empty-state">
                        Es liegen noch keine eingereichten Bewerbungen vor.
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
                                <?php foreach ($applications as $application): ?>
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
                                                Details anzeigen
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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

<?php require __DIR__ . '/includes/footer.php'; ?>