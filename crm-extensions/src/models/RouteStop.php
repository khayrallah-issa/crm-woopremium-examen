<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/models/RouteStop.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Model-klasse voor 1 stop in een route. Hoort bij wp_crm_route_stops (US-01, US-02).
 * ============================================================================
 */


namespace CrmExt\Models;

/** Eén stop in een route. */
final class RouteStop
{
    public function __construct(
        public ?int $id,
        public int $routeId,
        public int $dealerId,
        public int $sequenceNumber,
        public ?string $arrivalTime = null,
        public ?string $note = null
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:             isset($row['id']) ? (int)$row['id'] : null,
            routeId:        (int)$row['route_id'],
            dealerId:       (int)$row['dealer_id'],
            sequenceNumber: (int)$row['sequence_number'],
            arrivalTime:    $row['arrival_time'] ?? null,
            note:           $row['note'] ?? null,
        );
    }
}
