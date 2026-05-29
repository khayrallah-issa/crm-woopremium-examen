# Architectuur — CRM WooPremium uitbreiding

Auteur: Khayrallah Issa

Dit document legt de architectuurkeuzes uit zodat duidelijk is **waarom** de
code op de huidige manier is opgebouwd.

---

## 1. Waarom twee locaties?

De broncode is verdeeld over twee plekken. Dat is een **bewuste keuze**, geen
toeval:

```
wp-content/plugins/dealer-crm-plugin/   <-- bestaande WordPress-plugin
crm-extensions/                          <-- mijn nieuwe uitbreiding (geen WP-plugin)
```

### Reden

De `dealer-crm-plugin/` map bevat de **bestaande, productie-draaiende
WordPress-plugin** van WooPremium. Deze code mocht ik niet vervangen, alleen
op specifieke plekken uitbreiden. Concreet zijn dat:

- de Kaart-pagina (de routeplanner is hier ingebed)
- de Contacthistorie-tab (inbox-component toegevoegd)
- twee nieuwe AJAX-endpoints (`crm_mark_email_read` en `crm_fetch_inbox`)
- twee nieuwe database-methodes (`mark_email_read`, `get_email`)

De `crm-extensions/` map bevat **mijn eigen, schone code** in een klassieke
gelaagde architectuur (Model → Repository → Service → Controller). Dit is de
code waar ik volledig verantwoordelijk voor ben en die ik conform PSR-12 en
moderne PHP-patterns heb opgebouwd.

### Voordelen van deze scheiding

- De bestaande WP-plugin blijft schoon. Geen vermenging tussen mijn
  uitbreidings-architectuur en het legacy-systeem.
- Bij een update van de WP-plugin door WooPremium loopt mijn werk niet
  in de weg.
- Mijn code is hergebruikbaar buiten WordPress (de cron `fetch_emails.php`
  draait standalone via CLI).
- De unit-tests in `tests/` testen alleen mijn eigen code, niet het
  WordPress-framework.

### Nadeel + hoe ik dat heb gecompenseerd

Het feit dat code op twee plekken staat maakt het op het eerste gezicht
moeilijker te volgen. Om dat te compenseren heb ik:

- Een centrale README.md die uitlegt waar wat staat
- Een AJAX-endpoint in de plugin dat *delegeert* naar mijn cron-script
  (geen logica-duplicatie)
- Dual-write op database-niveau: e-mails worden zowel in mijn
  `wp_crm_emails` als in de oude `wp_crm_contact_log` weggeschreven, zodat
  de bestaande UI gewoon blijft werken

---

## 2. Gelaagde architectuur in `crm-extensions/`

```
                  HTTP-verzoek
                       |
                       v
              +---------------+
              |  Controller   |  validatie, JSON in/uit
              +-------+-------+
                      |
                      v
              +---------------+
              |    Service    |  business-logica, regels
              +-------+-------+
                      |
                      v
              +---------------+
              |  Repository   |  alle SQL hier
              +-------+-------+
                      |
                      v
              +---------------+
              |      DB       |
              +---------------+
```

### Verantwoordelijkheden per laag

| Laag | Verantwoordelijkheid | Waarom |
|------|---------------------|--------|
| Model | Een rij uit de database voorstellen | Type-veiligheid en autocompletion |
| Repository | Alle SQL-queries | SQL op één plek; makkelijk te swappen |
| Service | Business-logica + validatie | Testbaar zonder echte database |
| Controller | HTTP-verzoek vertalen | Dunne laag; geen business-logica |

### Regel: geen "shortcuts"

- Controllers roepen **alleen** services aan, nooit direct repositories
- Services bevatten **geen** SQL
- Repositories bevatten **geen** business-regels (bv. "max 25 dealers")

---

## 3. Database-ontwerp

Vier nieuwe tabellen, allemaal met prefix `wp_crm` zodat ze passen bij de
bestaande WooPremium-database:

- `wp_crm_routes` — opgeslagen routes per marketeer
- `wp_crm_route_stops` — volgorde van dealers in een route
- `wp_crm_emails` — alle e-mails (inkomend + uitgaand)
- `wp_crm_email_attachments` — voor toekomstige uitbreiding

### Ontwerpkeuzes

- **Foreign keys** met `ON DELETE SET NULL`: e-mails overleven als een
  dealer wordt verwijderd.
- **Unique key op `message_id`**: voorkomt dubbele opslag van dezelfde
  inkomende mail.
- **Collation `utf8mb4_unicode_520_ci`**: bewust gelijk aan de bestaande
  tabellen, anders breken JOINs.
- **Soft delete** via `deleted_at`-kolom op dealers: nooit per ongeluk
  data kwijt.

---

## 4. Externe services en fallbacks

Mijn code praat met twee externe services. Beide zijn ingepakt in
try/catch met een fallback, zodat de applicatie niet platligt bij
storingen elders.

| Service | Wat | Fallback |
|---------|-----|----------|
| OSRM (routing) | Echte rij-routes | Haversine-schatting (hemelsbrede afstand) |
| IMAP (mailserver) | Inkomende mails ophalen | Demo-modus genereert een nep-mail |

---

## 5. Beveiliging

| Risico | Maatregel | Plek |
|--------|-----------|------|
| SQL-injectie | PDO prepared statements + `EMULATE_PREPARES=false` | Alle repositories |
| XSS | `htmlspecialchars()` / `esc_html()` op uitvoer | Alle templates |
| CSRF | WordPress nonce-controle | Alle AJAX-endpoints |
| Onbevoegd | Sessie + capability-check | API-router |
| Datalek | `config.php` in `.gitignore` | Configuratie |
| Per ongeluk verwijderen | Soft delete + 30 dagen prullenbak | DealerService |

---

## 6. Toekomstige verbeteringen

Op basis van de code-review en feedback zou ik in een volgende sprint
aanpakken:

- De `crm-extensions/` map omzetten naar een echte WordPress-plugin, zodat
  alles binnen `wp-content/plugins/` valt
- PHPCS (PHP CodeSniffer) toevoegen aan de workflow voor automatische
  PSR-12 controle
- Composer-autoloader gebruiken in plaats van mijn handgeschreven
  autoloader
- Mailchimp- en Slack-koppelingen die nu in de plugin staan, ook
  refactoren naar het gelaagde model
- Frontend-tests met Cypress of Playwright toevoegen
- De cron-fetch herschrijven om mails per batch te verwerken in plaats
  van één voor één
