<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/services/NoteService.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Business-logica voor notities bij dealers (US-13). De service controleert
 *  de invoer en of de dealer wel bestaat, en gebruikt daarna de
 *  NoteRepository om de notitie op te slaan of op te halen.
 *
 *  Methodes:
 *    addNote()           - US-13 een notitie toevoegen bij een dealer
 *    getNotesByDealer()  - alle notities van een dealer ophalen
 * ============================================================================
 */

namespace CrmExt\Services;

use CrmExt\Models\Note;
use CrmExt\Repositories\DealerRepository;
use CrmExt\Repositories\NoteRepository;
use InvalidArgumentException;

final class NoteService
{
    // Een notitie mag niet eindeloos lang zijn.
    private const MAX_LENGTH = 2000;

    public function __construct(
        private NoteRepository $noteRepo,
        private DealerRepository $dealerRepo
    ) {}

    /**
     * US-13 Een notitie toevoegen bij een dealer.
     * Auteur: Khayrallah Issa
     *
     * Stappen:
     *   1. De tekst trimmen en controleren dat hij niet leeg of te lang is.
     *   2. Controleren dat de dealer bestaat en niet verwijderd is.
     *   3. De notitie opslaan en het nieuwe id teruggeven.
     *
     * @param int    $userId    Id van de ingelogde marketeer
     * @param int    $dealerId  Id van de dealer
     * @param string $content   De tekst van de notitie
     * @return int              Het id van de opgeslagen notitie
     */
    public function addNote(int $userId, int $dealerId, string $content): int
    {
        // Stap 1: tekst opschonen en controleren
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('De notitie mag niet leeg zijn.');
        }
        if (mb_strlen($content) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                'De notitie mag maximaal ' . self::MAX_LENGTH . ' tekens lang zijn.'
            );
        }

        // Stap 2: bestaat de dealer wel en is hij niet verwijderd?
        $dealer = $this->dealerRepo->findById($dealerId);
        if (!$dealer || $dealer->isDeleted()) {
            throw new InvalidArgumentException('Dealer bestaat niet of is verwijderd.');
        }

        // Stap 3: opslaan via de repository
        $note = new Note(
            id:       null,
            dealerId: $dealerId,
            userId:   $userId,
            content:  $content
        );
        return $this->noteRepo->insert($note);
    }

    /**
     * Auteur: Khayrallah Issa
     * Geeft alle notities van een dealer terug (nieuwste eerst).
     *
     * @return Note[]
     */
    public function getNotesByDealer(int $dealerId): array
    {
        return $this->noteRepo->findByDealerId($dealerId);
    }
}
