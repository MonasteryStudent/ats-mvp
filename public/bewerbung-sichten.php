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

function documentTypeLabel(string $documentType): string
{
    $labels = [
        'lebenslauf' => 'Lebenslauf',
        'anschreiben' => 'Anschreiben',
        'zeugnis' => 'Zeugnisse',
        'anlage' => 'Weitere Anlagen',
    ];

    return $labels[$documentType] ?? ucfirst($documentType);
}

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errors = [];

$changesSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === '1'
);

$statusOptions = [
    'eingegangen' => 'Eingegangen',
    'vorauswahl' => 'Vorauswahl',
    'interview' => 'Interview',
    'angebot' => 'Angebot',
    'abgelehnt' => 'Abgelehnt',
    'zurueckgezogen' => 'Zurückgezogen',
];

$ratingOptions = [
    '1' => '1 – sehr gut',
    '2' => '2 – gut',
    '3' => '3 – befriedigend',
    '4' => '4 – ausreichend',
    '5' => '5 – unzureichend',
];

$selectedStatus = '';
$selectedRating = '';
$recruitingNote = '';

$applicationId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$application = null;
$documents = [];
$databaseError = false;

if (
    $applicationId !== false
    && $applicationId !== null
    && $applicationId > 0
) {
    try {
        $applicationStatement = database()->prepare(
            "SELECT
                bewerbungen.id,
                bewerbungen.status,
                bewerbungen.eingereicht_am,
                bewerbungen.fruehestmoegliches_eintrittsdatum,
                bewerbungen.nachricht,
                bewerbungen.bewertung,
                bewerbungen.recruitingnotiz,
                benutzerkonten.vorname,
                benutzerkonten.nachname,
                benutzerkonten.email,
                benutzerkonten.telefon,
                stellen.titel,
                stellen.kennziffer
             FROM bewerbungen
             INNER JOIN benutzerkonten
                ON benutzerkonten.id =
                    bewerbungen.benutzerkonto_id
             INNER JOIN stellen
                ON stellen.id = bewerbungen.stelle_id
             WHERE bewerbungen.id = :id
             AND bewerbungen.status <> 'entwurf'
             LIMIT 1"
        );

        $applicationStatement->execute([
            'id' => $applicationId,
        ]);

        $application = $applicationStatement->fetch();

        if ($application) {
            $selectedStatus = (string) $application['status'];

            $selectedRating = $application['bewertung'] === null
                ? ''
                : (string) $application['bewertung'];

            $recruitingNote = (string) (
                $application['recruitingnotiz'] ?? ''
            );

            if ($requestMethod === 'POST') {
                $selectedStatus = trim(
                    (string) ($_POST['status'] ?? '')
                );

                $selectedRating = trim(
                    (string) ($_POST['bewertung'] ?? '')
                );

                $recruitingNote = trim(
                    (string) ($_POST['recruitingnotiz'] ?? '')
                );

                if (
                    !isValidCsrfToken(
                        $_POST['csrf_token'] ?? null
                    )
                ) {
                    $errors[] = (
                        'Die Anfrage ist abgelaufen. '
                        . 'Bitte lade die Seite neu und '
                        . 'versuche es erneut.'
                    );
                }

                if (
                    !array_key_exists(
                        $selectedStatus,
                        $statusOptions
                    )
                ) {
                    $errors[] = (
                        'Bitte wähle einen gültigen '
                        . 'Bewerbungsstatus aus.'
                    );
                }

                if (
                    $application['status'] === 'zurueckgezogen'
                    && $selectedStatus !== 'zurueckgezogen'
                ) {
                    $errors[] = (
                        'Eine zurückgezogene Bewerbung kann nicht '
                        . 'erneut in das Auswahlverfahren aufgenommen werden.'
                    );
                }

                $rating = null;

                if ($selectedRating !== '') {
                    $validatedRating = filter_var(
                        $selectedRating,
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => 1,
                                'max_range' => 5,
                            ],
                        ]
                    );

                    if ($validatedRating === false) {
                        $errors[] = (
                            'Die Bewertung muss zwischen '
                            . '1 und 5 liegen.'
                        );
                    } else {
                        $rating = (int) $validatedRating;
                    }
                }

                if (strlen($recruitingNote) > 5000) {
                    $errors[] = (
                        'Die interne Notiz darf höchstens '
                        . '5000 Zeichen enthalten.'
                    );
                }

                if ($errors === []) {
                    $updateStatement = database()->prepare(
                        "UPDATE bewerbungen
                         SET
                            status = CASE
                                WHEN status = 'zurueckgezogen' THEN status
                                ELSE :status
                            END,
                            bewertung = :bewertung,
                            recruitingnotiz =
                                :recruitingnotiz,
                            aktualisiert_am =
                                CURRENT_TIMESTAMP
                         WHERE id = :id
                         AND status <> 'entwurf'"
                    );

                    $updateStatement->execute([
                        'status' => $selectedStatus,
                        'bewertung' => $rating,
                        'recruitingnotiz' =>
                            $recruitingNote === ''
                                ? null
                                : $recruitingNote,
                        'id' => $applicationId,
                    ]);

                    header(
                        'Location: bewerbung-sichten.php?id='
                        . $applicationId
                        . '&saved=1'
                    );
                    exit;
                }
            }

            $documentStatement = database()->prepare(
                "SELECT
                    id,
                    dokumenttyp,
                    originaldateiname
                 FROM dokumente
                 WHERE bewerbung_id = :bewerbung_id
                 ORDER BY
                    CASE dokumenttyp
                        WHEN 'lebenslauf' THEN 1
                        WHEN 'anschreiben' THEN 2
                        WHEN 'zeugnis' THEN 3
                        WHEN 'anlage' THEN 4
                        ELSE 5
                    END"
            );

            $documentStatement->execute([
                'bewerbung_id' => $applicationId,
            ]);

            $documents = $documentStatement->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log(
            'Fehler bei der Verarbeitung '
            . 'der Bewerbungsdetails: '
            . $exception->getMessage()
        );

        $databaseError = true;
        http_response_code(500);
    }
}

