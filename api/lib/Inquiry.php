<?php
declare(strict_types=1);

/** @return array<string,string> */
function sala_kind_labels(): array
{
    return [
        'konferencia' => 'Konferencia',
        'skolenie' => 'Školenie',
        'prezentacia' => 'Prezentácia',
        'firemna-akcia' => 'Firemná akcia',
        'svadba' => 'Svadba',
        'stuzkova' => 'Stužková',
        'oslava' => 'Oslava — narodeniny, jubileum, krstiny',
        'ples' => 'Ples',
        'ine' => 'Iné',
    ];
}

/** @return array<string,list<string>> */
function sala_kinds_by_segment(): array
{
    return [
        'b2b' => ['konferencia', 'skolenie', 'prezentacia', 'firemna-akcia', 'ine'],
        'b2c' => ['svadba', 'stuzkova', 'oslava', 'ples', 'ine'],
    ];
}

/**
 * @param list<string>|null $occupied null = dostupnosť sa nepodarila načítať, dátum neblokujeme
 * @return array{errors:array<string,string>,data:?array<string,mixed>}
 */
function sala_validate_inquiry(array $post, ?array $occupied, string $today): array
{
    $errors = [];
    $name = sala_one_line((string) ($post['name'] ?? ''), 120);
    $email = sala_clean_header((string) ($post['email'] ?? ''));
    $phone = sala_one_line((string) ($post['phone'] ?? ''), 40);
    $segment = (string) ($post['segment'] ?? '');
    $kind = (string) ($post['kind'] ?? '');
    $message = sala_clean_text((string) ($post['message'] ?? ''), 4000);
    $gdpr = (string) ($post['gdpr'] ?? '');
    $guestsRaw = trim((string) ($post['guests'] ?? ''));

    if (sala_len($name) < 2) {
        $errors['name'] = 'Napíšte meno a priezvisko.';
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || sala_len($email) > 160) {
        $errors['email'] = 'E-mail nevyzerá správne.';
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 9) {
        $errors['phone'] = 'Napíšte telefón, na ktorom vás zastihneme.';
    }

    $allowed = sala_kinds_by_segment();
    if (!isset($allowed[$segment])) {
        $errors['segment'] = 'Vyberte, či ide o firemnú alebo súkromnú akciu.';
    } elseif (!in_array($kind, $allowed[$segment], true)) {
        $errors['kind'] = 'Vyberte druh podujatia.';
    }

    $guests = null;
    if ($guestsRaw === '' || !preg_match('/^\d{1,4}$/', $guestsRaw)) {
        $errors['guests'] = 'Odhadnite počet hostí.';
    } else {
        $guests = (int) $guestsRaw;
        if ($guests < 1 || $guests > 5000) {
            $errors['guests'] = 'Počet hostí vyzerá nesprávne.';
        }
    }

    $dates = sala_parse_dates((string) ($post['dates'] ?? ''));
    if ($dates === []) {
        $errors['dates'] = 'Vyberte aspoň jeden deň, alebo ho napíšte vo formáte RRRR-MM-DD.';
    } else {
        $past = [];
        $blocked = [];
        foreach ($dates as $date) {
            if ($date < $today) {
                $past[] = $date;
            } elseif (is_array($occupied) && in_array($date, $occupied, true)) {
                $blocked[] = $date;
            }
        }
        if ($past !== []) {
            $errors['dates'] = 'Dátum v minulosti sa rezervovať nedá.';
        } elseif ($blocked !== []) {
            $errors['dates'] = 'Niektorý z vybraných dní je už obsadený. Vyberte iný, alebo nám napíšte.';
        } elseif (count($dates) > 14) {
            $errors['dates'] = 'Označte najviac 14 dní. Pri dlhšom termíne nám napíšte.';
        }
    }

    if (sala_len($message) > 4000) {
        $errors['message'] = 'Správa je príliš dlhá.';
    }
    if ($gdpr !== '1') {
        $errors['gdpr'] = 'Na odoslanie potrebujeme súhlas so spracovaním údajov.';
    }

    if ($errors !== []) {
        return ['errors' => $errors, 'data' => null];
    }

    return [
        'errors' => [],
        'data' => [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'segment' => $segment,
            'kind' => $kind,
            'guests' => $guests,
            'dates' => $dates,
            'message' => $message,
        ],
    ];
}

