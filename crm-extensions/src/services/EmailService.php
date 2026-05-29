<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/services/EmailService.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  De business-laag voor alles wat met e-mails te maken heeft:
 *    sendEmail()          - US-05 E-mail versturen vanaf de dealerpagina
 *    getThreadByDealer()  - US-07 Alle mails van/aan een dealer ophalen
 *    markAsRead()         - US-08 Mail markeren als gelezen
 *    linkIncoming()       - US-06 Inkomende mail aan dealer koppelen
 *
 *  Belangrijk: de MailerClient is OPTIONEEL gemaakt. Als PHPMailer nog niet
 *  geinstalleerd is via composer, wordt de mail in 'dev-modus' alleen
 *  opgeslagen in de database en niet echt verstuurd. Zo kun je US-05 en
 *  US-07 testen zonder dat je eerst SMTP-instellingen hoeft te regelen.
 * ============================================================================
 */

namespace CrmExt\Services;

use CrmExt\Helpers\MailerClient;
use CrmExt\Models\EmailMessage;
use CrmExt\Repositories\DealerRepository;
use CrmExt\Repositories\EmailRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class EmailService
{
    /**
     * Constructor.
     *
     * @param EmailRepository  $emailRepo   Voor opslag in wp_crm_emails.
     * @param DealerRepository $dealerRepo  Om dealer-info op te halen.
     * @param MailerClient|null $mailer     Optioneel: als null wordt niet echt
     *                                      verzonden (handig in dev).
     */
    public function __construct(
        private EmailRepository $emailRepo,
        private DealerRepository $dealerRepo,
        private ?MailerClient $mailer = null
    ) {}

    /**
     * US-05 E-mail versturen vanaf dealerpagina.
     *
     * Stappen:
     *   1. Onderwerp en bericht trimmen + verplicht-check.
     *   2. Dealer ophalen en controleren of die nog bestaat.
     *   3. EmailMessage-object opbouwen met richting 'out'.
     *   4. Proberen te versturen (alleen als MailerClient is geinjecteerd).
     *   5. Bij succes (of in dev-modus zonder mailer) opslaan in de database.
     *
     * @return bool true bij succes, false als de mailserver de mail weigerde.
     */
    public function sendEmail(int $userId, int $dealerId, string $subject, string $body): bool
    {
        // Stap 1: input opschonen + checken
        $subject = trim($subject);
        $body    = trim($body);
        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('Onderwerp en bericht zijn verplicht.');
        }

        // Stap 2: dealer bestaan en niet verwijderd?
        $dealer = $this->dealerRepo->findById($dealerId);
        if (!$dealer || $dealer->isDeleted()) {
            throw new InvalidArgumentException('Dealer bestaat niet of is verwijderd.');
        }
        if (empty($dealer->email)) {
            throw new InvalidArgumentException('Deze dealer heeft geen e-mailadres.');
        }

        // Stap 3: bouw het EmailMessage-object op
        $message = new EmailMessage(
            id:          null,
            dealerId:    $dealerId,
            userId:      $userId,
            direction:   'out',
            fromAddress: 'crm@woopremium.nl',
            toAddress:   $dealer->email,
            subject:     $subject,
            body:        $body,
            messageId:   $this->generateMessageId(),
            sentAt:      new DateTimeImmutable()
        );

        // Stap 4: alleen echt versturen als MailerClient is geinjecteerd.
        // In dev-modus (mailer == null) slaan we 'm op alsof verstuurd.
        if ($this->mailer !== null) {
            if (!$this->mailer->send($message)) {
                return false;
            }
        }

        // Stap 5: opslaan in wp_crm_emails
        $this->emailRepo->insert($message);
        return true;
    }

    /**
     * US-07 E-mailgeschiedenis voor een dealer ophalen.
     * @return EmailMessage[]
     */
    public function getThreadByDealer(int $dealerId): array
    {
        return $this->emailRepo->findByDealerId($dealerId);
    }

    /** US-06 Inkomende mail koppelen aan dealer op basis van afzender. */
    public function linkIncoming(EmailMessage $incoming): bool
    {
        // Skip als dezelfde mail al binnenkwam (deduplicatie).
        if ($this->emailRepo->existsByMessageId($incoming->messageId)) {
            return false;
        }
        // Zoek de bijbehorende dealer op het afzender-adres.
        $dealer = $this->dealerRepo->findByEmail($incoming->fromAddress);
        $incoming->dealerId  = $dealer?->id;
        $incoming->direction = 'in';
        $this->emailRepo->insert($incoming);
        return true;
    }

    /** US-08 Markeer een mail als gelezen (zet read_at = NOW). */
    public function markAsRead(int $emailId): bool
    {
        return $this->emailRepo->markAsRead($emailId);
    }

    /**
     * Maak een unieke message-id aan voor uitgaande mail. Wordt later
     * gebruikt om te zien of dezelfde mail niet ergens dubbel binnenkomt.
     */
    private function generateMessageId(): string
    {
        return sprintf('<%s.%s@crm.woopremium.nl>', uniqid('', true), bin2hex(random_bytes(4)));
    }
}
