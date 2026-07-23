# Ticketsystem (PHP)

Vollständiges Ticketsystem als reine PHP-Website — läuft auf jedem Plesk-Shared-Hosting-Paket
mit PHP + MySQL, ohne Node.js, ohne root, ohne Build-Schritt.

## Sicherheitsmaßnahmen (aktueller Standard)

- **Passwörter:** `password_hash()` / `password_verify()` (bcrypt, automatisches Rehashing bei
  Algorithmus-Updates)
- **SQL:** ausschließlich PDO mit echten Prepared Statements (`PDO::ATTR_EMULATE_PREPARES => false`)
  — keine SQL-Injection-Angriffsfläche
- **XSS:** jede Ausgabe von Nutzereingaben läuft über `e()` (= `htmlspecialchars`)
- **CSRF:** jedes Formular enthält ein Session-gebundenes Token, das serverseitig via
  `hash_equals()` geprüft wird
- **Sessions:** `httponly`, `samesite=Lax`, `secure` (automatisch bei HTTPS), Session-ID-Regeneration
  bei Login (schützt vor Session-Fixation)
- **Brute-Force-Schutz:** Login sperrt ein Konto nach 5 Fehlversuchen für 15 Minuten
- **Config per `.htaccess` gesperrt:** `config/config.php` liegt zwar im selben Ordner wie die
  öffentlichen Seiten, ist aber über eine eigene `.htaccess`-Datei im Ordner komplett gegen
  direkten Browser-Zugriff abgeriegelt
- **Sicherheits-Header:** `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy`
  etc. per `.htaccess`
- **Kein `imap`-PHP-Modul:** seit PHP 8.4 aus dem Core entfernt und als veraltet eingestuft;
  stattdessen die aktiv gepflegte Bibliothek `webklex/php-imap`
- **Keine externen Font-/Icon-CDNs:** Schrift (Plus Jakarta Sans) und Icons (Boxicons) liegen
  selbst gehostet unter `assets/fonts/` bzw. `assets/vendor/` — Besucher-IPs werden dadurch nicht
  an Drittanbieter wie Google Fonts übertragen (in Deutschland datenschutzrechtlich relevant)
- **HTML-Bereinigung für Antworten/Vorlagen:** Der WYSIWYG-Editor (Quill) liefert HTML; `sanitizeHtml()`
  in `bootstrap.php` lässt serverseitig nur eine kleine, für Email/Anzeige sichere Tag-/Attribut-Auswahl
  zu (keine `<script>`, keine Event-Handler-Attribute, `href` nur `http(s)`/`mailto`) — unabhängig davon,
  was der Editor clientseitig bereits abfängt

## Architektur

```
TicketSystem/            <- Plesk Document Root (ALLES liegt direkt hier drin)
  index.php, login.php, logout.php, tickets.php, ticket.php, install.php
  admin/teams.php, admin/users.php, admin/templates.php, admin/analytics.php
  cron/poll_mailbox.php
  assets/style.css, assets/app.js
  assets/fonts/                 <- selbst gehostete Schrift (Plus Jakarta Sans) + Boxicons-Icon-Font
  assets/vendor/                <- Boxicons CSS + Quill (WYSIWYG-Editor), beide lokal eingebunden
  config/                <- per .htaccess von außen gesperrt (siehe unten)
    config.example.php
  src/                    <- per .htaccess von außen gesperrt (PHP-Klassen)
    bootstrap.php, Auth.php, Mailer.php, ImapFetcher.php
    Models/Ticket.php, Team.php, User.php, Message.php, Template.php
    Views/header.php, footer.php, admin_tabs.php
  sql/schema.sql          <- per .htaccess von außen gesperrt
  vendor/                  <- von Composer erzeugt, per .htaccess von außen gesperrt
  composer.json
  .htaccess
```

