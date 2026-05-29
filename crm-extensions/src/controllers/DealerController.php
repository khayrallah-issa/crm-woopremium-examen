<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/controllers/DealerController.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Deze klasse vangt de HTTP-aanvragen op die met dealers te maken hebben.
 *  De controller doet ZELF geen logica - hij vertaalt alleen wat de browser
 *  stuurt naar een aanroep op de DealerService, en stuurt het antwoord
 *  terug als JSON.
 *
 *  Endpoints die deze controller behandelt:
 *    GET    /dealers           -> index()    : lijst van alle actieve dealers
 *    GET    /dealers/trash     -> trash()    : lijst van verwijderde dealers
 *    DELETE /dealers/{id}      -> delete()   : dealer naar prullenbak (US-09)
 *    POST   /dealers/{id}/restore -> restore(): dealer terughalen (US-11)
 * ============================================================================
 */

namespace CrmExt\Controllers;

use CrmExt\Services\DealerService;
use InvalidArgumentException;

final class DealerController
{
    /**
     * Constructor.
     *
     * @param DealerService $service        De service met alle dealer-logica.
     * @param int           $currentUserId  Id van de ingelogde marketeer
     *                                      (komt uit de sessie in index.php).
     */
    public function __construct(
        private DealerService $service,
        private int $currentUserId
    ) {}

    /**
     * GET /dealers
     * Stuurt een lijst terug van alle actieve dealers (zonder verwijderde).
     */
    public function index(): void
    {
        $dealers = $this->service->listAll();
        $this->json(['dealers' => array_map([$this, 'serialize'], $dealers)]);
    }

    /**
     * GET /dealers/trash
     * Stuurt een lijst terug van dealers die in de prullenbak zitten.
     */
    public function trash(): void
    {
        $dealers = $this->service->listTrashed();
        $this->json(['dealers' => array_map([$this, 'serialize'], $dealers)]);
    }

    /**
     * DELETE /dealers/{id} - US-09 Dealer verwijderen.
     * Stuurt een 404 terug als de dealer niet bestaat of al verwijderd was.
     */
    public function delete(int $id): void
    {
        $ok = $this->service->softDelete($id, $this->currentUserId);
        if (!$ok) {
            http_response_code(404);
            $this->json(['error' => 'Dealer niet gevonden of al verwijderd.']);
            return;
        }
        $this->json(['message' => 'Dealer verwijderd.']);
    }

    /**
     * POST /dealers/{id}/restore - US-11 Dealer terughalen uit prullenbak.
     */
    public function restore(int $id): void
    {
        $ok = $this->service->restore($id, $this->currentUserId);
        if (!$ok) {
            http_response_code(404);
            $this->json(['error' => 'Dealer niet in prullenbak of bestaat niet.']);
            return;
        }
        $this->json(['message' => 'Dealer hersteld.']);
    }

    /**
     * Vertaalt een Dealer-object naar een 'simpel' array dat we als JSON
     * naar de browser kunnen sturen. Niet alle velden gaan mee - alleen
     * wat de frontend nodig heeft om de lijst te tonen.
     */
    private function serialize($dealer): array
    {
        return [
            'id'             => $dealer->id,
            'name'           => $dealer->name,
            'contact_person' => $dealer->contactPerson,
            'email'          => $dealer->email,
            'city'           => $dealer->city,
            'lat'            => $dealer->lat,
            'lng'            => $dealer->lng,
            'deleted_at'     => $dealer->deletedAt?->format('c'),
        ];
    }

    /** Stuurt het $data array terug als JSON met de juiste header. */
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
