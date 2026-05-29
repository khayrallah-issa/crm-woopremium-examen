<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/models/EmailMessage.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Model-klasse voor een e-mail (inkomend of uitgaand). Hoort bij wp_crm_emails.
 * ============================================================================
 */


namespace CrmExt\Models;

use DateTimeImmutable;

/** Een verzonden of ontvangen e-mail. */
final class EmailMessage
{
    /** @param array<int,array{name:string,path:string,mime:string,size:int}> $attachments */
    public function __construct(
        public ?int $id,
        public ?int $dealerId,
        public ?int $userId,
        public string $direction,     // 'in' of 'out'
        public string $fromAddress,
        public string $toAddress,
        public string $subject,
        public string $body,
        public string $messageId,
        public ?DateTimeImmutable $sentAt = null,
        public ?DateTimeImmutable $readAt = null,
        public array $attachments = []
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:          isset($row['id']) ? (int)$row['id'] : null,
            dealerId:    isset($row['dealer_id']) ? (int)$row['dealer_id'] : null,
            userId:      isset($row['user_id']) ? (int)$row['user_id'] : null,
            direction:   (string)$row['direction'],
            fromAddress: (string)$row['from_address'],
            toAddress:   (string)$row['to_address'],
            subject:     (string)$row['subject'],
            body:        (string)$row['body'],
            messageId:   (string)$row['message_id'],
            sentAt:      !empty($row['sent_at']) ? new DateTimeImmutable($row['sent_at']) : null,
            readAt:      !empty($row['read_at']) ? new DateTimeImmutable($row['read_at']) : null,
        );
    }

    public function isUnread(): bool
    {
        return $this->direction === 'in' && $this->readAt === null;
    }
}
