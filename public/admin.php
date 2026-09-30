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

requireRole('admin');

header('Cache-Control: no-store, no-cache, must-revalidate');

$recruitingAccounts = [];
$databaseError = false;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$formData = [
    'vorname' => '',
    'nachname' => '',
    'email' => '',
];

$creationErrors = [];

$statusResult = filter_input(INPUT_GET, 'status');

$accountCreated = filter_input(INPUT_GET, 'created') === '1';
$accountActivated = $statusResult === 'activated';
$accountDeactivated = $statusResult === 'deactivated';

if ($requestMethod === 'POST') {
    $formData['vorname'] = trim(
        (string) ($_POST['vorname'] ?? '')
    );

    $formData['nachname'] = trim(
        (string) ($_POST['nachname'] ?? '')
    );

    $formData['email'] = strtolower(trim(
        (string) ($_POST['email'] ?? '')
    ));

    $password = (string) ($_POST['passwort'] ?? '');

    $passwordConfirmation = (string) (
        $_POST['passwort_bestaetigung'] ?? ''
    );

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $creationErrors[] = (
            'Die Anfrage ist abgelaufen. '
            . 'Bitte lade die Seite neu und versuche es erneut.'
        );
    }

    if ($formData['vorname'] === '') {
        $creationErrors[] = 'Bitte gib einen Vornamen ein.';
    } elseif (strlen($formData['vorname']) > 100) {
        $creationErrors[] = (
            'Der Vorname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['nachname'] === '') {
        $creationErrors[] = 'Bitte gib einen Nachnamen ein.';
    } elseif (strlen($formData['nachname']) > 100) {
        $creationErrors[] = (
            'Der Nachname darf höchstens 100 Zeichen enthalten.'
        );
    }

    if ($formData['email'] === '') {
        $creationErrors[] = 'Bitte gib eine E-Mail-Adresse ein.';
    } elseif (
        filter_var(
            $formData['email'],
            FILTER_VALIDATE_EMAIL
        ) === false
    ) {
        $creationErrors[] = (
            'Bitte gib eine gültige E-Mail-Adresse ein.'
        );
    } elseif (strlen($formData['email']) > 254) {
        $creationErrors[] = (
            'Die E-Mail-Adresse darf höchstens 254 Zeichen enthalten.'
        );
    }

    if (strlen($password) < 8) {
        $creationErrors[] = (
            'Das Passwort muss mindestens 8 Zeichen enthalten.'
        );
    }

    if ($password !== $passwordConfirmation) {
        $creationErrors[] = (
            'Die Passwörter stimmen nicht überein.'
        );
    }

    if ($creationErrors === []) {
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
                $creationErrors[] = (
                    'Für diese E-Mail-Adresse besteht bereits '
                    . 'ein Benutzerkonto.'
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

                $insertStatement = database()->prepare(
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

                $insertStatement->execute([
                    'email' => $formData['email'],
                    'passwort_hash' => $passwordHash,
                    'vorname' => $formData['vorname'],
                    'nachname' => $formData['nachname'],
                    'rolle' => 'recruiting',
                    'ist_aktiv' => 1,
                ]);

                header('Location: admin.php?created=1');
                exit;
            }
        } catch (Throwable $exception) {
            error_log(
                'Fehler beim Anlegen eines Recruitingkontos: '
                . $exception->getMessage()
            );

            $creationErrors[] = (
                'Das Recruitingkonto konnte aus technischen '
                . 'Gründen nicht angelegt werden.'
            );

            http_response_code(500);
        }
    }
}

try {
    $statement = database()->prepare(
        'SELECT
            id,
            vorname,
            nachname,
            email,
            ist_aktiv
         FROM benutzerkonten
         WHERE rolle = :rolle
         ORDER BY nachname, vorname'
    );

    $statement->execute([
        'rolle' => 'recruiting',
    ]);

    $recruitingAccounts = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log(
        'Fehler beim Laden der Adminübersicht: '
        . $exception->getMessage()
    );

    $databaseError = true;
    http_response_code(500);
}

