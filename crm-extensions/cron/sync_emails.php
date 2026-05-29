<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  cron/sync_emails.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  EENMALIG sync-script. Trekt de twee e-mail-bronnen gelijk:
 *    A. wp_crm_emails        - mijn nieuwe tabel (US-05/06/07)
 *    B. wp_crm_contact_log   - de bestaande tabel van het dealer-crm-plugin
 *                              (type = 'email')
 *
 *  Voor de demo wil ik dat ALLE mails in BEIDE plekken staan, zodat:
 *    - mijn demo-pagina demo_dealer_emails.php alles toont
 *    - de bestaande "E-mail" tab in WordPress ook alles toont
 *
 *  Wat het script doet:
 *    1. Mails uit contact_log die nog niet in wp_crm_emails staan -> kopieren.
 *       Richting (in/uit) raden we: begint het onderwerp met '[INKOMEND]'
 *       dan is het 'in', anders 'out'.
 *    2. Mails uit wp_crm_emails die nog niet in contact_log staan -> kopieren.
 *
 *  Dubbele rijen worden voorkomen door te checken op dealer_id + onderwerp
 *  + datum (op de minuut).
 *
 *  Open in browser om te draaien:
 *    http://crm-issa.local/crm-extensions/cron/sync_emails.php
 *
 *  Dit is veilig om meerdere keren te draaien (idempotent).
 * ============================================================================
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// DB-verbinding via wp-config.php
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

echo "=== Sync e-mails: wp_crm_emails  <->  wp_crm_contact_log ===\n\n";

$copiedToEmails     = 0;
$copiedToContactLog = 0;

// --------------------------------------------------------------------------
// STAP 1: contact_log (type=email) -> wp_crm_emails
// Auteur: Khayrallah Issa
// --------------------------------------------------------------------------
echo "Stap 1: mails uit contact_log naar wp_crm_emails kopieren...\n";
// Auteur: Khayrallah Issa
// We koppelen wp_crm_dealers erbij zodat we het echte e-mailadres van de
// dealer kennen. Dat gebruiken we als afzender (inkomend) of ontvanger
// (uitgaand), in plaats van een algemene placeholder.
$contactMails = $pdo->query(
    "SELECT cl.*, d.email AS dealer_email
       FROM wp_crm_contact_log cl
       LEFT JOIN wp_crm_dealers d ON d.id = cl.dealer_id
      WHERE cl.type = 'email'
      ORDER BY cl.id"
)->fetchAll();

$existsInEmails = $pdo->prepare(
    "SELECT COUNT(*) FROM wp_crm_emails
     WHERE dealer_id = :d
       AND subject  = :s
       AND ABS(TIMESTAMPDIFF(MINUTE, sent_at, :date)) < 2"
);
$insertEmail = $pdo->prepare(
    "INSERT INTO wp_crm_emails
        (dealer_id, user_id, direction, from_address, to_address,
         subject, body, message_id, sent_at)
     VALUES (:d, :u, :dir, :from, :to, :subj, :body, :mid, :date)"
);

foreach ($contactMails as $row) {
    $subject = (string)($row['subject'] ?? '');
    // Richting raden op basis van de [INKOMEND] markering
    $isIncoming = str_starts_with($subject, '[INKOMEND]');
    $cleanSubject = trim(str_replace('[INKOMEND]', '', $subject));

    $existsInEmails->execute([
        ':d'    => $row['dealer_id'],
        ':s'    => $cleanSubject,
        ':date' => $row['contact_date'] ?? $row['created_at'],
    ]);
    if ((int)$existsInEmails->fetchColumn() > 0) {
        continue;   // al aanwezig, overslaan
    }

    // Auteur: Khayrallah Issa
    // Echt dealer-adres gebruiken; alleen terugvallen op een placeholder
    // als de dealer geen e-mailadres heeft.
    $dealerEmail = trim((string)($row['dealer_email'] ?? ''));
    $insertEmail->execute([
        ':d'    => $row['dealer_id'],
        ':u'    => $isIncoming ? null : ($row['user_id'] ?: 1),
        ':dir'  => $isIncoming ? 'in' : 'out',
        ':from' => $isIncoming
            ? ($dealerEmail ?: 'onbekend@dealer.nl')
            : 'crm@woopremium.nl',
        ':to'   => $isIncoming
            ? 'crm@woopremium.nl'
            : ($dealerEmail ?: 'dealer@onbekend.nl'),
        ':subj' => $cleanSubject,
        ':body' => (string)($row['content'] ?? ''),
        ':mid'  => '<sync-' . $row['id'] . '-' . uniqid() . '@crm.local>',
        ':date' => $row['contact_date'] ?? $row['created_at'],
    ]);
    $copiedToEmails++;
}
echo "  -> $copiedToEmails mails toegevoegd aan wp_crm_emails.\n\n";

// --------------------------------------------------------------------------
// STAP 2: wp_crm_emails -> contact_log (type=email)
// Auteur: Khayrallah Issa
// --------------------------------------------------------------------------
echo "Stap 2: mails uit wp_crm_emails naar contact_log kopieren...\n";
$myMails = $pdo->query(
    "SELECT * FROM wp_crm_emails WHERE dealer_id IS NOT NULL ORDER BY id"
)->fetchAll();

$existsInLog = $pdo->prepare(
    "SELECT COUNT(*) FROM wp_crm_contact_log
     WHERE type = 'email'
       AND dealer_id = :d
       AND REPLACE(subject, '[INKOMEND] ', '') = :s
       AND ABS(TIMESTAMPDIFF(MINUTE, contact_date, :date)) < 2"
);
$insertLog = $pdo->prepare(
    "INSERT INTO wp_crm_contact_log
        (dealer_id, user_id, type, subject, content, contact_date, created_at)
     VALUES (:d, :u, 'email', :subj, :body, :date, NOW())"
);

foreach ($myMails as $row) {
    $existsInLog->execute([
        ':d'    => $row['dealer_id'],
        ':s'    => $row['subject'],
        ':date' => $row['sent_at'],
    ]);
    if ((int)$existsInLog->fetchColumn() > 0) {
        continue;
    }
    $prefix = $row['direction'] === 'in' ? '[INKOMEND] ' : '';
    $insertLog->execute([
        ':d'    => $row['dealer_id'],
        ':u'    => $row['user_id'] ?: 1,
        ':subj' => $prefix . $row['subject'],
        ':body' => $row['body'],
        ':date' => $row['sent_at'],
    ]);
    $copiedToContactLog++;
}
echo "  -> $copiedToContactLog mails toegevoegd aan wp_crm_contact_log.\n\n";

// --------------------------------------------------------------------------
// Samenvatting
// --------------------------------------------------------------------------
echo "=== Klaar ===\n";
echo "Totaal naar wp_crm_emails:      $copiedToEmails\n";
echo "Totaal naar wp_crm_contact_log: $copiedToContactLog\n";
echo "\nBeide tabellen zijn nu gelijk getrokken.\n";
echo "Je 'test test' mail zou nu zowel in de plugin-tab als in\n";
echo "demo_dealer_emails.php zichtbaar moeten zijn.\n";
