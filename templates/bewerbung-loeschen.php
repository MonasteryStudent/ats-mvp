<main class="auth-page">
    <section class="auth-card auth-card--confirmation">
        <h1>Bewerbung löschen</h1>

        <?php if ($error !== null): ?>
            <div class="notice notice--error" role="alert">
                <p><?= escape($error) ?></p>
            </div>

            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        <?php else: ?>
            <p>
                Möchtest du die zurückgezogene Bewerbung von
                <strong><?= escape(
                    $application['vorname']
                    . ' '
                    . $application['nachname']
                ) ?></strong>
                für die Stelle
                <strong><?= escape($application['titel']) ?></strong>
                endgültig löschen?
            </p>

            <p>
                Dabei werden die Bewerbung, die interne Bewertung,
                die Recruitingnotiz und sämtliche zugehörigen
                Bewerbungsunterlagen entfernt.
                Das Benutzerkonto bleibt bestehen.
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

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="aufbewahrung_geprueft"
                        value="1"
                        required
                    >

                    <span>
                        Ich habe geprüft, dass kein weiterer
                        Aufbewahrungsgrund besteht, insbesondere
                        kein konkreter Rechtsstreit.
                    </span>
                </label>

                <button class="button" type="submit">
                    Endgültig löschen
                </button>

                <a
                    class="text-link"
                    href="bewerbung-sichten.php?id=<?= (int) $application['id'] ?>"
                >
                    Abbrechen
                </a>
            </form>
        <?php endif; ?>
    </section>
</main>