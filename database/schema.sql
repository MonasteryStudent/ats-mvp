PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS benutzerkonten (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    passwort_hash TEXT NOT NULL,
    vorname TEXT NOT NULL,
    nachname TEXT NOT NULL,
    telefon TEXT,
    rolle TEXT NOT NULL DEFAULT 'bewerbend'
        CHECK (rolle IN ('bewerbend', 'recruiting', 'admin')),
    ist_aktiv INTEGER NOT NULL DEFAULT 1
        CHECK (ist_aktiv IN (0, 1)),
    erstellt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stellen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kennziffer TEXT NOT NULL UNIQUE,
    titel TEXT NOT NULL,
    arbeitsort TEXT NOT NULL,
    eintrittsdatum TEXT,
    karrierestufe TEXT,
    beschaeftigungsgrad TEXT NOT NULL,
    befristung TEXT NOT NULL,
    verguetung TEXT,
    wer_wir_sind TEXT NOT NULL,
    das_erwartet_dich TEXT NOT NULL,
    aufgaben TEXT NOT NULL,
    anforderungen TEXT NOT NULL,
    leistungen TEXT NOT NULL,
    ansprechperson_name TEXT NOT NULL,
    ansprechperson_email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'aktiv'
        CHECK (status IN ('aktiv', 'inaktiv')),
    erstellt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bewerbungen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    benutzerkonto_id INTEGER NOT NULL,
    stelle_id INTEGER NOT NULL,
    fruehestmoegliches_eintrittsdatum TEXT,
    nachricht TEXT,
    status TEXT NOT NULL DEFAULT 'entwurf'
        CHECK (
            status IN (
                'entwurf',
                'eingegangen',
                'vorauswahl',
                'interview',
                'angebot',
                'abgelehnt',
                'zurueckgezogen'
            )
        ),
    eingereicht_am TEXT,
    bewertung INTEGER
        CHECK (bewertung IS NULL OR bewertung BETWEEN 1 AND 5),
    recruitingnotiz TEXT,
    erstellt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (benutzerkonto_id)
        REFERENCES benutzerkonten (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (stelle_id)
        REFERENCES stellen (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    UNIQUE (benutzerkonto_id, stelle_id),
    CHECK (
        (status = 'entwurf' AND eingereicht_am IS NULL)
        OR (status <> 'entwurf' AND eingereicht_am IS NOT NULL)
    )
);

CREATE TABLE IF NOT EXISTS dokumente (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bewerbung_id INTEGER NOT NULL,
    dokumenttyp TEXT NOT NULL
        CHECK (dokumenttyp IN ('lebenslauf', 'anschreiben', 'zeugnis', 'anlage')),
    originaldateiname TEXT NOT NULL,
    speicherdateiname TEXT NOT NULL UNIQUE,
    mime_typ TEXT NOT NULL DEFAULT 'application/pdf'
        CHECK (mime_typ = 'application/pdf'),
    dateigroesse INTEGER NOT NULL
        CHECK (dateigroesse > 0),
    hochgeladen_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bewerbung_id)
        REFERENCES bewerbungen (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bewerbungen_stelle_status
    ON bewerbungen (stelle_id, status);

CREATE INDEX IF NOT EXISTS idx_bewerbungen_benutzerkonto
    ON bewerbungen (benutzerkonto_id);

CREATE INDEX IF NOT EXISTS idx_dokumente_bewerbung
    ON dokumente (bewerbung_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_eindeutige_pflichtdokumente
    ON dokumente (bewerbung_id, dokumenttyp)
    WHERE dokumenttyp IN ('lebenslauf', 'anschreiben');

