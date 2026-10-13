<main class="account-page">
    <a class="back-link" href="recruiting.php">
        &larr; Zur Recruitingübersicht
    </a>

    <?php if ($databaseError): ?>
        <section class="notice notice--error" role="alert">
            <h1>Stellenformular konnte nicht geladen werden</h1>
            <p>Bitte versuche es später erneut.</p>
        </section>
    <?php elseif ($jobNotFound): ?>
        <section class="notice notice--error">
            <h1>Stelle nicht gefunden</h1>
            <p>Die gewünschte Stelle ist nicht verfügbar.</p>
            <a class="text-link" href="recruiting.php">
                Zur Recruitingübersicht
            </a>
        </section>
    <?php else: ?>
        <section class="account-intro">
            <p class="eyebrow">Recruitingbereich</p>

            <h1>
                <?= $isEditMode
                    ? 'Stelle bearbeiten'
                    : 'Neue Stelle anlegen' ?>
            </h1>

            <p>
                Erfasse die Informationen für die öffentliche
                Stellenausschreibung.
            </p>
        </section>

        <?php if ($errors !== []): ?>
            <div class="notice notice--error form-errors" role="alert">
                <strong>
                    Die Stelle konnte nicht gespeichert werden.
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            class="application-form job-form"
            method="post"
            novalidate
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(csrfToken()) ?>"
            >

            <section class="application-form__section">
                <h2>Stellendaten</h2>

                <div class="job-form__grid">
                    <div class="form-field">
                        <label for="job-title">
                            Stellenbezeichnung
                        </label>
                        <input
                            type="text"
                            id="job-title"
                            name="titel"
                            value="<?= escape($form['titel']) ?>"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="reference">Kennziffer</label>
                        <input
                            type="text"
                            id="reference"
                            name="kennziffer"
                            value="<?= escape($form['kennziffer']) ?>"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="location">Arbeitsort</label>
                        <input
                            type="text"
                            id="location"
                            name="arbeitsort"
                            value="<?= escape($form['arbeitsort']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="entry-date">
                            Eintrittsdatum
                        </label>
                        <input
                            type="date"
                            id="entry-date"
                            name="eintrittsdatum"
                            value="<?= escape(
                                $form['eintrittsdatum']
                            ) ?>"
                        >
                    </div>

                    <div class="form-field">
                        <label for="career-level">
                            Karrierestufe
                        </label>
                        <input
                            type="text"
                            id="career-level"
                            name="karrierestufe"
                            value="<?= escape(
                                $form['karrierestufe']
                            ) ?>"
                            maxlength="100"
                        >
                    </div>

                    <div class="form-field">
                        <label for="employment-type">
                            Beschäftigungsgrad
                        </label>
                        <select
                            id="employment-type"
                            name="beschaeftigungsgrad"
                            required
                        >
                            <option value="">Bitte auswählen</option>
                            <option
                                value="Vollzeit"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Vollzeit'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Vollzeit
                            </option>
                            <option
                                value="Teilzeit"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Teilzeit'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Teilzeit
                            </option>
                            <option
                                value="Minijob"
                                <?= $form['beschaeftigungsgrad']
                                    === 'Minijob'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Minijob
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="duration">Befristung</label>
                        <select
                            id="duration"
                            name="befristung"
                            required
                        >
                            <option value="">Bitte auswählen</option>
                            <option
                                value="Unbefristet"
                                <?= $form['befristung']
                                    === 'Unbefristet'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Unbefristet
                            </option>
                            <option
                                value="Befristet"
                                <?= $form['befristung']
                                    === 'Befristet'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Befristet
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="salary">Vergütung</label>
                        <input
                            type="text"
                            id="salary"
                            name="verguetung"
                            value="<?= escape($form['verguetung']) ?>"
                            maxlength="150"
                        >
                    </div>
                </div>
            </section>

            <section class="application-form__section">
                <h2>Stellenbeschreibung</h2>

                <?php
                $textareas = [
                    'wer_wir_sind' => 'Wer wir sind',
                    'das_erwartet_dich' => 'Das erwartet dich',
                    'aufgaben' => 'Deine Aufgaben',
                    'anforderungen' => 'Das bringst du mit',
                    'leistungen' => 'Das bieten wir dir',
                ];
                ?>

                <?php foreach ($textareas as $fieldName => $label): ?>
                    <div class="form-field">
                        <label for="<?= escape($fieldName) ?>">
                            <?= escape($label) ?>
                        </label>
                        <textarea
                            id="<?= escape($fieldName) ?>"
                            name="<?= escape($fieldName) ?>"
                            maxlength="5000"
                            required
                        ><?= escape($form[$fieldName]) ?></textarea>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="application-form__section">
                <h2>Ansprechperson und Veröffentlichung</h2>

                <div class="job-form__grid">
                    <div class="form-field">
                        <label for="contact-name">
                            Ansprechperson
                        </label>
                        <input
                            type="text"
                            id="contact-name"
                            name="ansprechperson_name"
                            value="<?= escape(
                                $form['ansprechperson_name']
                            ) ?>"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="contact-email">
                            E-Mail-Adresse
                        </label>
                        <input
                            type="email"
                            id="contact-email"
                            name="ansprechperson_email"
                            value="<?= escape(
                                $form['ansprechperson_email']
                            ) ?>"
                            maxlength="254"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="job-status">
                            Stellenstatus
                        </label>
                        <select
                            id="job-status"
                            name="status"
                            required
                        >
                            <option
                                value="aktiv"
                                <?= $form['status'] === 'aktiv'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Aktiv
                            </option>
                            <option
                                value="inaktiv"
                                <?= $form['status'] === 'inaktiv'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inaktiv
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="application-form__actions">
                <a
                    class="button button--secondary"
                    href="recruiting.php"
                >
                    Abbrechen
                </a>

                <button class="button" type="submit">
                    <?= $isEditMode
                        ? 'Änderungen speichern'
                        : 'Stelle anlegen' ?>
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>