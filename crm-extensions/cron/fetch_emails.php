<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  cron/fetch_emails.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Achtergrondtaak voor US-06: haalt inkomende e-mails op van de mailserver
 *  en koppelt ze automatisch aan de juiste dealer.
 *
 *  Dit script kan op DRIE manieren draaien:
 *    1. Echt (default): leest ongelezen mails via IMAP (vereist php-imap
 *       extensie en correcte gegevens in config.php).
 *    2. Demo (?demo=1): maakt 1 fake inkomende mail van een random dealer
 *       (handig om de UI te tonen als IMAP nog niet werkt).
 *    3. Test (?test=1): probeert alleen de IMAP-verbinding op te zetten
 *       (handig om credentials te controleren zonder mails te verwerken).
 *
 *  Draai handmatig (vanuit terminal):
 *    php cron/fetch_emails.php
 *
 *  Of via web:
 *    http://crm-issa.local/crm-extensions/cron/fetch_emails.php          (echt)
 *    http://crm-issa.local/crm-extensions/cron/fetch_emails.php?demo=1   (demo)
 *    http://crm-issa.local/crm-extensions/cron/fetch_emails.php?test=1   (test)
 *
 *  Plan voor productie: cronjob in Linux:
 *    *\/5 * * * * /usr/bin/php /pad/naar/cron/fetch_emails.php
 * ============================================================================
 */
declare(strict_types=1);

// --------------------------------------------------------------------------
// Auteur: Khayrallah Issa
// Bootstrap: autoloader laden + DB-verbinding maken via wp-config.php
// --------------------------------------------------------------------------

// Eenvoudige PSR-4-achtige autoloader voor CrmExt\*
spl_autoload_register(function ($class) {
    $prefix = 'CrmExt\\';
    if (strpos($class, $prefix) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $parts = explode('\\', $rel);
    $file = array_pop($parts);
    $dirs = array_map('strtolower', $parts);
    $path = __DIR__ . '/../src/' . ($dirs ? implode('/', $dirs) . '/' : '') . $file . '.php';
    if (file_exists($path)) require_once $path;
});

// DB-verbinding via wp-config.php (zelfde aanpak als de rest van het project)
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

// Auteur: Khayrallah Issa
// Detecteer of we via CLI of via de browser draaien, en welke modus.
$isWeb  = php_sapi_name() !== 'cli';
$isDemo = $isWeb && isset($_GET['demo']);
$isTest = $isWeb && isset($_GET['test']);

if ($isWeb) {
    header('Content-Type: text/plain; charset=utf-8');
}

// Config laden voor IMAP (alleen nodig in echt/test modus)
$config = null;
if (!$isDemo) {
    $cfgPath = __DIR__ . '/../config.php';
    if (!file_exists($cfgPath)) {
        echo "FOUT: config.php niet gevonden op $cfgPath\n";
        echo "Maak het bestand aan op basis van config.php.example en vul de IMAP-gegevens in.\n";
        exit(1);
    }
    $config = require $cfgPath;
}

// =========================================================================
// MODUS 1: DEMO -> fake inkomende mail genereren
// Auteur: Khayrallah Issa
// =========================================================================
if ($isDemo) {
    runDemo($pdo);
    exit;
}

// =========================================================================
// MODUS 2: TEST -> alleen IMAP-verbinding controleren
// Auteur: Khayrallah Issa
// =========================================================================
if ($isTest) {
    runImapTest($config['imap']);
    exit;
}

// =========================================================================
// MODUS 3: ECHT -> ongelezen mails ophalen uit IMAP-mailbox
// Auteur: Khayrallah Issa
// =========================================================================
runImapFetch($pdo, $config['imap']);


// ==========================================================================
// =============== FUNCTIES =================================================
// ==========================================================================

/**
 * Demo-modus: maak 1 fake inkomende mail aan voor een random dealer.
 * Auteur: Khayrallah Issa
 */
function runDemo(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT id, name, email FROM wp_crm_dealers
         WHERE deleted_at IS NULL AND email IS NOT NULL AND email <> ''
         ORDER BY RAND() LIMIT 1"
    );
    $dealer = $stmt->fetch();
    if (!$dealer) {
        echo "Geen dealers met e-mailadres gevonden.\n";
        return;
    }

    $messageId = '<demo-' . uniqid() . '@crm.test>';
    $subject   = pickRandomDemoSubject();
    $body      = sprintf(pickRandomDemoBody(), $dealer['name']);

    try {
        $pdo->beginTransaction();

        $newId = insertInkomendeMail(
            $pdo, (int)$dealer['id'], (string)$dealer['email'], $subject, $body, $messageId, null
        );

        $pdo->commit();

        echo "Inkomende mail gesimuleerd:\n";
        echo "  Van:       {$dealer['email']} ({$dealer['name']})\n";
        echo "  Onderwerp: $subject\n";
        echo "  Email ID:  $newId (ook in contact_log voor de plugin-tab)\n";
        echo "\nGa nu naar:\n";
        echo "  - demo_dealer_emails.php?dealer_id={$dealer['id']}\n";
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Fout: " . $e->getMessage() . "\n";
    }
}

