<?php
declare(strict_types=1);

namespace CrmExt\Helpers;

use PDO;

/**
 * AuditLogger
 *
 * Schrijft een regel in de BESTAANDE 'wp_crm_activity_log' tabel.
 * Bestaande kolommen: id, user_id, dealer_id, action, description, meta (JSON), created_at.
 * We zetten de oude waarde in het 'meta' veld als JSON.
 */
// Auteur: Khayrallah Issa
// Niet 'final' gemaakt zodat PHPUnit deze klasse kan mocken in de unit tests.
class AuditLogger
{
    private const TABLE = 'wp_crm_activity_log';

    public function __construct(private PDO $pdo) {}

    public function log(
        int $userId,
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValue = null
    ): void {
        $dealerId    = $entityType === 'dealer' ? $entityId : null;
        $description = sprintf('%s %s #%d', $action, $entityType, $entityId);
        $meta = [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_value'   => $oldValue,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (user_id, dealer_id, action, description, meta, created_at)
             VALUES (:user, :dealer, :action, :desc, :meta, NOW())'
        );
        $stmt->execute([
            ':user'   => $userId,
            ':dealer' => $dealerId,
            ':action' => $action,
            ':desc'   => $description,
            ':meta'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
