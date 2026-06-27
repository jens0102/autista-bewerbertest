# AutiSta Bewerbertest

Technische Einstiegshilfe fuer die Weiterentwicklung: [`docs/PROJECT_GUIDE.md`](docs/PROJECT_GUIDE.md)

PHP/SQLite-Anwendung für einen 30-Minuten-Bewerbertest im lokalen Netzwerk.

## Start unter XAMPP/Apache

Ordner `autista-bewerbertest` nach `htdocs` kopieren und im Browser öffnen:

`http://localhost/autista-bewerbertest/public/`

Die Anwendung erzeugt interne Links bewusst über `index.php`, z. B.:

`http://localhost/autista-bewerbertest/public/index.php/test`

Dadurch funktioniert sie auch ohne aktiviertes URL-Rewriting.

## Start mit PHP Built-in Server

```bash
cd autista-bewerbertest
php -S 0.0.0.0:8080 -t public
```

Dann öffnen:

`http://localhost:8080/`

Im Netzwerk ersetzen Sie `localhost` durch die IP-Adresse des Rechners.

## Start mit Docker

Image bauen und Container starten:

```bash
docker compose up -d --build
```

Dann öffnen:

`http://localhost:8080/`

Die SQLite-Datenbank, Logs und Backups liegen im Docker-Volume `autista-storage`.
Container stoppen:

```bash
docker compose down
```

## Start auf einem Linux-Server

Voraussetzungen auf dem Server:

- Docker Engine
- Docker Compose Plugin
- Zugriff auf `ghcr.io/jens0102/autista-bewerbertest:latest`

Bei einem privaten GitHub-Repository zuerst mit einem GitHub Token anmelden, das `read:packages` erlaubt:

```bash
echo "GITHUB_TOKEN" | docker login ghcr.io -u jens0102 --password-stdin
```

Dann `compose.prod.yml` auf den Server kopieren und starten:

```bash
docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
```

Die Anwendung ist danach auf Port `8080` erreichbar. Die SQLite-Datenbank, Logs und Backups liegen im Docker-Volume `autista-storage`.

Update auf eine neue Version:

```bash
docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
```

## Admin

Initial:

- Benutzer: `admin`
- Passwort: `admin123`

Bitte nach dem ersten Login unter **Einstellungen** ändern.

## Funktionen

- gemeinsamer Bewerberlink mit Name/E-Mail
- nur eine Teilnahme je E-Mail-Adresse
- 30-Minuten-Countdown
- Fragenpool mit 100 Vorlagen
- Fragen aktivieren/deaktivieren
- Fragen bearbeiten/anlegen
- automatische Bewertung
- Auswertung nach Kompetenzbereichen
- Musterantwort neben Bewerberantwort
- CSV-Export
- Löschfunktion für Bewerberdaten
- Fragenversion und Fragen-Snapshot je Teilnahme
- Statusverwaltung für gestartete, abgegebene und abgelaufene Tests
- erneute Freigabe/Reset einzelner Bewerber
- Teilbewertung für Multiple Choice, Reihenfolge und Zuordnung
- Filter, Kompetenzprofil und Druckansicht in der Auswertung
- Review-Workflow mit Entscheidung und Admin-Notiz pro Teilnahme
- persönliche Einladungslinks für Bewerber
- Import neuer Fragenkataloge im Adminbereich
- konfigurierbare Bestehensgrenze, Fragenanzahl, Hinweis- und Datenschutztexte
- optionale automatische Bereinigung alter Bewerberdaten
- versionierte Datenbankmigrationen
- Zwischenspeichern von Antworten während des Tests
- Admin-Login-Sperre nach Fehlversuchen und Session-Timeout
- Verwaltung mehrerer Admin-Benutzer mit Aktivstatus und Entsperrfunktion
- Fehlerlogging nach `storage/logs/app.log`
- Admin-Wartungsseite für Datenbank-Backup, Fragenexport und Logeinsicht

## Änderung: Gruppierter Testmodus

Die Fragen werden im Bewerbermodus nach Kompetenzbereichen gruppiert. Zwischen den Abschnitten wird mit „Weiter“ und „Zurück“ navigiert. Die Antwortoptionen werden pro Bewerber und Frage stabil gemischt, damit die richtige Antwort nicht immer an erster Stelle steht.

## Projektstruktur

- `public/index.php`: Frontcontroller und Routing
- `app/bootstrap.php`: gemeinsame Helfer, Datenbankverbindung und View-Renderer
- `app/Controllers`: Controller für Bewerber- und Adminbereich
- `app/Services`: wiederverwendbare Anwendungslogik, z. B. Installation und Testauswertung
- `app/Services/MigrationService.php`: versionierte SQLite-Migrationen
- `app/Views`: HTML-Templates
- `scripts/import_questions.php`: JSON-Fragenkatalog importieren
- `scripts/self_test.php`: kleiner Selbsttest für Bewertungslogik
- `database/schema.sql`: SQLite-Schema
- `database/questions.json`: initialer Fragenpool
- `storage/app.sqlite`: lokale SQLite-Datenbank, wird bei Bedarf erzeugt und nicht versioniert
