<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Entwurf löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="konto.php">Zurück zu Mein Konto</a>
        <?php else: ?>
            <p>
                Möchtest du deinen Entwurf für
                <strong><?= escape($draft['titel']) ?></strong>
                einschließlich aller hochgeladenen Unterlagen löschen?
                Diese Aktion kann nicht rückgängig gemacht werden.
            </p>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $draft['id'] ?>"
                >

                <button class="button" type="submit">
                    Entwurf endgültig löschen
                </button>

                <a class="text-link" href="konto.php">Abbrechen</a>
            </form>
        <?php endif; ?>
    </section>
</main>