-- ============================================================================
--  Auteur:   Khayrallah Issa
--  Project:  CRM WooPremium uitbreiding
--  Bestand:  sql/migrate_and_sync.sql
--
--  Wat doet dit script?
--  --------------------
--  1. MIGRATIE - maakt de 4 nieuwe tabellen aan (CREATE TABLE IF NOT EXISTS,
--     dus veilig om meerdere keren te draaien):
--        wp_crm_routes, wp_crm_route_stops, wp_crm_emails, wp_crm_email_attachments
--  2. SYNC - trekt de bestaande e-mails gelijk tussen wp_crm_emails en
--     wp_crm_contact_log (zelfde logica als cron/sync_emails.php, maar in SQL).
--
--  Veilig om opnieuw te draaien: bestaande tabellen en rijen worden overgeslagen.
-- ============================================================================

-- ===========================================================================
-- DEEL 1 - MIGRATIE: nieuwe tabellen aanmaken
-- Auteur: Khayrallah Issa
-- ===========================================================================

-- 1) wp_crm_routes - opgeslagen routes per marketeer (US-04)
CREATE TABLE IF NOT EXISTS wp_crm_routes (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             BIGINT UNSIGNED NOT NULL COMMENT 'FK naar wp_users.ID',
    name                VARCHAR(150) NOT NULL,
    total_distance_km   DECIMAL(8,2) NULL,
    estimated_time_min  INT          NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_routes_user_id (user_id),
    CONSTRAINT fk_crm_routes_user
        FOREIGN KEY (user_id) REFERENCES wp_users (ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 2) wp_crm_route_stops - stops binnen een route (US-01, US-02, US-03)
CREATE TABLE IF NOT EXISTS wp_crm_route_stops (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 3) wp_crm_emails - in- en uitgaande e-mails (US-05, US-06, US-07, US-08)
CREATE TABLE IF NOT EXISTS wp_crm_emails (
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
        FOREIGN KEY (dealer_id) REFERENCES wp_crm_dealers (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_emails_user
        FOREIGN KEY (user_id) REFERENCES wp_users (ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 4) wp_crm_email_attachments - bijlagen bij een e-mail
CREATE TABLE IF NOT EXISTS wp_crm_email_attachments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ===========================================================================
-- DEEL 2 - SYNC stap 1: mails uit wp_crm_contact_log -> wp_crm_emails
-- Richting wordt geraden via de [INKOMEND]-markering in het onderwerp.
-- Auteur: Khayrallah Issa
-- ===========================================================================
INSERT INTO wp_crm_emails
    (dealer_id, user_id, direction, from_address, to_address,
     subject, body, message_id, sent_at)
SELECT
    cl.dealer_id,
    CASE WHEN cl.subject LIKE '[INKOMEND]%' THEN NULL
         ELSE COALESCE(NULLIF(cl.user_id, 0), 1) END,
    CASE WHEN cl.subject LIKE '[INKOMEND]%' THEN 'in' ELSE 'out' END,
    -- Echt dealer-adres gebruiken; placeholder alleen als fallback.
    CASE WHEN cl.subject LIKE '[INKOMEND]%'
         THEN COALESCE(NULLIF(d.email, ''), 'onbekend@dealer.nl')
         ELSE 'crm@woopremium.nl' END,
    CASE WHEN cl.subject LIKE '[INKOMEND]%'
         THEN 'crm@woopremium.nl'
         ELSE COALESCE(NULLIF(d.email, ''), 'dealer@onbekend.nl') END,
    TRIM(REPLACE(cl.subject, '[INKOMEND]', '')),
    COALESCE(cl.content, ''),
    CONCAT('<sync-cl-', cl.id, '@crm.local>'),
    COALESCE(cl.contact_date, cl.created_at)
FROM wp_crm_contact_log cl
LEFT JOIN wp_crm_dealers d ON d.id = cl.dealer_id
WHERE cl.type = 'email'
  AND NOT EXISTS (
        SELECT 1 FROM wp_crm_emails e
        WHERE e.dealer_id = cl.dealer_id
          AND e.subject   = TRIM(REPLACE(cl.subject, '[INKOMEND]', ''))
          AND ABS(TIMESTAMPDIFF(MINUTE, e.sent_at,
                                COALESCE(cl.contact_date, cl.created_at))) < 2
      );

-- ===========================================================================
-- DEEL 2 - SYNC stap 2: mails uit wp_crm_emails -> wp_crm_contact_log
-- Auteur: Khayrallah Issa
-- ===========================================================================
INSERT INTO wp_crm_contact_log
    (dealer_id, user_id, type, subject, content, contact_date, created_at)
SELECT
    e.dealer_id,
    COALESCE(NULLIF(e.user_id, 0), 1),
    'email',
    LEFT(CONCAT(CASE WHEN e.direction = 'in' THEN '[INKOMEND] ' ELSE '' END,
                e.subject), 255),
    e.body,
    e.sent_at,
    NOW()
FROM wp_crm_emails e
WHERE e.dealer_id IS NOT NULL
  AND NOT EXISTS (
        SELECT 1 FROM wp_crm_contact_log cl
        WHERE cl.type = 'email'
          AND cl.dealer_id = e.dealer_id
          AND REPLACE(cl.subject, '[INKOMEND] ', '') = e.subject
          AND ABS(TIMESTAMPDIFF(MINUTE, cl.contact_date, e.sent_at)) < 2
      );

-- ===========================================================================
-- DEEL 3 - Controle: laat het resultaat zien
-- Auteur: Khayrallah Issa
-- ===========================================================================
SELECT 'wp_crm_emails (totaal)'        AS controle, COUNT(*) AS aantal FROM wp_crm_emails
UNION ALL
SELECT 'wp_crm_emails (inkomend)',     COUNT(*) FROM wp_crm_emails WHERE direction = 'in'
UNION ALL
SELECT 'wp_crm_emails (uitgaand)',     COUNT(*) FROM wp_crm_emails WHERE direction = 'out'
UNION ALL
SELECT 'wp_crm_contact_log (e-mails)', COUNT(*) FROM wp_crm_contact_log WHERE type = 'email'
UNION ALL
SELECT 'wp_crm_routes',                COUNT(*) FROM wp_crm_routes;
