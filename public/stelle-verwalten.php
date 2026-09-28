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

requireRole('recruiting');

header('Cache-Control: no-store, no-cache, must-revalidate');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$jobIdProvided = array_key_exists('id', $_GET);

$isEditMode = (
    $jobIdProvided
    && $jobId !== false
    && $jobId !== null
    && $jobId > 0
);

$jobNotFound = $jobIdProvided && !$isEditMode;
$databaseError = false;
$errors = [];

$form = [
    'titel' => '',
    'kennziffer' => '',
    'arbeitsort' => '',
    'eintrittsdatum' => '',
    'karrierestufe' => '',
    'beschaeftigungsgrad' => '',
    'befristung' => '',
    'verguetung' => '',
    'wer_wir_sind' => '',
    'das_erwartet_dich' => '',
    'aufgaben' => '',
    'anforderungen' => '',
    'leistungen' => '',
    'ansprechperson_name' => '',
    'ansprechperson_email' => '',
    'status' => 'aktiv',
];

try {
    if ($isEditMode) {
        $statement = database()->prepare(
            'SELECT
                titel,
                kennziffer,
                arbeitsort,
                eintrittsdatum,
                karrierestufe,
                beschaeftigungsgrad,
                befristung,
                verguetung,
                wer_wir_sind,
                das_erwartet_dich,
                aufgaben,
                anforderungen,
                leistungen,
                ansprechperson_name,
                ansprechperson_email,
                status
             FROM stellen
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $jobId,
        ]);

        $job = $statement->fetch();

        if ($job === false) {
            $jobNotFound = true;
            http_response_code(404);
        } else {
            foreach (array_keys($form) as $fieldName) {
                $form[$fieldName] = (string) (
                    $job[$fieldName] ?? ''
                );
            }
        }
    }

    if ($requestMethod === 'POST' && !$jobNotFound) {
        foreach (array_keys($form) as $fieldName) {
            $form[$fieldName] = trim(
                (string) ($_POST[$fieldName] ?? '')
            );
        }

        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = (
                'Die Anfrage ist abgelaufen. '
                . 'Bitte lade die Seite neu und versuche es erneut.'
            );
        }

        $requiredFields = [
            'titel' => 'Stellenbezeichnung',
            'kennziffer' => 'Kennziffer',
            'arbeitsort' => 'Arbeitsort',
            'beschaeftigungsgrad' => 'Beschäftigungsgrad',
            'befristung' => 'Befristung',
            'wer_wir_sind' => 'Wer wir sind',
            'das_erwartet_dich' => 'Das erwartet dich',
            'aufgaben' => 'Deine Aufgaben',
            'anforderungen' => 'Das bringst du mit',
            'leistungen' => 'Das bieten wir dir',
            'ansprechperson_name' => 'Ansprechperson',
            'ansprechperson_email' => 'E-Mail-Adresse',
            'status' => 'Stellenstatus',
        ];

        foreach ($requiredFields as $fieldName => $label) {
            if ($form[$fieldName] === '') {
                $errors[] = $label . ' ist erforderlich.';
            }
        }

        if (
            $form['ansprechperson_email'] !== ''
            && filter_var(
                $form['ansprechperson_email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $errors[] = (
                'Bitte gib eine gültige E-Mail-Adresse '
                . 'für die Ansprechperson ein.'
            );
        }

        $allowedEmploymentTypes = [
            'Vollzeit',
            'Teilzeit',
            'Minijob',
        ];

        if (
            !in_array(
                $form['beschaeftigungsgrad'],
                $allowedEmploymentTypes,
                true
            )
        ) {
            $errors[] = 'Bitte wähle einen Beschäftigungsgrad aus.';
        }

        $allowedDurations = [
            'Unbefristet',
            'Befristet',
        ];

        if (
            !in_array(
                $form['befristung'],
                $allowedDurations,
                true
            )
        ) {
            $errors[] = 'Bitte wähle eine Befristung aus.';
        }

        if (!in_array($form['status'], ['aktiv', 'inaktiv'], true)) {
            $errors[] = 'Der ausgewählte Stellenstatus ist ungültig.';
        }

        if ($form['eintrittsdatum'] !== '') {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $form['eintrittsdatum']
            );

            if (
                $date === false
                || $date->format('Y-m-d')
                    !== $form['eintrittsdatum']
            ) {
                $errors[] = 'Das Eintrittsdatum ist ungültig.';
            }
        }

        $maximumLengths = [
            'titel' => 200,
            'kennziffer' => 50,
            'arbeitsort' => 150,
            'karrierestufe' => 100,
            'beschaeftigungsgrad' => 50,
            'befristung' => 50,
            'verguetung' => 150,
            'ansprechperson_name' => 200,
            'ansprechperson_email' => 254,
        ];

        foreach ($maximumLengths as $fieldName => $maximumLength) {
            if (strlen($form[$fieldName]) > $maximumLength) {
                $errors[] = (
                    $requiredFields[$fieldName]
                    ?? ucfirst($fieldName)
                ) . ' ist zu lang.';
            }
        }

        if ($errors === []) {
            $duplicateStatement = database()->prepare(
                'SELECT id
                 FROM stellen
                 WHERE kennziffer = :kennziffer
                   AND (:id IS NULL OR id <> :id)
                 LIMIT 1'
            );

            $duplicateStatement->execute([
                'kennziffer' => $form['kennziffer'],
                'id' => $isEditMode ? $jobId : null,
            ]);

            if ($duplicateStatement->fetch() !== false) {
                $errors[] = (
                    'Die angegebene Kennziffer wird bereits verwendet.'
                );
            }
        }

        if ($errors === []) {
            $parameters = [
                'titel' => $form['titel'],
                'kennziffer' => $form['kennziffer'],
                'arbeitsort' => $form['arbeitsort'],
                'eintrittsdatum' =>
                    $form['eintrittsdatum'] === ''
                        ? null
                        : $form['eintrittsdatum'],
                'karrierestufe' =>
                    $form['karrierestufe'] === ''
                        ? null
                        : $form['karrierestufe'],
                'beschaeftigungsgrad' =>
                    $form['beschaeftigungsgrad'],
                'befristung' => $form['befristung'],
                'verguetung' =>
                    $form['verguetung'] === ''
                        ? null
                        : $form['verguetung'],
                'wer_wir_sind' => $form['wer_wir_sind'],
                'das_erwartet_dich' =>
                    $form['das_erwartet_dich'],
                'aufgaben' => $form['aufgaben'],
                'anforderungen' => $form['anforderungen'],
                'leistungen' => $form['leistungen'],
                'ansprechperson_name' =>
                    $form['ansprechperson_name'],
                'ansprechperson_email' =>
                    $form['ansprechperson_email'],
                'status' => $form['status'],
            ];

            if ($isEditMode) {
                $parameters['id'] = $jobId;

                $saveStatement = database()->prepare(
                    'UPDATE stellen
                     SET
                        titel = :titel,
                        kennziffer = :kennziffer,
                        arbeitsort = :arbeitsort,
                        eintrittsdatum = :eintrittsdatum,
                        karrierestufe = :karrierestufe,
                        beschaeftigungsgrad =
                            :beschaeftigungsgrad,
                        befristung = :befristung,
                        verguetung = :verguetung,
                        wer_wir_sind = :wer_wir_sind,
                        das_erwartet_dich =
                            :das_erwartet_dich,
                        aufgaben = :aufgaben,
                        anforderungen = :anforderungen,
                        leistungen = :leistungen,
                        ansprechperson_name =
                            :ansprechperson_name,
                        ansprechperson_email =
                            :ansprechperson_email,
                        status = :status,
                        aktualisiert_am = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );

                $saveStatement->execute($parameters);

                header(
                    'Location: recruiting.php?saved=updated'
                );
                exit;
            }

            $saveStatement = database()->prepare(
                'INSERT INTO stellen (
                    titel,
                    kennziffer,
                    arbeitsort,
                    eintrittsdatum,
                    karrierestufe,
                    beschaeftigungsgrad,
                    befristung,
                    verguetung,
                    wer_wir_sind,
                    das_erwartet_dich,
                    aufgaben,
                    anforderungen,
                    leistungen,
                    ansprechperson_name,
                    ansprechperson_email,
                    status
                 ) VALUES (
                    :titel,
                    :kennziffer,
                    :arbeitsort,
                    :eintrittsdatum,
                    :karrierestufe,
                    :beschaeftigungsgrad,
                    :befristung,
                    :verguetung,
                    :wer_wir_sind,
                    :das_erwartet_dich,
                    :aufgaben,
                    :anforderungen,
                    :leistungen,
                    :ansprechperson_name,
                    :ansprechperson_email,
                    :status
                 )'
            );

            $saveStatement->execute($parameters);

            header('Location: recruiting.php?saved=created');
            exit;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler bei der Stellenverwaltung: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

