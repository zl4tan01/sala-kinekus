<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/Availability.php';
require_once __DIR__ . '/lib/Inquiry.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sala_respond(['ok' => false, 'errors' => ['form' => 'Formulár treba odoslať znova.']], 405);
}

$config = sala_config();
$post = $_POST;

if (trim((string) ($post['company_url'] ?? '')) !== '') {
    sala_respond(['ok' => true]);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (!sala_rate_limit_allows(
    sala_cache_dir(),
    $ip,
    (int) $config['rate_limit_max'],
    (int) $config['rate_limit_window']
)) {
    sala_respond([
        'ok' => false,
        'errors' => ['form' => 'Z tejto adresy prišlo veľa správ naraz. Skúste o chvíľu, alebo nám zavolajte.'],
    ], 429);
}

$availability = sala_availability($config);
$occupied = is_array($availability['occupied']) ? $availability['occupied'] : null;
$result = sala_validate_inquiry($post, $occupied, sala_today());

if ($result['errors'] !== [] || $result['data'] === null) {
    sala_respond(['ok' => false, 'errors' => $result['errors']], 422);
}

if (!sala_send_inquiry($config, $result['data'])) {
    sala_respond([
        'ok' => false,
        'errors' => ['form' => 'Správu sa nepodarilo odoslať. Napíšte nám priamo na marketing@salazilina.sk alebo zavolajte.'],
    ], 500);
}

sala_respond(['ok' => true]);

/** @param array<string,mixed> $payload */
function sala_respond(array $payload, int $code = 200): void
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $wantsJson = str_contains($accept, 'application/json') || $xhr === 'fetch';

    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = !empty($payload['ok']) ? '/?odoslane=1#termin' : '/?chyba=1#termin';
    header('Location: ' . $target, true, 303);
    exit;
}
