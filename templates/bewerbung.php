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
                        <?php if ($submissionAttempted): ?>
                            Die Bewerbung konnte nicht eingereicht werden.
                        <?php else: ?>
                            Der Entwurf konnte nicht gespeichert werden.
                        <?php endif; ?>
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

                    <?php foreach ($documentFields as $field): ?>
                        <?php
                        $existingDocument = (
                            $documentsByType[$field['type']] ?? null
                        );
                        ?>

                        <div class="form-field document-upload">
                            <label for="<?= escape($field['id']) ?>">
                                <?= escape($field['label']) ?>

                                <?php if ($field['optional']): ?>
                                    <span class="hint">(optional)</span>
                                <?php endif; ?>
                            </label>

                            <?php if ($existingDocument !== null): ?>
                                <div class="document-upload__saved">
                                    <div class="document-upload__details">
                                        <strong>
                                            <?= escape(
                                                $existingDocument['originaldateiname']
                                            ) ?>
                                        </strong>

                                        <span class="form-hint">
                                            Gespeichert ·
                                            <?= escape(formatFileSize(
                                                (int) $existingDocument['dateigroesse']
                                            )) ?>
                                        </span>
                                    </div>

                                    <button
                                        class="document-upload__remove"
                                        type="submit"
                                        name="dokument_id"
                                        value="<?= (int) $existingDocument['id'] ?>"
                                        formaction="dokument-loeschen.php"
                                        formmethod="post"
                                        formenctype="application/x-www-form-urlencoded"
                                        formnovalidate
                                    >
                                        Entfernen
                                    </button>
                                </div>
                            <?php else: ?>
                                <input
                                    type="file"
                                    id="<?= escape($field['id']) ?>"
                                    name="<?= escape($field['name']) ?>"
                                    accept=".pdf,application/pdf"
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
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
                    <button
                        class="button button--secondary"
                        type="submit"
                        name="action"
                        value="save_draft"
                        formnovalidate
                    >
                        Als Entwurf speichern
                    </button>

                    <button
                        class="button"
                        type="submit"
                        name="action"
                        value="submit_application"
                    >
                        Bewerbung einreichen
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>