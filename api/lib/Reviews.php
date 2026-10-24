<?php
declare(strict_types=1);

function sala_reviews_default_file(): string
{
    return dirname(__DIR__, 2) . '/content/google-reviews.json';
}

function sala_review_count_label(int $count): string
{
    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod100 < 10 || $mod100 > 20) {
        if ($mod10 === 1) {
            return $count . ' recenzia';
        }
        if ($mod10 >= 2 && $mod10 <= 4) {
            return $count . ' recenzie';
        }
    }
    return $count . ' recenzií';
}

function sala_rating_label(float $rating): string
{
    return number_format(round($rating, 1), 1, ',', '');
}

function sala_review_first_name(string $name): string
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    if ($name === '') {
        return '';
    }
    $first = explode(' ', $name)[0];
    return mb_strlen($first) >= 2 ? $first : '';
}

function sala_review_excerpt(string $text, int $limit = 280): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($text === '' || mb_strlen($text) <= $limit) {
        return $text;
    }
    $cut = mb_substr($text, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > (int) ($limit * 0.6)) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " \t.,;:") . '…';
}

/**
 * @param array<string,mixed> $body
 * @return array<string,mixed>
 */
function sala_reviews_from_places(array $body, string $placeId, string $fallbackListing): array
{
    $listing = is_string($body['googleMapsUri'] ?? null) && $body['googleMapsUri'] !== ''
        ? $body['googleMapsUri']
        : $fallbackListing;
    $write = 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId);
    $reviews = [];
    foreach ($body['reviews'] ?? [] as $review) {
        if (!is_array($review)) {
            continue;
        }
        $normalized = sala_reviews_one($review, true);
        if ($normalized !== null) {
            $reviews[] = $normalized;
        }
    }
    return sala_reviews_payload(
        isset($body['rating']) ? (float) $body['rating'] : null,
        isset($body['userRatingCount']) ? (int) $body['userRatingCount'] : null,
        $listing,
        $write,
        $reviews
    );
}

/**
 * @param array<string,mixed> $file
 * @return array<string,mixed>
 */
function sala_reviews_from_manual(array $file): array
{
    if (($file['sample'] ?? false) === true) {
        return ['available' => false];
    }
    $listing = is_string($file['listingUrl'] ?? null) ? $file['listingUrl'] : '';
    $write = is_string($file['writeReviewUrl'] ?? null) ? $file['writeReviewUrl'] : '';
    $reviews = [];
    foreach ($file['reviews'] ?? [] as $review) {
        if (!is_array($review) || ($review['sample'] ?? false) === true) {
            continue;
        }
        $normalized = sala_reviews_one($review, false);
        if ($normalized !== null) {
            $reviews[] = $normalized;
        }
    }
    $rating = is_numeric($file['rating'] ?? null) ? (float) $file['rating'] : null;
    $count = is_numeric($file['userRatingCount'] ?? null) ? (int) $file['userRatingCount'] : null;
    return sala_reviews_payload($rating, $count, $listing, $write, $reviews);
}

/**
 * @param array<string,mixed> $review
 * @return array{author:string,rating:int,text:string,url:string}|null
 */