**Wichtig zur Sicherheit:** Da hier alles (auch `config/`, `src/`, `sql/`, `vendor/`) im selben,
öffentlich erreichbaren Ordner liegt, sperrt jeweils eine eigene `.htaccess`-Datei in diesen vier
Ordnern jeglichen direkten Zugriff von außen (egal welcher Dateityp). Diese `.htaccess`-Dateien
sind bereits im Projekt enthalten — bitte nicht löschen. PHP-Dateien in `src/` und `config/` werden
ausschließlich intern über `require`/`include` eingebunden, niemals direkt aufgerufen.

## Deployment auf Plesk Shared Hosting

### 1. Dateien hochladen

Lade den **kompletten Ordnerinhalt** (also `index.php`, `login.php`, `admin/`, `assets/`, `cron/`,
`config/`, `src/`, `sql/`, `composer.json` — alles) direkt in das Document-Root-Verzeichnis deiner
(Sub-)Domain in Plesk hoch (per Git, FTP oder Dateimanager). Es ist **keine** gesonderte
Einstellung des Document Root auf einen Unterordner nötig — alles liegt bereits auf einer Ebene.

Die Ordner `config/`, `src/`, `sql/` und `vendor/` sind zwar technisch im selben, öffentlich
erreichbaren Verzeichnis wie die restlichen Seiten, werden aber durch eine jeweils eigene
`.htaccess`-Datei darin komplett gegen direkten Zugriff von außen abgeriegelt (siehe
Architektur-Abschnitt oben). Diese `.htaccess`-Dateien sind im Projekt bereits enthalten.

### 2. PHP-Version

Stelle in Plesk unter **Websites & Domains → PHP-Einstellungen** mindestens **PHP 8.1** ein
(empfohlen: 8.2 oder 8.3 — 8.4 funktioniert ebenfalls, ohne dass die entfernte imap-Extension
gebraucht wird).

### 3. Composer-Abhängigkeiten installieren

Falls dein Plesk einen **"Composer"-Button** in den PHP-Einstellungen der Domain hat: einfach
dort auf **Composer Install** klicken (nutzt automatisch die `composer.json`).

Falls nicht verfügbar: lokal `composer install` ausführen und den erzeugten `vendor/`-Ordner
mit hochladen (per FTP/Dateimanager, in dasselbe Verzeichnis wie `config/`, `src/`, `index.php`
etc. — die `vendor/.htaccess` sperrt den direkten Zugriff darauf bereits ab).

### 4. MySQL-Datenbank anlegen

In Plesk: **Websites & Domains → Datenbanken → Datenbank hinzufügen**. Die Zugangsdaten notierst
du dir für den nächsten Schritt.

### 5. Konfigurieren

`config/config.example.php` nach `config/config.php` kopieren und ausfüllen:
- MySQL-Zugangsdaten (aus Schritt 4)
- `setup_token` und `cron_secret`: jeweils einen langen Zufallsstring eintragen (z. B. lokal mit
  `php -r "echo bin2hex(random_bytes(32));"` erzeugen)
- `seed_admin_email` / `seed_admin_password`: dein gewünschter erster Admin-Zugang
- IMAP- und SMTP-Zugangsdaten deines Postfachs

### 6. Installation ausführen

Einmalig im Browser aufrufen:
```
https://deinedomain.de/install.php?token=DEIN_SETUP_TOKEN
```
Das legt alle Tabellen an sowie den Admin-User, Standard-Teams und Beispiel-Vorlagen.

**Danach `install.php` unbedingt löschen** (oder zumindest per `.htaccess` sperren) — sie darf
nicht dauerhaft erreichbar bleiben.

### 7. Mail-Abruf per Plesk "Geplante Aufgabe"

Der Mail-Abruf läuft nicht dauerhaft im Hintergrund, sondern wird regelmäßig von Plesk
angestoßen. Zwei Varianten, je nachdem was dein Hosting-Paket bei **Geplante Aufgaben** erlaubt:

**Variante A — PHP-Skript direkt ausführen (falls verfügbar):**
```
php /var/www/vhosts/deinedomain.de/TicketSystem/cron/poll_mailbox.php --token=DEIN_CRON_SECRET
```

