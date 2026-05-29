<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/demo_dealer_emails.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Demo-pagina voor US-05 (E-mail versturen) en US-07 (E-mailgeschiedenis).
 *  Werkt zonder dat we eerst alles in WordPress hoeven te integreren.
 *
 *  Wat zie je op deze pagina?
 *    - Een dropdown met dealers waar je er een uit kiest.
 *    - De volledige info van de gekozen dealer (naam, e-mail, plaats, etc.).
 *    - Een knop 'Stuur e-mail' die een modal opent (US-05).
 *    - Onder de info: de hele e-mailgeschiedenis met die dealer (US-07).
 *      Uitgaande mails zijn blauw, inkomende mails groen.
 *    - Klikken op een mail toont de volledige tekst (en markeert 'm als
 *      gelezen via US-08).
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/public/demo_dealer_emails.php
 *    http://crm-issa.local/crm-extensions/public/demo_dealer_emails.php?dealer_id=42
 * ============================================================================
 */
declare(strict_types=1);

session_name('crm_session');
session_start();

// Voor de demo zetten we user_id = 1 (vaak de WP admin) in de sessie.
// In productie loopt dit via de bestaande WordPress-login.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// Database-verbinding maken op basis van wp-config.php (zodat we
// niet eerst config.php voor het project hoeven in te vullen).
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

// Pak 30 dealers met een geldig e-mailadres om uit te kunnen kiezen.
$dealers = $pdo->query(
    "SELECT id, name, email, city
     FROM wp_crm_dealers
     WHERE deleted_at IS NULL
       AND email IS NOT NULL
       AND email <> ''
     ORDER BY name
     LIMIT 30"
)->fetchAll();

// Welke dealer is gekozen? Default: eerste in de lijst.
$dealerId = isset($_GET['dealer_id']) ? (int)$_GET['dealer_id'] : ($dealers[0]['id'] ?? 0);

// Haal het dealer-object op.
$dealer = null;
if ($dealerId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM wp_crm_dealers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $dealerId]);
    $dealer = $stmt->fetch() ?: null;
}

