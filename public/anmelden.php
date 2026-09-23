<?php

declare(strict_types=1);

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$registrationSuccessful = (
    filter_input(INPUT_GET, 'registered') === '1'
);

$pageTitle = 'Anmelden | FiktivFit Karriere';
$headerLinkLabel = 'Zur Stellenübersicht';
$headerLinkHref = 'index.php';
$registrationHref = 'registrieren.php?from=overview';

if (
    $from === 'job'
    && $returnJobId !== false
    && $returnJobId !== null
    && $returnJobId > 0
) {
    $headerLinkLabel = 'Zur Stelle';
    $headerLinkHref = 'stelle.php?id=' . $returnJobId;
    $registrationHref = 'registrieren.php?from=job&id=' . $returnJobId;
}

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card" aria-labelledby="login-heading">
        <p class="eyebrow">Mein Konto</p>
        <h1 id="login-heading">Anmelden</h1>

        <p>
            Melde dich an, um deine Bewerbungen zu verwalten und ihren
            aktuellen Status einzusehen.
        </p>

        <form method="post">
            <?php if ($registrationSuccessful): ?>
                <div class="notice notice--success" role="status">
                    <strong>Dein Konto wurde erfolgreich angelegt.</strong>
                    <p>Du kannst dich jetzt mit deinen Zugangsdaten anmelden.</p>
                </div>
            <?php endif; ?>
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
                href="<?= htmlspecialchars(
                    $registrationHref,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>"
            >          
                Jetzt registrieren
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>