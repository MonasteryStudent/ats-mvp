<main class="auth-page">
    <section class="auth-card" aria-labelledby="login-heading">
        <p class="eyebrow">Mein Konto</p>
        <h1 id="login-heading">Anmelden</h1>

        <p>
            Melde dich an, um deine Bewerbungen zu verwalten und ihren
            aktuellen Status einzusehen.
        </p>

        <?php if ($deletionResult === 'deleted'): ?>
            <div class="notice notice--success" role="status">
                <strong>
                    Dein Konto und die zugehörigen Entwürfe
                    und Unterlagen wurden gelöscht.
                </strong>
            </div>
        <?php elseif ($deletionResult === 'requested'): ?>
            <div class="notice notice--success" role="status">
                <strong>Dein Löschantrag wurde gespeichert.</strong>
                <p>
                    Dein Kontozugang ist gesperrt.
                    Das Recruiting prüft die Löschung deiner Daten.
                </p>
            </div>
        <?php elseif ($deletionResult === 'cleanup_pending'): ?>
            <div class="notice notice--error" role="alert">
                <strong>Dein Konto wurde gelöscht.</strong>
                <p>
                    Einzelne Dateien konnten nicht entfernt werden.
                    Bitte informiere die zuständige Stelle,
                    damit die Löschung vervollständigt wird.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($registrationSuccessful): ?>
            <div class="notice notice--success" role="status">
                <strong>Dein Konto wurde erfolgreich angelegt.</strong>
                <p>
                    Du kannst dich jetzt mit deinen Zugangsdaten anmelden.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>Die Anmeldung war nicht erfolgreich.</strong>

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
                <label for="email">E-Mail-Adresse</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escape($email) ?>"
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
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="button" type="submit">
                Anmelden
            </button>
        </form>

        <p class="auth-card__footer">
            Noch kein Konto?
            <a
                class="text-link"
                href="<?= escape($registrationHref) ?>"
            >
                Jetzt registrieren
            </a>
        </p>
    </section>
</main>