<?php
declare(strict_types=1);

require_once __DIR__ . '/Calendar.php';

/**
 * @return array{occupied:?list<string>,updated:?string,source?:string}
 */
function sala_availability(array $config): array
{
    $today = sala_today();
    $until = sala_add_months($today, (int) $config['months_ahead']);
    $mode = (string) $config['mode'];
    $cacheFile = sala_cache_dir() . '/availability-' . preg_replace('/[^a-z]/', '', $mode) . '.json';
    $ttl = max(60, (int) $config['cache_seconds']);

    if ($mode !== 'mock') {
        $cached = sala_cache_read($cacheFile, $ttl);
        if ($cached !== null) {
            return $cached;
        }
    }

    try {
        $occupied = match ($mode) {
            'ical' => sala_occupied_ical($config, $today, $until),
            'google' => sala_occupied_google($config, $today, $until),
            default => sala_mock_occupied($today),
        };
        $payload = [
            'occupied' => $occupied,
            'updated' => (new DateTimeImmutable('now'))->format('c'),
        ];
        if ($mode === 'mock' || ($mode !== 'ical' && $mode !== 'google')) {
            $payload['source'] = 'mock';
        } else {
            $payload['source'] = 'calendar';
        }
        if ($mode !== 'mock') {
            sala_cache_write($cacheFile, $payload);
        }
        return $payload;
    } catch (Throwable $e) {
        $stale = sala_cache_read($cacheFile, 6 * 3600);
        if ($stale !== null) {
            return $stale;
        }
        return [
            'occupied' => null,
            'updated' => null,
        ];
    }
}

/** @return list<string> */
function sala_occupied_ical(array $config, string $from, string $until): array
{
    $url = trim((string) $config['ical_url']);
    if ($url === '') {
        throw new RuntimeException('ical missing');
    }
    $ics = sala_http_get($url);
    return sala_occupied_from_ics($ics, $from, $until);
}

/** @return list<string> */
function sala_occupied_google(array $config, string $from, string $until): array
{
    $jsonPath = trim((string) $config['google_service_account_json']);
    $calendarId = trim((string) $config['calendar_id']);
    if ($jsonPath === '' || $calendarId === '' || !is_file($jsonPath)) {
        throw new RuntimeException('google missing');
    }
    $sa = json_decode((string) file_get_contents($jsonPath), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
        throw new RuntimeException('google key');
    }
    $token = sala_google_token($sa);
    $items = sala_google_events($token, $calendarId, $from, $until, (string) $config['timezone']);
    return sala_occupied_from_google_items($items, $from, $until);
}

function sala_google_token(array $sa): string
{
    $now = time();
    $header = sala_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $claim = sala_b64url(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));
    $unsigned = $header . '.' . $claim;
    $key = openssl_pkey_get_private($sa['private_key']);
    if ($key === false) {
        throw new RuntimeException('openssl');
    }
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('sign');
    }
    $jwt = $unsigned . '.' . sala_b64url($signature);
    $body = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    $response = sala_http_request(
        'POST',
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        $body
    );
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['access_token']) || !is_string($decoded['access_token'])) {
        throw new RuntimeException('token');
    }
    return $decoded['access_token'];
}

/** @return list<array<string,mixed>> */
function sala_google_events(string $token, string $calendarId, string $from, string $until, string $tz): array
{
    $items = [];
    $page = null;
    $timeMin = (new DateTimeImmutable($from . ' 00:00:00'))->format('c');
    $timeMax = (new DateTimeImmutable($until . ' 00:00:00'))->format('c');

    for ($i = 0; $i < 12; $i++) {
        $query = [
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => '2500',
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'timeZone' => $tz,
            'fields' => 'items(status,start,end),nextPageToken',
        ];
        if ($page !== null) {
            $query['pageToken'] = $page;
        }
        $url = 'https://www.googleapis.com/calendar/v3/calendars/'
            . rawurlencode($calendarId)
            . '/events?'
            . http_build_query($query);
        $response = sala_http_request('GET', $url, ['Authorization: Bearer ' . $token]);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('events');
        }
        if (!empty($decoded['items']) && is_array($decoded['items'])) {
            foreach ($decoded['items'] as $item) {
                if (is_array($item)) {
                    $items[] = [
                        'status' => $item['status'] ?? '',
                        'start' => $item['start'] ?? [],
                        'end' => $item['end'] ?? [],
                    ];
                }
            }
        }
        $page = isset($decoded['nextPageToken']) && is_string($decoded['nextPageToken'])
            ? $decoded['nextPageToken']
            : null;
        if ($page === null || $page === '') {
            break;
        }
    }

    return $items;
}

function sala_http_get(string $url): string
{
    if (str_starts_with($url, 'file://')) {
        $path = substr($url, 7);
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('read');
        }
        return $data;
    }
    if (str_starts_with($url, '/')) {
        $data = @file_get_contents($url);
        if ($data === false) {
            throw new RuntimeException('read');
        }
        return $data;
    }
    return sala_http_request('GET', $url, ['User-Agent: SalaKinekusAvailability/1.0']);
}

function sala_http_request(string $method, string $url, array $headers = [], ?string $body = null): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl');
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($response) || $code < 200 || $code >= 300) {
            throw new RuntimeException('http');
        }
        return $response;
    }

    $header = implode("\r\n", $headers);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $header,
            'content' => $body ?? '',
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    if (!is_string($response) || $status < 200 || $status >= 300) {
        throw new RuntimeException('http');
    }
    return $response;
}

function sala_b64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function sala_cache_read(string $file, int $ttl): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded) || !isset($decoded['savedAt'], $decoded['payload']) || !is_array($decoded['payload'])) {
        return null;
    }
    if ((time() - (int) $decoded['savedAt']) > $ttl) {
        return null;
    }
    $payload = $decoded['payload'];
    if (!array_key_exists('occupied', $payload)) {
        return null;
    }
    return [
        'occupied' => $payload['occupied'],
        'updated' => $payload['updated'] ?? null,
        'source' => $payload['source'] ?? 'calendar',
    ];
}

function sala_cache_write(string $file, array $payload): void
{
    $dir = dirname($file);
    if (!is_dir($dir) || !is_writable($dir)) {
        static $logged = false;
        if (!$logged) {
            $logged = true;
            error_log('Sála Kinekus: api/cache nie je zapisovateľná, dostupnosť sa neukladá do cache.');
        }
        return;
    }
    @file_put_contents($file, json_encode([
        'savedAt' => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
}
