<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_dealer_notes.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Demo-pagina voor US-13 (Notitie toevoegen bij een dealer). Werkt los van
 *  WordPress, zodat ik US-13 los kan tonen aan de stagebegeleider.
 *
 *  Wat zie je op deze pagina?
 *    - Een dropdown met dealers waar je er een uit kiest.
 *    - Een formulier om een notitie te typen en op te slaan (US-13).
 *    - Onder het formulier alle notities van die dealer, nieuwste eerst.
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_dealer_notes.php
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

// Lijst dealers voor de dropdown
$dealers = $pdo->query(
    "SELECT id, name, city FROM wp_crm_dealers
     WHERE deleted_at IS NULL
     ORDER BY name
     LIMIT 30"
)->fetchAll();

// Welke dealer is gekozen? Standaard de eerste.
$dealerId = isset($_GET['dealer_id']) ? (int)$_GET['dealer_id'] : ($dealers[0]['id'] ?? 0);
$dealer = null;
if ($dealerId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM wp_crm_dealers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $dealerId]);
    $dealer = $stmt->fetch() ?: null;
}

// Notities van de gekozen dealer (nieuwste eerst), met de naam van de marketeer.
$notes = [];
if ($dealer) {
    $stmt = $pdo->prepare(
        "SELECT n.*, u.display_name AS author
         FROM wp_crm_notes n
         LEFT JOIN wp_users u ON n.user_id = u.ID
         WHERE n.dealer_id = :d
         ORDER BY n.created_at DESC"
    );
    $stmt->execute([':d' => $dealerId]);
    $notes = $stmt->fetchAll();
}

// Helper om tekst veilig te tonen (XSS-bescherming).
function esc(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Demo - Notitie bij dealer (US-13)</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 760px; margin: 0 auto; padding: 20px; color: #222; background: #f4f6f9; }
    header { background: #1F4E79; color: #fff; padding: 18px 22px; border-radius: 6px; }
    header h1 { margin: 0; font-size: 21px; }
    header .sub { font-size: 12px; opacity: .85; margin-top: 3px; }
    .card { background: #fff; padding: 16px 20px; border-radius: 6px; margin-top: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    label { font-weight: bold; display: block; margin-bottom: 4px; }
    select, textarea { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ccc; border-radius: 3px; font: inherit; }
    textarea { resize: vertical; }
    .btn { background: #1F4E79; color: #fff; border: 0; padding: 9px 16px; border-radius: 3px; cursor: pointer; font-weight: bold; margin-top: 8px; }
    .btn:hover { background: #163a5c; }
    h2 { color: #2E75B6; font-size: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    .note { border-left: 3px solid #1F4E79; background: #fafafa; padding: 8px 12px; border-radius: 3px; margin-bottom: 8px; }
    .note .meta { font-size: 12px; color: #777; margin-bottom: 3px; }
    .note .body { white-space: pre-wrap; font-size: 14px; color: #333; }
    .empty { color: #999; font-style: italic; }
    #crm-note-status { margin-top: 8px; font-size: 13px; min-height: 16px; }
</style>
</head>
<body>
    <header>
        <h1>Notitie bij dealer &ndash; US-13</h1>
        <div class="sub">Khayrallah Issa &ndash; &ndash; CRM WooPremium uitbreiding</div>
    </header>

    <div class="card">
        <form method="get">
            <label for="dealer">Kies een dealer</label>
            <select name="dealer_id" id="dealer" onchange="this.form.submit()">
                <?php foreach ($dealers as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === $dealerId ? 'selected' : '' ?>>
                        <?= esc($d['name']) ?><?= $d['city'] ? ' - ' . esc($d['city']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

<?php if ($dealer): ?>
    <div class="card">
        <h2>Nieuwe notitie voor <?= esc($dealer['name']) ?></h2>
        <!-- Auteur: Khayrallah Issa - formulier wordt afgehandeld door dealer_notes.js -->
        <form id="crm-note-form" data-dealer-id="<?= (int)$dealerId ?>">
            <label for="content">Notitie</label>
            <textarea name="content" id="content" rows="4" maxlength="2000"
                      placeholder="Typ hier je notitie over deze dealer..."></textarea>
            <button type="submit" class="btn">Notitie opslaan</button>
            <div id="crm-note-status"></div>
        </form>
    </div>

    <div class="card">
        <h2>Notities (<?= count($notes) ?>)</h2>
        <?php if (empty($notes)): ?>
            <p class="empty">Nog geen notities voor deze dealer.</p>
        <?php else: ?>
            <?php foreach ($notes as $n): ?>
                <div class="note">
                    <div class="meta">
                        <?= esc($n['author'] ?? 'Onbekend') ?> &middot;
                        <?= esc(date('d-m-Y H:i', strtotime((string)$n['created_at']))) ?>
                    </div>
                    <div class="body"><?= esc($n['content']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card"><p class="empty">Geen dealer gekozen.</p></div>
<?php endif; ?>

    <script src="/crm-extensions/public/js/dealer_notes.js"></script>
</body>
</html>
