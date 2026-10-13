<main class="auth-page">
    <section
        class="auth-card auth-card--confirmation"
        aria-labelledby="deletion-heading"
    >
        <p class="eyebrow">Kontoeinstellungen</p>
        <h1 id="deletion-heading">Kontolöschung</h1>

        <?php if ($databaseError): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Die Kontolöschung konnte nicht verarbeitet werden.
                </strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php else: ?>
            <?php if ($hasSubmittedApplications): ?>
                <p>
                    Es sind bereits eingereichte Bewerbungen vorhanden.
                    Nach deiner Bestätigung wird dein Kontozugang
                    gesperrt und du wirst abgemeldet.
                </p>
                <p>
                    Das Recruiting prüft anschließend deinen Löschantrag
                    und einen möglichen weiteren Aufbewahrungsbedarf
                    deiner Bewerbungsdaten.
                </p>
            <?php else: ?>
                <p>
                    Dein Konto sowie vorhandene Bewerbungsentwürfe
                    und ihre Unterlagen werden nach deiner Bestätigung
                    endgültig gelöscht. Dieser Vorgang kann nicht
                    rückgängig gemacht werden.
                </p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div
                    class="notice notice--error form-errors"
                    role="alert"
                >
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <input
                    type="hidden"
                    name="deletion_mode"
                    value="<?= escape($deletionMode) ?>"
                >

                <div class="form-field">
                    <label for="deletion-password">
                        Aktuelles Passwort zur Bestätigung
                    </label>
                    <input
                        type="password"
                        id="deletion-password"
                        name="passwort"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="button" type="submit">
                    <?= $hasSubmittedApplications
                        ? 'Kontolöschung anfordern'
                        : 'Konto endgültig löschen' ?>
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-card__footer">
            <a class="text-link" href="konto.php">
                Abbrechen und zurück zum Konto
            </a>
        </p>
    </section>
</main>