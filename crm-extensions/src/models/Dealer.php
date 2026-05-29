<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/models/Dealer.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Een 'model' / data-klasse. Vertegenwoordigt 1 rij uit de tabel
 *  wp_crm_dealers. Geen logica, alleen de velden + een fromRow() factory
 *  die een database-rij omzet naar een object.
 *
 *  De veldnamen sluiten 1-op-1 aan op de echte kolommen in de database
 *  (name, contact_person, street, postcode, lat, lng, deleted_at, ...).
 * ============================================================================
 */

namespace CrmExt\Models;

use DateTimeImmutable;
final class Dealer
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $contactPerson,
        public ?string $owner,
        public ?string $email,
        public ?string $phone,
        public ?string $street,
        public ?string $postcode,
        public ?string $city,
        public ?string $website,
        public ?string $status,
        public ?float $lat,
        public ?float $lng,
        public ?DateTimeImmutable $deletedAt
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:            isset($row['id']) ? (int)$row['id'] : null,
            name:          (string)($row['name'] ?? ''),
            contactPerson: $row['contact_person'] ?? null,
            owner:         $row['owner'] ?? null,
            email:         $row['email'] ?? null,
            phone:         $row['phone'] ?? null,
            street:        $row['street'] ?? null,
            postcode:      $row['postcode'] ?? null,
            city:          $row['city'] ?? null,
            website:       $row['website'] ?? null,
            status:        $row['status'] ?? null,
            lat:           isset($row['lat']) ? (float)$row['lat'] : null,
            lng:           isset($row['lng']) ? (float)$row['lng'] : null,
            deletedAt:     !empty($row['deleted_at']) ? new DateTimeImmutable($row['deleted_at']) : null,
        );
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
