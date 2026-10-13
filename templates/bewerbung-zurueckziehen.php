<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Bewerbung zurückziehen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="konto.php">
                Zurück zu Mein Konto
            </a>
        <?php else: ?>
            <p>
                Möchtest du deine Bewerbung für die Stelle
                <strong><?= escape($application['titel']) ?></strong>
                wirklich zurückziehen?
            </p>

            <p>
                Damit beendest du deine Teilnahme am Auswahlverfahren.
                Deine Unterlagen werden dadurch nicht sofort gelöscht.
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
                    value="<?= (int) $application['id'] ?>"
                >

                <button class="button" type="submit">
                    Bewerbung zurückziehen
                </button>

                <a class="text-link" href="konto.php">
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>