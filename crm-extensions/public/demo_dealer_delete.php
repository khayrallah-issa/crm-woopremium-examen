<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_dealer_delete.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Een demo-pagina om US-09 (Dealer verwijderen) te testen ZONDER dat we
 *  meteen het bestaande WordPress dashboard hoeven aan te passen. De pagina:
 *    1. Toont een lijst van 20 actieve dealers uit wp_crm_dealers.
 *    2. Bij elke dealer staat een rode 'Verwijderen'-knop.
 *    3. Bij klik verschijnt de bevestigingsdialoog (zie dealer_delete.js).
 *    4. Na bevestiging verdwijnt de dealer uit de lijst (zachte verwijdering).
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_dealer_delete.php
 *
 *  Voor de demo zetten we een fake user_id in de sessie zodat de audit-log
 *  weet wie de verwijder-actie uitvoert. In productie loopt dit via de
 *  bestaande inlog van WordPress.
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();

// Fake-login voor de demo: gebruik user 1 (meestal de admin in WordPress)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// Lees DB-gegevens uit wp-config.php
$wpConfig = file_get_contents(__DIR__ . '/../../wp-config.php');
preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbName = $m[1] ?? 'local';
preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbUser = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';

[$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Pak 20 actieve dealers om te tonen.
$dealers = $pdo->query(
    'SELECT id, name, contact_person, email, city
     FROM wp_crm_dealers
     WHERE deleted_at IS NULL
     ORDER BY name
     LIMIT 20'
)->fetchAll();
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Demo - Dealer verwijderen (US-09)</title>
<link rel="stylesheet" href="/crm-extensions/public/css/crm_extensions.css">
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 30px auto; padding: 0 20px; color: #222; }
    h1 { color: #1F4E79; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
    th { background: #1F4E79; color: #fff; }
    tr:nth-child(even) td { background: #f7f7f7; }
    .btn-delete {
        background: #C00000; color: #fff; border: none;
        padding: 6px 12px; cursor: pointer; border-radius: 3px;
        font-size: 13px;
    }
    .btn-delete:hover { background: #900000; }
    .info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 14px; border-radius: 4px; }
</style>
</head>
<body>
    <h1>Demo - US-09 Dealer verwijderen</h1>

    <div class="info">
        <strong>Hoe te testen:</strong><br>
        1. Klik op de rode <em>Verwijderen</em>-knop bij een dealer.<br>
        2. Bevestig in de pop-up.<br>
        3. Vernieuw de pagina (F5) - de dealer is verdwenen uit de lijst.<br>
        4. Open in Adminer: <code>SELECT * FROM wp_crm_dealers WHERE deleted_at IS NOT NULL</code> - daar zie je hem.<br>
        5. En in <code>wp_crm_activity_log</code> staat een regel met de delete-actie.
    </div>

    <p>Ingelogd als marketeer met user_id = <strong><?=htmlspecialchars((string)$_SESSION['user_id'])?></strong>. Totaal aantal actieve dealers in de tabel: een hele berg, hier toon ik er 20.</p>

    <table>
        <tr>
            <th>Id</th>
            <th>Naam</th>
            <th>Contactpersoon</th>
            <th>E-mail</th>
            <th>Plaats</th>
            <th>Actie</th>
        </tr>
        <?php foreach ($dealers as $d): ?>
            <tr>
                <td><?=htmlspecialchars((string)$d['id'])?></td>
                <td><?=htmlspecialchars((string)$d['name'])?></td>
                <td><?=htmlspecialchars((string)($d['contact_person'] ?? ''))?></td>
                <td><?=htmlspecialchars((string)($d['email'] ?? ''))?></td>
                <td><?=htmlspecialchars((string)($d['city'] ?? ''))?></td>
                <td>
                    <button type="button"
                            class="btn-delete"
                            data-dealer-delete
                            data-dealer-id="<?=(int)$d['id']?>"
                            data-dealer-name="<?=htmlspecialchars((string)$d['name'])?>"
                            data-dealer-email="<?=htmlspecialchars((string)($d['email'] ?? ''))?>">
                        Verwijderen
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><em>Verwijder dit bestand (<code>demo_dealer_delete.php</code>) voordat je naar productie gaat. Het bypassed de WordPress login.</em></p>

    <script src="/crm-extensions/public/js/dealer_delete.js"></script>
</body>
</html>
