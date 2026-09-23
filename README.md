# ATS-MVP

Minimum Viable Product eines Applicant-Tracking-Systems für ein fiktives Fitnessunternehmen. Der ATS-MVP wird im Rahmen eines Hochschulprojekts entwickelt und befindet sich derzeit im Aufbau. Er dient ausschließlich zu Lehr- und Demonstrationszwecken und ist nicht für den produktiven Einsatz oder die Verarbeitung echter personenbezogener Daten vorgesehen.

## Technischer Rahmen

- HTML und CSS für die Oberfläche
- JavaScript für spätere clientseitige Interaktionen
- PHP ohne Framework für die serverseitige Logik
- SQLite als relationale Datenbank
- XAMPP als lokale Entwicklungsumgebung

## Projektstruktur

```text
ats-mvp/
├── .htaccess           Beschränkung des Webzugriffs auf public/
├── config/             Konfiguration und Datenbankverbindung
├── database/           SQL-Schema und Beispieldaten
├── public/             Öffentlich erreichbare PHP-, CSS- und JS-Dateien
├── scripts/            Kommandozeilenskripte
└── storage/            SQLite-Datenbank und hochgeladene Dateien
```

Die SQLite-Datenbank und hochgeladene Bewerbungsunterlagen liegen außerhalb von `public/`. Eine `.htaccess`-Datei verhindert in der lokalen XAMPP-Umgebung den direkten Webzugriff auf die übrigen Projektverzeichnisse.

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