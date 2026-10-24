<?php
declare(strict_types=1);

function sala_config(): array
{
    $defaults = [
        'mode' => 'mock',
        'calendar_id' => 's5ehtdjuripasjnt59qrui0su4@group.calendar.google.com',
        'timezone' => 'Europe/Prague',
        'months_ahead' => 18,
        'cache_seconds' => 900,
        'ical_url' => '',
        'google_service_account_json' => '',
        'mail_to' => 'marketing@salazilina.sk',
        'mail_from' => 'marketing@salazilina.sk',
        'mail_from_name' => 'Sála Kinekus',
        'mail_transport' => 'mail',
        'mail_file' => '',
        'rate_limit_max' => 8,
        'rate_limit_window' => 1800,
        'google_places_api_key' => '',
        'google_place_id' => 'ChIJyXiJBeZZFEcRWa4yofV7rGg',
        'google_maps_url' => 'https://www.google.com/maps/place/S%C3%A1la+%C5%BDilina/@49.2010185,18.7673104,17z/data=!4m8!3m7!1s0x471459e6058978c9:0x68ac7bf5a132ae59!8m2!3d49.2013982!4d18.7699948!9m1!1b1!16s%2Fg%2F11g0lbzfrx?hl=sk',
        'google_reviews_cache_seconds' => 86400,
        'google_reviews_file' => '',
    ];

    $explicit = getenv('SALAK_CONFIG');
    $path = is_string($explicit) && $explicit !== ''
        ? $explicit
        : __DIR__ . '/config.php';

    $file = [];
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $file = $loaded;
        }
    }

    $config = array_merge($defaults, $file);
    $tz = (string) $config['timezone'];
    if (!in_array($tz, timezone_identifiers_list(), true)) {
        $tz = 'Europe/Prague';
        $config['timezone'] = $tz;
    }
    date_default_timezone_set($tz);

    return $config;
}

function sala_cache_dir(): string
{
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function sala_today(): string
{
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

function sala_add_days(string $iso, int $days): string
{
    $sign = $days >= 0 ? '+' : '';
    return (new DateTimeImmutable($iso))->modify($sign . $days . ' days')->format('Y-m-d');
}

function sala_add_months(string $iso, int $months): string
{
    $sign = $months >= 0 ? '+' : '';
    return (new DateTimeImmutable($iso))->modify($sign . $months . ' months')->format('Y-m-d');
}

/** @return list<string> */
function sala_each_date(string $start, string $endExclusive): array
{
    if ($endExclusive <= $start) {
        return [];
    }
    $out = [];
    for ($d = $start, $guard = 0; $d < $endExclusive && $guard < 800; $d = sala_add_days($d, 1), $guard++) {
        $out[] = $d;
    }
    return $out;
}