function sala_reviews_one(array $review, bool $places): ?array
{
    $text = '';
    if ($places) {
        $original = $review['originalText']['text'] ?? null;
        $localized = $review['text']['text'] ?? ($review['text'] ?? null);
        if (is_string($original) && trim($original) !== '') {
            $text = $original;
        } elseif (is_string($localized)) {
            $text = $localized;
        }
        $author = (string) ($review['authorAttribution']['displayName'] ?? '');
        $url = is_string($review['googleMapsUri'] ?? null) ? $review['googleMapsUri'] : '';
    } else {
        $text = is_string($review['text'] ?? null) ? $review['text'] : '';
        $author = is_string($review['author'] ?? null) ? $review['author'] : '';
        $url = is_string($review['url'] ?? null) ? $review['url'] : '';
        if (str_contains($text, 'Tento vzorový záznam sa na webe nezobrazí')) {
            return null;
        }
    }
    $text = sala_review_excerpt($text);
    if (mb_strlen($text) < 8) {
        return null;
    }
    $rating = (int) ($review['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        return null;
    }
    if ($url !== '' && preg_match('#^https://#', $url) !== 1) {
        $url = '';
    }
    return [
        'author' => sala_review_first_name($author),
        'rating' => $rating,
        'text' => $text,
        'url' => $url,
    ];
}

/** @return array<string, list<string>> */
function sala_review_theme_needles(): array
{
    return [
        'priestor' => ['priestor', 'priestrann', 'sála', 'sálu', 'sále', 'salu'],
        'parkovanie' => ['parkov'],
        'personal' => ['personál', 'personal', 'obsluh', 'ochotn', 'prívet', 'pracovní'],
        'organizacia' => ['organiz', 'koordin'],
        'svadba' => ['svadb'],
        'stuzkova' => ['stužk', 'stuzk'],
        'atmosfera' => ['atmosfér', 'atmosfer'],
    ];
}

/** @return list<string> */
function sala_review_theme_hits(string $text): array
{
    $hay = mb_strtolower($text);
    $hits = [];
    foreach (sala_review_theme_needles() as $id => $needles) {
        foreach ($needles as $needle) {
            if (mb_strpos($hay, $needle) !== false) {
                $hits[] = $id;
                break;
            }
        }
    }
    return $hits;
}

/** @param list<string> $needles */
function sala_review_clause(string $text, array $needles): string
{
    $parts = preg_split('/[\.!\?;,]+/u', $text) ?: [];
    $best = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if (mb_strlen($part) < 12) {
            continue;
        }
        $hay = mb_strtolower($part);
        $matched = false;
        foreach ($needles as $needle) {
            if (mb_strpos($hay, $needle) !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            continue;
        }
        if ($best === '' || mb_strlen($part) < mb_strlen($best)) {
            $best = $part;
        }
    }
    return $best === '' ? '' : sala_review_excerpt($best, 80);
}

/**
 * @param array{author:string,rating:int,text:string,url:string} $review
 * @return array{author:string,rating:int,text:string,url:string}
 */
function sala_review_line(array $review, int $limit): array
{
    return [
        'author' => $review['author'],
        'rating' => $review['rating'],
        'text' => sala_review_excerpt($review['text'], $limit),
        'url' => $review['url'],
    ];
}

/**
 * @param list<array{author:string,rating:int,text:string,url:string}> $reviews
 * @return array{reviews:list<array{author:string,rating:int,text:string,url:string}>,hero:array{author:string,rating:int,text:string,url:string},formQuote:array{author:string,rating:int,text:string,url:string},themes:list<array{text:string}>}
 */
function sala_reviews_curate(array $reviews): array
{
    $picked = [];
    foreach ($reviews as $review) {
        if ($review['rating'] < 4) {
            continue;
        }
        $hits = sala_review_theme_hits($review['text']);
        if ($hits === []) {
            continue;
        }
        $review['hits'] = $hits;
        $picked[] = $review;
    }
    usort($picked, static function (array $a, array $b): int {
        $byLength = mb_strlen($a['text']) <=> mb_strlen($b['text']);
        if ($byLength !== 0) {
            return $byLength;
        }
        return count($b['hits']) <=> count($a['hits']);
    });
    $picked = array_slice($picked, 0, 5);

    $shown = [];
    foreach ($picked as $review) {
        unset($review['hits']);
        $shown[] = $review;
    }
    if ($shown === []) {
        return ['reviews' => [], 'hero' => ['author' => '', 'rating' => 0, 'text' => '', 'url' => ''], 'formQuote' => ['author' => '', 'rating' => 0, 'text' => '', 'url' => ''], 'themes' => []];
    }

    $counts = [];
    foreach ($picked as $review) {
        foreach ($review['hits'] as $hit) {
            $counts[$hit] = ($counts[$hit] ?? 0) + 1;
        }
    }
    $priority = array_flip(array_keys(sala_review_theme_needles()));
    $themeIds = array_keys(array_filter($counts, static fn (int $count): bool => $count >= 2));
    usort($themeIds, static function (string $a, string $b) use ($counts, $priority): int {
        $byCount = $counts[$b] <=> $counts[$a];
        if ($byCount !== 0) {
            return $byCount;
        }
        return ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99);
    });
    $needles = sala_review_theme_needles();
    $themes = [];
    $used = [];
    foreach ($themeIds as $id) {
        if (count($themes) >= 4) {
            break;
        }
        $phrase = '';
        foreach ($picked as $review) {
            if (!in_array($id, $review['hits'], true)) {
                continue;
            }
            $clause = sala_review_clause($review['text'], $needles[$id]);
            if ($clause === '' || isset($used[$clause])) {
                continue;
            }
            if ($phrase === '' || mb_strlen($clause) < mb_strlen($phrase)) {
                $phrase = $clause;
            }
        }
        if ($phrase !== '') {
            $used[$phrase] = true;
            $themes[] = ['text' => $phrase];
        }
    }

    $heroSource = $shown[0];
    $formSource = $shown[1] ?? $shown[0];
    return [
        'reviews' => $shown,
        'hero' => sala_review_line($heroSource, 140),
        'formQuote' => sala_review_line($formSource, 160),
        'themes' => $themes,
    ];
}

