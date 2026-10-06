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
    return match ($status) {
        'eingegangen' => 'Eingegangen',
        'vorauswahl' => 'Vorauswahl',
        'interview' => 'Interview',
        'angebot' => 'Angebot',
        'abgelehnt' => 'Abgelehnt',
        'zurueckgezogen' => 'Zurückgezogen',
        default => $status,
    };
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

$deletionRequests = [];
$applicationsByAccount = [];
$databaseError = false;

$accountDeleted = (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && filter_input(INPUT_GET, 'deleted') === 'account'
);

$fileCleanupPending = (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && filter_input(INPUT_GET, 'cleanup') === 'files'
);

try {
    $requestStatement = database()->prepare(
        'SELECT
            id,
            vorname,
            nachname,
            email,
            loeschung_angefordert_am
         FROM benutzerkonten
         WHERE rolle = :rolle
           AND ist_aktiv = 0
           AND loeschung_angefordert_am IS NOT NULL
         ORDER BY loeschung_angefordert_am, id'
    );

    $requestStatement->execute([
        'rolle' => 'bewerbend',
    ]);

    $deletionRequests = $requestStatement->fetchAll();

    $applicationStatement = database()->prepare(
        'SELECT
            bewerbungen.id,
            bewerbungen.benutzerkonto_id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.titel,
            stellen.kennziffer
         FROM bewerbungen
         INNER JOIN stellen
             ON stellen.id = bewerbungen.stelle_id
         INNER JOIN benutzerkonten
             ON benutzerkonten.id = bewerbungen.benutzerkonto_id
         WHERE benutzerkonten.rolle = :rolle
           AND benutzerkonten.ist_aktiv = 0
           AND benutzerkonten.loeschung_angefordert_am IS NOT NULL
           AND bewerbungen.status <> :entwurfsstatus
         ORDER BY bewerbungen.eingereicht_am, bewerbungen.id'
    );

    $applicationStatement->execute([
        'rolle' => 'bewerbend',
        'entwurfsstatus' => 'entwurf',
    ]);

    foreach ($applicationStatement->fetchAll() as $application) {
        $accountId = (int) $application['benutzerkonto_id'];
        $applicationsByAccount[$accountId][] = $application;
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Kontolöschanträge: '
        . $exception->getMessage()
    );

    $deletionRequests = [];
    $applicationsByAccount = [];
    $databaseError = true;

    http_response_code(500);
}

$pageTitle = 'Kontolöschanträge | FiktivFit Karriere';
$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page recruiting-page">
    <section class="account-intro">
        <p class="eyebrow">Recruitingbereich</p>
        <h1>Kontolöschanträge</h1>
        <p>
            Prüfe bei den aufgeführten Bewerbungen, ob ein weiterer
            Aufbewahrungsgrund besteht. Die Kontozugänge sind bereits
            gesperrt.
        </p>
    </section>

    <?php if ($accountDeleted): ?>
        <div class="notice notice--success" role="status">
            <strong>
                Das Bewerbendenkonto und sämtliche zugehörigen
                Bewerbungsdaten und Unterlagen wurden gelöscht.
            </strong>
        </div>
    <?php elseif ($fileCleanupPending): ?>
        <div class="notice notice--error" role="alert">
            <strong>
                Das Konto und seine Datenbankeinträge wurden gelöscht.
            </strong>
            <p>
                Einzelne Dateien konnten nicht entfernt werden.
                Die technischen Details stehen im Fehlerprotokoll.
                Die Dateilöschung muss vervollständigt werden.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>Die Löschanträge konnten nicht geladen werden.</strong>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php elseif ($deletionRequests === []): ?>
        <p class="notice">
            Zurzeit liegen keine offenen Kontolöschanträge vor.
        </p>
    <?php else: ?>
        <div class="recruiting-sections">
            <?php foreach ($deletionRequests as $deletionRequest): ?>
                <?php
                $accountId = (int) $deletionRequest['id'];
                $accountApplications = (
                    $applicationsByAccount[$accountId] ?? []
                );
                ?>

                <section class="recruiting-section">
                    <h2>
                        <?= escape(
                            $deletionRequest['vorname']
                            . ' '
                            . $deletionRequest['nachname']
                        ) ?>
                    </h2>

                    <p>
                        <?= escape($deletionRequest['email']) ?>
                        <br>
                        Löschung angefordert am
                        <?= escape(formatDate(
                            $deletionRequest['loeschung_angefordert_am']
                        )) ?>
                    </p>

                    <h3>Eingereichte Bewerbungen</h3>

                    <?php if ($accountApplications === []): ?>
                        <p class="empty-state">
                            Es sind keine eingereichten Bewerbungen
                            mehr vorhanden.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stelle</th>
                                        <th scope="col">Kennziffer</th>
                                        <th scope="col">
                                            Einreichungsdatum
                                        </th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Aktion</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach (
                                        $accountApplications as $application
                                    ): ?>
                                        <tr>
                                            <td>
                                                <?= escape(
                                                    $application['titel']
                                                ) ?>
                                            </td>
                                            <td>
                                                <?= escape(
                                                    $application['kennziffer']
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
                                                    href="bewerbung-sichten.php?id=<?= (int) $application['id'] ?>&amp;from=deletion_requests"
                                                >
                                                    Bewerbung prüfen
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <p class="auth-card__footer">
                        <a
                            class="text-link"
                            href="loeschantrag-bearbeiten.php?id=<?= $accountId ?>"
                        >
                            Kontolöschung nach Prüfung bestätigen
                        </a>
                    </p>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>