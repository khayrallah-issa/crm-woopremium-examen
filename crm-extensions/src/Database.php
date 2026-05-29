<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/Database.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Centrale plek waar de database-verbinding wordt opgezet. Het is een
 *  'singleton': de verbinding wordt maar 1 keer aangemaakt en daarna
 *  gedeeld door iedereen. Zo voorkomen we dat we 10 keer verbinding
 *  hoeven te maken.
 *
 *  De verbinding leest de DB-gegevens uit config.php en gebruikt PDO
 *  met de strengste instellingen (exceptions, prepared statements, geen
 *  emulatie). Dat is onze eerste verdediging tegen SQL-injectie.
 *
 *  Gebruik in andere bestanden:
 *    $pdo = \CrmExt\Database::getConnection();
 * ============================================================================
 */

namespace CrmExt;

use PDO;
use PDOException;
use RuntimeException;
final class Database
{
    private static ?PDO $instance = null;

    /** Private constructor: deze klasse wordt nooit ge-instantieerd. */
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $configFile = __DIR__ . '/../config.php';
        if (!file_exists($configFile)) {
            throw new RuntimeException(
                "config.php ontbreekt. Kopieer config.example.php naar config.php en vul in."
            );
        }

        $config = require $configFile;
        $db     = $config['db'] ?? [];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host']     ?? '127.0.0.1',
            $db['port']     ?? 3306,
            $db['database'] ?? 'crm_issa',
            $db['charset']  ?? 'utf8mb4'
        );

        try {
            self::$instance = new PDO(
                $dsn,
                $db['username'] ?? 'root',
                $db['password'] ?? '',
                [
                    // Exceptions in plaats van stille fouten
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // Resultaten standaard als associatieve arrays
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Echte prepared statements (geen emulatie door MySQL)
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // Geen wachtwoord naar de gebruiker lekken
            error_log('DB-verbinding mislukt: ' . $e->getMessage());
            throw new RuntimeException('Kon geen verbinding maken met de database.');
        }

        return self::$instance;
    }

    /**
     * Handig voor tests: maakt het mogelijk om een eigen PDO te injecteren.
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