// E-mailgeschiedenis voor deze dealer (alleen als de tabel wp_crm_emails
// al bestaat - anders krijgen we een nette melding).
$emails = [];
$emailsTableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'wp_crm_emails'");
    if ($check && $check->fetchColumn()) {
        $emailsTableExists = true;
        if ($dealer) {
            $stmt = $pdo->prepare(
                'SELECT * FROM wp_crm_emails WHERE dealer_id = :d ORDER BY sent_at DESC'
            );
            $stmt->execute([':d' => $dealerId]);
            $emails = $stmt->fetchAll();
        }
    }
} catch (\Throwable $e) {
    $emailsTableExists = false;
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Demo - Dealer e-mails (US-05 + US-07)</title>
<link rel="stylesheet" href="/crm-extensions/public/css/crm_extensions.css">
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 30px auto; padding: 0 20px; color: #222; }
    h1 { color: #1F4E79; }
    h2 { color: #2E75B6; margin-top: 32px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
    .dealer-card { background: #f9f9f9; padding: 14px 18px; border-radius: 4px; margin-bottom: 12px; }
    .toolbar { margin-bottom: 12px; }
    .btn { background: #1F4E79; color: #fff; border: none; padding: 10px 18px; cursor: pointer; border-radius: 3px; font-size: 14px; font-weight: bold; }
    .btn:hover { background: #163a5c; }
    select { padding: 6px 10px; }
    .mail-item { padding: 12px 14px; border: 1px solid #ccc; border-radius: 4px; margin: 8px 0; cursor: pointer; }
    .mail-item.in  { background: #E8F5E9; border-color: #008B4F; }
    .mail-item.out { background: #DDEBF7; border-color: #1F4E79; }
    .mail-item.unread { box-shadow: 0 0 0 2px #C00000 inset; }
    .mail-meta { color: #666; font-size: 12px; margin-top: 4px; }
    .mail-body { display: none; margin-top: 10px; padding: 10px; background: #fff; border-left: 3px solid #ccc; }
    .mail-item.open .mail-body { display: block; }
    .warn { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 14px; border-radius: 4px; }
</style>
</head>
<body>
    <h1>Demo - E-mail per dealer (US-05 versturen + US-07 geschiedenis)</h1>

    <?php if (!$emailsTableExists): ?>
        <div class="warn">
            <strong>De tabel <code>wp_crm_emails</code> bestaat nog niet.</strong>
            Open Local -> Database -> Adminer en draai het SQL-script
            <code>crm-extensions/sql/2026_05_add_new_tables.sql</code> eerst.
        </div>
    <?php endif; ?>

    <form method="get" class="toolbar">
        <label for="dealer_id"><strong>Kies een dealer:</strong></label>
        <select name="dealer_id" id="dealer_id" onchange="this.form.submit()">
            <?php foreach ($dealers as $d): ?>
                <option value="<?=$d['id']?>" <?=$d['id']==$dealerId?'selected':''?>>
                    <?=htmlspecialchars($d['name'])?>
                    <?=$d['city'] ? '(' . htmlspecialchars($d['city']) . ')' : ''?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($dealer): ?>
        <div class="dealer-card">
            <h2 style="margin-top:0"><?=htmlspecialchars($dealer['name'])?></h2>
            <p>
                <strong>Contactpersoon:</strong> <?=htmlspecialchars($dealer['contact_person'] ?? '-')?><br>
                <strong>E-mail:</strong> <?=htmlspecialchars($dealer['email'] ?? '-')?><br>
                <strong>Plaats:</strong> <?=htmlspecialchars($dealer['city'] ?? '-')?>
            </p>
            <button type="button" class="btn"
                    data-email-compose
                    data-dealer-id="<?=$dealer['id']?>"
                    data-dealer-name="<?=htmlspecialchars($dealer['name'])?>"
                    data-dealer-email="<?=htmlspecialchars($dealer['email'] ?? '')?>">
                Stuur e-mail
            </button>
        </div>

        <h2>E-mailgeschiedenis (<?=count($emails)?>)</h2>
        <?php if (!$emails): ?>
            <p><em>Nog geen e-mails met deze dealer. Stuur er een via de knop hierboven.</em></p>
        <?php else: ?>
            <?php foreach ($emails as $m):
                $isUnread = $m['direction'] === 'in' && empty($m['read_at']); ?>
                <div class="mail-item <?=$m['direction']?> <?=$isUnread?'unread':''?>"
                     data-email-id="<?=$m['id']?>"
                     onclick="this.classList.toggle('open'); markAsRead(<?=$m['id']?>, this);">
                    <strong>
                        <?=$m['direction']==='in' ? 'IN' : 'UIT'?> -
                        <?=htmlspecialchars($m['subject'])?>
                    </strong>
                    <div class="mail-meta">
                        Van: <?=htmlspecialchars($m['from_address'])?> -
                        Aan: <?=htmlspecialchars($m['to_address'])?> -
                        <?=htmlspecialchars($m['sent_at'])?>
                        <?=$isUnread ? ' - <strong style="color:#C00">ongelezen</strong>' : ''?>
                    </div>
                    <div class="mail-body">
                        <?=nl2br(htmlspecialchars($m['body']))?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <hr>
    <p><em>Demo-pagina door Khayrallah Issa. Verwijder voor productie.</em></p>

    <script src="/crm-extensions/public/js/email_compose.js"></script>
    <script>
        // US-08: bij openen van een ongelezen mail wordt 'ie als gelezen
        // gemarkeerd via de markAsRead-endpoint.
        async function markAsRead(emailId, el) {
            if (!el.classList.contains('unread') || !el.classList.contains('open')) return;
            try {
                await fetch('/crm-extensions/public/api/index.php?route=/emails/' + emailId + '/read',
                            { method: 'POST' });
                el.classList.remove('unread');
            } catch (e) { /* stil ignoren, gebruiker hoeft dit niet te zien */ }
        }
    </script>
</body>
</html>
