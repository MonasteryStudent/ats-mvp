<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session.php';

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

startSession();

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

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
    'email_bestaetigung' => '',
];

$conditionsAccepted = false;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formData['vorname'] = trim(
        (string) ($_POST['vorname'] ?? '')
    );
    $formData['nachname'] = trim(
        (string) ($_POST['nachname'] ?? '')
    );
    $formData['email'] = trim(
        (string) ($_POST['email'] ?? '')
    );
    $formData['email_bestaetigung'] = trim(
        (string) ($_POST['email_bestaetigung'] ?? '')
    );

    $password = (string) ($_POST['passwort'] ?? '');
    $passwordConfirmation = (string) (
        $_POST['passwort_bestaetigung'] ?? ''
    );

    $conditionsAccepted = (
        $_POST['bedingungen_akzeptiert'] ?? ''
    ) === '1';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if ($formData['vorname'] === '') {
        $errors[] = 'Bitte gib deinen Vornamen ein.';
    } elseif (strlen($formData['vorname']) > 100) {
        $errors[] = 'Der Vorname darf höchstens 100 Zeichen enthalten.';
    }

    if ($formData['nachname'] === '') {
        $errors[] = 'Bitte gib deinen Nachnamen ein.';
    } elseif (strlen($formData['nachname']) > 100) {
        $errors[] = 'Der Nachname darf höchstens 100 Zeichen enthalten.';
    }

    if ($formData['email'] === '') {
        $errors[] = 'Bitte gib deine E-Mail-Adresse ein.';
    } elseif (
        filter_var($formData['email'], FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    }

    if (
        strcasecmp(
            $formData['email'],
            $formData['email_bestaetigung']
        ) !== 0
    ) {
        $errors[] = 'Die E-Mail-Adressen stimmen nicht überein.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen enthalten.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if (!$conditionsAccepted) {
        $errors[] = (
            'Bitte akzeptiere die Nutzungsbedingungen '
            . 'und bestätige die Datenschutzhinweise.'
        );
    }

    if ($errors === []) {
        try {
            $emailCheck = database()->prepare(
                'SELECT id
                 FROM benutzerkonten
                 WHERE email = :email
                 LIMIT 1'
            );
            $emailCheck->execute([
                'email' => $formData['email'],
            ]);

            if ($emailCheck->fetch() !== false) {
                $errors[] = (
                    'Für diese E-Mail-Adresse besteht bereits ein Konto.'
                );
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($passwordHash === false) {
                    throw new RuntimeException(
                        'Das Passwort konnte nicht verarbeitet werden.'
                    );
                }

                $statement = database()->prepare(
                    'INSERT INTO benutzerkonten (
                        email,
                        passwort_hash,
                        vorname,
                        nachname,
                        rolle,
                        ist_aktiv
                    ) VALUES (
                        :email,
                        :passwort_hash,
                        :vorname,
                        :nachname,
                        :rolle,
                        :ist_aktiv
                    )'
                );

                $statement->execute([
                    'email' => strtolower($formData['email']),
                    'passwort_hash' => $passwordHash,
                    'vorname' => $formData['vorname'],
                    'nachname' => $formData['nachname'],
                    'rolle' => 'bewerbend',
                    'ist_aktiv' => 1,
                ]);

                header(
                    'Location: '
                    . $loginHref
                    . '&registered=1'
                );
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler bei der Registrierung: '
                . $exception->getMessage()
            );

            $errors[] = (
                'Das Konto konnte aus technischen Gründen '
                . 'nicht angelegt werden. Bitte versuche es erneut.'
            );

            http_response_code(500);
        }
    }
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

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>Bitte überprüfe deine Eingaben.</strong>

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
                <label for="first-name">Vorname</label>
                <input
                    type="text"
                    id="first-name"
                    name="vorname"
                    value="<?= escape($formData['vorname']) ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="form-field">
                <label for="last-name">Nachname</label>
                <input
                    type="text"
                    id="last-name"
                    name="nachname"
                    value="<?= escape($formData['nachname']) ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="form-field">
                <label for="email">E-Mail-Adresse</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escape($formData['email']) ?>"
                    maxlength="254"
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
                    value="<?= escape(
                        $formData['email_bestaetigung']
                    ) ?>"
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
                    name="passwort"
                    minlength="8"
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
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <label class="checkbox-field">
                <input
                    type="checkbox"
                    name="bedingungen_akzeptiert"
                    value="1"
                    <?= $conditionsAccepted ? 'checked' : '' ?>
                    required
                >
                <span>
                    Ich habe die
                    <a
                        href="datenschutz.php"
                        target="_blank"
                        rel="noopener"
                    >
                        Datenschutzhinweise
                    </a>
                    zur Kenntnis genommen und akzeptiere die
                    <a
                        href="nutzungsbedingungen.php"
                        target="_blank"
                        rel="noopener"
                    >
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
                href="<?= escape($loginHref) ?>"
            >
                Zur Anmeldung
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>