/**
 * Test-modus: probeer een IMAP-verbinding op te zetten, niets verwerken.
 * Auteur: Khayrallah Issa
 */
function runImapTest(array $imap): void
{
    echo "=== IMAP-verbindingstest ===\n";
    echo "Host:     {$imap['host']}\n";
    echo "Poort:    {$imap['port']}\n";
    echo "Gebruik:  {$imap['username']}\n";
    echo "Beveil.:  {$imap['encryption']}\n";
    echo "Mailbox:  {$imap['mailbox']}\n\n";

    if (!extension_loaded('imap')) {
        echo "FOUT: PHP imap-extensie staat NIET aan.\n";
        echo "In Local: open de site -> 'Open Site Shell' -> php -m | findstr imap\n";
        echo "Zet 'extension=imap' aan in de php.ini van Local (zonder ; ervoor).\n";
        return;
    }

    $mailbox = bouwImapMailboxString($imap);
    echo "Probeer verbinding: $mailbox\n";

    $stream = @imap_open($mailbox, $imap['username'], $imap['password'], 0, 1);
    if ($stream === false) {
        echo "FOUT bij verbinden: " . imap_last_error() . "\n";
        echo "Tip: probeer 'encryption' op 'ssl' te zetten in config.php als 'tls' niet werkt.\n";
        return;
    }

    $status = imap_status($stream, $mailbox, SA_ALL);
    if ($status) {
        echo "OK - verbinding gelukt.\n";
        echo "Totaal aantal mails:    {$status->messages}\n";
        echo "Ongelezen mails (nieuw): {$status->unseen}\n";
        echo "Recent ontvangen:        {$status->recent}\n";
    } else {
        echo "Verbinding gemaakt, maar status kon niet gelezen worden.\n";
    }
    imap_close($stream);
}

/**
 * Echte IMAP-fetch: leest ongelezen mails uit INBOX en slaat ze op.
 * Auteur: Khayrallah Issa
 */
