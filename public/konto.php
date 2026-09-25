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

function statusLabel(string $status): string
{
    $labels = [
        'eingegangen' => 'Eingegangen',
        'vorauswahl' => 'Vorauswahl',
        'interview' => 'Interview',
        'angebot' => 'Angebot',
        'abgelehnt' => 'Abgelehnt',
        'zurueckgezogen' => 'Zurückgezogen',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function formatDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '–';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? '–'
        : date('d.m.Y', $timestamp);
}

requireAuthentication();

header('Cache-Control: no-store, no-cache, must-revalidate');

$user = null;
$databaseError = false;

$applications = [];
$drafts = [];
$errors = [];
$passwordErrors = [];
$passwordSaved = filter_input(INPUT_GET, 'saved') === 'password';
$profileSaved = filter_input(INPUT_GET, 'saved') === 'profile';
$applicationSubmitted = (
    filter_input(INPUT_GET, 'submitted') === '1'
);

try {
    $statement = database()->prepare(
        'SELECT
            id,
            email,
            passwort_hash,
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

    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_POST['action'] ?? '') === 'update_profile'
    ) {
        $firstName = trim((string) ($_POST['vorname'] ?? ''));
        $lastName = trim((string) ($_POST['nachname'] ?? ''));
        $phone = trim((string) ($_POST['telefon'] ?? ''));

        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Die Anfrage konnte nicht überprüft werden.';
        }

        if ($firstName === '' || $lastName === '') {
            $errors[] = 'Vorname und Nachname sind erforderlich.';
        }

        if (
            strlen($firstName) > 100
            || strlen($lastName) > 100
        ) {
            $errors[] = 'Vorname und Nachname dürfen höchstens 100 Zeichen enthalten.';
        }

        if (strlen($phone) > 50) {
            $errors[] = 'Die Telefonnummer darf höchstens 50 Zeichen enthalten.';
        }

        $user['vorname'] = $firstName;
        $user['nachname'] = $lastName;
        $user['telefon'] = $phone;

        if ($errors === []) {
            $updateStatement = database()->prepare(
                'UPDATE benutzerkonten
                SET vorname = :vorname,
                    nachname = :nachname,
                    telefon = :telefon,
                    aktualisiert_am = CURRENT_TIMESTAMP
                WHERE id = :id'
            );

            $updateStatement->execute([
                'vorname' => $firstName,
                'nachname' => $lastName,
                'telefon' => $phone === '' ? null : $phone,
                'id' => authenticatedUserId(),
            ]);

            header('Location: konto.php?saved=profile');
            exit;
        }
    }

    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_POST['action'] ?? '') === 'change_password'
    ) {
        $currentPassword = (string) (
            $_POST['aktuelles_passwort'] ?? ''
        );
        $newPassword = (string) (
            $_POST['neues_passwort'] ?? ''
        );
        $passwordConfirmation = (string) (
            $_POST['passwort_bestaetigung'] ?? ''
        );

        if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $passwordErrors[] =
                'Die Anfrage konnte nicht überprüft werden.';
        }

        if (
            !password_verify(
                $currentPassword,
                $user['passwort_hash']
            )
        ) {
            $passwordErrors[] =
                'Das aktuelle Passwort ist nicht korrekt.';
        }

        if (strlen($newPassword) < 8) {
            $passwordErrors[] =
                'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        }

        if ($newPassword !== $passwordConfirmation) {
            $passwordErrors[] =
                'Die neuen Passwörter stimmen nicht überein.';
        }

        if ($passwordErrors === []) {
            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $updateStatement = database()->prepare(
                'UPDATE benutzerkonten
                SET passwort_hash = :passwort_hash,
                    aktualisiert_am = CURRENT_TIMESTAMP
                WHERE id = :id'
            );

            $updateStatement->execute([
                'passwort_hash' => $passwordHash,
                'id' => authenticatedUserId(),
            ]);

            header('Location: konto.php?saved=password');
            exit;
        }
    }

    $applicationStatement = database()->prepare(
        'SELECT
            bewerbungen.id,
            bewerbungen.stelle_id,
            bewerbungen.status,
            bewerbungen.eingereicht_am,
            stellen.kennziffer,
            stellen.titel,
            stellen.arbeitsort
        FROM bewerbungen
        INNER JOIN stellen
            ON stellen.id = bewerbungen.stelle_id
        WHERE bewerbungen.benutzerkonto_id = :benutzerkonto_id
        ORDER BY bewerbungen.aktualisiert_am DESC'
    );

    $applicationStatement->execute([
        'benutzerkonto_id' => authenticatedUserId(),
    ]);

    foreach ($applicationStatement->fetchAll() as $application) {
        if ($application['status'] === 'entwurf') {
            $drafts[] = $application;
        } else {
            $applications[] = $application;
        }
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
$headerLinkLabel = 'Stellenangebote';
$headerLinkHref = 'index.php';

require __DIR__ . '/includes/header.php';

?>

<main class="account-page">
    <section class="account-intro" aria-labelledby="account-heading">
        <p class="eyebrow">Bewerbendenbereich</p>
        <h1 id="account-heading">Mein Konto</h1>

        <p>
            In diesem Bereich kannst du deine Profilinformationen ergänzen,
            eingereichte Bewerbungen einsehen, gespeicherte Entwürfe
            weiterbearbeiten und deine Kontoeinstellungen ändern.
        </p>
    </section>

    <?php if ($databaseError): ?>
        <div class="notice notice--error" role="alert">
            <strong>Dein Konto konnte nicht geladen werden.</strong>
            <p>Bitte versuche es später erneut.</p>
        </div>
    <?php else: ?>

        <?php if ($applicationSubmitted): ?>
            <div class="notice notice--success" role="status">
                <strong>Deine Bewerbung wurde erfolgreich eingereicht.</strong>
                <p>Der aktuelle Status lautet „Eingegangen“.</p>
            </div>
        <?php endif; ?>

        <?php if ($profileSaved): ?>
            <div class="notice notice--success" role="status">
                <strong>
                    Deine Profilinformationen wurden gespeichert.
                </strong>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>
                    Die Profilinformationen konnten nicht gespeichert werden.
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="account-sections">
            <details
                class="account-section"
                name="account-sections"
                <?= ($profileSaved || $errors !== []) ? 'open' : '' ?>
            >
                <summary>Profilinformationen</summary>

                <div class="account-section__body">
                    <form class="account-form" method="post" novalidate>
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape(csrfToken()) ?>"
                        >
                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >

                        <div class="account-profile-grid">
                            <div class="form-field">
                                <label for="first-name">Vorname</label>
                                <input
                                    type="text"
                                    id="first-name"
                                    name="vorname"
                                    value="<?= escape($user['vorname']) ?>"
                                    maxlength="100"
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
                                    value="<?= escape($user['nachname']) ?>"
                                    maxlength="100"
                                    autocomplete="family-name"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="phone">Telefonnummer</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="telefon"
                                    value="<?= escape((string) $user['telefon']) ?>"
                                    maxlength="50"
                                    autocomplete="tel"
                                >
                            </div>

                            <div class="form-field">
                                <label for="account-email">
                                    E-Mail-Adresse
                                </label>
                                <input
                                    type="email"
                                    id="account-email"
                                    value="<?= escape($user['email']) ?>"
                                    aria-describedby="email-hint"
                                    readonly
                                >
                                <small id="email-hint" class="form-hint">
                                    Die E-Mail-Adresse kann nicht geändert werden.
                                </small>
                            </div>
                        </div>

                        <button class="button button--fit" type="submit">
                            Änderungen speichern
                        </button>
                    </form>
                </div>
            </details>

            <details
                class="account-section"
                name="account-sections"
                <?= $applicationSubmitted ? 'open' : '' ?>
            >
                <summary>Bewerbungen</summary>

                <div class="account-section__body">
                    <?php if ($applications === []): ?>
                        <p class="empty-state">
                            Du hast noch keine Bewerbung eingereicht.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stellenbezeichnung</th>
                                        <th scope="col">Kennziffer</th>
                                        <th scope="col">Arbeitsort</th>
                                        <th scope="col">Einreichungsdatum</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $application): ?>
                                        <tr>
                                            <td>
                                                <?= escape($application['titel']) ?>
                                            </td>
                                            <td>
                                                <?= escape($application['kennziffer']) ?>
                                            </td>
                                            <td>
                                                <?= escape($application['arbeitsort']) ?>
                                            </td>
                                            <td>
                                                <?= escape(formatDate(
                                                    $application['eingereicht_am']
                                                )) ?>
                                            </td>
                                            <td>
                                                <?= escape(statusLabel(
                                                    $application['status']
                                                )) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <details class="account-section" name="account-sections">
                <summary>Bewerbungsentwürfe</summary>

                <div class="account-section__body">
                    <?php if ($drafts === []): ?>
                        <p class="empty-state">
                            Du hast zurzeit keine gespeicherten
                            Bewerbungsentwürfe.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Stellenbezeichnung</th>
                                        <th scope="col">Kennziffer</th>
                                        <th scope="col">Arbeitsort</th>
                                        <th scope="col">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drafts as $draft): ?>
                                        <tr>
                                            <td><?= escape($draft['titel']) ?></td>
                                            <td>
                                                <?= escape($draft['kennziffer']) ?>
                                            </td>
                                            <td>
                                                <?= escape($draft['arbeitsort']) ?>
                                            </td>
                                            <td>
                                                <a
                                                    class="text-link"
                                                    href="bewerbung.php?stelle_id=<?= (int) $draft['stelle_id'] ?>"
                                                >
                                                    Weiterbearbeiten
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <details
                class="account-section"
                name="account-sections"
                <?= (
                    $passwordSaved
                    || $passwordErrors !== []
                ) ? 'open' : '' ?>
            >
                <summary>Kontoeinstellungen</summary>

                <div class="account-section__body">
                    <?php if ($passwordSaved): ?>
                        <div class="notice notice--success" role="status">
                            <strong>Dein Passwort wurde geändert.</strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($passwordErrors !== []): ?>
                        <div
                            class="notice notice--error form-errors"
                            role="alert"
                        >
                            <strong>
                                Das Passwort konnte nicht geändert werden.
                            </strong>

                            <ul>
                                <?php foreach ($passwordErrors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form class="account-form" method="post" novalidate>
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape(csrfToken()) ?>"
                        >
                        <input
                            type="hidden"
                            name="action"
                            value="change_password"
                        >

                        <h2>Passwort ändern</h2>

                        <div class="account-password-fields">
                            <div class="form-field">
                                <label for="current-password">
                                    Aktuelles Passwort
                                </label>
                                <input
                                    type="password"
                                    id="current-password"
                                    name="aktuelles_passwort"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="new-password">
                                    Neues Passwort
                                </label>
                                <input
                                    type="password"
                                    id="new-password"
                                    name="neues_passwort"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>

                            <div class="form-field">
                                <label for="new-password-confirmation">
                                    Neues Passwort wiederholen
                                </label>
                                <input
                                    type="password"
                                    id="new-password-confirmation"
                                    name="passwort_bestaetigung"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <button class="button button--fit" type="submit">
                            Passwort ändern
                        </button>
                    </form>
                </div>
            </details>
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