<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/controllers/RouteController.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  HTTP-laag voor route-endpoints (calculate, save, index, show).
 * ============================================================================
 */


namespace CrmExt\Controllers;

use CrmExt\Services\RouteService;
use InvalidArgumentException;

final class RouteController
{
    public function __construct(
        private RouteService $service,
        private int $currentUserId
    ) {}

    public function calculate(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids  = $body['dealer_ids'] ?? [];
        try {
            $result = $this->service->calculateRoute($ids);
            $this->json($result);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    public function save(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $id = $this->service->saveRoute(
                userId:    $this->currentUserId,
                name:      (string)($body['name'] ?? ''),
                dealerIds: $body['dealer_ids'] ?? [],
                distKm:    (float)($body['total_distance_km'] ?? 0),
                timeMin:   (int)($body['estimated_time_min'] ?? 0)
            );
            $this->json(['id' => $id, 'message' => 'Route opgeslagen.']);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    public function index(): void
    {
        $routes = $this->service->getUserRoutes($this->currentUserId);
        $this->json([
            'routes' => array_map(fn($r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'total_distance_km' => $r->totalDistanceKm,
                'estimated_time_min'=> $r->estimatedTimeMin,
                'created_at'        => $r->createdAt?->format('c'),
            ], $routes),
        ]);
    }

    /**
     * GET /routes/{id} - 1 opgeslagen route ophalen, mét de dealer-volgorde,
     * zodat de frontend de route opnieuw kan laden en gebruiken (US-04).
     * Auteur: Khayrallah Issa
     */
    public function show(int $id): void
    {
        $route = $this->service->getRoute($id, $this->currentUserId);
        if ($route === null) {
            http_response_code(404);
            $this->json(['error' => 'Route niet gevonden.']);
            return;
        }
        $this->json([
            'id'                 => $route->id,
            'name'               => $route->name,
            'total_distance_km'  => $route->totalDistanceKm,
            'estimated_time_min' => $route->estimatedTimeMin,
            // Dealer-ids in de opgeslagen volgorde (op sequence_number).
            'dealer_ids'         => array_map(
                static fn($stop) => $stop->dealerId,
                $route->stops
            ),
        ]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