/**
 * @param list<array{author:string,rating:int,text:string,url:string}> $reviews
 * @return array<string,mixed>
 */
function sala_reviews_payload(?float $rating, ?int $count, string $listing, string $write, array $reviews): array
{
    unset($rating);
    $curated = sala_reviews_curate($reviews);
    if ($curated['reviews'] === []) {
        return ['available' => false];
    }
    $payload = [
        'available' => true,
        'listingUrl' => $listing,
        'writeUrl' => $write,
        'hero' => $curated['hero'],
        'formQuote' => $curated['formQuote'],
        'themes' => $curated['themes'],
        'reviews' => $curated['reviews'],
    ];
    if ($count !== null && $count >= 1) {
        $payload['count'] = $count;
        $payload['countLabel'] = sala_review_count_label($count);
        $payload['proofLabel'] = '★ ' . $payload['countLabel'] . ' na Google';
    }
    return $payload;
}

/** @return array<string,mixed> */
function sala_reviews_public(array $config): array
{
    $placeId = trim((string) ($config['google_place_id'] ?? ''));
    $key = trim((string) ($config['google_places_api_key'] ?? ''));
    $listing = trim((string) ($config['google_maps_url'] ?? ''));
    if ($listing === '') {
        $listing = 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8';
    }
    $ttl = (int) ($config['google_reviews_cache_seconds'] ?? 86400);
    if ($ttl < 300) {
        $ttl = 86400;
    }
    $cacheFile = sala_cache_dir() . '/reviews.json';

    if ($key !== '' && $placeId !== '') {
        $cached = sala_reviews_cache_read($cacheFile, $ttl);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $fresh = sala_reviews_fetch_places($key, $placeId, $listing);
            sala_reviews_cache_write($cacheFile, $fresh);
            return $fresh;
        } catch (Throwable $e) {
            $stale = sala_reviews_cache_read($cacheFile, 86400 * 14);
            if ($stale !== null) {
                return $stale;
            }
        }
    }

    $path = trim((string) ($config['google_reviews_file'] ?? ''));
    if ($path === '') {
        $path = sala_reviews_default_file();
    }
    return sala_reviews_read_manual($path);
}

/** @return array<string,mixed> */
function sala_reviews_fetch_places(string $key, string $placeId, string $listing): array
{
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=sk';
    $raw = sala_http_request('GET', $url, [
        'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews.rating,reviews.text,reviews.originalText,reviews.authorAttribution.displayName,reviews.googleMapsUri',
        'Accept: application/json',
    ]);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('reviews');
    }
    return sala_reviews_from_places($decoded, $placeId, $listing);
}

/** @return array<string,mixed> */
function sala_reviews_read_manual(string $path): array
{
    if (!is_file($path)) {
        return ['available' => false];
    }
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return ['available' => false];
    }
    return sala_reviews_from_manual($decoded);
}

/** @return array<string,mixed>|null */
function sala_reviews_cache_read(string $file, int $ttl): ?array
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
    return $decoded['payload'];
}

/** @param array<string,mixed> $payload */
function sala_reviews_cache_write(string $file, array $payload): void
{
    @file_put_contents($file, json_encode([
        'savedAt' => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
