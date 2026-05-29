<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/api/index.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Dit is de centrale 'router' van de API. De browser stuurt een verzoek
 *  zoals /api/index.php?route=/dealers/42 en dit script kiest dan de juiste
 *  Controller-methode om uit te voeren.
 *
 *  Endpoints (alles JSON):
 *    GET    /dealers                 -> lijst van actieve dealers
 *    GET    /dealers/trash           -> prullenbak
 *    DELETE /dealers/{id}            -> US-09 dealer verwijderen
 *    POST   /dealers/{id}/restore    -> US-11 dealer herstellen
 *
 *    POST   /emails                  -> US-05 e-mail versturen
 *    GET    /dealers/{id}/emails     -> US-07 e-mailgeschiedenis
 *    POST   /emails/{id}/read        -> US-08 mail als gelezen markeren
 *
 *    POST   /routes/calculate        -> US-02 route berekenen
 *    POST   /routes                  -> US-04 route opslaan
 *    GET    /routes                  -> mijn opgeslagen routes
 *
 *  Werkt OOK zonder composer install: dan worden de classes handmatig
 *  geladen via een eenvoudige autoloader hieronder.
 * ============================================================================
 */

namespace {
    // -------------------------------------------------------------------
    //  Auteur: Khayrallah Issa
    //  Eenvoudige autoloader (zodat de demo ook draait zonder composer)
    //  Werkt zo: vraag class CrmExt\Services\EmailService -> require
    //  src/services/EmailService.php
    // -------------------------------------------------------------------
    spl_autoload_register(function ($class) {
        $prefix = 'CrmExt\\';
        if (strpos($class, $prefix) !== 0) return;

        // Class -> bestandspad. Sub-namespaces in lowercase (controllers,
        // services, repositories, models, helpers).
        $relative = substr($class, strlen($prefix));
        $parts    = explode('\\', $relative);
        $fileName = array_pop($parts);
        $dirs     = array_map('strtolower', $parts);
        $path     = __DIR__ . '/../../src/' .
                    ($dirs ? implode('/', $dirs) . '/' : '') . $fileName . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });

    // Als composer-autoload bestaat, ook die laden (voor PHPMailer etc).
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
    }
}

namespace {
    use CrmExt\Helpers\AuditLogger;
    use CrmExt\Helpers\MailerClient;
    use CrmExt\Repositories\DealerRepository;
    use CrmExt\Repositories\EmailRepository;
    use CrmExt\Repositories\RouteRepository;
    use CrmExt\Services\DealerService;
    use CrmExt\Services\EmailService;
    use CrmExt\Services\RouteService;
    use CrmExt\Controllers\DealerController;
    use CrmExt\Controllers\EmailController;
    use CrmExt\Controllers\RouteController;
    // Auteur: Khayrallah Issa - US-13 Notities
    use CrmExt\Repositories\NoteRepository;
    use CrmExt\Services\NoteService;
    use CrmExt\Controllers\NoteController;

    session_name('crm_session');
    session_start();
    header('Content-Type: application/json; charset=utf-8');

