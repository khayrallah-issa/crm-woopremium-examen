<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_route_planner.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Demo-pagina voor US-01 (dealers selecteren), US-02 (route berekenen),
 *  US-03 (volgorde aanpassen) en US-04 (route opslaan). Werkt los van
 *  WordPress; ideaal om de routeplanner te tonen aan de stagebegeleider.
 *
 *  Hoe te gebruiken (eenmaal de pagina open):
 *    1. Linksboven kun je op markers klikken om dealers toe te voegen.
 *    2. In de zijbalk verschijnt de volgorde + omhoog/omlaag knoppen.
 *    3. Klik op 'Plan route' om OSRM aan te roepen.
 *    4. De rode lijn op de kaart is de werkelijke weg-route.
 *    5. Onder de knop staat de afstand en geschatte reistijd.
 *    6. Geef de route een naam en klik 'Route opslaan' (US-04).
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_route_planner.php
 *
 *  We laden ALLE dealers met geldige coordinaten (lat/lng niet leeg) -
 *  dezelfde set als de normale Kaart-pagina. De kaart bundelt nabije
 *  dealers in clusters zodat duizenden markers overzichtelijk blijven.
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;     // fake login voor demo
}

// DB-verbinding via wp-config.php (auteur: Khayrallah Issa)
$wpConfig = file_get_contents(__DIR__ . '/../../wp-config.php');
preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbName = $m[1] ?? 'local';
preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbUser = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';
[$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Haal ALLE dealers met lat/lng op - dezelfde set als de normale Kaart-pagina.
// Auteur: Khayrallah Issa - geen LIMIT meer; de kaart bundelt
// nabije dealers in clusters zodat duizenden markers overzichtelijk blijven.
// Auteur: Khayrallah Issa
// We halen ook telefoon, e-mail en adresvelden op zodat de marker-popup
// dezelfde info kan tonen als de oude WP-admin kaart deed (naam, plaats,
// telefoon, e-mail, merken, status).
$dealers = $pdo->query(
    "SELECT id, name, city, status, lat, lng,
            phone, email, street, postcode, website, contact_person
     FROM wp_crm_dealers
     WHERE deleted_at IS NULL
       AND lat IS NOT NULL AND lng IS NOT NULL
     ORDER BY name"
)->fetchAll();

// --------------------------------------------------------------------------
// Auteur: Khayrallah Issa
// Filter-data: de merken per dealer + lijsten met alle merken en statussen.
// Hiermee kan de routeplanner filteren op merk, status en postcode/straal.
// --------------------------------------------------------------------------
$allBrands   = [];
$allStatuses = [];
try {
    $dealerIds = array_map('intval', array_column($dealers, 'id'));
    $brandsByDealer = [];
    if ($dealerIds) {
        $inClause  = implode(',', $dealerIds);
        $brandRows = $pdo->query(
            "SELECT db.dealer_id, b.name
             FROM wp_crm_brands b
             INNER JOIN wp_crm_dealer_brand db ON b.id = db.brand_id
             WHERE db.dealer_id IN ($inClause)
             ORDER BY b.name"
        )->fetchAll();
        foreach ($brandRows as $br) {
            $brandsByDealer[(int)$br['dealer_id']][] = $br['name'];
        }
    }
    foreach ($dealers as &$d) {
        $d['brands'] = $brandsByDealer[(int)$d['id']] ?? [];
    }
    unset($d);

    $allBrands = $pdo->query(
        "SELECT name FROM wp_crm_brands ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);
    $allStatuses = $pdo->query(
        "SELECT DISTINCT status FROM wp_crm_dealers
         WHERE status IS NOT NULL AND status <> '' AND deleted_at IS NULL
         ORDER BY status"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    // Merken-/statustabel niet beschikbaar -> filters blijven leeg.
    foreach ($dealers as &$d) {
        if (!isset($d['brands'])) {
            $d['brands'] = [];
        }
    }
    unset($d);
}

// JSON voor het JS-script (in data-attribuut)
$dealersJson = htmlspecialchars(
    json_encode($dealers, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS),
    ENT_QUOTES, 'UTF-8'
);

// Auteur: Khayrallah Issa
// ?embed=1 verbergt de paginakop, zodat de routeplanner netjes binnen een
// iframe op de WP-admin Kaart-pagina past.
$embed = isset($_GET['embed']);
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Demo - Routeplanner (US-01 t/m US-04)</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #222; }
    header { background: #1F4E79; color: #fff; padding: 12px 20px; }
    header h1 { margin: 0; font-size: 20px; }
    header .small { font-size: 12px; opacity: .8; }
    .layout { display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - 56px); }
    /* Auteur: Khayrallah Issa - in embed-modus is er geen kop. */
    body.embed .layout { height: 100vh; }
    aside  { padding: 14px 16px; overflow: auto; background: #f9f9f9; border-right: 1px solid #ddd; }
    #crm-map { width: 100%; height: 100%; }
    h2 { color: #2E75B6; margin: 16px 0 6px; font-size: 14px; }
    button { font: inherit; }
    .crm-btn-primary { background: #1F4E79; color: #fff; border: 0; padding: 10px 14px; cursor: pointer; font-weight: bold; border-radius: 3px; }
    .crm-btn-secondary { background: #fff; color: #1F4E79; border: 1px solid #1F4E79; padding: 8px 12px; cursor: pointer; border-radius: 3px; }
    .crm-btn-primary:hover { background: #163a5c; }
    #crm-selected { list-style: none; padding: 0; margin: 0; }
    .crm-stop { display: flex; align-items: center; gap: 6px; padding: 6px; background: #fff; border: 1px solid #ddd; margin-bottom: 4px; border-radius: 3px; }
    .crm-stop-num { font-weight: bold; color: #C00; min-width: 22px; }
    .crm-stop-name { flex: 1; font-size: 13px; }
    .crm-stop-buttons button { padding: 2px 6px; cursor: pointer; }
    .crm-marker-num { background: #C00 !important; color: #fff !important; font-weight: bold; border: 2px solid #fff !important; border-radius: 50% !important; padding: 2px 6px !important; box-shadow: none !important; }
    .crm-marker-num::before { display: none !important; }
    #crm-route-info { margin-top: 12px; padding: 10px; background: #fff; border: 1px solid #1F4E79; border-radius: 3px; font-size: 13px; min-height: 30px; }
</style>
</head>
<body class="<?= $embed ? 'embed' : '' ?>">
    <?php /* Auteur: Khayrallah Issa - kop verbergen in embed-modus. */ ?>
    <?php if (!$embed): ?>
    <header>
        <h1>Routeplanner Demo - US-01 / US-02 / US-03 / US-04</h1>
        <div class="small">Khayrallah Issa - <?=count($dealers)?> dealers op de kaart</div>
    </header>
    <?php endif; ?>

    <div class="layout">
        <aside>
            <?php /* Auteur: Khayrallah Issa
                     Filterbalk: dealers filteren op merk, status en
                     postcode + straal. JS-afhandeling in route_planner.js. */ ?>
            <h2 style="margin-top:0;">Dealers filteren</h2>
            <select id="crm-filter-brand" style="width:100%;padding:6px;margin-bottom:6px;">
                <option value="">Alle merken</option>
                <?php foreach ($allBrands as $b): ?>
                    <option value="<?=htmlspecialchars($b)?>"><?=htmlspecialchars($b)?></option>
                <?php endforeach; ?>
            </select>
            <select id="crm-filter-status" style="width:100%;padding:6px;margin-bottom:6px;">
                <option value="">Alle statussen</option>
                <?php foreach ($allStatuses as $s): ?>
                    <option value="<?=htmlspecialchars($s)?>"><?=htmlspecialchars(ucfirst($s))?></option>
                <?php endforeach; ?>
            </select>
            <div style="display:flex;gap:6px;">
                <input type="text" id="crm-filter-postcode" placeholder="Postcode"
                       style="flex:1;padding:6px;border:1px solid #ccc;border-radius:3px;">
                <select id="crm-filter-radius" style="padding:6px;">
                    <?php foreach ([5, 10, 15, 20, 30, 50, 100] as $r): ?>
                        <option value="<?=$r?>" <?=$r === 10 ? 'selected' : ''?>><?=$r?> km</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:6px;margin-top:6px;">
                <button id="crm-filter-search" class="crm-btn-secondary" style="flex:1;">Zoeken</button>
                <button id="crm-filter-reset"  class="crm-btn-secondary" style="flex:1;">Reset</button>
            </div>
            <div id="crm-filter-count" style="font-size:12px;color:#666;margin-top:6px;"></div>

            <h2>Hoe te gebruiken</h2>
            <ol style="padding-left:18px;font-size:13px;line-height:1.5;">
                <li>Klik op blauwe markers om dealers toe te voegen.</li>
                <li>Zet ze in de juiste volgorde met de pijl-knoppen.</li>
                <li>Klik 'Plan route' voor de echte route.</li>
                <li>Geef de route een naam en klik 'Route opslaan'.</li>
            </ol>

            <h2>Geselecteerd (<span id="crm-count">0 van 25</span>)</h2>
            <ul id="crm-selected">
                <li style="color:#888;">Nog niets geselecteerd. Klik op markers.</li>
            </ul>

            <div style="margin-top:14px;display:flex;gap:8px;flex-direction:column;">
                <button id="crm-plan-btn"  class="crm-btn-primary">Plan route</button>
                <button id="crm-clear-btn" class="crm-btn-secondary">Wis selectie</button>
                <!-- Auteur: Khayrallah Issa
                     Opent de geplande route in Google Maps (nieuw tabblad)
                     zodat de marketeer hem direct op zijn telefoon kan gebruiken. -->
                <button id="crm-gmaps-btn" type="button"
                        style="display:none; background:#1A73E8; color:#fff; border:0; padding:10px 12px; border-radius:3px; cursor:pointer; font-weight:600;">
                    Open in Google Maps
                </button>
            </div>

            <div id="crm-route-info"></div>

            <!-- Auteur: Khayrallah Issa
                 US-04: een berekende route opslaan onder een naam.
                 Dit blok blijft verborgen tot er een route berekend is. -->
            <div id="crm-save-box" style="display:none;margin-top:14px;">
                <h2 style="margin-top:0;">Route opslaan</h2>
                <input type="text" id="crm-route-name" maxlength="150"
                       placeholder="Naam van de route, bv. Maandag Noord"
                       style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:3px;">
                <button id="crm-save-btn" class="crm-btn-primary" style="margin-top:8px;width:100%;">
                    Route opslaan
                </button>
                <div id="crm-save-msg" style="margin-top:8px;font-size:13px;min-height:18px;"></div>
            </div>

            <?php /* Auteur: Khayrallah Issa
                     Lijst met opgeslagen routes (US-04), geladen via GET /routes. */ ?>
            <h2>Opgeslagen routes</h2>
            <ul id="crm-saved-routes" style="list-style:none;padding:0;margin:0;">
                <li style="color:#888;font-size:13px;">Laden...</li>
            </ul>

            <p style="margin-top:20px;font-size:11px;color:#888;">
                Demo door Khayrallah Issa.<br>
                Routes worden berekend met OSRM (open routing).
            </p>
        </aside>
        <main>
            <div id="crm-map" data-dealers="<?=$dealersJson?>"></div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="/crm-extensions/public/js/route_planner.js"></script>
</body>
</html>
