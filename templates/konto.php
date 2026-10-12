<main class="account-page">
    <section class="account-intro" aria-labelledby="account-heading">
        <p class="eyebrow">Bewerbendenbereich</p>
        <h1 id="account-heading">Mein Konto</h1>

        <p>
            In diesem Bereich kannst du deine Profilinformationen ergänzen,
            eingereichte Bewerbungen einsehen, gespeicherte Entwürfe
            weiterbearbeiten und deine Kontoeinstellungen ändern.
        </p>
    </section>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>Dein Konto konnte nicht geladen werden.</strong>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php else: ?>

        <?php if ($draftDeleted): ?>
            <div class="notice notice--success" role="status">
                <strong>
                    Der Bewerbungsentwurf und seine Unterlagen wurden gelöscht.
                </strong>
            </div>
        <?php endif; ?>

        <?php if ($applicationWithdrawn): ?>
            <div class="notice notice--success" role="status">
                <strong>Deine Bewerbung wurde zurückgezogen.</strong>
            </div>
        <?php endif; ?>

        <?php if ($applicationSubmitted): ?>
            <div class="notice notice--success" role="status">
                <strong>Deine Bewerbung wurde erfolgreich eingereicht.</strong>
                <p>Der aktuelle Status lautet „Eingegangen“.</p>
            </div>
        <?php endif; ?>

        <?php if ($profileSaved): ?>
            <div class="notice notice--success" role="status">
                <strong>
                    Deine Profilinformationen wurden gespeichert.
                </strong>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>
                    Die Profilinformationen konnten nicht gespeichert werden.
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="account-sections">
            <details
                class="account-section"
                name="account-sections"
                <?= ($profileSaved || $errors !== []) ? 'open' : '' ?>
            >
                <summary>Profilinformationen</summary>

                <div class="account-section__body">
                    <form class="account-form" method="post" novalidate>
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape(csrfToken()) ?>"
                        >
                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >

                        <div class="account-profile-grid">
                            <div class="form-field">
                                <label for="first-name">Vorname</label>
                                <input
                                    type="text"
                                    id="first-name"
                                    name="vorname"
                                    value="<?= escape($user['vorname']) ?>"
                                    maxlength="100"
                                    autocomplete="given-name"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="last-name">Nachname</label>
                                <input
                                    type="text"
                                    id="last-name"
                                    name="nachname"
                                    value="<?= escape($user['nachname']) ?>"
                                    maxlength="100"
                                    autocomplete="family-name"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="phone">
                                    Telefonnummer
                                    <span class="hint">(optional)</span>
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="telefon"
                                    value="<?= escape((string) $user['telefon']) ?>"
                                    maxlength="50"
                                    autocomplete="tel"
                                >
                            </div>

                            <div class="form-field">
                                <label for="account-email">
                                    E-Mail-Adresse
                                </label>
                                <input
                                    type="email"
                                    id="account-email"
                                    value="<?= escape($user['email']) ?>"
                                    aria-describedby="email-hint"
                                    readonly
                                >
                                <small id="email-hint" class="form-hint">
                                    Die E-Mail-Adresse kann nicht geändert werden.
                                </small>
                            </div>
                        </div>

                        <button class="button button--fit" type="submit">
                            Änderungen speichern
                        </button>
                    </form>
                </div>
            </details>

            <details
                class="account-section"
                name="account-sections"
                <?= ($applicationSubmitted || $applicationWithdrawn) ? 'open' : '' ?>
            >
                <summary>Bewerbungen</summary>

                <div class="account-section__body">
                    <?php if ($applications === []): ?>
                        <p class="empty-state">
                            Du hast noch keine Bewerbung eingereicht.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stellenbezeichnung</th>
                                        <th scope="col">Kennziffer</th>
                                        <th scope="col">Arbeitsort</th>
                                        <th scope="col">Einreichungsdatum</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $application): ?>
                                        <tr>
                                            <td>
                                                <?= escape($application['titel']) ?>
                                            </td>
                                            <td>
                                                <?= escape($application['kennziffer']) ?>
                                            </td>
                                            <td>
                                                <?= escape($application['arbeitsort']) ?>
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
                                                <?php if (in_array(
                                                    $application['status'],
                                                    ['eingegangen', 'vorauswahl', 'interview', 'angebot'],
                                                    true
                                                )): ?>
                                                    <a
                                                        class="text-link"
                                                        href="bewerbung-zurueckziehen.php?id=<?= (int) $application['id'] ?>"
                                                    >
                                                        Zurückziehen
                                                    </a>
                                                <?php else: ?>
                                                    –
                                                <?php endif; ?>
                                            </td>
                                            </tr>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <details class="account-section" name="account-sections">
                <summary>Bewerbungsentwürfe</summary>

                <div class="account-section__body">
                    <?php if ($drafts === []): ?>
                        <p class="empty-state">
                            Du hast zurzeit keine gespeicherten
                            Bewerbungsentwürfe.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stellenbezeichnung</th>
                                        <th scope="col">Kennziffer</th>
                                        <th scope="col">Arbeitsort</th>
                                        <th scope="col">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drafts as $draft): ?>
                                        <tr>
                                            <td><?= escape($draft['titel']) ?></td>
                                            <td>
                                                <?= escape($draft['kennziffer']) ?>
                                            </td>
                                            <td>
                                                <?= escape($draft['arbeitsort']) ?>
                                            </td>
                                            <td>
                                                <a
                                                    class="text-link"
                                                    href="bewerbung.php?stelle_id=<?= (int) $draft['stelle_id'] ?>"
                                                >
                                                    Weiterbearbeiten
                                                </a>

                                                <span aria-hidden="true"> | </span>

                                                <a
                                                    class="text-link"
                                                    href="entwurf-loeschen.php?id=<?= (int) $draft['id'] ?>"
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
                </div>
            </details>

            <details
                class="account-section"
                name="account-sections"
                <?= (
                    $passwordSaved
                    || $passwordErrors !== []
                ) ? 'open' : '' ?>
            >
                <summary>Kontoeinstellungen</summary>

                <div class="account-section__body">
                    <?php if ($passwordSaved): ?>
                        <div class="notice notice--success" role="status">
                            <strong>Dein Passwort wurde geändert.</strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($passwordErrors !== []): ?>
                        <div
                            class="notice notice--error form-errors"
                            role="alert"
                        >
                            <strong>
                                Das Passwort konnte nicht geändert werden.
                            </strong>

                            <ul>
                                <?php foreach ($passwordErrors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form class="account-form" method="post" novalidate>
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape(csrfToken()) ?>"
                        >
                        <input
                            type="hidden"
                            name="action"
                            value="change_password"
                        >

                        <h2>Passwort ändern</h2>

                        <div class="account-password-fields">
                            <div class="form-field">
                                <label for="current-password">
                                    Aktuelles Passwort
                                </label>
                                <input
                                    type="password"
                                    id="current-password"
                                    name="aktuelles_passwort"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="new-password">
                                    Neues Passwort
                                </label>
                                <input
                                    type="password"
                                    id="new-password"
                                    name="neues_passwort"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="new-password-confirmation">
                                    Neues Passwort wiederholen
                                </label>
                                <input
                                    type="password"
                                    id="new-password-confirmation"
                                    name="passwort_bestaetigung"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <button class="button button--fit" type="submit">
                            Passwort ändern
                        </button>
                    </form>

                    <section class="account-deletion">
                        <h2>Kontolöschung</h2>
                        <p>
                            Du kannst dein Konto löschen oder bei
                            vorhandenen eingereichten Bewerbungen
                            eine Löschung anfordern.
                        </p>
                        <a
                            class="text-link"
                            href="konto-loeschen.php"
                        >
                            Kontolöschung
                        </a>
                    </section>
                </div>
            </details>
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