<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/repositories/EmailRepository.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Database-toegang voor wp_crm_emails. SQL-only laag.
 * ============================================================================
 */


namespace CrmExt\Repositories;

use CrmExt\Models\EmailMessage;
use PDO;

/** Praat met de tabel 'wp_crm_emails' en 'wp_crm_email_attachments'. */
// Auteur: Khayrallah Issa
// Niet 'final' gemaakt zodat PHPUnit deze klasse kan mocken in de unit tests.
class EmailRepository
{
    private const TABLE         = 'wp_crm_emails';
    private const CONTACT_LOG   = 'wp_crm_contact_log';

    public function __construct(private PDO $pdo) {}

    /**
     * Slaat een mail op in wp_crm_emails EN schrijft tegelijk een regel
     * in wp_crm_contact_log met type='email'. Daardoor zien zowel mijn
     * eigen demo-pagina als de bestaande "E-mail" tab van het dealer-crm
     * plugin de mail.
     *
     * Auteur: Khayrallah Issa
     */
    public function insert(EmailMessage $mail): int
    {
        $sentAtStr = $mail->sentAt?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s');

        // ---- 1) In mijn eigen tabel opslaan (volledige info) -----------
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (dealer_id, user_id, direction, from_address, to_address,
                                 subject, body, message_id, sent_at)
             VALUES (:dealer, :user, :dir, :from, :to, :subject, :body, :msgid, :sent)'
        );
        $stmt->execute([
            ':dealer'  => $mail->dealerId,
            ':user'    => $mail->userId,
            ':dir'     => $mail->direction,
            ':from'    => $mail->fromAddress,
            ':to'      => $mail->toAddress,
            ':subject' => $mail->subject,
            ':body'    => $mail->body,
            ':msgid'   => $mail->messageId,
            ':sent'    => $sentAtStr,
        ]);
        $insertedId = (int)$this->pdo->lastInsertId();

        // ---- 2) Tegelijk in wp_crm_contact_log schrijven ---------------
        // Auteur: Khayrallah Issa
        // Hier hoeft alleen dealer_id, user_id, type, subject, content,
        // contact_date - precies wat de bestaande plugin ook gebruikt.
        // Als er geen dealer of user gekoppeld is, slaan we deze stap over,
        // want wp_crm_contact_log heeft die NOT NULL.
        if ($mail->dealerId !== null) {
            $logStmt = $this->pdo->prepare(
                'INSERT INTO ' . self::CONTACT_LOG . '
                    (dealer_id, user_id, type, subject, content, contact_date, created_at)
                 VALUES (:d, :u, :t, :s, :c, :date, NOW())'
            );
            $prefix = $mail->direction === 'in'
                ? '[INKOMEND] '       // duidelijk markeren in de bestaande tab
                : '';
            $logStmt->execute([
                ':d'    => $mail->dealerId,
                // Bestaande plugin verwacht een geldige user_id; bij
                // inkomende mail gebruiken we de ingelogde marketeer (=1).
                ':u'    => $mail->userId ?: 1,
                ':t'    => 'email',
                ':s'    => $prefix . $mail->subject,
                ':c'    => $mail->body,
                ':date' => $sentAtStr,
            ]);
        }

        return $insertedId;
    }

    /** Voor deduplicatie van inkomende mails (zie UC-06). */
    public function existsByMessageId(string $messageId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ' . self::TABLE . ' WHERE message_id = :m LIMIT 1');
        $stmt->execute([':m' => $messageId]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return EmailMessage[] */
    public function findByDealerId(int $dealerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE dealer_id = :d ORDER BY sent_at DESC'
        );
        $stmt->execute([':d' => $dealerId]);
        return array_map([EmailMessage::class, 'fromRow'], $stmt->fetchAll());
    }

    public function countUnreadByDealerId(int $dealerId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM " . self::TABLE . "
             WHERE dealer_id = :d AND direction = 'in' AND read_at IS NULL"
        );
        $stmt->execute([':d' => $dealerId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead(int $emailId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET read_at = NOW() WHERE id = :id AND read_at IS NULL'
        );
        $stmt->execute([':id' => $emailId]);
        return $stmt->rowCount() === 1;
    }
}
