<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_dealer_list.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Demo-pagina voor US-08 / US-14: een dealerlijst met badges en een filter.
 *    - Per dealer een badge met de status.
 *    - Een rode badge met het aantal ONGELEZEN inkomende mails (US-08).
 *    - Een badge met het aantal notities bij die dealer.
 *    - Een zoekveld (op naam) en een statusfilter die de lijst direct
 *      filteren in de browser (US-14).
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_dealer_list.php
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;   // fake login voor de demo
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

// Alle statussen voor het statusfilter
$statuses = $pdo->query(
    "SELECT DISTINCT status FROM wp_crm_dealers
     WHERE status IS NOT NULL AND status <> '' AND deleted_at IS NULL
     ORDER BY status"
)->fetchAll(PDO::FETCH_COLUMN);

// Alle actieve dealers + per dealer het aantal ongelezen mails en notities.
// De subqueries gebruiken de indexen op dealer_id, dus dit blijft snel.
$dealers = $pdo->query(
    "SELECT d.id, d.name, d.city, d.status,
            (SELECT COUNT(*) FROM wp_crm_emails e
              WHERE e.dealer_id = d.id
                AND e.direction = 'in'
                AND e.read_at IS NULL) AS unread,
            (SELECT COUNT(*) FROM wp_crm_notes n
              WHERE n.dealer_id = d.id) AS notes
     FROM wp_crm_dealers d
     WHERE d.deleted_at IS NULL
     ORDER BY d.name"
)->fetchAll();

// Helper om tekst veilig te tonen (XSS-bescherming).
function esc(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Kleur per status voor de status-badge.
function statusKleur(string $status): string {
    $map = [
        'actief'      => '#008B4F',
        'inactief'    => '#888888',
        'prospect'    => '#BF8F00',
        'geblokkeerd' => '#C00000',
    ];
    return $map[strtolower($status)] ?? '#2E75B6';
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Demo - Dealerlijst met badges en filter (US-08 / US-14)</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 960px; margin: 0 auto; padding: 20px; color: #222; background: #f4f6f9; }
    header { background: #1F4E79; color: #fff; padding: 18px 22px; border-radius: 6px; }
    header h1 { margin: 0; font-size: 21px; }
    header .sub { font-size: 12px; opacity: .85; margin-top: 3px; }
    .card { background: #fff; padding: 14px 18px; border-radius: 6px; margin-top: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .filters { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filters input, .filters select { padding: 8px; border: 1px solid #ccc; border-radius: 3px; font: inherit; }
    .filters input { flex: 1; min-width: 180px; }
    #crm-count { color: #666; font-size: 13px; margin-left: auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
    th { color: #2E75B6; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
    tr:hover td { background: #f7f9fc; }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 11px; font-size: 12px; font-weight: bold; color: #fff; }
    .badge-grey { background: #c4c4c4; color: #333; }
    .badge-red { background: #C00000; }
    .badge-note { background: #1F4E79; }
    .muted { color: #bbb; }
</style>
</head>
<body>
    <header>
        <h1>Dealerlijst met badges en filter &ndash; US-08 / US-14</h1>
        <div class="sub">Khayrallah Issa &ndash; &ndash; CRM WooPremium uitbreiding</div>
    </header>

    <div class="card">
        <div class="filters">
            <input type="text" id="crm-search" placeholder="Zoek op naam...">
            <select id="crm-status-filter">
                <option value="">Alle statussen</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= esc($s) ?>"><?= esc(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <span id="crm-count"></span>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Dealer</th>
                    <th>Plaats</th>
                    <th>Status</th>
                    <th>Mails</th>
                    <th>Notities</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dealers as $d):
                    $status = (string)($d['status'] ?? '');
                    $unread = (int)$d['unread'];
                    $notes  = (int)$d['notes'];
                ?>
                    <tr data-name="<?= esc(strtolower((string)$d['name'])) ?>"
                        data-status="<?= esc($status) ?>">
                        <td><strong><?= esc($d['name']) ?></strong></td>
                        <td><?= $d['city'] ? esc($d['city']) : '<span class="muted">-</span>' ?></td>
                        <td>
                            <?php if ($status !== ''): ?>
                                <span class="badge" style="background:<?= statusKleur($status) ?>;">
                                    <?= esc(ucfirst($status)) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($unread > 0): ?>
                                <span class="badge badge-red"><?= $unread ?> ongelezen</span>
                            <?php else: ?>
                                <span class="muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($notes > 0): ?>
                                <span class="badge badge-note"><?= $notes ?></span>
                            <?php else: ?>
                                <span class="muted">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($dealers)): ?>
            <p class="muted">Geen dealers gevonden.</p>
        <?php endif; ?>
    </div>

    <script src="/crm-extensions/public/js/dealer_list.js"></script>
</body>
</html>
