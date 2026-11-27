<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/Availability.php';
require_once __DIR__ . '/lib/Reviews.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$payload = sala_reviews_without_aggregate(sala_reviews_public(sala_config()));
if (($payload['available'] ?? false) !== true) {
    $payload = ['available' => false];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
