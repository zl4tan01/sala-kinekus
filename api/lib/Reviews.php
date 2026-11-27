<?php
declare(strict_types=1);

function sala_reviews_default_file(): string
{
    return dirname(__DIR__, 2) . '/content/google-reviews.json';
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
    return sala_reviews_payload($listing, $write, $reviews);
}

/**
 * @param array<string,mixed> $file
 * @return array<string,mixed>
 */
function sala_review_without_staff(string $text): string
{
    $parts = preg_split('/(?<=[\.!\?])\s+/u', trim($text)) ?: [];
    $kept = [];
    foreach ($parts as $part) {
        if (preg_match('/mack[uů]/iu', $part) === 1) {
            continue;
        }
        $part = trim($part);
        if ($part !== '') {
            $kept[] = $part;
        }
    }
    return trim(implode(' ', $kept));
}

/**
 * @param array<string,mixed> $spec
 * @param list<array{author:string,rating:int,text:string,url:string}> $reviews
 * @return array{author:string,rating:int,text:string,url:string}|null
 */
function sala_review_specified(array $spec, array $reviews): ?array
{
    $text = trim((string) ($spec['text'] ?? ''));
    if ($text === '' || preg_match('/mack[uů]/iu', $text) === 1) {
        return null;
    }
    $author = sala_review_first_name((string) ($spec['author'] ?? ''));
    $rating = 0;
    $url = '';
    $found = false;
    foreach ($reviews as $review) {
        if (mb_stripos($review['text'], $text) === false) {
            continue;
        }
        $found = true;
        $rating = $review['rating'];
        $url = $review['url'];
        if ($author === '') {
            $author = $review['author'];
        }
        break;
    }
    if (!$found || $rating < 4) {
        return null;
    }
    return [
        'author' => $author,
        'rating' => $rating,
        'text' => $text,
        'url' => $url,
    ];
}

/**
 * @param list<array{author:string,rating:int,text:string,url:string,section?:string}> $reviews
 * @return array<string,mixed>
 */
function sala_reviews_guided(array $file, array $reviews, string $listing, string $write): array
{
    $general = [];
    $sections = [];
    foreach ($reviews as $review) {
        if ($review['rating'] < 4) {
            continue;
        }
        $section = trim((string) ($review['section'] ?? ''));
        unset($review['section']);
        if ($section !== '' && $section !== 'general') {
            $sections[$section][] = $review;
        } else {
            $general[] = $review;
        }
    }
    $pool = array_merge($general, ...array_values($sections));
    if ($pool === []) {
        return ['available' => false];
    }

    $hero = is_array($file['hero'] ?? null) ? sala_review_specified($file['hero'], $pool) : null;
    $form = is_array($file['formQuote'] ?? null) ? sala_review_specified($file['formQuote'], $pool) : null;
    $sectionQuotes = [];
    foreach ($file['sectionQuotes'] ?? [] as $name => $spec) {
        if (!is_string($name) || !is_array($spec)) {
            continue;
        }
        $line = sala_review_specified($spec, $pool);
        if ($line !== null) {
            $sectionQuotes[$name] = $line;
        }
    }

    $themes = [];
    foreach ($file['themes'] ?? [] as $phrase) {
        if (!is_string($phrase)) {
            continue;
        }
        $phrase = trim($phrase);
        if ($phrase === '' || preg_match('/mack[uů]/iu', $phrase) === 1) {
            continue;
        }
        foreach ($pool as $review) {
            if (mb_stripos($review['text'], $phrase) !== false) {
                $themes[] = ['text' => $phrase];
                break;
            }
        }
        if (count($themes) >= 5) {
            break;
        }
    }

    if ($hero === null || $form === null || $themes === []) {
        $curated = sala_reviews_curate($pool);
        if ($hero === null) {
            $hero = $curated['hero'];
        }
        if ($form === null) {
            $form = $curated['formQuote'];
        }
        if ($themes === []) {
            $themes = $curated['themes'];
        }
    }
    if (($hero['text'] ?? '') === '') {
        return ['available' => false];
    }

    return [
        'available' => true,
        'listingUrl' => $listing,
        'writeUrl' => $write,
        'hero' => $hero,
        'formQuote' => $form,
        'sectionQuotes' => $sectionQuotes,
        'themes' => $themes,
        'reviews' => $general,
        'sections' => $sections,
    ];
}

