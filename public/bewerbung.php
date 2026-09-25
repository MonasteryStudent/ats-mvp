<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/uploads.php';

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function documentTypeLabel(string $documentType): string
{
    return match ($documentType) {
        'lebenslauf' => 'Lebenslauf',
        'anschreiben' => 'Anschreiben',
        'zeugnis' => 'Zeugnisse',
        'anlage' => 'Weitere Anlagen',
        default => 'Dokument',
    };
}

function formatFileSize(int $bytes): string
{
    return number_format(
        $bytes / 1024,
        0,
        ',',
        '.'
    ) . ' KB';
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
$documents = [];

$earliestStartDate = '';
$message = '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$draftSaved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'saved') === 'draft'
);

$documentRemoved = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'removed') === '1'
);

$documentRemovalFailed = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'removal_failed') === '1'
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

                $documentStatement = database()->prepare(
                    'SELECT
                        id,
                        dokumenttyp,
                        originaldateiname,
                        speicherdateiname,
                        dateigroesse
                    FROM dokumente
                    WHERE bewerbung_id = :bewerbung_id
                    ORDER BY hochgeladen_am, id'
                );

                $documentStatement->execute([
                    'bewerbung_id' => $application['id'],
                ]);

                $documents = $documentStatement->fetchAll();
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
                $connection = database();
                $newStoredFiles = [];
                $replacedStoredFiles = [];

                $connection->beginTransaction();

                try {
                    if ($application === null) {
                        $saveStatement = $connection->prepare(
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

                        $applicationId = (int) (
                            $connection->lastInsertId()
                        );
                    } else {
                        $applicationId = (int) $application['id'];

                        $saveStatement = $connection->prepare(
                            'UPDATE bewerbungen
                            SET
                                fruehestmoegliches_eintrittsdatum =
                                    :eintrittsdatum,
                                nachricht = :nachricht,
                                aktualisiert_am = CURRENT_TIMESTAMP
                            WHERE id = :id
                            AND benutzerkonto_id = :benutzerkonto_id
                            AND status = :status'
                        );

                        $saveStatement->execute([
                            'eintrittsdatum' =>
                                $earliestStartDate === ''
                                    ? null
                                    : $earliestStartDate,
                            'nachricht' =>
                                $message === '' ? null : $message,
                            'id' => $applicationId,
                            'benutzerkonto_id' =>
                                authenticatedUserId(),
                            'status' => 'entwurf',
                        ]);
                    }

                    $uploadFields = [
                        'lebenslauf' => [
                            'type' => 'lebenslauf',
                            'label' => 'Lebenslauf',
                            'replace' => true,
                        ],
                        'anschreiben' => [
                            'type' => 'anschreiben',
                            'label' => 'Anschreiben',
                            'replace' => true,
                        ],
                        'zeugnisse' => [
                            'type' => 'zeugnis',
                            'label' => 'Zeugnis',
                            'replace' => true,
                        ],
                        'anlagen' => [
                            'type' => 'anlage',
                            'label' => 'Weitere Anlage',
                            'replace' => true,
                        ],
                    ];

                    foreach ($uploadFields as $fieldName => $configuration) {
                        foreach (uploadedFiles($fieldName) as $uploadedFile) {
                            try {
                                $storedFile = storeUploadedPdf(
                                    $uploadedFile
                                );
                            } catch (UploadValidationException $exception) {
                                throw new UploadValidationException(
                                    $configuration['label']
                                    . ': '
                                    . $exception->getMessage()
                                );
                            }

                            $newStoredFiles[] =
                                $storedFile['speicherdateiname'];

                            if ($configuration['replace']) {
                                $existingStatement = $connection->prepare(
                                    'SELECT speicherdateiname
                                    FROM dokumente
                                    WHERE bewerbung_id = :bewerbung_id
                                    AND dokumenttyp = :dokumenttyp'
                                );

                                $existingStatement->execute([
                                    'bewerbung_id' => $applicationId,
                                    'dokumenttyp' => $configuration['type'],
                                ]);

                                foreach (
                                    $existingStatement->fetchAll()
                                    as $existingDocument
                                ) {
                                    $replacedStoredFiles[] =
                                        $existingDocument[
                                            'speicherdateiname'
                                        ];
                                }

                                $deleteStatement = $connection->prepare(
                                    'DELETE FROM dokumente
                                    WHERE bewerbung_id = :bewerbung_id
                                    AND dokumenttyp = :dokumenttyp'
                                );

                                $deleteStatement->execute([
                                    'bewerbung_id' => $applicationId,
                                    'dokumenttyp' => $configuration['type'],
                                ]);
                            }

                            $insertDocumentStatement =
                                $connection->prepare(
                                    'INSERT INTO dokumente (
                                        bewerbung_id,
                                        dokumenttyp,
                                        originaldateiname,
                                        speicherdateiname,
                                        mime_typ,
                                        dateigroesse
                                    ) VALUES (
                                        :bewerbung_id,
                                        :dokumenttyp,
                                        :originaldateiname,
                                        :speicherdateiname,
                                        :mime_typ,
                                        :dateigroesse
                                    )'
                                );

                            $insertDocumentStatement->execute([
                                'bewerbung_id' => $applicationId,
                                'dokumenttyp' => $configuration['type'],
                                'originaldateiname' =>
                                    $storedFile['originaldateiname'],
                                'speicherdateiname' =>
                                    $storedFile['speicherdateiname'],
                                'mime_typ' => $storedFile['mime_typ'],
                                'dateigroesse' =>
                                    $storedFile['dateigroesse'],
                            ]);
                        }
                    }

                    $connection->commit();

                    foreach ($replacedStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    header(
                        'Location: bewerbung.php?stelle_id='
                        . $jobId
                        . '&saved=draft'
                    );
                    exit;
                } catch (UploadValidationException $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }

                    foreach ($newStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    $errors[] = $exception->getMessage();
                } catch (Throwable $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }

                    foreach ($newStoredFiles as $storedFilename) {
                        $path = UPLOAD_PATH . '/' . $storedFilename;

                        if (is_file($path)) {
                            unlink($path);
                        }
                    }

                    throw $exception;
                }
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

            <?php if ($documentRemoved): ?>
                <div class="notice notice--success" role="status">
                    <strong>Das Dokument wurde entfernt.</strong>
                </div>
            <?php endif; ?>

            <?php if ($documentRemovalFailed): ?>
                <div class="notice notice--error" role="alert">
                    <strong>Das Dokument konnte nicht entfernt werden.</strong>
                    <p>Bitte versuche es erneut.</p>
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

            <form
                method="post"
                enctype="multipart/form-data"
                class="application-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <input
                    type="hidden"
                    name="stelle_id"
                    value="<?= (int) $job['id'] ?>"
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
                    <h2>Bewerbungsunterlagen</h2>

                    <p class="hint">
                        Zulässig sind PDF-Dateien mit höchstens 5 MB pro Datei.
                        Lebenslauf und Anschreiben sind vor dem Einreichen
                        erforderlich.
                    </p>

                    <?php if ($documents !== []): ?>
                        <ul class="document-list">
                            <?php foreach ($documents as $document): ?>
                                <li>
                                    <div>
                                        <strong>
                                            <?= escape(documentTypeLabel(
                                                $document['dokumenttyp']
                                            )) ?>
                                        </strong>

                                        <span>
                                            <?= escape(
                                                $document['originaldateiname']
                                            ) ?>
                                        </span>
                                    </div>

                                    <div class="document-list__actions">
                                        <span class="hint">
                                            <?= escape(formatFileSize(
                                                (int) $document['dateigroesse']
                                            )) ?>
                                        </span>

                                        <button
                                            class="document-list__remove"
                                            type="submit"
                                            name="dokument_id"
                                            value="<?= (int) $document['id'] ?>"
                                            formaction="dokument-loeschen.php"
                                            formmethod="post"
                                            formenctype="application/x-www-form-urlencoded"
                                            formnovalidate
                                        >
                                            Entfernen
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="form-field">
                        <label for="resume">
                            Lebenslauf
                        </label>
                        <input
                            type="file"
                            id="resume"
                            name="lebenslauf"
                            accept=".pdf,application/pdf"
                        >
                        <small class="form-hint">
                            Eine neue Datei ersetzt den vorhandenen Lebenslauf.
                        </small>
                    </div>

                    <div class="form-field">
                        <label for="cover-letter">
                            Anschreiben
                        </label>
                        <input
                            type="file"
                            id="cover-letter"
                            name="anschreiben"
                            accept=".pdf,application/pdf"
                        >
                        <small class="form-hint">
                            Eine neue Datei ersetzt das vorhandene Anschreiben.
                        </small>
                    </div>

                    <div class="form-field">
                        <label for="certificates">
                            Zeugnisse als zusammengefasste PDF
                            <span class="hint">(optional)</span>
                        </label>
                        <input
                            type="file"
                            id="certificates"
                            name="zeugnisse"
                            accept=".pdf,application/pdf"
                        >
                    </div>

                    <div class="form-field">
                        <label for="attachments">
                            Weitere Anlagen als zusammengefasste PDF
                            <span class="hint">(optional)</span>
                        </label>
                        <input
                            type="file"
                            id="attachments"
                            name="anlagen"
                            accept=".pdf,application/pdf"
                        >
                    </div>
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
