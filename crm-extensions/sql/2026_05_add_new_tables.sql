-- ============================================================================
-- CRM WooPremium uitbreiding - SQL migratiescript v2
-- Auteur: Khayrallah Issa
-- Datum: 17 mei 2026
-- Aangepast aan de bestaande wp_crm_* tabelstructuur.
--
-- Wat doet dit script?
--   Maakt 4 nieuwe tabellen aan:
--     1. wp_crm_routes              - opgeslagen routes per marketeer
--     2. wp_crm_route_stops         - stops binnen een route
--     3. wp_crm_emails              - in- en uitgaande e-mails
--     4. wp_crm_email_attachments   - bijlagen
--
-- Wat doet dit script NIET (omdat het al bestaat in jouw DB)?
--   * deleted_at toevoegen aan wp_crm_dealers  --> bestaat al
--   * wp_crm_audit_log aanmaken                --> wp_crm_activity_log doet hetzelfde
--   * wp_crm_notes aanmaken                    --> bestaat al (US-13)
--   * wp_crm_followups aanmaken                --> bestaat al (US-17)
--
-- Hoe te draaien:
--   Local --> Database tab --> Adminer --> SQL --> paste & Execute
-- ============================================================================

START TRANSACTION;

-- ============================================================================
-- 1) wp_crm_routes - Opgeslagen routes per marketeer (US-04)
-- ============================================================================
CREATE TABLE wp_crm_routes (
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
        FOREIGN KEY (user_id) REFERENCES wp_users (ID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- 2) wp_crm_route_stops - Stops in een route (US-01, US-02, US-03)
-- ============================================================================
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
        FOREIGN KEY (route_id) REFERENCES wp_crm_routes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_stops_dealer
        FOREIGN KEY (dealer_id) REFERENCES wp_crm_dealers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- 3) wp_crm_emails - In- en uitgaande e-mails (US-05, US-06, US-07, US-08)
-- ============================================================================
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
        FOREIGN KEY (dealer_id) REFERENCES wp_crm_dealers (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_crm_emails_user
        FOREIGN KEY (user_id) REFERENCES wp_users (ID)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- 4) wp_crm_email_attachments - Bijlagen bij een e-mail
-- ============================================================================
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
        FOREIGN KEY (email_id) REFERENCES wp_crm_emails (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

COMMIT;

-- ============================================================================
-- Rollback (handmatig draaien als terug gewild)
-- ============================================================================
-- DROP TABLE wp_crm_email_attachments;
-- DROP TABLE wp_crm_emails;
-- DROP TABLE wp_crm_route_stops;
-- DROP TABLE wp_crm_routes;
