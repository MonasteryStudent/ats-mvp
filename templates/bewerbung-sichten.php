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

                            <?php if ($application['status'] === 'zurueckgezogen'): ?>
                                <input
                                    type="text"
                                    id="status"
                                    value="Zurückgezogen"
                                    readonly
                                >

                                <input
                                    type="hidden"
                                    name="status"
                                    value="zurueckgezogen"
                                >
                            <?php else: ?>
                                <select
                                    id="status"
                                    name="status"
                                    required
                                >
                                    <?php foreach ($statusOptions as $value => $label): ?>
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
                            <?php endif; ?>
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

            <?php if ($application['status'] === 'zurueckgezogen'): ?>
                <section class="recruiting-section recruiting-delete">
                    <h2>Bewerbung löschen</h2>

                    <p class="hint">
                        Die Bewerbung wurde zurückgezogen. Vor einer
                        Löschung ist zu prüfen, ob ein weiterer
                        Aufbewahrungsgrund besteht.
                    </p>

                    <a
                        class="text-link"
                        href="bewerbung-loeschen.php?id=<?= (int) $application['id'] ?>"
                    >
                        Bewerbung endgültig löschen
                    </a>
                </section>
            <?php endif; ?>
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