if ($jobNotFound) {
    http_response_code(404);
}

$pageTitle = $isEditMode
    ? 'Stelle bearbeiten | FiktivFit Karriere'
    : 'Neue Stelle anlegen | FiktivFit Karriere';

$headerLinkLabel = 'Zur Recruitingübersicht';
$headerLinkHref = 'recruiting.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page">
    <a class="back-link" href="recruiting.php">
        &larr; Zur Recruitingübersicht
    </a>

    <?php if ($databaseError): ?>
        <section class="notice notice--error" role="alert">
            <h1>Stellenformular konnte nicht geladen werden</h1>
            <p>Bitte versuche es später erneut.</p>
        </section>
    <?php elseif ($jobNotFound): ?>
        <section class="notice notice--error">
            <h1>Stelle nicht gefunden</h1>
            <p>Die gewünschte Stelle ist nicht verfügbar.</p>
            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        </section>
    <?php else: ?>
        <section class="account-intro">
            <p class="eyebrow">Recruitingbereich</p>

            <h1>
                <?= $isEditMode
                    ? 'Stelle bearbeiten'
                    : 'Neue Stelle anlegen' ?>
            </h1>

            <p>
                Erfasse die Informationen für die öffentliche
                Stellenausschreibung.
            </p>
        </section>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>
                    Die Stelle konnte nicht gespeichert werden.
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            class="application-form job-form"
            method="post"
            novalidate
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(csrfToken()) ?>"
            >

            <section class="application-form__section">
                <h2>Stellendaten</h2>

                <div class="job-form__grid">
                    <div class="form-field">
                        <label for="job-title">
                            Stellenbezeichnung
                        </label>
                        <input
                            type="text"
                            id="job-title"
                            name="titel"
                            value="<?= escape($form['titel']) ?>"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="reference">Kennziffer</label>
                        <input
                            type="text"
                            id="reference"
                            name="kennziffer"
                            value="<?= escape($form['kennziffer']) ?>"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="location">Arbeitsort</label>
                        <input
                            type="text"
                            id="location"
                            name="arbeitsort"
                            value="<?= escape($form['arbeitsort']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="entry-date">
                            Eintrittsdatum
                        </label>
                        <input
                            type="date"
                            id="entry-date"
                            name="eintrittsdatum"
                            value="<?= escape(
                                $form['eintrittsdatum']
                            ) ?>"
                        >
                    </div>

                    <div class="form-field">
                        <label for="career-level">
                            Karrierestufe
                        </label>
                        <input
                            type="text"
                            id="career-level"
                            name="karrierestufe"
                            value="<?= escape(
                                $form['karrierestufe']
                            ) ?>"
                            maxlength="100"
                        >
                    </div>

                    <div class="form-field">
                        <label for="employment-type">
                            Beschäftigungsgrad
                        </label>
                        <select
                            id="employment-type"
                            name="beschaeftigungsgrad"
                            required
                        >
                            <option value="">Bitte auswählen</option>
                            <option
                                value="Vollzeit"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Vollzeit'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Vollzeit
                            </option>
                            <option
                                value="Teilzeit"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Teilzeit'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Teilzeit
                            </option>
                            <option
                                value="Minijob"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Minijob'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Minijob
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="duration">Befristung</label>
                        <select
                            id="duration"
                            name="befristung"
                            required
                        >
                            <option value="">Bitte auswählen</option>
                            <option
                                value="Unbefristet"
                                <?= $form['befristung']
                                    === 'Unbefristet'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Unbefristet
                            </option>
                            <option
                                value="Befristet"
                                <?= $form['befristung']
                                    === 'Befristet'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Befristet
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="salary">Vergütung</label>
                        <input
                            type="text"
                            id="salary"
                            name="verguetung"
                            value="<?= escape($form['verguetung']) ?>"
                            maxlength="150"
                        >
                    </div>
                </div>
            </section>

            <section class="application-form__section">
                <h2>Stellenbeschreibung</h2>

                <?php
                $textareas = [
                    'wer_wir_sind' => 'Wer wir sind',
                    'das_erwartet_dich' => 'Das erwartet dich',
                    'aufgaben' => 'Deine Aufgaben',
                    'anforderungen' => 'Das bringst du mit',
                    'leistungen' => 'Das bieten wir dir',
                ];
                ?>

                <?php foreach ($textareas as $fieldName => $label): ?>
                    <div class="form-field">
                        <label for="<?= escape($fieldName) ?>">
                            <?= escape($label) ?>
                        </label>
                        <textarea
                            id="<?= escape($fieldName) ?>"
                            name="<?= escape($fieldName) ?>"
                            maxlength="5000"
                            required
                        ><?= escape($form[$fieldName]) ?></textarea>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="application-form__section">
                <h2>Ansprechperson und Veröffentlichung</h2>

                <div class="job-form__grid">
                    <div class="form-field">
                        <label for="contact-name">
                            Ansprechperson
                        </label>
                        <input
                            type="text"
                            id="contact-name"
                            name="ansprechperson_name"
                            value="<?= escape(
                                $form['ansprechperson_name']
                            ) ?>"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="contact-email">
                            E-Mail-Adresse
                        </label>
                        <input
                            type="email"
                            id="contact-email"
                            name="ansprechperson_email"
                            value="<?= escape(
                                $form['ansprechperson_email']
                            ) ?>"
                            maxlength="254"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="job-status">
                            Stellenstatus
                        </label>
                        <select
                            id="job-status"
                            name="status"
                            required
                        >
                            <option
                                value="aktiv"
                                <?= $form['status'] === 'aktiv'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Aktiv
                            </option>
                            <option
                                value="inaktiv"
                                <?= $form['status'] === 'inaktiv'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inaktiv
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="application-form__actions">
                <a
                    class="button button--secondary"
                    href="recruiting.php"
                >
                    Abbrechen
                </a>

                <button class="button" type="submit">
                    <?= $isEditMode
                        ? 'Änderungen speichern'
                        : 'Stelle anlegen' ?>
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>