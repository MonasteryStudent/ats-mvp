<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

requireAuthentication();

header('Cache-Control: no-store, no-cache, must-revalidate');

$user = null;
$databaseError = false;

try {
    $statement = database()->prepare(
        'SELECT
            id,
            email,
            vorname,
            nachname,
            telefon,
            rolle
         FROM benutzerkonten
         WHERE id = :id
           AND ist_aktiv = 1'
    );

    $statement->execute([
        'id' => authenticatedUserId(),
    ]);

    $user = $statement->fetch();

    if ($user === false) {
        signOutUser();

        header('Location: anmelden.php?from=overview');
        exit;
    }
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden des Benutzerkontos: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Mein Konto | FiktivFit Karriere';
$headerLinkLabel = 'Zur Stellenübersicht';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="auth-page">
    <section class="auth-card" aria-labelledby="account-heading">
        <p class="eyebrow">Mein Konto</p>

        <?php if ($databaseError): ?>
            <h1 id="account-heading">Konto nicht verfügbar</h1>

            <div class="notice notice--error" role="alert">
                <strong>Dein Konto konnte nicht geladen werden.</strong>
                <p>Bitte versuche es später erneut.</p>
            </div>
        <?php else: ?>
            <h1 id="account-heading">
                Hallo, <?= escape($user['vorname']) ?>!
            </h1>

            <p>
                Du bist als
                <strong>
                    <?= escape(
                        $user['vorname'] . ' ' . $user['nachname']
                    ) ?>
                </strong>
                angemeldet.
            </p>

            <p>
                E-Mail-Adresse:
                <strong><?= escape($user['email']) ?></strong>
            </p>

            <form action="abmelden.php" method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape(csrfToken()) ?>"
                >

                <button class="button" type="submit">
                    Abmelden
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>