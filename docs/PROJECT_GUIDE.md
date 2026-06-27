# Projektdokumentation AutiSta Bewerbertest

Diese Datei ist der schnelle Einstieg fuer die naechste Arbeitssitzung. Sie beschreibt Zweck, Architektur, wichtige Dateien, Datenhaltung, Deployment und typische Aenderungsstellen.

## Zweck

Die Anwendung ist ein PHP/SQLite-basierter Bewerbertest fuer ein lokales Netzwerk oder einen Docker-Server. Bewerber starten einen zeitlich begrenzten Test, beantworten zusammengestellte Fragen und Admins werten Ergebnisse im geschuetzten Adminbereich aus.

## Aktueller Stand

- PHP-App ohne Framework, Frontcontroller in `public/index.php`
- SQLite-Datenbank in `storage/app.sqlite`
- Automatische Installation/Migration beim ersten Request
- Adminbereich fuer Fragen, Auswertungen, Einladungen, Einstellungen, Benutzer und Wartung
- Dockerfile und Compose-Dateien vorhanden
- GitHub Actions bauen ein Image nach `ghcr.io/jens0102/autista-bewerbertest:latest`
- Produktivstart ueber `compose.prod.yml`

## Wichtige URLs

Lokal mit Docker:

```text
http://localhost:8080/
```

Admin:

```text
/index.php/admin/login
```

Initialer Admin:

```text
Benutzer: admin
Passwort: admin123
```

Nach dem ersten Login muss das Passwort geaendert werden.

## Projektstruktur

```text
public/index.php                     Frontcontroller und Routing
app/bootstrap.php                    Session, DB, Helpers, Rendering, Scoring
app/Controllers/TestController.php   Bewerberseiten, Teststart, Test, Autosave
app/Controllers/AdminController.php  Adminbereich, Fragen, Auswertung, Settings
app/Services/TestService.php         Testlogik, Bewertung, Import, Einladungen
app/Services/MigrationService.php    Versionierte DB-Migrationen
app/Views/                           PHP-Templates
database/schema.sql                  Ziel-Schema fuer neue Installationen
database/questions.json              Initialer Fragenkatalog
scripts/self_test.php                Kleiner Test fuer Bewertungslogik
Dockerfile                           Lokales/produktives Image
docker-compose.yml                   Lokale Entwicklung
compose.prod.yml                     Serverbetrieb mit GHCR-Image
.github/workflows/docker-publish.yml GitHub Action fuer Image-Build
storage/                             Laufzeitdaten, nicht versioniert
```

## Routing

Alle Routen stehen zentral in `public/index.php`.

Bewerber-Routen:

- `/`
- `/test`
- `/test/autosave`
- `/thanks`

Admin-Routen:

- `/admin/login`
- `/admin`
- `/admin/questions`
- `/admin/question/edit`
- `/admin/attempts`
- `/admin/attempt`
- `/admin/settings`
- `/admin/users`
- `/admin/invitations`
- `/admin/maintenance`

Die App erzeugt Links bewusst ueber `index.php`, damit sie auch ohne URL-Rewriting unter XAMPP/Apache funktioniert.

## Datenbank und Migrationen

Die Datenbank liegt standardmaessig hier:

```text
storage/app.sqlite
```

Beim Start ruft `public/index.php` immer `TestService::installIfNeeded()` auf. Dadurch werden Schema und Migrationen automatisch angewendet.

Wenn eine neue Spalte oder Tabelle gebraucht wird:

1. `database/schema.sql` fuer Neuinstallationen aktualisieren.
2. Eine neue Migration in `app/Services/MigrationService.php` ergaenzen.
3. Falls die App historisch auch ohne MigrationService laufen soll, ggf. die `ensure...`-Methoden in `TestService.php` pruefen.
4. Import-/Exportlogik anpassen, falls Fragenfelder betroffen sind.

Wichtige Tabellen:

- `admins`
- `settings`
- `questions`
- `attempts`
- `answers`
- `answer_drafts`
- `invitations`
- `question_catalogs`

## Fragenmodell

Fragen werden in `questions` gespeichert. Wichtige Felder:

- `category`: Kompetenzbereich/Gruppierung
- `competency`: feinere Kompetenzbeschreibung
- `difficulty`: Schwierigkeitsgrad
- `document_ref`: interner Dokumentbezug
- `source_hint`: optionaler Quellenhinweis fuer Bewerber
- `type`: `single`, `multiple`, `true_false`, `ordering`, `matching`
- `options`: JSON
- `correct_answers`: JSON
- `explanation`: Mustererklaerung fuer Auswertung/Admin
- `points`: Punkte
- `active`: Frage aktiv
- `sort_order`: Reihenfolge

Wichtig: `document_ref` ist intern, `source_hint` wird im Test nur angezeigt, wenn es ausgefuellt ist.

## Bewertungslogik

Die Bewertungsfunktion ist `score_question()` in `app/bootstrap.php`.

Regeln:

