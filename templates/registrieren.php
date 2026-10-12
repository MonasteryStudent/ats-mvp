<main class="auth-page">
    <section
        class="auth-card auth-card--wide"
        aria-labelledby="registration-heading"
    >
        <p class="eyebrow">Mein Konto</p>
        <h1 id="registration-heading">Konto anlegen</h1>

        <p>
            Erstelle ein Konto, um Bewerbungen zu speichern,
            einzureichen und ihren Status einzusehen.
        </p>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>Bitte überprüfe deine Eingaben.</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(csrfToken()) ?>"
            >

            <div class="form-field">
                <label for="first-name">Vorname</label>
                <input
                    type="text"
                    id="first-name"
                    name="vorname"
                    value="<?= escape($formData['vorname']) ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="form-field">
                <label for="last-name">Nachname</label>
                <input
                    type="text"
                    id="last-name"
                    name="nachname"
                    value="<?= escape($formData['nachname']) ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="form-field">
                <label for="email">E-Mail-Adresse</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escape($formData['email']) ?>"
                    maxlength="254"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-field">
                <label for="email-confirmation">
                    E-Mail-Adresse wiederholen
                </label>
                <input
                    type="email"
                    id="email-confirmation"
                    name="email_bestaetigung"
                    value="<?= escape(
                        $formData['email_bestaetigung']
                    ) ?>"
                    maxlength="254"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-field">
                <label for="password">Passwort</label>
                <input
                    type="password"
                    id="password"
                    name="passwort"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-field">
                <label for="password-confirmation">
                    Passwort wiederholen
                </label>
                <input
                    type="password"
                    id="password-confirmation"
                    name="passwort_bestaetigung"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <label class="checkbox-field">
                <input
                    type="checkbox"
                    name="bedingungen_akzeptiert"
                    value="1"
                    <?= $conditionsAccepted ? 'checked' : '' ?>
                    required
                >
                <span>
                    Ich habe die
                    <a
                        href="datenschutz.php"
                        target="_blank"
                        rel="noopener"
                    >
                        Datenschutzhinweise
                    </a>
                    zur Kenntnis genommen und akzeptiere die
                    <a
                        href="nutzungsbedingungen.php"
                        target="_blank"
                        rel="noopener"
                    >
                        Nutzungsbedingungen
                    </a>.
                </span>
            </label>

            <button class="button" type="submit">
                Konto anlegen
            </button>
        </form>

        <p class="auth-card__footer">
            Bereits registriert?
            <a
                class="text-link"
                href="<?= escape($loginHref) ?>"
            >
                Zur Anmeldung
            </a>
        </p>
    </section>
</main>