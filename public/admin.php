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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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