/** @return list<string> */
function sala_parse_dates(string $raw): array
{
    $raw = str_replace([';', "\n", "\r", '|'], ',', $raw);
    $out = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $iso = null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $part, $m)) {
            $iso = sala_date_only($m[1] . $m[2] . $m[3]);
        } elseif (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/', $part, $m)) {
            $iso = sala_date_only(sprintf('%04d%02d%02d', (int) $m[3], (int) $m[2], (int) $m[1]));
        }
        if ($iso !== null) {
            $out[$iso] = true;
        }
    }
    $dates = array_keys($out);
    sort($dates);
    return $dates;
}

function sala_inquiry_body(array $data): string
{
    $labels = sala_kind_labels();
    $segment = $data['segment'] === 'b2b' ? 'Firemná akcia' : 'Súkromná oslava';
    $kind = $labels[$data['kind']] ?? $data['kind'];
    $dates = array_map('sala_format_sk_date', $data['dates']);
    $lines = [
        'Nezáväzný dopyt z webu Sála Kinekus.',
        'Termín NIE JE rezervovaný. Potvrďte ho klientovi, až keď ho overíte.',
        '',
        'Dátumy: ' . implode(', ', $dates),
        'Typ: ' . $segment . ' — ' . $kind,
        'Odhad hostí: ' . $data['guests'],
        'Meno: ' . $data['name'],
        'E-mail: ' . $data['email'],
        'Telefón: ' . $data['phone'],
        '',
        'Správa:',
        $data['message'] !== '' ? $data['message'] : '—',
    ];
    return implode("\n", $lines) . "\n";
}

function sala_send_inquiry(array $config, array $data): bool
{
    $to = sala_clean_header((string) $config['mail_to']);
    $from = sala_clean_header((string) $config['mail_from']);
    $fromName = sala_clean_header((string) $config['mail_from_name']);
    if ($to === '' || $from === '') {
        return false;
    }

    $first = sala_format_sk_date($data['dates'][0]);
    $kind = sala_kind_labels()[$data['kind']] ?? 'dopyt';
    $subject = sala_clean_header('Dopyt na sálu: ' . $kind . ', ' . $first);
    $body = sala_inquiry_body($data);
    $reply = sala_clean_header($data['email']);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sala_header_phrase($fromName) . ' <' . $from . '>',
        'Reply-To: ' . $reply,
    ];

    $transport = (string) ($config['mail_transport'] ?? 'mail');
    if ($transport === 'file') {
        $file = (string) ($config['mail_file'] ?? '');
        if ($file === '') {
            return false;
        }
        $record = "To: {$to}\nSubject: {$subject}\n" . implode("\n", $headers) . "\n\n" . $body . "\n----\n";
        return file_put_contents($file, $record, FILE_APPEND | LOCK_EX) !== false;
    }

    return mail($to, sala_mime_subject($subject), $body, implode("\r\n", $headers));
}

function sala_rate_limit_allows(string $dir, string $ip, int $max, int $window): bool
{
    if (!is_dir($dir) || !is_writable($dir)) {
        return true;
    }
    $file = $dir . '/rl-' . hash('sha256', $ip) . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = $decoded;
        }
    }
    $hits = array_values(array_filter(
        $hits,
        static fn ($hit): bool => is_int($hit) && $hit > $now - $window
    ));
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

function sala_format_sk_date(string $iso): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if ($dt === false) {
        return $iso;
    }
    return $dt->format('j. n. Y');
}

function sala_one_line(string $value, int $max): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return sala_clean_text($value, $max);
}

function sala_clean_text(string $value, int $max): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = trim($value);
    if (sala_len($value) > $max) {
        $value = function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
    return $value;
}

function sala_clean_header(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

function sala_header_phrase(string $name): string
{
    $name = sala_clean_header($name);
    if ($name === '') {
        return 'Sala Kinekus';
    }
    if (preg_match('/[^\x20-\x7E]/', $name)) {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }
    return '"' . addcslashes($name, "\"\\") . '"';
}

function sala_mime_subject(string $subject): string
{
    if (preg_match('/[^\x20-\x7E]/', $subject)) {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
    return $subject;
}

function sala_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}
