<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/services/DealerService.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Dit is de 'business-logic laag' voor alles wat met dealers te maken heeft.
 *  De DealerService gebruikt:
 *    - de DealerRepository (om met de database te praten)
 *    - de AuditLogger      (om elke verwijder/herstel-actie op te slaan)
 *
 *  Methodes in dit bestand:
 *    softDelete()  - Verwijdert een dealer met 'soft-delete' + audit-log (US-09)
 *    restore()     - Haalt een verwijderde dealer terug uit de prullenbak (US-11)
 *    listAll()     - Geeft alle actieve dealers terug
 *    listTrashed() - Geeft de verwijderde dealers terug (US-10 prullenbak)
 *    purgeOld()    - Cronjob: verwijdert definitief na X dagen (US-10)
 *
 *  Waarom een aparte Service-laag?
 *  Hier zit de logica die meer doet dan alleen een SQL-query. Bijvoorbeeld:
 *  'eerst kijken of de dealer bestaat, dan verwijderen, dan ook nog loggen'.
 *  De Controller hoeft dit alles niet te weten; die roept gewoon softDelete()
 *  aan en krijgt true of false terug.
 * ============================================================================
 */

namespace CrmExt\Services;

use CrmExt\Helpers\AuditLogger;
use CrmExt\Models\Dealer;
use CrmExt\Repositories\DealerRepository;
use RuntimeException;

final class DealerService
{
    // Constructor: deze service heeft een DealerRepository en een AuditLogger
    // nodig om te kunnen werken. Die worden ge-injecteerd vanuit de API-router.
    public function __construct(
        private DealerRepository $repo,
        private AuditLogger $auditLog
    ) {}

    /**
     * US-09 Dealer verwijderen (met audit-log).
     *
     * Stappen die deze methode doet:
     *   1. Zoek de dealer op via de repository.
     *   2. Als de dealer niet bestaat of al verwijderd is -> false teruggeven.
     *   3. Markeer de dealer als verwijderd (zet deleted_at op NOW()).
     *   4. Schrijf een regel in het audit-log met wie het deed en wat de
     *      oude waarden waren (naam + e-mail), zodat we het later kunnen
     *      terugzien als er vragen komen.
     *
     * @param int $dealerId  Id van de dealer die verwijderd wordt
     * @param int $userId    Id van de marketeer die de actie uitvoert
     * @return bool          true als verwijdering gelukt is, anders false
     */
    public function softDelete(int $dealerId, int $userId): bool
    {
        // Stap 1: dealer ophalen
        $dealer = $this->repo->findById($dealerId);

        // Stap 2: bestaat de dealer wel en is hij nog niet verwijderd?
        if (!$dealer || $dealer->isDeleted()) {
            return false;
        }

        // Stap 3: zachte verwijdering uitvoeren (deleted_at = NOW())
        $ok = $this->repo->softDelete($dealerId);

        // Stap 4: alleen loggen als de verwijdering ook echt geslaagd is
        if ($ok) {
            $this->auditLog->log(
                userId:     $userId,
                entityType: 'dealer',
                entityId:   $dealerId,
                action:     'delete',
                oldValue:   ['name' => $dealer->name, 'email' => $dealer->email]
            );
        }
        return $ok;
    }

    /**
     * US-11 Dealer herstellen uit prullenbak.
     * Zet deleted_at terug op NULL en logt het in het audit-log.
     */
    public function restore(int $dealerId, int $userId): bool
    {
        $ok = $this->repo->restore($dealerId);
        if ($ok) {
            $this->auditLog->log(
                userId:     $userId,
                entityType: 'dealer',
                entityId:   $dealerId,
                action:     'restore'
            );
        }
        return $ok;
    }

    /**
     * Geeft alle actieve dealers terug (dus zonder de verwijderde).
     * @return Dealer[]
     */
    public function listAll(): array
    {
        return $this->repo->findAll();
    }

    /**
     * Geeft de dealers in de prullenbak terug (US-10).
     * @return Dealer[]
     */
    public function listTrashed(): array
    {
        return $this->repo->findTrashed();
    }

    /**
     * US-10 Cronjob: dealers die langer dan $days in de prullenbak staan
     * worden definitief verwijderd. Standaard 30 dagen.
     *
     * @param int $days  Aantal dagen dat een dealer in de prullenbak mag staan
     * @return int       Aantal dealers dat definitief is verwijderd
     */
    public function purgeOld(int $days = 30): int
    {
        if ($days < 1) {
            throw new RuntimeException('Aantal dagen moet positief zijn.');
        }
        return $this->repo->purgeOlderThanDays($days);
    }
}
