# Code conventions — CRM WooPremium uitbreiding

Auteur: Khayrallah Issa

Dit document beschrijft de conventies die ik in dit project hanteer, zodat
beoordelaar en toekomstige ontwikkelaars weten welke standaard ze mogen
verwachten.

---

## 1. PHP

### Algemeen

- **PHP 8.0+** alleen. Gebruik van strict types: `declare(strict_types=1);`
  bovenaan elk eigen PHP-bestand in `crm-extensions/src/`.
- **PSR-12** als basis (4 spaties, opening brace op nieuwe regel voor
  classes/functions, enz.).
- **PSR-4 autoloading** met namespace `CrmExt\`.

### Naamgeving

| Element | Stijl | Voorbeeld |
|---------|-------|-----------|
| Klassenaam | PascalCase | `RouteService`, `EmailRepository` |
| Methode | camelCase | `calculateRoute()`, `softDelete()` |
| Variabele | camelCase | `$dealerId`, `$selectedIds` |
| Constante | UPPER_SNAKE | `MAX_DEALERS`, `OSRM_URL` |
| Bestand | match klasse | `RouteService.php` |
| Map | lowercase | `services/`, `repositories/` |

### Code-stijl

- Type-hints op alle parameters en return-types.
- Strict comparisons (`===`, `!==`) waar mogelijk.
- Geen `else` na een `return` (early return).
- Maximaal 80 tekens per regel waar mogelijk; soft limit 100.

### Documentatie

- Elke klasse heeft een docblock met `Wat doet dit bestand?`.
- Elke publieke methode heeft een docblock met korte uitleg.
- Korte private methodes mogen zonder docblock als de naam zelfsprekend is.

---

## 2. JavaScript

- Gebruik van **`const`** waar mogelijk, anders `let`. Geen `var`.
- Strict mode (`'use strict';`) bovenaan elke module.
- Module-pattern met een IIFE (zelf-aanroepende functie) om globale
  variabelen te vermijden.
- Eventhandlers worden geregistreerd via `addEventListener`, niet via
  inline-attributen.
- Async-code met `async/await` in plaats van geneste callbacks.

---

## 3. SQL

- Alle queries als **prepared statements** met named placeholders
  (`:dealer_id`, niet `?`).
- Geen `SELECT *` in productie-queries; expliciet de kolommen benoemen.
- Foreign keys altijd benoemen met een betekenisvolle naam
  (`fk_crm_emails_dealer`).
- `ENGINE=InnoDB` en `utf8mb4_unicode_520_ci` als default.

---

## 4. Versiebeheer

### Commit-boodschappen

- Eerste regel max. 72 tekens.
- Begint met een werkwoord in de gebiedende wijs ("Voeg toe", "Fix",
  "Refactor").
- Specifiek over wat veranderd is, niet over hoe.

Voorbeelden:

| Goed | Slecht |
|------|--------|
| `Voeg soft-delete toe aan DealerService` | `update` |
| `Fix XSS in dealer-notities` | `fix bug` |
| `Refactor RouteService::calculateRoute met OSRM-fallback` | `change route stuff` |

### Branch-strategie

Voor dit examenproject is alleen `main` gebruikt (solo-project). In een
team-setting zou ik feature-branches gebruiken per user story.

---

## 5. Bestanden en mappen

```
crm-extensions/
├── src/                    # eigen broncode
│   ├── controllers/        # HTTP-entrypoints
│   ├── services/           # business-logica
│   ├── repositories/       # SQL-laag
│   ├── models/             # data-objecten
│   └── helpers/            # losse hulpklassen
├── public/                 # publieke entry-points en demo's
├── cron/                   # achtergrondscripts
├── sql/                    # migratiescripts
├── tests/                  # PHPUnit-tests
└── config.example.php      # voorbeeld-configuratie (zonder geheimen)
```

---

## 6. Bewust afwijkend

Een paar plekken waar ik bewust van PSR-12 afwijk:

- **Nederlandstalige variabelen** in een paar oudere helper-functies
  (`$onderwerpen`, `$berichten` in `fetch_emails.php`). Reden: snel
  leesbaar voor de stakeholder. In een nieuwe versie zou ik dit
  consistent Engels maken.
- **Geen DI-container** maar handmatige object-constructie in
  `public/api/index.php`. Reden: simpelheid voor een klein project; bij
  groei zou PHP-DI of een vergelijkbare container een betere keuze zijn.

Beide afwijkingen zijn een bewuste trade-off, niet onkunde.
