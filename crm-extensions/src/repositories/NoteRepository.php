<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/repositories/NoteRepository.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Database-toegang voor de tabel wp_crm_notes (US-13). Dit is een SQL-only
 *  laag: geen business-logica, alleen queries met prepared statements zodat
 *  SQL-injectie niet mogelijk is.
 * ============================================================================
 */

namespace CrmExt\Repositories;

use CrmExt\Models\Note;
use PDO;

/** Praat met de bestaande tabel 'wp_crm_notes'. */
// Auteur: Khayrallah Issa
// Niet 'final' gemaakt zodat PHPUnit deze klasse kan mocken in de unit tests.
class NoteRepository
{
    private const TABLE = 'wp_crm_notes';

    public function __construct(private PDO $pdo) {}

    /**
     * Auteur: Khayrallah Issa
     * Slaat een nieuwe notitie op en geeft het nieuwe id terug.
     */
    public function insert(Note $note): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (dealer_id, user_id, content, created_at)
             VALUES (:dealer, :user, :content, NOW())'
        );
        $stmt->execute([
            ':dealer'  => $note->dealerId,
            ':user'    => $note->userId,
            ':content' => $note->content,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Auteur: Khayrallah Issa
     * Haalt alle notities van één dealer op, nieuwste eerst. Via een LEFT JOIN
     * met wp_users halen we meteen de naam van de marketeer erbij.
     *
     * @return Note[]
     */
    public function findByDealerId(int $dealerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.display_name AS author
             FROM ' . self::TABLE . ' n
             LEFT JOIN wp_users u ON n.user_id = u.ID
             WHERE n.dealer_id = :d
             ORDER BY n.created_at DESC'
        );
        $stmt->execute([':d' => $dealerId]);
        return array_map([Note::class, 'fromRow'], $stmt->fetchAll());
    }

    /** Telt hoeveel notities een dealer heeft. */
    public function countByDealerId(int $dealerId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE dealer_id = :d'
        );
        $stmt->execute([':d' => $dealerId]);
        return (int)$stmt->fetchColumn();
    }
}
