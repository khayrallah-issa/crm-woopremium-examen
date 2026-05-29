<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/controllers/NoteController.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  HTTP-laag voor de notitie-endpoints (US-13). De controller vertaalt een
 *  HTTP-verzoek naar een aanroep op de NoteService en stuurt het antwoord
 *  terug als JSON. Zelf bevat de controller geen logica.
 *
 *  Endpoints:
 *    POST /dealers/{id}/notes  -> create()        notitie toevoegen
 *    GET  /dealers/{id}/notes  -> listByDealer()  notities ophalen
 * ============================================================================
 */

namespace CrmExt\Controllers;

use CrmExt\Services\NoteService;
use InvalidArgumentException;

final class NoteController
{
    public function __construct(
        private NoteService $service,
        private int $currentUserId
    ) {}

    /**
     * POST /dealers/{id}/notes - US-13 een notitie toevoegen.
     * Auteur: Khayrallah Issa
     */
    public function create(int $dealerId): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        try {
            $id = $this->service->addNote(
                userId:   $this->currentUserId,
                dealerId: $dealerId,
                content:  (string)($body['content'] ?? '')
            );
            $this->json(['id' => $id, 'message' => 'Notitie toegevoegd.']);
        } catch (InvalidArgumentException $e) {
            // Ongeldige invoer -> 422 met een nette foutmelding.
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /dealers/{id}/notes - de notities van een dealer ophalen.
     * Auteur: Khayrallah Issa
     */
    public function listByDealer(int $dealerId): void
    {
        $notes = $this->service->getNotesByDealer($dealerId);
        $this->json([
            'notes' => array_map(fn($n) => [
                'id'         => $n->id,
                'content'    => $n->content,
                'author'     => $n->author,
                'created_at' => $n->createdAt?->format('c'),
            ], $notes),
        ]);
    }

    /** Stuurt het $data-array terug als JSON met de juiste header. */
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
