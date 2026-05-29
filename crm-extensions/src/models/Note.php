<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/models/Note.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Model-klasse voor een notitie bij een dealer. Hoort bij de bestaande
 *  tabel wp_crm_notes (US-13). Bevat geen logica - alleen de velden en een
 *  fromRow()-factory die een database-rij omzet naar een object.
 * ============================================================================
 */

namespace CrmExt\Models;

use DateTimeImmutable;

/** Eén notitie die een marketeer bij een dealer plaatst. */
final class Note
{
    public function __construct(
        public ?int $id,
        public int $dealerId,
        public int $userId,
        public string $content,
        public ?DateTimeImmutable $createdAt = null,
        // Naam van de marketeer (komt uit een JOIN met wp_users); kan leeg zijn.
        public ?string $author = null
    ) {}

    /**
     * Auteur: Khayrallah Issa
     * Zet een rij uit wp_crm_notes om naar een Note-object.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:        isset($row['id']) ? (int)$row['id'] : null,
            dealerId:  (int)$row['dealer_id'],
            userId:    (int)$row['user_id'],
            content:   (string)$row['content'],
            createdAt: !empty($row['created_at'])
                ? new DateTimeImmutable($row['created_at'])
                : null,
            author:    $row['author'] ?? null,
        );
    }
}