function runImapFetch(PDO $pdo, array $imap): void
{
    echo "=== US-06: Inkomende mails ophalen via IMAP ===\n";
    echo "Tijd: " . date('Y-m-d H:i:s') . "\n\n";

    if (!extension_loaded('imap')) {
        echo "FOUT: PHP imap-extensie staat NIET aan. Open Local -> site -> php.ini\n";
        echo "en zet 'extension=imap' aan (regel zonder ; ervoor). Daarna PHP herstarten.\n";
        exit(1);
    }

    $mailbox = bouwImapMailboxString($imap);
    $stream  = @imap_open($mailbox, $imap['username'], $imap['password']);
    if ($stream === false) {
        echo "FOUT bij verbinden met IMAP: " . imap_last_error() . "\n";
        echo "Controleer host/poort/wachtwoord in config.php.\n";
        exit(1);
    }

    // Auteur: Khayrallah Issa
    // Alleen ongelezen mails ophalen, anders verwerk je elke run hetzelfde.
    $ids = imap_search($stream, 'UNSEEN');
    if ($ids === false || count($ids) === 0) {
        echo "Geen nieuwe (ongelezen) mails in INBOX.\n";
        imap_close($stream);
        return;
    }

    echo "Aantal nieuwe mails: " . count($ids) . "\n\n";

    $aantalGekoppeld = 0;
    $aantalOnbekend  = 0;
    $aantalDubbel    = 0;

    foreach ($ids as $uid) {
        $headerObj = imap_headerinfo($stream, $uid);
        if (!$headerObj) {
            echo "  - mail #$uid: header niet leesbaar, overslaan.\n";
            continue;
        }

        // Afzender uit headers
        $fromMail = '';
        $fromName = '';
        if (!empty($headerObj->from[0])) {
            $f = $headerObj->from[0];
            $fromMail = strtolower(($f->mailbox ?? '') . '@' . ($f->host ?? ''));
            $fromName = $f->personal ?? $fromMail;
            $fromName = imap_utf8($fromName);
        }
        $subject  = imap_utf8($headerObj->subject ?? '(geen onderwerp)');
        $msgId    = $headerObj->message_id ?? ('<no-id-' . uniqid() . '@' . $imap['host'] . '>');
        $sentAt   = !empty($headerObj->date) ? date('Y-m-d H:i:s', strtotime($headerObj->date)) : null;

        // Auteur: Khayrallah Issa
        // Body uit de mail halen. We pakken eerst PLAIN, anders HTML stripped.
        $body = haalMailBody($stream, $uid);

        echo "- van $fromMail / $subject\n";

        // Dubbel-check op message_id
        $exists = $pdo->prepare("SELECT id FROM wp_crm_emails WHERE message_id = :m LIMIT 1");
        $exists->execute([':m' => $msgId]);
        if ($exists->fetchColumn()) {
            echo "    (al eerder verwerkt, message_id bestaat)\n";
            $aantalDubbel++;
            // Markeer toch als gelezen zodat we hem niet steeds terug zien
            imap_setflag_full($stream, (string)$uid, "\\Seen");
            continue;
        }

        // Dealer opzoeken op e-mailadres (case-insensitive)
        $q = $pdo->prepare(
            "SELECT id, name FROM wp_crm_dealers
              WHERE deleted_at IS NULL
                AND LOWER(email) = :e
              LIMIT 1"
        );
        $q->execute([':e' => $fromMail]);
        $dealer = $q->fetch();

        if (!$dealer) {
            echo "    geen dealer met dit e-mailadres, mail overslaan.\n";
            $aantalOnbekend++;
            // We laten 'm ongelezen staan in INBOX (geen \\Seen) zodat de
            // gebruiker zelf actie kan nemen.
            continue;
        }

        try {
            $pdo->beginTransaction();
            $newId = insertInkomendeMail(
                $pdo,
                (int)$dealer['id'],
                $fromMail,
                $subject,
                $body,
                $msgId,
                $sentAt
            );
            $pdo->commit();

            echo "    -> gekoppeld aan dealer #{$dealer['id']} ({$dealer['name']}), nieuw email_id $newId\n";
            $aantalGekoppeld++;

            // Markeer als gelezen op de server zodat we hem niet nog eens pakken
            imap_setflag_full($stream, (string)$uid, "\\Seen");
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "    FOUT bij opslaan: " . $e->getMessage() . "\n";
        }
    }

    imap_close($stream);

    echo "\n=== Klaar ===\n";
    echo "Gekoppeld aan dealer: $aantalGekoppeld\n";
    echo "Onbekende afzender:   $aantalOnbekend  (blijven ongelezen in INBOX)\n";
    echo "Al eerder verwerkt:   $aantalDubbel\n";
}

/**
 * Bouw de IMAP-URL string die imap_open() verwacht.
 * Auteur: Khayrallah Issa
 */
function bouwImapMailboxString(array $imap): string
{
    // Voor poort 993 is /imap/ssl meestal correcter dan /imap/tls; de config
    // mag 'tls' of 'ssl' zeggen. Beide werken in PHP IMAP.
    $sec = strtolower($imap['encryption'] ?? 'ssl');
    $sec = in_array($sec, ['tls', 'ssl', 'notls'], true) ? $sec : 'ssl';

    // /novalidate-cert is handig op localhost als het servercertificaat
    // niet 100% klopt. We laten dit standaard AAN voor Local by Flywheel.
    return sprintf(
        '{%s:%d/imap/%s/novalidate-cert}%s',
        $imap['host'],
        (int)$imap['port'],
        $sec,
        $imap['mailbox'] ?? 'INBOX'
    );
}

/**
 * Haal de tekstuele body van een IMAP-mail (PLAIN > HTML stripped).
 * Auteur: Khayrallah Issa
 */