function sala_reviews_from_manual(array $file): array
{
    if (($file['sample'] ?? false) === true) {
        return ['available' => false];
    }
    $listing = is_string($file['listingUrl'] ?? null) ? $file['listingUrl'] : '';
    $write = is_string($file['writeReviewUrl'] ?? null) ? $file['writeReviewUrl'] : '';
    $reviews = [];
    $guided = is_array($file['hero'] ?? null) || is_array($file['themes'] ?? null);
    foreach ($file['reviews'] ?? [] as $review) {
        if (!is_array($review) || ($review['sample'] ?? false) === true) {
            continue;
        }
        $normalized = sala_reviews_one($review, false);
        if ($normalized === null) {
            continue;
        }
        $section = trim((string) ($review['section'] ?? ''));
        if ($section !== '') {
            $normalized['section'] = $section;
            $guided = true;
        }
        $reviews[] = $normalized;
    }
    if ($guided) {
        return sala_reviews_guided($file, $reviews, $listing, $write);
    }
    return sala_reviews_payload($listing, $write, $reviews);
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
    $text = sala_review_excerpt(sala_review_without_staff($text), 640);
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
 * Priemer a počet recenzií z profilu sa na web nedostanú, ani keď ich Google vráti.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function sala_reviews_without_aggregate(array $payload): array
{
    unset(
        $payload['proofLabel'],
        $payload['count'],
        $payload['countLabel'],
        $payload['ratingLabel'],
        $payload['userRatingCount']
    );
    if (array_key_exists('rating', $payload) && !is_array($payload['rating'])) {
        unset($payload['rating']);
    }
    return $payload;
}

/**
 * @param list<array{author:string,rating:int,text:string,url:string}> $reviews
 * @return array<string,mixed>
 */
function sala_reviews_payload(string $listing, string $write, array $reviews): array
{
    $curated = sala_reviews_curate($reviews);
    if ($curated['reviews'] === []) {
        return ['available' => false];
    }
    return [
        'available' => true,
        'listingUrl' => $listing,
        'writeUrl' => $write,
        'hero' => $curated['hero'],
        'formQuote' => $curated['formQuote'],
        'themes' => $curated['themes'],
        'reviews' => $curated['reviews'],
    ];
}

/** @return array<string,mixed> */
function sala_reviews_public(array $config): array
{
    $placeId = trim((string) ($config['google_place_id'] ?? ''));
    $key = trim((string) ($config['google_places_api_key'] ?? ''));
    $listing = trim((string) ($config['google_maps_url'] ?? ''));
    if ($listing === '') {
        $listing = 'https://www.google.com/maps/place/S%C3%A1la+%C5%BDilina/@49.2010185,18.7673104,17z/data=!4m8!3m7!1s0x471459e6058978c9:0x68ac7bf5a132ae59!8m2!3d49.2013982!4d18.7699948!9m1!1b1!16s%2Fg%2F11g0lbzfrx?hl=sk';
    }
    $ttl = (int) ($config['google_reviews_cache_seconds'] ?? 86400);
    if ($ttl < 300) {
        $ttl = 86400;
    }
    $cacheFile = sala_cache_dir() . '/reviews.json';

    if ($key !== '' && $placeId !== '') {
        $cached = sala_reviews_cache_read($cacheFile, $ttl);
        if ($cached !== null) {
            return sala_reviews_without_aggregate($cached);
        }
        try {
            $fresh = sala_reviews_without_aggregate(sala_reviews_fetch_places($key, $placeId, $listing));
            sala_reviews_cache_write($cacheFile, $fresh);
            return $fresh;
        } catch (Throwable $e) {
            $stale = sala_reviews_cache_read($cacheFile, 86400 * 14);
            if ($stale !== null) {
                return sala_reviews_without_aggregate($stale);
            }
        }
    }

    $path = trim((string) ($config['google_reviews_file'] ?? ''));
    if ($path === '') {
        $path = sala_reviews_default_file();
    }
    return sala_reviews_without_aggregate(sala_reviews_read_manual($path));
}

/** @return array<string,mixed> */
function sala_reviews_fetch_places(string $key, string $placeId, string $listing): array
{
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=sk';
    $raw = sala_http_request('GET', $url, [
        'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: googleMapsUri,reviews.rating,reviews.text,reviews.originalText,reviews.authorAttribution.displayName,reviews.googleMapsUri',
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
