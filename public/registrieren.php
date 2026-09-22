<?php

declare(strict_types=1);

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$loginHref = 'anmelden.php?from=overview';

if (
    $from === 'job'
    && $returnJobId !== false
    && $returnJobId !== null
    && $returnJobId > 0
) {
    $loginHref = 'anmelden.php?from=job&id=' . $returnJobId;
}

$pageTitle = 'Registrieren | FiktivFit Karriere';
$headerLinkLabel = 'Zur Anmeldung';
$headerLinkHref = $loginHref;

require __DIR__ . '/includes/header.php';

?>

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

        <form method="post">
            <div class="form-field">
                <label for="first-name">Vorname</label>
                <input
                    type="text"
                    id="first-name"
                    name="vorname"
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
                    autocomplete="family-name"
                    required
                >
            </div>

            <div class="form-field">
                <label for="email">E-Mail-Adresse</label>
                <input
                    type="email"
                    id="email"
                    name="email"
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
                    autocomplete="new-password"
                    required
                >
            </div>

            <label class="checkbox-field">
                <input
                    type="checkbox"
                    name="bedingungen_akzeptiert"
                    value="1"
                    required
                >
                <span>
                    Ich habe die
                    <a href="datenschutz.php">Datenschutzhinweise</a>
                    zur Kenntnis genommen und akzeptiere die
                    <a href="nutzungsbedingungen.php">
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
                href="<?= htmlspecialchars(
                    $loginHref,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>"
            >
                Zur Anmeldung
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>