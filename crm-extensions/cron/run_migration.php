<?php
/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  cron/run_migration.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Voert het SQL-migratiescript uit dat de 4 nieuwe tabellen aanmaakt:
 *    wp_crm_routes, wp_crm_route_stops, wp_crm_emails, wp_crm_email_attachments
 *
 *  Normaal draai je SQL via Adminer, maar dit script doet het automatisch
 *  zodat je niet hoeft te knippen/plakken. Het script:
 *    1. Controleert per tabel of die al bestaat.
 *    2. Maakt de ontbrekende tabellen aan.
 *    3. Is veilig om meerdere keren te draaien (bestaande tabellen worden
 *       overgeslagen).
 *
 *  Open in browser:
 *    http://crm-issa.local/crm-extensions/cron/run_migration.php
 * ============================================================================
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// --- DB-verbinding via wp-config.php --------------------------------------
$wpConfig = file_get_contents(__DIR__ . '/../../wp-config.php');
preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbName = $m[1] ?? 'local';
preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbUser = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';
[$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo "Kon geen verbinding maken met de database: " . $e->getMessage() . "\n";
    exit;
}

echo "=== Migratie: nieuwe tabellen aanmaken ===\n";
echo "Database: $dbName\n\n";

// Bestaande tabellen ophalen zodat we niets dubbel doen
$existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// --------------------------------------------------------------------------
// De CREATE TABLE statements - per tabel apart zodat we ze 1 voor 1 kunnen
// controleren. Auteur: Khayrallah Issa
// --------------------------------------------------------------------------
$tables = [

    'wp_crm_routes' => "
        CREATE TABLE wp_crm_routes (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id             BIGINT UNSIGNED NOT NULL,
            name                VARCHAR(150) NOT NULL,
            total_distance_km   DECIMAL(8,2) NULL,
            estimated_time_min  INT          NULL,
            created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_routes_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci",

    'wp_crm_route_stops' => "
        CREATE TABLE wp_crm_route_stops (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            route_id        BIGINT UNSIGNED NOT NULL,
            dealer_id       BIGINT UNSIGNED NOT NULL,
            sequence_number SMALLINT     NOT NULL,
            arrival_time    TIME         NULL,
            note            VARCHAR(255) NULL,
            PRIMARY KEY (id),
            KEY idx_stops_route_seq (route_id, sequence_number),
            KEY idx_stops_dealer    (dealer_id),
            CONSTRAINT fk_crm_stops_route
                FOREIGN KEY (route_id) REFERENCES wp_crm_routes (id) ON DELETE CASCADE,
            CONSTRAINT fk_crm_stops_dealer
                FOREIGN KEY (dealer_id) REFERENCES wp_crm_dealers (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci",

    'wp_crm_emails' => "
        CREATE TABLE wp_crm_emails (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dealer_id     BIGINT UNSIGNED NULL,
            user_id       BIGINT UNSIGNED NULL,
            direction     ENUM('in','out') NOT NULL,
            from_address  VARCHAR(190) NOT NULL,
            to_address    VARCHAR(190) NOT NULL,
            subject       VARCHAR(255) NOT NULL,
            body          MEDIUMTEXT   NOT NULL,
            message_id    VARCHAR(190) NOT NULL,
            sent_at       DATETIME     NOT NULL,
            read_at       DATETIME     NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_emails_message_id (message_id),
            KEY idx_emails_dealer (dealer_id),
            KEY idx_emails_read   (read_at),
            CONSTRAINT fk_crm_emails_dealer
                FOREIGN KEY (dealer_id) REFERENCES wp_crm_dealers (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci",

    'wp_crm_email_attachments' => "
        CREATE TABLE wp_crm_email_attachments (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id        BIGINT UNSIGNED NOT NULL,
            file_name       VARCHAR(255) NOT NULL,
            file_path       VARCHAR(500) NOT NULL,
            mime_type       VARCHAR(100) NOT NULL,
            file_size_bytes INT          NOT NULL,
            PRIMARY KEY (id),
            KEY idx_attachments_email (email_id),
            CONSTRAINT fk_crm_attachments_email
                FOREIGN KEY (email_id) REFERENCES wp_crm_emails (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci",
];

$created = 0;
$skipped = 0;

foreach ($tables as $name => $sql) {
    if (in_array($name, $existing, true)) {
        echo "OVERSLAAN  $name (bestaat al)\n";
        $skipped++;
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "AANGEMAAKT $name\n";
        $created++;
    } catch (PDOException $e) {
        echo "FOUT       $name : " . $e->getMessage() . "\n";
    }
}

echo "\n=== Klaar ===\n";
echo "Aangemaakt: $created tabel(len)\n";
echo "Overgeslagen (bestond al): $skipped\n\n";

if ($created > 0 || $skipped === count($tables)) {
    echo "De database is nu klaar. Volgende stappen:\n";
    echo "  1. Draai cron/sync_emails.php om de bestaande mails gelijk te trekken.\n";
    echo "  2. Open public/ voor de demo's.\n";
}
