<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/services/RouteService.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Business-logic rond routes (US-01 t/m US-04). Bevat:
 *    calculateRoute()  - haalt de coordinaten van alle dealers op en
 *                        belt OSRM (open routing service) om een echte
 *                        weg-route te berekenen met afstand en reistijd.
 *    saveRoute()       - slaat een geplande route op in wp_crm_routes.
 *    getUserRoutes()   - haalt alle routes van een marketeer op.
 *
 *  OSRM (Open Source Routing Machine) is een gratis service die
 *  beschikbaar is op https://router.project-osrm.org. We sturen 'm
 *  GPS-coordinaten en krijgen een lijst punten + afstand + tijd terug.
 *
 *  Veiligheid: maximaal 25 dealers per route om misbruik te voorkomen.
 * ============================================================================
 */

namespace CrmExt\Services;

use CrmExt\Models\Route;
use CrmExt\Models\RouteStop;
use CrmExt\Repositories\DealerRepository;
use CrmExt\Repositories\RouteRepository;
use InvalidArgumentException;
use RuntimeException;

final class RouteService
{
    // Maximaal aantal dealers per route (zie US-01)
    private const MAX_DEALERS = 25;

    // OSRM publieke server (kosteloos, beperkt gebruik)
    private const OSRM_URL = 'https://router.project-osrm.org';

    public function __construct(
        private RouteRepository $routeRepo,
        private DealerRepository $dealerRepo
    ) {}

    /**
     * US-02 Route berekenen
     *
     * Stappen:
     *  1. Controleer dat er minimaal 2 en maximaal 25 dealers zijn.
     *  2. Haal per dealer de lat/lng op uit de database.
     *  3. Vraag OSRM om een echte wegroute langs alle waypoints.
     *  4. Bij API-fout: fallback naar hemelsbrede schatting (Haversine).
     *
     * @param int[] $dealerIds
     * @return array{waypoints: array, geometry: array, total_distance_km: float, estimated_time_min: int}
     */
    public function calculateRoute(array $dealerIds): array
    {
        $count = count($dealerIds);
        if ($count < 2) {
            throw new InvalidArgumentException('Selecteer minimaal 2 dealers.');
        }
        if ($count > self::MAX_DEALERS) {
            throw new InvalidArgumentException('Maximaal ' . self::MAX_DEALERS . ' dealers per route.');
        }

        // Stap 2: dealer-info opzoeken
        $waypoints = [];
        foreach ($dealerIds as $i => $id) {
            $dealer = $this->dealerRepo->findById((int)$id);
            if (!$dealer || $dealer->isDeleted()) {
                throw new InvalidArgumentException("Dealer $id bestaat niet.");
            }
            if ($dealer->lat === null || $dealer->lng === null) {
                throw new InvalidArgumentException(
                    "Dealer '{$dealer->name}' heeft geen coordinaten (lat/lng)."
                );
            }
            $waypoints[] = [
                'dealer_id' => $dealer->id,
                'name'      => $dealer->name,
                'lat'       => (float)$dealer->lat,
                'lng'       => (float)$dealer->lng,
                'sequence'  => $i + 1,
            ];
        }

        // Stap 3: OSRM bellen voor een echte route
        try {
            $route = $this->fetchRouteFromOsrm($waypoints);
            return [
                'waypoints'          => $waypoints,
                'geometry'           => $route['geometry'],
                'total_distance_km'  => $route['distance_km'],
                'estimated_time_min' => $route['duration_min'],
            ];
        } catch (\Throwable $e) {
            // Stap 4: fallback (OSRM offline of geen internet)
            error_log('OSRM-fout, val terug op schatting: ' . $e->getMessage());
            $km = $this->estimateDistance($waypoints);
            return [
                'waypoints'          => $waypoints,
                'geometry'           => [],
                'total_distance_km'  => $km,
                'estimated_time_min' => (int)round($km * 1.2),
                'warning'            => 'Geschatte afstand (OSRM niet bereikbaar).',
            ];
        }
    }

    /**
     * US-04 Route opslaan onder een naam.
     */
    public function saveRoute(int $userId, string $name, array $dealerIds, float $distKm, int $timeMin): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Geef de route een naam.');
        }

        $route = new Route(
            id:               null,
            userId:           $userId,
            name:             $name,
            totalDistanceKm:  $distKm,
            estimatedTimeMin: $timeMin
        );
        foreach ($dealerIds as $i => $dId) {
            $route->stops[] = new RouteStop(
                id:             null,
                routeId:        0,                    // wordt door de repo gezet
                dealerId:       (int)$dId,
                sequenceNumber: $i + 1
            );
        }
        return $this->routeRepo->insert($route);
    }

    /** @return Route[] */
    public function getUserRoutes(int $userId): array
    {
        return $this->routeRepo->findByUserId($userId);
    }

    /**
     * Haalt 1 opgeslagen route op (inclusief stops), maar alleen als die
     * van de opgegeven marketeer is. Gebruikt om een route opnieuw te laden.
     * Auteur: Khayrallah Issa
     */
    public function getRoute(int $id, int $userId): ?Route
    {
        $route = $this->routeRepo->findById($id);
        if ($route === null || $route->userId !== $userId) {
            return null;
        }
        return $route;
    }

    // ====================================================================
    //  Interne helpers (private)
    // ====================================================================

    /**
     * Roept OSRM aan om een route langs alle waypoints te krijgen.
     * Geeft afstand (km), tijd (min) en de polyline-punten terug.
     */
    private function fetchRouteFromOsrm(array $waypoints): array
    {
        // OSRM wil ;-separated lng,lat (let op: lng eerst!)
        $coords = implode(';', array_map(
            fn($w) => $w['lng'] . ',' . $w['lat'],
            $waypoints
        ));
        $url = self::OSRM_URL . "/route/v1/driving/$coords?overview=full&geometries=geojson";

        $ctx = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true],
        ]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            throw new RuntimeException('OSRM niet bereikbaar.');
        }
        $data = json_decode($json, true);
        if (!isset($data['routes'][0])) {
            throw new RuntimeException('OSRM gaf geen route terug.');
        }
        $first = $data['routes'][0];
        return [
            'distance_km' => round($first['distance'] / 1000, 2),
            'duration_min'=> (int)round($first['duration'] / 60),
            // GeoJSON: [[lng,lat], [lng,lat], ...] - voor frontend
            'geometry'    => $first['geometry']['coordinates'] ?? [],
        ];
    }

    /** Fallback: schat afstand hemelsbreed (Haversine-formule). */
    private function estimateDistance(array $waypoints): float
    {
        $total = 0.0;
        for ($i = 1; $i < count($waypoints); $i++) {
            $total += $this->haversine(
                (float)$waypoints[$i-1]['lat'], (float)$waypoints[$i-1]['lng'],
                (float)$waypoints[$i]['lat'],   (float)$waypoints[$i]['lng']
            );
        }
        return round($total, 2);
    }

    /** Bereken afstand tussen 2 GPS-punten in km. */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;     // straal aarde in km
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return 2 * $earth * asin(sqrt($a));
    }
}