**Variante B — "URL aufrufen":**
```
https://deinedomain.de/cron/poll_mailbox.php?token=DEIN_CRON_SECRET
```

Intervall: alle 2–5 Minuten.

### 8. Login

Auf `https://deinedomain.de/login.php` mit dem in `config.php` hinterlegten Admin-Zugang anmelden.

## Email-Postfach einrichten

Für Gmail/Outlook empfiehlt sich ein **App-Passwort** (nicht das normale Login-Passwort), da diese
Anbieter 2FA erzwingen.

Antworten aus dem System enthalten automatisch `[FALL-<nummer>]` im Betreff (fünfstellig,
z. B. `[FALL-00142]`) und setzen die Email-Header `In-Reply-To`/`References` korrekt. Antwortet
der Kunde per "Antworten" in seinem Email-Programm, wird die Mail garantiert dem richtigen Fall
zugeordnet — auch wenn kein Tag mehr im Betreff steht. Das ältere Tag-Format `[TICKET-<nummer>]`
aus Zeiten vor der Umbenennung wird beim Einlesen weiterhin erkannt, damit laufende Email-Threads
nicht abreißen.

## Team-Routing konfigurieren

Im Admin-Bereich → "Teams & Routing" Teams mit Keyword-Listen anlegen (z. B. Team "Rechnungen"
mit Keywords `rechnung,zahlung,billing`). Trifft ein Keyword auf Betreff oder Text einer neuen
Mail zu, wird das Ticket automatisch diesem Team zugewiesen. Ein Team lässt sich als "Standard"
markieren — das ist der Fallback, falls kein Keyword passt.

## Analytics

Im Admin-Bereich → "Analytics" gibt es eine Übersicht mit Kennzahlen (Fälle gesamt, offen,
wartend, dringend, durchschnittliche Erstantwortzeit), dem Verlauf neuer Fälle der letzten 14
Tage sowie Verteilungen nach Status, Priorität, Team und offener Fälle je Agent.

## Antwort-Vorlagen bearbeiten

Im Admin-Bereich → "Antwort-Vorlagen" lassen sich Vorlagen anlegen, mit einem WYSIWYG-Editor
(Fett/Kursiv/Unterstrichen, Listen, Zitat, Links) bearbeiten und löschen. Derselbe Editor steht
auch beim Antworten direkt im Fall zur Verfügung — eine ausgewählte Vorlage wird mit den
Platzhalterwerten (Name, Fall-Nr., Betreff) befüllt in den Editor eingesetzt und lässt sich vor
dem Versenden noch anpassen. Alte, vor dem Editor angelegte Vorlagen (reiner Text) werden beim
Öffnen automatisch in Absätze umgewandelt.

## Lokale Entwicklung

```bash
composer install
cp config/config.example.php config/config.php   # DATABASE-Zugangsdaten anpassen
php -S localhost:8000
# Danach einmalig: http://localhost:8000/install.php?token=DEIN_SETUP_TOKEN
```

**Hinweis:** Der eingebaute PHP-Server (`php -S`) wertet keine `.htaccess`-Dateien aus — die
Sperren für `config/`, `src/`, `sql/`, `vendor/` greifen dort also nicht. Das ist für lokale Tests
auf `localhost` unkritisch, auf einem echten Apache-Server (Plesk) greifen die Sperren aber ganz
normal.

## Nächste sinnvolle Ausbaustufen (optional)

- Anhänge aus Emails speichern und im UI anzeigen
- Volltextsuche über Ticket-Inhalte statt nur Betreff (MySQL `FULLTEXT`-Index)
- Passwort-Reset-Funktion ("Passwort vergessen")
- Zwei-Faktor-Authentifizierung für Admin-Accounts
- Benachrichtigungen (z. B. Email) bei neuen/zugewiesenen Tickets
- SLA-Timer und Eskalationsregeln
