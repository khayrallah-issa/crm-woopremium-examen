<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/repositories/DealerRepository.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Deze klasse is de ENIGE plek waar SQL-queries voor de dealers-tabel
 *  worden uitgevoerd. Geen business logic, alleen 'database in, database uit'.
 *  Alle queries gebruiken PDO met prepared statements zodat SQL-injectie
 *  niet mogelijk is.
 *
 *  Tabel: wp_crm_dealers (bestaand)
 *  Belangrijke kolommen: id, name, contact_person, email, lat, lng, deleted_at
 * ============================================================================
 */

namespace CrmExt\Repositories;

use CrmExt\Models\Dealer;
use PDO;

// Auteur: Khayrallah Issa
// Niet 'final' gemaakt zodat PHPUnit deze klasse kan mocken in de unit tests.
class DealerRepository
{
    // De tabelnaam staat in 1 constante; als de naam ooit verandert,
    // hoeven we maar op 1 plek aan te passen.
    private const TABLE = 'wp_crm_dealers';

    public function __construct(private PDO $pdo) {}

    /**
     * Alle actieve dealers (niet in prullenbak).
     * @return Dealer[]
     */
    public function findAll(): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE deleted_at IS NULL ORDER BY name';
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map([Dealer::class, 'fromRow'], $rows);
    }

    /** Zoekt 1 dealer op id. Geeft null terug als niet gevonden. */
    public function findById(int $id): ?Dealer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Dealer::fromRow($row) : null;
    }

    /**
     * Zoekt een dealer op e-mailadres. Wordt gebruikt door UC-06 om
     * inkomende mails aan de juiste dealer te koppelen.
     */
    public function findByEmail(string $email): ?Dealer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ? Dealer::fromRow($row) : null;
    }

    /**
     * Soft-delete: zet de kolom deleted_at op de huidige datum.
     * De dealer blijft in de database maar verschijnt niet meer in de
     * standaard-lijst. Geeft true als precies 1 rij is bijgewerkt.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    /** Zet deleted_at terug op NULL (dealer komt weer in de hoofdlijst). */
    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Alle dealers in de prullenbak (deleted_at IS NOT NULL).
     * @return Dealer[]
     */
    public function findTrashed(): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC';
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map([Dealer::class, 'fromRow'], $rows);
    }

    /**
     * Definitief verwijderen na X dagen (gebruikt door de cronjob in US-10).
     * Geeft het aantal verwijderde rijen terug.
     */
    public function purgeOlderThanDays(int $days): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . self::TABLE .
            ' WHERE deleted_at IS NOT NULL
               AND deleted_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute([':days' => $days]);
        return $stmt->rowCount();
    }
}
