<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_trash.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Demo-pagina voor US-10 (Prullenbak) en US-11 (Dealer herstellen).
 *  Laat alle verwijderde dealers zien. Bij elke regel een 'Herstel'-knop
 *  die de dealer terughaalt naar de hoofdlijst.
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_trash.php
 *
 *  Dealers worden definitief verwijderd na 30 dagen (zie de cronjob
 *  cron/purge_trash.php, die de oude dealers opruimt).
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;

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

$dealers = $pdo->query(
    "SELECT id, name, email, city, deleted_at,
            DATEDIFF(NOW(), deleted_at) AS days_in_trash
     FROM wp_crm_dealers
     WHERE deleted_at IS NOT NULL
     ORDER BY deleted_at DESC"
)->fetchAll();
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Prullenbak (US-10 / US-11)</title>
<link rel="stylesheet" href="/crm-extensions/public/css/crm_extensions.css">
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 30px auto; padding: 0 20px; color: #222; }
    h1 { color: #1F4E79; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
    th { background: #1F4E79; color: #fff; }
    tr:nth-child(even) td { background: #f7f7f7; }
    .btn-restore { background: #008B4F; color: #fff; border: 0; padding: 6px 12px; cursor: pointer; border-radius: 3px; }
    .btn-restore:hover { background: #006400; }
    .warning { color: #c00; font-weight: bold; }
    .info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 14px; border-radius: 4px; }
</style>
</head>
<body>
    <h1>Prullenbak (US-10) en Herstellen (US-11)</h1>

    <div class="info">
        Hier zie je alle dealers die in de prullenbak staan. Na <strong>30 dagen</strong>
        worden ze definitief verwijderd door <code>cron/purge_trash.php</code>.
    </div>

    <p>Aantal in prullenbak: <strong><?=count($dealers)?></strong></p>

    <?php if (!$dealers): ?>
        <p><em>Prullenbak is leeg. Verwijder eerst een dealer op
        <a href="demo_dealer_delete.php">demo_dealer_delete.php</a>.</em></p>
    <?php else: ?>
        <table>
            <tr>
                <th>Id</th>
                <th>Naam</th>
                <th>Plaats</th>
                <th>Verwijderd op</th>
                <th>Dagen in prullenbak</th>
                <th>Wordt definitief weg over</th>
                <th>Actie</th>
            </tr>
            <?php foreach ($dealers as $d):
                $days = (int)$d['days_in_trash'];
                $left = 30 - $days; ?>
                <tr>
                    <td><?=$d['id']?></td>
                    <td><?=htmlspecialchars($d['name'])?></td>
                    <td><?=htmlspecialchars($d['city'] ?? '')?></td>
                    <td><?=htmlspecialchars($d['deleted_at'])?></td>
                    <td><?=$days?></td>
                    <td class="<?=$left<=5?'warning':''?>"><?=max(0,$left)?> dagen</td>
                    <td>
                        <button type="button" class="btn-restore"
                                onclick="restoreDealer(<?=$d['id']?>, this)">
                            Herstellen
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <script>
    // Auteur: Khayrallah Issa
    // Bij klik op Herstellen: POST naar /dealers/{id}/restore
    async function restoreDealer(id, btn) {
        btn.disabled = true;
        btn.textContent = '...';
        try {
            const res = await fetch(
                '/crm-extensions/public/api/index.php?route=/dealers/' + id + '/restore',
                { method: 'POST' }
            );
            const data = await res.json();
            if (!res.ok) {
                alert(data.error || 'Herstellen mislukt.');
                btn.disabled = false;
                btn.textContent = 'Herstellen';
                return;
            }
            // Rij wegnemen uit de tabel
            btn.closest('tr').remove();
        } catch (e) {
            alert('Fout: ' + e.message);
            btn.disabled = false;
            btn.textContent = 'Herstellen';
        }
    }
    </script>
</body>
</html>