$pageTitle = 'Adminübersicht | FiktivFit Karriere';
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page recruiting-page">
    <section
        class="account-intro"
        aria-labelledby="admin-heading"
    >
        <p class="eyebrow">Adminbereich</p>
        <h1 id="admin-heading">Adminübersicht</h1>

        <p>
            In diesem Bereich werden die Recruitingkonten verwaltet.
        </p>
    </section>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>
                Die Adminübersicht konnte nicht geladen werden.
            </strong>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php else: ?>

        <?php if ($accountCreated): ?>
            <div class="notice notice--success" role="status">
                <strong>Das Recruitingkonto wurde angelegt.</strong>
            </div>
        <?php elseif ($accountActivated): ?>
            <div class="notice notice--success" role="status">
                <strong>Das Recruitingkonto wurde aktiviert.</strong>
            </div>
        <?php elseif ($accountDeactivated): ?>
            <div class="notice notice--success" role="status">
                <strong>Das Recruitingkonto wurde deaktiviert.</strong>
            </div>
        <?php endif; ?>

        <div class="recruiting-sections">
            <section
                class="recruiting-section"
                aria-labelledby="accounts-heading"
            >
                <div class="recruiting-section__header">
                    <h2 id="accounts-heading">Recruitingkonten</h2>
                </div>

                <?php if ($recruitingAccounts === []): ?>
                    <p class="empty-state">
                        Es wurden noch keine Recruitingkonten angelegt.
                    </p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">E-Mail-Adresse</th>
                                    <th scope="col">Kontostatus</th>
                                    <th scope="col">Aktion</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (
                                    $recruitingAccounts as $account
                                ): ?>
                                    <?php
                                    $isActive = (
                                        (int) $account['ist_aktiv'] === 1
                                    );

                                    $status = $isActive
                                        ? 'aktiv'
                                        : 'inaktiv';
                                    ?>

                                    <tr>
                                        <td>
                                            <?= escape(
                                                $account['vorname']
                                                . ' '
                                                . $account['nachname']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escape($account['email']) ?>
                                        </td>

                                        <td>
                                            <span
                                                class="status-label status-label--<?= $status ?>"
                                            >
                                                <?= $isActive
                                                    ? 'Aktiv'
                                                    : 'Inaktiv' ?>
                                            </span>
                                        </td>

                                        <td>
                                            <form
                                                class="account-table__action-form"
                                                action="recruitingkonto-status.php"
                                                method="post"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape(csrfToken()) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="account_id"
                                                    value="<?= (int) $account['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="<?= $isActive
                                                        ? 'deactivate'
                                                        : 'activate' ?>"
                                                >

                                                <button
                                                    class="account-table__action<?= $isActive
                                                        ? ' account-table__action--danger'
                                                        : '' ?>"
                                                    type="submit"
                                                >
                                                    <?= $isActive
                                                        ? 'Deaktivieren'
                                                        : 'Aktivieren' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            
            <section
                class="recruiting-section"
                aria-labelledby="create-account-heading"
            >
                <div class="recruiting-section__header">
                    <h2 id="create-account-heading">
                        Neues Konto anlegen
                    </h2>
                </div>

                <?php if ($creationErrors !== []): ?>
                    <div
                        class="notice notice--error form-errors"
                        role="alert"
                    >
                        <strong>Bitte überprüfe deine Eingaben.</strong>

                        <ul>
                            <?php foreach ($creationErrors as $error): ?>
                                <li><?= escape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form
                    class="admin-account-form"
                    method="post"
                    novalidate
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape(csrfToken()) ?>"
                    >

                    <div class="form-field">
                        <label for="recruiting-first-name">
                            Vorname
                        </label>

                        <input
                            type="text"
                            id="recruiting-first-name"
                            name="vorname"
                            value="<?= escape($formData['vorname']) ?>"
                            maxlength="100"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="recruiting-last-name">
                            Nachname
                        </label>

                        <input
                            type="text"
                            id="recruiting-last-name"
                            name="nachname"
                            value="<?= escape($formData['nachname']) ?>"
                            maxlength="100"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="recruiting-email">
                            E-Mail-Adresse
                        </label>

                        <input
                            type="email"
                            id="recruiting-email"
                            name="email"
                            value="<?= escape($formData['email']) ?>"
                            maxlength="254"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="recruiting-password">
                            Passwort
                        </label>

                        <input
                            type="password"
                            id="recruiting-password"
                            name="passwort"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="recruiting-password-confirmation">
                            Passwort wiederholen
                        </label>

                        <input
                            type="password"
                            id="recruiting-password-confirmation"
                            name="passwort_bestaetigung"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button class="button" type="submit">
                        Konto anlegen
                    </button>
                </form>
            </section>
        </div>

        <form
            class="account-logout"
            action="abmelden.php"
            method="post"
        >
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
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>