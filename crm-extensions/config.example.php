<?php
/**
 * Configuratie voor CRM-extensies.
 *
 * INSTRUCTIES:
 *   1. Kopieer dit bestand naar 'config.php' (zonder .example).
 *   2. Vul de juiste waarden in voor jouw omgeving.
 *   3. 'config.php' staat in .gitignore en komt dus NIET in de Git-repo.
 */

return [

    // --- Database (zie Local -> Database tab voor jouw waardes) -----------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 10003,                  // Local poort
        'database' => 'crm_issa',
        'username' => 'root',
        'password' => 'root',
        'charset'  => 'utf8mb4',
    ],

    // --- Uitgaande mail (PHPMailer / SMTP) ---------------------------------
    'smtp' => [
        'host'      => 'smtp.woopremium.nl',
        'port'      => 587,
        'username'  => 'crm@woopremium.nl',
        'password'  => 'VUL_HIER_IN',
        'encryption'=> 'tls',                 // 'tls' of 'ssl'
        'from_addr' => 'crm@woopremium.nl',
        'from_name' => 'WooPremium CRM',
    ],

    // --- Inkomende mail (IMAP) --------------------------------------------
    'imap' => [
        'host'     => 'imap.woopremium.nl',
        'port'     => 993,
        'username' => 'crm@woopremium.nl',
        'password' => 'VUL_HIER_IN',
        'encryption' => 'ssl',
        'mailbox'  => 'INBOX',
    ],

    // --- Kaart en routes ---------------------------------------------------
    // Kies 'leaflet' (gratis, OpenStreetMap + OSRM) of 'google' (API-sleutel nodig)
    'map' => [
        'provider'    => 'leaflet',
        'osrm_url'    => 'https://router.project-osrm.org',
        'google_key'  => '',
    ],

    // --- Algemeen -----------------------------------------------------------
    'app' => [
        'session_name' => 'crm_session',
        'timezone'     => 'Europe/Amsterdam',
        'log_path'     => __DIR__ . '/logs/app.log',
        // Aantal dagen dat een soft-deleted dealer in de prullenbak blijft
        'trash_days'   => 30,
    ],
];