if (!$databaseError && !$application) {
    http_response_code(404);
}

if ($databaseError) {
    $pageTitle = (
        'Technischer Fehler | FiktivFit Karriere'
    );
} elseif ($application) {
    $pageTitle = (
        'Bewerbungsdetails | FiktivFit Karriere'
    );
} else {
    $pageTitle = (
        'Bewerbung nicht gefunden | FiktivFit Karriere'
    );
}

$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page recruiting-page">
    <section
        class="account-intro"
        aria-labelledby="application-heading"
    >
        <p class="eyebrow">Recruitingbereich</p>
        <h1 id="application-heading">
            Bewerbungsdetails
        </h1>
    </section>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>
                Die Bewerbungsdetails konnten nicht
                geladen werden.
            </strong>

            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php elseif (!$application): ?>
        <div class="notice notice--error">
            <strong>
                Die Bewerbung wurde nicht gefunden.
            </strong>

            <p>
                Sie ist möglicherweise nicht vorhanden
                oder noch nicht eingereicht worden.
            </p>

            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        </div>
    <?php else: ?>
        <?php if ($changesSaved): ?>
            <div
                class="notice notice--success"
                role="status"
            >
                <strong>
                    Die Änderungen wurden gespeichert.
                </strong>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div
                class="notice notice--error form-errors"
                role="alert"
            >
                <strong>
                    Die Änderungen konnten nicht
                    gespeichert werden.
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="recruiting-sections">
            <section class="recruiting-section">
                <h2>Übersicht</h2>

                <div class="table-wrapper">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Stelle</th>
                                <th scope="col">
                                    Kennziffer
                                </th>
                                <th scope="col">
                                    Einreichungsdatum
                                </th>
                                <th scope="col">
                                    Frühestmögliches
                                    Eintrittsdatum
                                </th>
                                <th scope="col">
                                    E-Mail-Adresse
                                </th>
                                <th scope="col">
                                    Telefonnummer
                                </th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <?= escape(
                                        $application['vorname']
                                        . ' '
                                        . $application['nachname']
                                    ) ?>
                                </td>

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
                                        $application[
                                            'eingereicht_am'
                                        ]
                                    )) ?>
                                </td>

                                <td>
                                    <?= escape(formatDate(
                                        $application[
                                            'fruehestmoegliches_eintrittsdatum'
                                        ]
                                    )) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $application['email']
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $application['telefon']
                                        ?: '–'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(statusLabel(
                                        $application['status']
                                    )) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="recruiting-section">
                <h2>Bewerbungsunterlagen</h2>

                <?php if ($documents === []): ?>
                    <p class="empty-state">
                        Es wurden keine Dokumente gefunden.
                    </p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th scope="col">
                                        Dokumenttyp
                                    </th>
                                    <th scope="col">
                                        Dateiname
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (
                                    $documents as $document
                                ): ?>
                                    <tr>
                                        <td>
                                            <?= escape(
                                                documentTypeLabel(
                                                    $document[
                                                        'dokumenttyp'
                                                    ]
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <a
                                                class="text-link"
                                                href="dokument-anzeigen.php?id=<?= (int) $document['id'] ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <?= escape(
                                                    $document[
                                                        'originaldateiname'
                                                    ]
                                                ) ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="recruiting-section">
                <h2>
                    Nachricht der bewerbenden Person
                </h2>

                <?php if (
                    $application['nachricht'] === null
                    || $application['nachricht'] === ''
                ): ?>
                    <p class="empty-state">
                        Es wurde keine zusätzliche
                        Nachricht hinterlegt.
                    </p>
                <?php else: ?>
                    <p>
                        <?= nl2br(escape(
                            $application['nachricht']
                        )) ?>
                    </p>
                <?php endif; ?>
            </section>

            <section class="recruiting-section">
                <h2>Bewerbung bearbeiten</h2>

                <form
                    class="recruiting-review-form job-form"
                    action="bewerbung-sichten.php?id=<?= (int) $application['id'] ?>"
                    method="post"
                    novalidate
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape(csrfToken()) ?>"
                    >

                    <div class="job-form__grid">
                        <div class="form-field">
                            <label for="status">
                                Bewerbungsstatus
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >
                                <?php foreach (
                                    $statusOptions
                                    as $value => $label
                                ): ?>
                                    <option
                                        value="<?= escape($value) ?>"
                                        <?= $selectedStatus === $value
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="rating">
                                Interne Bewertung
                            </label>

                            <select
                                id="rating"
                                name="bewertung"
                            >
                                <option value="">
                                    Keine Bewertung
                                </option>

                                <?php foreach (
                                    $ratingOptions
                                    as $value => $label
                                ): ?>
                                    <option
                                        value="<?= escape(
                                            (string) $value
                                        ) ?>"
                                        <?= $selectedRating
                                            === (string) $value
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="recruiting-note">
                            Interne Notiz
                        </label>

                        <textarea
                            id="recruiting-note"
                            name="recruitingnotiz"
                            maxlength="5000"
                            rows="8"
                        ><?= escape($recruitingNote) ?></textarea>
                    </div>

                    <div class="application-form__actions">
                        <button
                            class="button"
                            type="submit"
                        >
                            Änderungen speichern
                        </button>
                    </div>
                </form>
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