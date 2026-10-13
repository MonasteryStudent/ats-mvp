# ATS-MVP

Minimum Viable Product eines Applicant-Tracking-Systems für ein fiktives Fitnessunternehmen. Der ATS-MVP wird im Rahmen eines Hochschulprojekts entwickelt und befindet sich derzeit im Aufbau. Er dient ausschließlich zu Lehr- und Demonstrationszwecken und ist nicht für den produktiven Einsatz oder die Verarbeitung echter personenbezogener Daten vorgesehen.

## Technischer Rahmen

- HTML und CSS für die Oberfläche
- PHP ohne Framework für die serverseitige Logik
- SQLite als relationale Datenbank
- XAMPP als lokale Entwicklungsumgebung

## Projektstruktur

| Verzeichnis oder Datei | Aufgabe |
|---|---|
| `.htaccess` | Beschränkt den direkten Webzugriff auf `public/` und schützt `public/includes/`. |
| `config/` | Enthält Konfiguration, Datenbankverbindung und gemeinsame Funktionen für Sitzungen, Zugriffsschutz, Uploads und Ausgabe. |
| `database/` | Enthält das SQL-Schema und fiktive Beispieldaten. |
| `public/` | Enthält die öffentlich aufrufbaren PHP-Endpunkte und CSS-Dateien. |
| `src/` | Enthält Datenzugriffsfunktionen sowie die Validierung und Verarbeitung der Anwendungsfunktionen. |
| `templates/` | Enthält die HTML-Vorlagen der dynamischen Seiten. |
| `scripts/` | Enthält Kommandozeilenskripte zur Datenbankinitialisierung. |
| `storage/` | Enthält die lokale SQLite-Datenbank und hochgeladene Bewerbungsunterlagen. |

Die `.htaccess`-Datei beschränkt den direkten Webzugriff auf den öffentlichen Bereich. Die lokale SQLite-Datenbank und hochgeladene Bewerbungsunterlagen werden nicht mit Git versioniert.

## Lokale Einrichtung mit XAMPP

1. Den Ordner `ats-mvp` nach `C:\xampp\htdocs\` kopieren.
2. In `C:\xampp\php\php.ini` prüfen, ob `pdo_sqlite` aktiviert ist.
3. Ein Terminal im Projektordner `C:\xampp\htdocs\ats-mvp` öffnen.
4. Die Datenbank initialisieren:

   ```powershell
   & C:\xampp\php\php.exe .\scripts\init_database.php
   ```

5. Apache über das XAMPP Control Panel starten.
6. Im Browser `http://localhost/ats-mvp/public/` öffnen.

Das Initialisierungsskript kann erneut ausgeführt werden. Bereits vorhandene Tabellen und Beispielstellen werden dabei nicht dupliziert.