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
$user = null;
$application = null;
$databaseError = false;
$accessDenied = false;
$errors = [];

$earliestStartDate = '';
$message = '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$draftSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === 'draft'
);

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
    header('Cache-Control: no-store, no-cache, must-revalidate');

    try {
        $jobStatement = database()->prepare(
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

        $jobStatement->execute([
            'id' => $jobId,
        ]);

        $job = $jobStatement->fetch();

        if ($job === false) {
            $job = null;
            http_response_code(404);
        }

        if ($job !== null) {
            $userStatement = database()->prepare(
                'SELECT
                    id,
                    email,
                    vorname,
                    nachname,
                    telefon
                 FROM benutzerkonten
                 WHERE id = :id
                   AND rolle = :rolle
                   AND ist_aktiv = 1'
            );

            $userStatement->execute([
                'id' => authenticatedUserId(),
                'rolle' => 'bewerbend',
            ]);

            $user = $userStatement->fetch();

            if ($user === false) {
                signOutUser();

                header(
                    'Location: anmelden.php?from=application&id='
                    . $jobId
                );
                exit;
            }

            $applicationStatement = database()->prepare(
                'SELECT
                    id,
                    status,
                    fruehestmoegliches_eintrittsdatum,
                    nachricht
                 FROM bewerbungen
                 WHERE benutzerkonto_id = :benutzerkonto_id
                   AND stelle_id = :stelle_id
                 LIMIT 1'
            );

            $applicationStatement->execute([
                'benutzerkonto_id' => authenticatedUserId(),
                'stelle_id' => $jobId,
            ]);

            $application = $applicationStatement->fetch();

            if ($application === false) {
                $application = null;
            } else {
                $earliestStartDate = (string) (
                    $application[
                        'fruehestmoegliches_eintrittsdatum'
                    ] ?? ''
                );

                $message = (string) (
                    $application['nachricht'] ?? ''
                );
            }
        }

        if (
            $job !== null
            && $user !== null
            && $requestMethod === 'POST'
            && ($application === null
                || $application['status'] === 'entwurf')
        ) {
            $earliestStartDate = trim(
                (string) (
                    $_POST[
                        'fruehestmoegliches_eintrittsdatum'
                    ] ?? ''
                )
            );

            $message = trim(
                (string) ($_POST['nachricht'] ?? '')
            );

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $errors[] = (
                    'Die Anfrage konnte nicht überprüft werden. '
                    . 'Bitte lade die Seite neu.'
                );
            }

            if ($earliestStartDate !== '') {
                $date = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $earliestStartDate
                );

                if (
                    $date === false
                    || $date->format('Y-m-d') !== $earliestStartDate
                ) {
                    $errors[] = (
                        'Bitte gib ein gültiges Eintrittsdatum an.'
                    );
                }
            }

            if (strlen($message) > 2000) {
                $errors[] = (
                    'Die Nachricht darf höchstens '
                    . '2000 Zeichen enthalten.'
                );
            }

            if ($errors === []) {
                if ($application === null) {
                    $saveStatement = database()->prepare(
                        'INSERT INTO bewerbungen (
                            benutzerkonto_id,
                            stelle_id,
                            fruehestmoegliches_eintrittsdatum,
                            nachricht
                        ) VALUES (
                            :benutzerkonto_id,
                            :stelle_id,
                            :eintrittsdatum,
                            :nachricht
                        )'
                    );

                    $saveStatement->execute([
                        'benutzerkonto_id' =>
                            authenticatedUserId(),
                        'stelle_id' => $jobId,
                        'eintrittsdatum' =>
                            $earliestStartDate === ''
                                ? null
                                : $earliestStartDate,
                        'nachricht' =>
                            $message === '' ? null : $message,
                    ]);
                } else {
                    $saveStatement = database()->prepare(
                        'UPDATE bewerbungen
                         SET
                            fruehestmoegliches_eintrittsdatum =
                                :eintrittsdatum,
                            nachricht = :nachricht,
                            aktualisiert_am = CURRENT_TIMESTAMP
                         WHERE id = :id
                           AND benutzerkonto_id =
                                :benutzerkonto_id
                           AND status = :status'
                    );

                    $saveStatement->execute([
                        'eintrittsdatum' =>
                            $earliestStartDate === ''
                                ? null
                                : $earliestStartDate,
                        'nachricht' =>
                            $message === '' ? null : $message,
                        'id' => $application['id'],
                        'benutzerkonto_id' =>
                            authenticatedUserId(),
                        'status' => 'entwurf',
                    ]);
                }

                header(
                    'Location: bewerbung.php?stelle_id='
                    . $jobId
                    . '&saved=draft'
                );
                exit;
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler beim Bearbeiten der Bewerbung: '
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
        </section>

        <?php if (
            $application !== null
            && $application['status'] !== 'entwurf'
        ): ?>
            <div class="notice">
                <strong>Diese Bewerbung wurde bereits eingereicht.</strong>
                <p>
                    Eingereichte Bewerbungen können nicht mehr
                    bearbeitet werden.
                </p>
                <a class="text-link" href="konto.php">
                    Bewerbung im Konto ansehen
                </a>
            </div>
        <?php else: ?>
            <?php if ($draftSaved): ?>
                <div class="notice notice--success" role="status">
                    <strong>Der Entwurf wurde gespeichert.</strong>
                    <p>
                        Du kannst die Bewerbung später in deinem
                        Konto weiterbearbeiten.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div
                    class="notice notice--error form-errors"
                    role="alert"
                >
                    <strong>
                        Der Entwurf konnte nicht gespeichert werden.
                    </strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="application-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <section class="application-form__section">
                    <h2>Profildaten</h2>

                    <p class="hint">
                        Diese Angaben werden aus deinem Konto
                        übernommen.
                    </p>

                    <div class="form-field">
                        <label for="name">Name</label>
                        <input
                            type="text"
                            id="name"
                            value="<?= escape(
                                $user['vorname']
                                . ' '
                                . $user['nachname']
                            ) ?>"
                            readonly
                        >
                    </div>

                    <div class="form-field">
                        <label for="email">E-Mail-Adresse</label>
                        <input
                            type="email"
                            id="email"
                            value="<?= escape($user['email']) ?>"
                            readonly
                        >
                    </div>

                    <div class="form-field">
                        <label for="phone">Telefonnummer</label>
                        <input
                            type="text"
                            id="phone"
                            value="<?= escape(
                                (string) ($user['telefon'] ?? '')
                            ) ?>"
                            readonly
                        >
                    </div>

                    <a class="text-link" href="konto.php">
                        Profildaten im Konto bearbeiten
                    </a>
                </section>

                <section class="application-form__section">
                    <h2>Bewerbungsdaten</h2>

                    <div class="form-field">
                        <label for="earliest-start-date">
                            Frühestmögliches Eintrittsdatum
                        </label>
                        <input
                            type="date"
                            id="earliest-start-date"
                            name="fruehestmoegliches_eintrittsdatum"
                            value="<?= escape($earliestStartDate) ?>"
                        >
                    </div>

                    <div class="form-field">
                        <label for="message">
                            Nachricht
                            <span class="hint">(optional)</span>
                        </label>
                        <textarea
                            id="message"
                            name="nachricht"
                            rows="7"
                            maxlength="2000"
                        ><?= escape($message) ?></textarea>
                    </div>
                </section>

                <div class="application-form__actions">
                    <button class="button" type="submit">
                        Als Entwurf speichern
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>