<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/models/Route.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Model-klasse voor een opgeslagen route. Hoort bij wp_crm_routes (US-04).
 * ============================================================================
 */


namespace CrmExt\Models;

use DateTimeImmutable;

/** Een opgeslagen route met meerdere stops. */
final class Route
{
    /** @param RouteStop[] $stops */
    public function __construct(
        public ?int $id,
        public int $userId,
        public string $name,
        public ?float $totalDistanceKm = null,
        public ?int $estimatedTimeMin = null,
        public ?DateTimeImmutable $createdAt = null,
        public array $stops = []
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:               isset($row['id']) ? (int)$row['id'] : null,
            userId:           (int)$row['user_id'],
            name:             (string)$row['name'],
            totalDistanceKm:  isset($row['total_distance_km']) ? (float)$row['total_distance_km'] : null,
            estimatedTimeMin: isset($row['estimated_time_min']) ? (int)$row['estimated_time_min'] : null,
            createdAt:        !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
        );
    }
}
