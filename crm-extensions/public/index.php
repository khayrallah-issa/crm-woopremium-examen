<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/index.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Hoofdpagina van mijn examen-uitbreiding. Geeft een overzicht van alle
 *  gerealiseerde user stories met directe links naar de demo's.
 *  Hier kun je doorklikken naar elke functie en zien hoe ver ik ben.
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;

// DB-verbinding voor de tellers
$wpConfig = file_get_contents(__DIR__ . '/../../wp-config.php');
preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbName = $m[1] ?? 'local';
preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbUser = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';
[$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\Throwable $e) {
    $pdo = null;
}

// Helper om veilig een count te doen
$count = function(string $sql) use ($pdo): int {
    if (!$pdo) return 0;
    try { return (int)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
};

$totals = [
    'dealers'     => $count("SELECT COUNT(*) FROM wp_crm_dealers WHERE deleted_at IS NULL"),
    'trashed'     => $count("SELECT COUNT(*) FROM wp_crm_dealers WHERE deleted_at IS NOT NULL"),
    'emails_in'   => $count("SELECT COUNT(*) FROM wp_crm_emails WHERE direction='in'"),
    'emails_out'  => $count("SELECT COUNT(*) FROM wp_crm_emails WHERE direction='out'"),
    'routes'      => $count("SELECT COUNT(*) FROM wp_crm_routes"),
];

// Check of de nieuwe tabellen bestaan
$tables    = $pdo ? $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) : [];
$haveEmails = in_array('wp_crm_emails', $tables, true);
$haveRoutes = in_array('wp_crm_routes', $tables, true);
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>CRM uitbreiding - overzicht (Khayrallah Issa)</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 0 auto; padding: 20px; color: #222; background: #f4f6f9; }
    header { background: #1F4E79; color: #fff; padding: 24px 30px; border-radius: 6px; }
    header h1 { margin: 0; font-size: 26px; }
    header .sub { opacity: .85; font-size: 14px; margin-top: 4px; }
    .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin: 20px 0; }
    .stat { background: #fff; padding: 14px; border-radius: 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .stat strong { display: block; font-size: 26px; color: #1F4E79; }
    .stat span { font-size: 12px; color: #666; }
    h2 { color: #2E75B6; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
    .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 14px; }
    .card { background: #fff; padding: 16px 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.06); border-left: 4px solid #1F4E79; }
    .card.must  { border-left-color: #C00000; }
    .card.should{ border-left-color: #BF8F00; }
    .card.could { border-left-color: #008B4F; }
    .card.tool  { border-left-color: #7030A0; }
    .card h3 { margin: 0 0 4px; font-size: 16px; color: #1F4E79; }
    .card .id { font-size: 11px; color: #999; font-weight: bold; }
    .card p { margin: 6px 0; font-size: 13px; color: #444; }
    .card a { display: inline-block; margin-top: 6px; padding: 6px 12px; background: #1F4E79; color: #fff; text-decoration: none; border-radius: 3px; font-size: 13px; }
    .card a:hover { background: #163a5c; }
    .card .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; color: #fff; }
    .badge.must   { background: #C00000; }
    .badge.should { background: #BF8F00; }
    .badge.could  { background: #008B4F; }
    .warn { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 14px; border-radius: 4px; margin: 16px 0; }
    footer { margin-top: 40px; padding: 14px; font-size: 12px; color: #888; text-align: center; }
</style>
</head>
<body>

<header>
    <h1>CRM WooPremium - Uitbreiding</h1>
    <div class="sub">
        Examenproject Realiseren -
        <strong>Khayrallah Issa</strong> -
        Praktijkbeoordelaar: Mohamed Talbi
    </div>
</header>

<?php if (!$haveEmails || !$haveRoutes): ?>
<div class="warn">
    <strong>Database niet compleet:</strong> de nieuwe tabellen
    <?=!$haveEmails?'<code>wp_crm_emails</code> ':''?>
    <?=!$haveRoutes?'<code>wp_crm_routes</code> ':''?>
    ontbreken. Open Local -> Database -> Adminer en draai
    <code>sql/2026_05_add_new_tables.sql</code>.
</div>
<?php endif; ?>

<div class="stats">
    <div class="stat"><strong><?=$totals['dealers']?></strong><span>actieve dealers</span></div>
    <div class="stat"><strong><?=$totals['trashed']?></strong><span>in prullenbak</span></div>
    <div class="stat"><strong><?=$totals['emails_in']?></strong><span>inkomende mails</span></div>
    <div class="stat"><strong><?=$totals['emails_out']?></strong><span>uitgaande mails</span></div>
    <div class="stat"><strong><?=$totals['routes']?></strong><span>opgeslagen routes</span></div>
</div>

<h2>Must-have user stories</h2>
<div class="cards">

    <div class="card must">
        <div class="id">US-01, US-02, US-03</div>
        <h3>Routeplanner <span class="badge must">Must</span></h3>
        <p>Selecteer dealers op de kaart en bereken de route via OSRM. Volgorde kun je
        aanpassen met pijl-knoppen.</p>
        <a href="demo_route_planner.php">Open kaart</a>
    </div>

    <div class="card must">
        <div class="id">US-05, US-07, US-08</div>
        <h3>E-mail per dealer <span class="badge must">Must</span></h3>
        <p>Kies een dealer, stuur een mail en bekijk de hele geschiedenis. Ongelezen
        mails zijn rood gemarkeerd.</p>
        <a href="demo_dealer_emails.php">Open e-mails</a>
    </div>

    <div class="card must">
        <div class="id">US-06</div>
        <h3>Inkomende mail (IMAP) <span class="badge must">Must</span></h3>
        <p>Cronjob die ongelezen mails uit <code>mail.woopremium.nl</code> ophaalt
        en automatisch aan de juiste dealer koppelt. Demo voegt een fake mail toe.</p>
        <a href="../cron/fetch_emails.php">Ophalen (echt)</a>
        <a href="../cron/fetch_emails.php?demo=1" style="background:#7030A0">Simuleer 1</a>
        <a href="../cron/fetch_emails.php?test=1" style="background:#666">Test IMAP</a>
    </div>

    <div class="card must">
        <div class="id">US-09</div>
        <h3>Dealer verwijderen <span class="badge must">Must</span></h3>
        <p>Lijst van 20 dealers met rode Verwijder-knop. Pop-up vraagt om bevestiging.
        Dealer komt in prullenbak, niet permanent weg.</p>
        <a href="demo_dealer_delete.php">Open verwijderen</a>
    </div>

</div>

<h2>Should-have user stories</h2>
<div class="cards">

    <div class="card should">
        <div class="id">US-10, US-11</div>
        <h3>Prullenbak en herstellen <span class="badge should">Should</span></h3>
        <p>Toont alle verwijderde dealers en hoeveel dagen ze nog blijven staan.
        Klik op Herstellen om er een terug te halen.</p>
        <a href="demo_trash.php">Open prullenbak</a>
    </div>

    <div class="card should">
        <div class="id">US-13</div>
        <h3>Notitie bij dealer <span class="badge should">Should</span></h3>
        <p>Kies een dealer en plaats een notitie. Alle notities van die dealer
        staan eronder, nieuwste eerst.</p>
        <a href="demo_dealer_notes.php">Open notities</a>
    </div>

    <div class="card should">
        <div class="id">US-08, US-14</div>
        <h3>Dealerlijst met badges <span class="badge should">Should</span></h3>
        <p>Alle dealers met badges voor ongelezen mails en notities. Filter
        live op naam en status.</p>
        <a href="demo_dealer_list.php">Open dealerlijst</a>
    </div>

</div>

<h2>Hulpscripts</h2>
<div class="cards">
    <div class="card tool">
        <h3>Database controleren</h3>
        <p>Laat alle tabellen + hun kolommen zien. Handig om te checken of de
        SQL-migratie goed gedraaid is.</p>
        <a href="db_check.php">Open check</a>
    </div>
    <div class="card tool">
        <h3>API-endpoints</h3>
        <p>De router voor alle JSON-endpoints. Direct openen geeft 401 (niet ingelogd).
        Wordt door de demo-pagina's gebruikt.</p>
        <a href="api/index.php?route=/dealers">/dealers</a>
    </div>
</div>

<footer>
    Auteur: Khayrallah Issa () -
    Examen SD_SD20 deel-examen Realiseren -
    Stagebedrijf WooPremium -
    Praktijkbeoordelaar: Mohamed Talbi
</footer>

</body>
</html>
