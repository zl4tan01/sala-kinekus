<?php
/**
 * Skopírujte tento súbor na serveri ako api/config.php.
 * config.php nepatrí do gitu — je v .gitignore.
 *
 * Stačí vyplniť JEDEN spôsob čítania kalendára:
 *   mode = ical     tajná adresa iCal (najjednoduchšie na Websupporte)
 *   mode = google   Google Calendar API a service account
 *   mode = mock     ukážkové obsadené dni, bez prístupu ku kalendáru
 */
return [
    'mode' => 'mock',

    // Kalendár „Sála Žilina - Kinekus“. Pri režime google sa použije toto ID.
    'calendar_id' => 's5ehtdjuripasjnt59qrui0su4@group.calendar.google.com',
    'timezone' => 'Europe/Prague',

    // Koľko mesiacov dopredu endpoint vracia a ako dlho drží medzipamäť.
    'months_ahead' => 18,
    'cache_seconds' => 900,

    // --- Režim ical -------------------------------------------------------
    // Google Calendar → nastavenia kalendára → Integrovať kalendár
    // → Tajná adresa vo formáte iCal. Celú adresu vložte sem.
    // NIKDY ju nedávajte do prehliadača ani do verejného repozitára.
    'ical_url' => '',

    // --- Režim google -----------------------------------------------------
    // Cesta k JSON kľúču service accountu. Kalendár s ním musí byť zdieľaný
    // (oprávnenie „Zobraziť všetky podrobnosti udalosti“).
    'google_service_account_json' => '',

    // --- E-mail z formulára ----------------------------------------------
    'mail_to' => 'marketing@salazilina.sk',
    // Obálka From musí byť schránka na doméne, inak Websupport poštu odmietne.
    'mail_from' => 'marketing@salazilina.sk',
    'mail_from_name' => 'Sála Kinekus',

    // mail = PHP mail(). file = uloží správu do súboru (len na skúšku).
    'mail_transport' => 'mail',
    'mail_file' => '',

    // Koľko odoslaní z jednej IP za okno (sekundy).
    'rate_limit_max' => 8,
    'rate_limit_window' => 1800,
];
