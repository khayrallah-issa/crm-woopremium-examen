<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/controllers/EmailController.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  HTTP-laag voor email-endpoints (send, listByDealer, markAsRead).
 * ============================================================================
 */


namespace CrmExt\Controllers;

use CrmExt\Services\EmailService;
use InvalidArgumentException;

final class EmailController
{
    public function __construct(
        private EmailService $service,
        private int $currentUserId
    ) {}

    public function send(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        try {
            $ok = $this->service->sendEmail(
                userId:   $this->currentUserId,
                dealerId: (int)($body['dealer_id'] ?? 0),
                subject:  (string)($body['subject'] ?? ''),
                body:     (string)($body['body']    ?? '')
            );
            if (!$ok) {
                http_response_code(502);
                $this->json(['error' => 'Mailserver weigerde het bericht. Probeer later opnieuw.']);
                return;
            }
            $this->json(['message' => 'E-mail verzonden.']);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    public function listByDealer(int $dealerId): void
    {
        $mails = $this->service->getThreadByDealer($dealerId);
        $this->json(['emails' => array_map([$this, 'serialize'], $mails)]);
    }

    public function markAsRead(int $emailId): void
    {
        $this->service->markAsRead($emailId);
        $this->json(['message' => 'Gemarkeerd als gelezen.']);
    }

    private function serialize($m): array
    {
        return [
            'id'           => $m->id,
            'dealer_id'    => $m->dealerId,
            'direction'    => $m->direction,
            'from'         => $m->fromAddress,
            'to'           => $m->toAddress,
            'subject'      => $m->subject,
            'body'         => $m->body,
            'sent_at'      => $m->sentAt?->format('c'),
            'unread'       => $m->isUnread(),
        ];
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
