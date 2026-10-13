<main class="auth-page">
    <section
        class="auth-card auth-card--confirmation"
        aria-labelledby="deletion-heading"
    >
        <p class="eyebrow">Recruitingbereich</p>
        <h1 id="deletion-heading">Kontolöschung bestätigen</h1>

        <?php if ($databaseError): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Der Löschantrag konnte nicht verarbeitet werden.
                </strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php elseif (!$account): ?>
            <div class="notice notice--error" role="alert">
                <strong>
                    Es wurde kein offener Löschantrag gefunden.
                </strong>
            </div>
        <?php else: ?>
            <p>
                Das folgende Bewerbendenkonto soll endgültig
                gelöscht werden:
            </p>

            <p>
                <strong>
                    <?= escape(
                        $account['vorname']
                        . ' '
                        . $account['nachname']
                    ) ?>
                </strong>
                <br>
                <?= escape($account['email']) ?>
            </p>

            <p>
                Die Löschung umfasst das Konto, sämtliche Bewerbungen
                und Entwürfe, Bewertungen, Recruitingnotizen sowie
                alle zugehörigen Unterlagen. Sie kann nicht
                rückgängig gemacht werden.
            </p>

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
                    name="id"
                    value="<?= (int) $account['id'] ?>"
                >

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="aufbewahrung_geprueft"
                        value="1"
                        required
                    >
                    <span>
                        Ich habe sämtliche zugehörigen Bewerbungen
                        geprüft. Es besteht kein weiterer
                        Aufbewahrungsgrund, insbesondere kein
                        konkreter Rechtsstreit.
                    </span>
                </label>

                <button class="button" type="submit">
                    Konto und zugehörige Daten endgültig löschen
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-card__footer">
            <a class="text-link" href="loeschantraege.php">
                Zurück zur Übersicht der Löschanträge
            </a>
        </p>
    </section>
</main>