<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Stelle löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        <?php elseif ($hasApplications): ?>
            <p>
                Die Stelle
                <strong><?= escape($job['titel']) ?></strong>
                kann nicht gelöscht werden, da Bewerbungen oder
                Bewerbungsentwürfe zugeordnet sind.
            </p>

            <p>
                Über die Bearbeitung kann die Stelle deaktiviert werden.
            </p>

            <a
                class="text-link"
                href="stelle-verwalten.php?id=<?= (int) $job['id'] ?>"
            >
                Stelle bearbeiten
            </a>
        <?php else: ?>
            <p>
                Möchtest du die Stelle
                <strong><?= escape($job['titel']) ?></strong>
                mit der Kennziffer
                <strong><?= escape($job['kennziffer']) ?></strong>
                endgültig löschen?
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
                    value="<?= (int) $job['id'] ?>"
                >

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="loeschung_bestaetigt"
                        value="1"
                        required
                    >

                    <span>
                        Ich bestätige die endgültige Löschung dieser Stelle.
                    </span>
                </label>

                <button class="button" type="submit">
                    Endgültig löschen
                </button>

                <a class="text-link" href="recruiting.php">
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>