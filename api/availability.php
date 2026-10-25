<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/Availability.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$config = sala_config();
$payload = sala_availability($config);

$public = [
    'occupied' => $payload['occupied'],
    'updated' => $payload['updated'],
];
if (($payload['source'] ?? '') === 'mock') {
    $public['source'] = 'mock';
}

echo json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