function haalMailBody($stream, int $uid): string
{
    $structure = imap_fetchstructure($stream, $uid);
    if (!$structure) return '';

    // Geen onderdelen -> hele body in 1 keer pakken
    if (empty($structure->parts)) {
        $raw = imap_body($stream, $uid);
        return decodeerBodyDeel($raw, $structure->encoding ?? 0, $structure->subtype ?? 'PLAIN');
    }

    // Eerst proberen PLAIN te vinden, anders HTML
    $plain = zoekBodyDeel($stream, $uid, $structure->parts, 'PLAIN');
    if ($plain !== '') return $plain;

    $html = zoekBodyDeel($stream, $uid, $structure->parts, 'HTML');
    if ($html !== '') {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return '';
}

/**
 * Loop door de body-delen van een mail tot we het juiste subtype vinden.
 * Auteur: Khayrallah Issa
 */
function zoekBodyDeel($stream, int $uid, array $parts, string $wantSubtype, string $prefix = ''): string
{
    foreach ($parts as $i => $part) {
        $section = $prefix === '' ? (string)($i + 1) : "$prefix." . ($i + 1);
        $subtype = strtoupper($part->subtype ?? '');

        if ($subtype === $wantSubtype) {
            $raw = imap_fetchbody($stream, $uid, $section);
            return decodeerBodyDeel($raw, $part->encoding ?? 0, $subtype);
        }

        if (!empty($part->parts)) {
            $sub = zoekBodyDeel($stream, $uid, $part->parts, $wantSubtype, $section);
            if ($sub !== '') return $sub;
        }
    }
    return '';
}

/**
 * Decodeer een ruwe IMAP-body op basis van de encoding-vlag.
 * Auteur: Khayrallah Issa
 */
function decodeerBodyDeel(string $raw, int $encoding, string $subtype): string
{
    switch ($encoding) {
        case 3: // BASE64
            $decoded = base64_decode($raw, true);
            break;
        case 4: // QUOTED-PRINTABLE
            $decoded = quoted_printable_decode($raw);
            break;
        default:
            $decoded = $raw;
    }
    // Naar UTF-8 normaliseren als het al niet zo is
    if (!mb_check_encoding($decoded, 'UTF-8')) {
        $decoded = mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1, UTF-8, ASCII');
    }
    return trim((string)$decoded);
}

/**
 * Dubbele insert: in wp_crm_emails EN in wp_crm_contact_log,
 * zodat zowel mijn demo-pagina als de bestaande WP-admin tab het toont.
 * Geeft het nieuwe id terug uit wp_crm_emails.
 * Auteur: Khayrallah Issa
 */
function insertInkomendeMail(
    PDO $pdo,
    int $dealerId,
    string $fromAddress,
    string $subject,
    string $body,
    string $messageId,
    ?string $sentAt
): int {
    $sentAt = $sentAt ?: date('Y-m-d H:i:s');

    // 1) Mijn nieuwe tabel
    $ins = $pdo->prepare(
        "INSERT INTO wp_crm_emails
            (dealer_id, user_id, direction, from_address, to_address,
             subject, body, message_id, sent_at)
         VALUES (:d, NULL, 'in', :from, 'crm@woopremium.nl',
                 :subj, :body, :mid, :sent)"
    );
    $ins->execute([
        ':d'    => $dealerId,
        ':from' => $fromAddress,
        ':subj' => $subject,
        ':body' => $body,
        ':mid'  => $messageId,
        ':sent' => $sentAt,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // 2) Bestaande contact_log tabel (zodat de "E-mail" tab het laat zien)
    $logIns = $pdo->prepare(
        "INSERT INTO wp_crm_contact_log
            (dealer_id, user_id, type, subject, content, contact_date, created_at)
         VALUES (:d, 1, 'email', :subj, :body, :date, NOW())"
    );
    $logIns->execute([
        ':d'    => $dealerId,
        ':subj' => '[INKOMEND] ' . $subject,
        ':body' => $body,
        ':date' => $sentAt,
    ]);

    return $newId;
}

/**
 * Kies een willekeurig onderwerp voor een demo-mail.
 * Auteur: Khayrallah Issa
 */
function pickRandomDemoSubject(): string
{
    $subjects = [
        'Vraag over levertijd',
        'Bevestiging afspraak',
        'Aanvullende info gevraagd',
        'Re: Voorstel showroom-update',
        'Klacht over levering',
    ];
    return $subjects[array_rand($subjects)];
}

/**
 * Kies een willekeurige body-template voor een demo-mail.
 * %s wordt later vervangen door de dealer-naam met sprintf().
 * Auteur: Khayrallah Issa
 */
function pickRandomDemoBody(): string
{
    $bodies = [
        "Hoi,\n\nIk heb nog een vraag over de volgende bestelling. Kun je mij even bellen?\n\nGroeten,\n%s",
        "Goedemorgen,\n\nDe afspraak op vrijdag is bevestigd. Tot dan.\n\n%s",
        "Beste,\n\nKun je mij wat meer informatie sturen over de prijzen voor 2026?\n\nGroet,\n%s",
    ];
    return $bodies[array_rand($bodies)];
}