- Single/True-False: volle Punktzahl nur bei exakter Antwort
- Multiple Choice: Teilbewertung mit Abzug fuer falsche Antworten
- Ordering: anteilige Punkte pro korrekter Position
- Matching: anteilige Punkte pro korrekter Zuordnung

Nach Aenderungen an der Bewertungslogik ausfuehren:

```bash
php scripts/self_test.php
```

## Bewerber-Workflow

1. Bewerber oeffnet Startseite oder persoenlichen Einladungslink.
2. Name/E-Mail werden erfasst.
3. `TestService::createAttempt()` legt den Versuch an.
4. Aktive Fragen werden geladen, optional per `question_limit` begrenzt.
5. Fragen werden als Snapshot im Versuch gespeichert.
6. Antworten werden waehrend des Tests als Draft gespeichert.
7. Bei Abgabe bewertet `TestService::finalizeAttempt()` alle Antworten.
8. Bewerber sieht nur die Abschlussseite, keine Loesungen.

## Admin-Workflow

Admins koennen:

- Dashboard und Kennzahlen sehen
- Fragen anlegen, bearbeiten, importieren, aktivieren/deaktivieren
- Bewerberversuche filtern, exportieren, resetten oder loeschen
- Detailauswertungen pruefen
- Review-Entscheidung und Admin-Notiz speichern
- Einladungslinks erzeugen
- Einstellungen bearbeiten
- Admin-Benutzer verwalten
- Backup, Fragenexport und Logs ueber Wartung nutzen

## Docker lokal

Start:

```bash
docker compose up -d --build
```

Status:

```bash
docker compose ps
```

Logs:

```bash
docker compose logs -f
```

Stop:

```bash
docker compose down
```

Die lokale Compose-Datei baut aus dem lokalen Quellcode und nutzt das Volume `autista-storage`.

## Linux-Server Deployment

Das produktive Image wird per GitHub Actions gebaut:

```text
ghcr.io/jens0102/autista-bewerbertest:latest
```

Auf dem Server:

```bash
mkdir -p /opt/autista-bewerbertest
cd /opt/autista-bewerbertest
curl -fsSL -o compose.prod.yml https://raw.githubusercontent.com/jens0102/autista-bewerbertest/main/compose.prod.yml
docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
```

Wenn Port `8080` belegt ist, in `compose.prod.yml` z. B. aendern:

```yaml
ports:
  - "8081:80"
```

Status:

```bash
docker compose -f compose.prod.yml ps
```

Logs:

```bash
docker compose -f compose.prod.yml logs --tail 50
```

Update:

```bash
docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
```

## Persistenz und Backups

Nicht ins Git-Repo:

- `storage/app.sqlite`
- `storage/backups/*`
- `storage/logs/*`

In Docker liegt `storage/` im Volume:

```text
autista-storage
```

Alternativ im Adminbereich die Wartungsseite fuer Datenbank-Backup nutzen.

## Typische Aenderungsstellen

Neue Adminseite:

1. Route in `public/index.php`
2. Methode in `AdminController`
3. Template unter `app/Views/admin/`
4. Navigation in `app/Views/layout.php`

Neue Bewerberseite:

1. Route in `public/index.php`
2. Methode in `TestController`
3. Template unter `app/Views/`

Neues Fragenfeld:

1. `database/schema.sql`
2. Migration in `MigrationService`
3. Admin-Formular `app/Views/admin/question-form.php`
4. Speichern in `AdminController::questionEdit()`
5. Import/Export in `TestService`
6. Anzeige im Test oder in Auswertung, falls noetig

Neue Einstellung:

1. Default in `TestService::installIfNeeded()`
2. Formular in `app/Views/admin/settings.php`
3. Speichern in `AdminController::settings()`
4. Nutzung per `setting('key', 'default')`

## Sicherheitsnotizen

- Admin-Passwort direkt nach Erstlogin aendern.
- `storage/app.sqlite` enthaelt Bewerberdaten und darf nicht veroeffentlicht werden.
- Bei oeffentlichem Server HTTPS vorsehen, idealerweise ueber Reverse Proxy.
- GitHub Package kann privat sein; dann muss der Server mit `docker login ghcr.io` angemeldet sein.
- Bewerber sehen keine Sofortbewertung und keine Musterantworten.

## Qualitaetschecks vor Push

Mindestens:

```bash
php -l public/index.php
php -l app/bootstrap.php
php -l app/Controllers/AdminController.php
php -l app/Controllers/TestController.php
php -l app/Services/TestService.php
php -l app/Services/MigrationService.php
php scripts/self_test.php
docker compose config
```

Bei Docker-Aenderungen:

```bash
docker compose up -d --build
docker compose ps
```

Startseite pruefen:

```bash
curl -I http://localhost:8080/
```

## Aktuelle offene Punkte

- Server nutzt aktuell Port `8080`; bei Portkonflikt auf `8081` oder Reverse Proxy wechseln.
- Noch kein E-Mail-Versand fuer Einladungen.
- Noch keine automatische Backup-Rotation ausser optionaler Bereinigung alter Bewerberdaten.
- Noch keine umfangreiche Test-Suite, nur `scripts/self_test.php`.
