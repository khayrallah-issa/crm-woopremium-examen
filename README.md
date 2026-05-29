# CRM WooPremium - Examen-uitbreiding

**Auteur:** Khayrallah Issa
**Project:** Deel-examen Realiseren - SD_SD20
**Stagebedrijf:** WooPremium
**Praktijkbeoordelaar:** Mohamed Talbi

---

## Documentatie

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — waarom de code op deze manier is opgebouwd, gelaagde architectuur, ontwerpkeuzes
- **[CODE_CONVENTIONS.md](CODE_CONVENTIONS.md)** — gehanteerde codestijl (PSR-12), naamgeving, commit-conventies
- **[examen-documenten/](examen-documenten/)** — ontwerpdocument, realisatiedocument, aanvullende bewijsstukken

---

## Wat is dit?

Een uitbreiding van het bestaande CRM-systeem van WooPremium voor het beheren van interieur-dealers. Gebouwd in PHP (met WordPress als basis), JavaScript (Leaflet voor de kaart) en MySQL.

De uitbreiding voegt drie hoofdgebieden toe:

1. **Routeplanning** - meerdere dealer-adressen tot een dagroute combineren via OSRM
2. **Inkomende e-mail** - mails uit een mailbox ophalen en automatisch koppelen aan de juiste dealer (IMAP)
3. **Dealer verwijderen + prullenbak** - veilige soft-delete met 30 dagen hersteltijd

## Mappenstructuur

```
.
+- crm-extensions/        # Mijn eigen examen-uitbreiding (geen WP-plugin)
|   +- src/               # Service / Controller / Repository / Model lagen
|   +- public/            # Demo-paginas en API-router
|   +- cron/              # IMAP-fetch script (US-06)
|   +- sql/               # Migratiescripts voor de extra tabellen
|   +- tests/             # PHPUnit unit tests
|
+- wp-content/plugins/dealer-crm-plugin/
|   # De bestaande WordPress-plugin met mijn aanpassingen:
|   # - Inbox in de Contacthistorie-tab
|   # - Kaart-pagina toont nu de routeplanner
|   # - 2 nieuwe AJAX-endpoints + database-methodes
|
+- examen-documenten/     # Word-documenten voor de beoordelaar
    +- 00_Inleverchecklist.docx
    +- 01_Ontwerpdocument.docx
    +- 02_Realisatiedocument.docx
    +- 03_Uitleg_codewerk_volledig.docx
```

## Gerealiseerde user stories

| ID | Story | Bestanden |
|----|-------|-----------|
| US-01 | Dealers selecteren op de kaart | `route_planner.js`, `demo_route_planner.php` |
| US-02 | Route berekenen via OSRM | `RouteService::calculateRoute()` |
| US-03 | Volgorde aanpassen | `route_planner.js` (omhoog/omlaag knoppen) |
| US-04 | Route opslaan en opnieuw gebruiken | `RouteService::saveRoute()` + UI |
| US-05 | E-mail versturen naar dealer | `EmailService` + `MailerClient` |
| US-06 | Inkomende mails automatisch koppelen | `cron/fetch_emails.php` |
| US-07 | E-mailgeschiedenis bekijken | Inbox in Contacthistorie-tab |
| US-08 | Dealerlijst met badges | `demo_dealer_list.php` |
| US-09 | Dealer verwijderen (soft delete) | `DealerService::trashDealer()` |
| US-10 | Prullenbak tonen | `demo_trash.php` |
| US-11 | Dealer herstellen | `DealerService::restoreDealer()` |
| US-13 | Notitie toevoegen | `NoteService` + demo |
| US-14 | Live filter op dealerlijst | `dealer_list.js` |

Plus extras: Google Maps-knop op de routeplanner, routeplanner ingebed op WP-admin Kaart-pagina.

## Architectuur

Klassiek Service-Repository-Controller patroon:

```
Controller  (HTTP entry point, validatie)
    |
    v
Service     (business logic, gemockte unit-tests)
    |
    v
Repository  (SQL, alle PDO-queries)
    |
    v
Model       (data-objects)
```

Voorbeeld voor US-02:

`RouteController::calculate()` -> `RouteService::calculateRoute([1,2,3])` -> `DealerRepository::findByIds(...)` -> OSRM-aanroep -> antwoord terug.

## Installatie (op een WordPress-site)

### 1. Bestanden plaatsen

- Kopieer `wp-content/plugins/dealer-crm-plugin/` naar de plugins-map van WordPress
- Kopieer `crm-extensions/` naar de root van de WordPress-installatie

### 2. Plugin activeren

Ga naar WP-admin -> Plugins en activeer **Dealer CRM Plugin**.

### 3. Database-tabellen aanmaken

Open in je browser:

```
http://<jouw-site>/crm-extensions/cron/run_migration.php
```

Of importeer handmatig:

```
crm-extensions/sql/2026_05_add_new_tables.sql
```

### 4. Config aanmaken

Kopieer `crm-extensions/config.example.php` naar `crm-extensions/config.php` en vul de SMTP- en IMAP-wachtwoorden in voor `crm@woopremium.nl`. Dit bestand staat in `.gitignore` en komt niet in de repo.

### 5. IMAP-fetch automatisch laten draaien (productie)

Op een Linux-server een cronjob aanmaken:

```
*/5 * * * * /usr/bin/php /pad/naar/crm-extensions/cron/fetch_emails.php
```

## Testen

Unit-tests draaien via Composer:

```
cd crm-extensions
composer install
composer test
```

Vier test-klassen:

- `DealerServiceTest` - dealer-CRUD en soft delete
- `EmailServiceTest` - mail-versturen en validaties
- `NoteServiceTest` - notitie toevoegen aan dealer
- `RouteServiceTest` - route berekenen en opslaan

## Demo

Centrale demo-pagina (na installatie) op:

```
http://<jouw-site>/crm-extensions/public/
```

Daarin staan alle gerealiseerde user stories met directe doorklik-knoppen.

## Versiebeheer

Deze repo bevat de volledige commit-historie van mijn examen-project. Per user story zoveel mogelijk een aparte commit met een betekenisvolle boodschap.

---

**Vragen?** Zie `examen-documenten/03_Uitleg_codewerk_volledig.docx` - daar leg ik per bewerkt bestand uit wat ik gemaakt heb en waarom.