    // ----- Veiligheid: alleen ingelogde marketeers --------------------
    // Auteur: Khayrallah Issa
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Niet ingelogd.']);
        exit;
    }

    // ----- Database verbinden via wp-config.php ------------------------
    // Auteur: Khayrallah Issa
    // We hergebruiken de WP-config zodat we geen aparte config.php nodig
    // hebben in de demo-fase.
    $wpConfig = file_get_contents(__DIR__ . '/../../../wp-config.php');
    preg_match("/define\(\s*'DB_NAME'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbName = $m[1] ?? 'local';
    preg_match("/define\(\s*'DB_USER'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbUser = $m[1] ?? 'root';
    preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*\"?'?([^'\"\)]+)/", $wpConfig, $m); $dbPass = $m[1] ?? 'root';
    preg_match("/define\(\s*'DB_HOST'\s*,\s*\"?'?([^'\"\)]+)/",     $wpConfig, $m); $dbHost = $m[1] ?? 'localhost';

    [$host, $port] = array_pad(explode(':', $dbHost), 2, '3306');
    $pdo = new \PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",
                    $dbUser, $dbPass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // ----- Dependency wiring -------------------------------------------
    // Auteur: Khayrallah Issa
    // We bouwen handmatig elke laag op. In dev-mode wordt de MailerClient
    // NIET geinjecteerd (PHPMailer is dan optioneel).
    $dealerRepo   = new DealerRepository($pdo);
    $emailRepo    = new EmailRepository($pdo);
    $routeRepo    = new RouteRepository($pdo);
    $auditLogger  = new AuditLogger($pdo);

    // MailerClient alleen aanmaken als PHPMailer beschikbaar is.
    $mailer = null;
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        $configFile = __DIR__ . '/../../config.php';
        if (file_exists($configFile)) {
            $cfg = require $configFile;
            $mailer = new MailerClient($cfg['smtp']);
        }
    }

    $dealerService = new DealerService($dealerRepo, $auditLogger);
    $emailService  = new EmailService($emailRepo, $dealerRepo, $mailer);
    $routeService  = new RouteService($routeRepo, $dealerRepo);

    $dealerCtrl = new DealerController($dealerService, $currentUserId);
    $emailCtrl  = new EmailController($emailService, $currentUserId);
    $routeCtrl  = new RouteController($routeService, $currentUserId);

    // Auteur: Khayrallah Issa - US-13 Notities: laag voor laag opbouwen
    $noteRepo    = new NoteRepository($pdo);
    $noteService = new NoteService($noteRepo, $dealerRepo);
    $noteCtrl    = new NoteController($noteService, $currentUserId);

    // ----- Route-matcher -----------------------------------------------
    // Auteur: Khayrallah Issa
    $method = $_SERVER['REQUEST_METHOD'];
    $route  = '/' . trim((string)($_GET['route'] ?? ''), '/');

    try {
        // ---- DEALERS --------------------------------------------------
        if ($method === 'GET'  && $route === '/dealers')          { $dealerCtrl->index(); exit; }
        if ($method === 'GET'  && $route === '/dealers/trash')    { $dealerCtrl->trash(); exit; }
        if ($method === 'DELETE' && preg_match('#^/dealers/(\d+)$#', $route, $m)) {
            $dealerCtrl->delete((int)$m[1]); exit;
        }
        if ($method === 'POST' && preg_match('#^/dealers/(\d+)/restore$#', $route, $m)) {
            $dealerCtrl->restore((int)$m[1]); exit;
        }

        // ---- EMAILS ---------------------------------------------------
        if ($method === 'POST' && $route === '/emails')           { $emailCtrl->send(); exit; }
        if ($method === 'GET'  && preg_match('#^/dealers/(\d+)/emails$#', $route, $m)) {
            $emailCtrl->listByDealer((int)$m[1]); exit;
        }
        if ($method === 'POST' && preg_match('#^/emails/(\d+)/read$#', $route, $m)) {
            $emailCtrl->markAsRead((int)$m[1]); exit;
        }

        // ---- NOTITIES (US-13) - Auteur: Khayrallah Issa -----
        if ($method === 'POST' && preg_match('#^/dealers/(\d+)/notes$#', $route, $m)) {
            $noteCtrl->create((int)$m[1]); exit;
        }
        if ($method === 'GET'  && preg_match('#^/dealers/(\d+)/notes$#', $route, $m)) {
            $noteCtrl->listByDealer((int)$m[1]); exit;
        }

        // ---- ROUTES ---------------------------------------------------
        if ($method === 'POST' && $route === '/routes/calculate') { $routeCtrl->calculate(); exit; }
        if ($method === 'POST' && $route === '/routes')           { $routeCtrl->save(); exit; }
        if ($method === 'GET'  && $route === '/routes')           { $routeCtrl->index(); exit; }
        if ($method === 'GET'  && preg_match('#^/routes/(\d+)$#', $route, $m)) {
            $routeCtrl->show((int)$m[1]); exit;   // US-04: 1 route opnieuw laden
        }

        http_response_code(404);
        echo json_encode(['error' => 'Endpoint niet gevonden: ' . $method . ' ' . $route]);
    } catch (\Throwable $e) {
        error_log('API-fout: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Er is iets misgegaan: ' . $e->getMessage()]);
    }
}
