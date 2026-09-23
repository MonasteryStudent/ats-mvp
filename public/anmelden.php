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

startSession();

if (userIsAuthenticated()) {
    header('Location: konto.php');
    exit;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$from = filter_input(INPUT_GET, 'from');
$returnJobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$registrationSuccessful = (
    $requestMethod === 'GET'
    && filter_input(INPUT_GET, 'registered') === '1'
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
    $registrationHref = (
        'registrieren.php?from=job&id=' . $returnJobId
    );
}

$email = '';
$errors = [];

if ($requestMethod === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if (
        $email === ''
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    }

    if ($password === '') {
        $errors[] = 'Bitte gib dein Passwort ein.';
    }

    if ($errors === []) {
        try {
            $statement = database()->prepare(
                'SELECT
                    id,
                    email,
                    passwort_hash,
                    rolle,
                    ist_aktiv
                 FROM benutzerkonten
                 WHERE email = :email
                 LIMIT 1'
            );

            $statement->execute([
                'email' => $email,
            ]);

            $user = $statement->fetch();

            if (
                $user === false
                || !password_verify(
                    $password,
                    $user['passwort_hash']
                )
            ) {
                $errors[] = (
                    'E-Mail-Adresse oder Passwort ist nicht korrekt.'
                );
            } elseif ((int) $user['ist_aktiv'] !== 1) {
                $errors[] = 'Dieses Benutzerkonto ist deaktiviert.';
            } else {
                signInUser(
                    (int) $user['id'],
                    $user['rolle']
                );

                header('Location: konto.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler bei der Anmeldung: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Die Anmeldung ist aus technischen Gründen '
                . 'nicht möglich. Bitte versuche es erneut.'
            );

            http_response_code(500);
        }
    }
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

<?php require __DIR__ . '/includes/footer.php'; ?>