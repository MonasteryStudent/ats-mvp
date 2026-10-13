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