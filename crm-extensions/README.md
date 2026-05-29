# CRM WooPremium - Uitbreidingen

Uitbreiding van het bestaande CRM-systeem van WooPremium met routeplanning,
e-mailfunctionaliteit en verbeterd dealerbeheer.

**Examen:** SD_SD20 - Deel examen Realiseren
**Werkproces:** B1-K1-W3 (Realiseert software)
**Auteur:** Khayrallah Issa ()
**Stagebedrijf:** WooPremium
**Praktijkbeoordelaar:** Mohamed Talbi

---

## Inhoud van deze map

```
crm-extensions/
├── README.md                  Dit bestand
├── .gitignore                 Bestanden die niet in Git horen
├── composer.json              Externe PHP-bibliotheken (PHPMailer, php-imap)
├── config.example.php         Voorbeeld config (kopieer naar config.php)
├── sql/                       SQL-migratiescripts
│   └── 2026_05_add_new_tables.sql
├── src/                       PHP-code (backend)
│   ├── Database.php           PDO singleton + prepared statements
│   ├── controllers/           HTTP-laag (vangt requests op)
│   ├── services/              Business-logica
│   ├── repositories/          Database-queries
│   ├── models/                Data-objecten (Dealer, Route, EmailMessage, ...)
│   └── helpers/               Externe systemen (kaart-API, mailer, audit-log)
├── public/                    Frontend assets en endpoints
│   ├── api/                   Entry-points (route naar Controller)
│   ├── js/                    JavaScript per scherm
│   └── css/                   Stylesheets
├── cron/                      Achtergrondtaken
│   ├── fetch_emails.php       Elke 5 min nieuwe mails ophalen
│   └── purge_trash.php        1x per dag oude verwijderde dealers opruimen
├── tests/                     PHPUnit unit tests
└── docs/                      API-documentatie en deploy-instructies
```

---

## Installatie (lokaal, Local by WP Engine)

### 1. Plaats de bestanden

Kopieer deze hele map naar je Local-site:
```
~/Local Sites/crm-issa/app/public/crm-extensions/
```

### 2. Maak de database aan

Open Local -> klik op je site **crm-issa** -> tab **Database** -> Adminer
(of phpMyAdmin). Open de SQL-tab en plak de inhoud van:
```
sql/2026_05_add_new_tables.sql
```

Klik **Execute**. Je krijgt 5 nieuwe tabellen en 1 ALTER op `dealers`.

### 3. Installeer PHP-bibliotheken

Open een terminal in deze map en draai:
```
composer install
```

Dit downloadt PHPMailer (mail versturen) en php-imap (mail ontvangen).

### 4. Configureer

```
cp config.example.php config.php
```

Pas in `config.php` aan:
- DB_HOST, DB_NAME, DB_USER, DB_PASS (zie Local -> Database tab)
- SMTP_HOST, SMTP_USER, SMTP_PASS (mailserver van WooPremium)
- IMAP_HOST, IMAP_USER, IMAP_PASS (ontvangen)
- MAP_API (LEAFLET of GOOGLE)

`config.php` staat in `.gitignore` zodat geheimen niet in Git komen.

### 5. Plan de cronjobs in

Op productie via crontab; lokaal kun je ze handmatig draaien:
```
php cron/fetch_emails.php
php cron/purge_trash.php
```

---

## Conventies

- **PHP:** klassen in CamelCase, methodes/variabelen in camelCase, 4 spaties.
- **SQL:** tabel/kolom in snake_case, tabellen in meervoud.
- **JS:** camelCase, ES6+.
- **Commits:** korte beschrijvende berichten in het Engels, bv. `feat: route opslaan endpoint`.

---

## Branch-strategie

Per user story een aparte branch:
```
feature/US-01-select-dealers
feature/US-02-calculate-route
...
```
Na akkoord van de stagebegeleider gemerged naar `main`.

---

## Tests draaien

```
./vendor/bin/phpunit tests
```

---

## User stories status (sprint 1)

| ID | Story | Status |
|----|-------|--------|
| US-01 | Meerdere dealers selecteren | Backlog |
| US-02 | Route berekenen en tonen | Backlog |
| US-03 | Volgorde stops aanpassen | Backlog |
| US-05 | E-mail versturen | Backlog |
| US-06 | Inkomende e-mails ophalen | Backlog |
| US-07 | E-mailgeschiedenis bekijken | Backlog |
| US-09 | Dealer verwijderen | Backlog |

Volledige backlog: zie ontwerpdocument hoofdstuk 2.5.
