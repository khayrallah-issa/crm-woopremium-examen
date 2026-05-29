<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/repositories/RouteRepository.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Database-toegang voor wp_crm_routes en wp_crm_route_stops. Bevat alleen SQL, geen logica.
 * ============================================================================
 */


namespace CrmExt\Repositories;

use CrmExt\Models\Route;
use CrmExt\Models\RouteStop;
use PDO;

/** Praat met de tabellen 'wp_crm_routes' en 'wp_crm_route_stops'. */
// Auteur: Khayrallah Issa
// Niet 'final' gemaakt zodat PHPUnit deze klasse kan mocken in de unit tests.
class RouteRepository
{
    private const TBL_ROUTES = 'wp_crm_routes';
    private const TBL_STOPS  = 'wp_crm_route_stops';

    public function __construct(private PDO $pdo) {}

    public function insert(Route $route): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::TBL_ROUTES . ' (user_id, name, total_distance_km, estimated_time_min)
                 VALUES (:user_id, :name, :dist, :time)'
            );
            $stmt->execute([
                ':user_id' => $route->userId,
                ':name'    => $route->name,
                ':dist'    => $route->totalDistanceKm,
                ':time'    => $route->estimatedTimeMin,
            ]);
            $routeId = (int)$this->pdo->lastInsertId();

            $stopStmt = $this->pdo->prepare(
                'INSERT INTO ' . self::TBL_STOPS . ' (route_id, dealer_id, sequence_number, arrival_time, note)
                 VALUES (:r, :d, :s, :t, :n)'
            );
            foreach ($route->stops as $stop) {
                /** @var RouteStop $stop */
                $stopStmt->execute([
                    ':r' => $routeId,
                    ':d' => $stop->dealerId,
                    ':s' => $stop->sequenceNumber,
                    ':t' => $stop->arrivalTime,
                    ':n' => $stop->note,
                ]);
            }

            $this->pdo->commit();
            return $routeId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?Route
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TBL_ROUTES . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $route = Route::fromRow($row);

        $stopStmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TBL_STOPS . ' WHERE route_id = :id ORDER BY sequence_number'
        );
        $stopStmt->execute([':id' => $id]);
        foreach ($stopStmt->fetchAll() as $stopRow) {
            $route->stops[] = RouteStop::fromRow($stopRow);
        }
        return $route;
    }

    /** @return Route[] */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TBL_ROUTES . ' WHERE user_id = :u ORDER BY created_at DESC'
        );
        $stmt->execute([':u' => $userId]);
        return array_map([Route::class, 'fromRow'], $stmt->fetchAll());
    }
}
