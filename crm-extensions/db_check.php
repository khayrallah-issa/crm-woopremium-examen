<?php
/**
 * Database diagnostic - open in browser:
 *   http://crm-issa.local/crm-extensions/db_check.php
 *
 * Toont de kolommen van de bestaande wp_crm_* tabellen zodat we de SQL-migratie
 * goed kunnen aansluiten op jouw bestaande database.
 */
declare(strict_types=1);

$wpConfig = file_get_contents(__DIR__ . '/../wp-config.php');
preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbName = $m[1] ?? 'local';
preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbUser = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';

[$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Tabellen waarvan we de kolommen willen zien
$inspect = array_filter($tables, function($t) {
    return str_starts_with($t, 'wp_crm_') || $t === 'wp_users';
});
sort($inspect);

// Onze 5 nieuwe tabellen krijgen ook de wp_crm_ prefix
$expected_new = ['wp_crm_routes', 'wp_crm_route_stops', 'wp_crm_emails', 'wp_crm_email_attachments', 'wp_crm_audit_log'];
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>DB check v2 - CRM extensions</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 30px auto; padding: 0 20px; color: #222; }
    h1 { color: #1F4E79; }
    h2 { color: #2E75B6; margin-top: 36px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
    h3 { color: #555; margin-top: 24px; }
    table { border-collapse: collapse; margin: 8px 0; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 5px 9px; text-align: left; font-size: 13px; }
    th { background: #f2f2f2; }
    .ok    { color: #006400; font-weight: bold; }
    .miss  { color: #c00000; font-weight: bold; }
    code   { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    .pk    { color: #1F4E79; font-weight: bold; }
</style>
</head>
<body>

<h1>Database check v2 - CRM uitbreiding</h1>

<h2>Status nieuwe tabellen</h2>
<table>
    <tr><th>Tabel</th><th>Status</th></tr>
    <?php foreach ($expected_new as $t):
        $exists = in_array($t, $tables, true); ?>
        <tr>
            <td><code><?=$t?></code></td>
            <td class="<?=$exists?'ok':'miss'?>"><?=$exists?'AANWEZIG':'Nog aanmaken'?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Kolommen van bestaande wp_crm_* en wp_users tabellen</h2>

<?php foreach ($inspect as $t): ?>
    <h3><code><?=$t?></code></h3>
    <table>
        <tr><th>Veld</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>
        <?php foreach ($pdo->query("SHOW COLUMNS FROM `$t`") as $col):
            $isKey = $col['Key'] === 'PRI'; ?>
            <tr>
                <td class="<?=$isKey?'pk':''?>"><code><?=htmlspecialchars($col['Field'])?></code></td>
                <td><?=htmlspecialchars($col['Type'])?></td>
                <td><?=htmlspecialchars($col['Null'])?></td>
                <td><?=htmlspecialchars($col['Key'])?></td>
                <td><?=htmlspecialchars((string)$col['Default'])?></td>
                <td><?=htmlspecialchars($col['Extra'])?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php
    // Toon ook 1 rij data (eerste record) om te zien hoe data eruitziet
    $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "<p><em>$count rijen aanwezig.</em></p>";
    ?>
<?php endforeach; ?>

<hr>
<p><em>Stuur de inhoud van deze pagina door (kopieer-plak) zodat ik mijn SQL en code precies kan aansluiten op jouw kolommen.</em></p>

</body>
</html>
