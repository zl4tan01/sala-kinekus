<?php
declare(strict_types=1);

/** @return array<string,string> */
function sala_kind_labels(): array
{
    return [
        'svadba' => 'Svadba',
        'stuzkova' => 'Stužková',
        'firemna' => 'Firemná akcia alebo konferencia',
    ];
}

/** @return array<string,string> */
function sala_guest_ranges(): array
{
    return [
        'do-50' => 'do 50',
        '50-100' => '50–100',
        '100-150' => '100–150',
        '150-200' => '150–200',
        '200-250' => '200–250',
        '250-plus' => 'viac ako 250',
    ];
}

function sala_segment_for_kind(string $kind): ?string
{
    $map = [
        'svadba' => 'b2c',
        'stuzkova' => 'b2c',
        'firemna' => 'b2b',
    ];
    return $map[$kind] ?? null;
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
    $kind = (string) ($post['kind'] ?? '');
    $segment = sala_segment_for_kind($kind) ?? '';
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

    if ($segment === '') {
        $errors['kind'] = 'Vyberte druh podujatia.';
    }

    $guests = null;
    if ($guestsRaw !== '') {
        if (isset(sala_guest_ranges()[$guestsRaw])) {
            $guests = $guestsRaw;
        } elseif (preg_match('/^\d{1,4}$/', $guestsRaw)) {
            $count = (int) $guestsRaw;
            if ($count < 1 || $count > 5000) {
                $errors['guests'] = 'Počet hostí vyzerá nesprávne.';
            } else {
                $guests = (string) $count;
            }
        } else {
            $errors['guests'] = 'Počet hostí vyberte z ponuky.';
        }
    }

    $dates = sala_parse_dates((string) ($post['dates'] ?? ''));
    if ($dates === []) {
        $errors['dates'] = 'Vyberte deň v kalendári alebo napíšte dátum, napríklad 28. 10. 2026.';
    } else {
        $past = [];
        $blocked = [];
        foreach ($dates as $date) {
            if ($date <= $today) {
                $past[] = $date;
            } elseif (is_array($occupied) && in_array($date, $occupied, true)) {
                $blocked[] = $date;
            }
        }
        if ($past !== []) {
            $errors['dates'] = 'Dnešný deň a dni v minulosti sa vybrať nedajú.';
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
    $segment = $data['segment'] === 'b2b' ? 'Pre firmy' : 'Svadba alebo stužková';
    $kind = $labels[$data['kind']] ?? $data['kind'];
    $dates = array_map('sala_format_sk_date', $data['dates']);
    $ranges = sala_guest_ranges();
    $guestLabel = $data['guests'] === null || $data['guests'] === ''
        ? 'neuvádza'
        : ($ranges[$data['guests']] ?? (string) $data['guests']);
    $lines = [
        'Nezáväzný dopyt z webu Sála Kinekus.',
        'Termín NIE JE rezervovaný. Potvrďte ho klientovi, až keď ho overíte.',
        '',
        'Dátumy: ' . implode(', ', $dates),
        'Typ: ' . $segment . ' — ' . $kind,
        'Odhad hostí: ' . $guestLabel,
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
    if ($to === '' || $from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
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

    return mail($to, sala_mime_subject($subject), $body, implode("\r\n", $headers), '-f' . $from);
}

function sala_rate_limit_allows(string $dir, string $ip, int $max, int $window): ?bool
{
    if (!is_dir($dir) || !is_writable($dir)) {
        static $logged = false;
        if (!$logged) {
            $logged = true;
            error_log('Sála Kinekus: api/cache nie je zapisovateľná, formulár dopyt neprijal.');
        }
        return null;
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

function sala_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Stránka pre odoslanie bez JavaScriptu. Polia ostanú vyplnené, súhlas nie.
 *
 * @param array<string,mixed> $post
 * @param array<string,string> $errors
 */
function sala_inquiry_error_html(array $post, array $errors): string
{
    $kind = (string) ($post['kind'] ?? '');
    $guests = (string) ($post['guests'] ?? '');
    $values = [
        'dates' => (string) ($post['dates'] ?? ''),
        'name' => (string) ($post['name'] ?? ''),
        'email' => (string) ($post['email'] ?? ''),
        'phone' => (string) ($post['phone'] ?? ''),
        'message' => (string) ($post['message'] ?? ''),
    ];
    $formError = $errors['form'] ?? '';
    $field = static function (string $key) use ($errors): string {
        $message = $errors[$key] ?? '';
        $invalid = $message !== '' ? ' aria-invalid="true"' : '';
        $hidden = $message === '' ? ' hidden' : '';
        return $invalid . '|' . $hidden . '|' . $message;
    };
    $parts = [];
    foreach (['kind', 'dates', 'guests', 'name', 'email', 'phone', 'message', 'gdpr'] as $key) {
        $bits = explode('|', $field($key), 3);
        $parts[$key] = ['invalid' => $bits[0], 'hidden' => $bits[1], 'message' => $bits[2] ?? ''];
    }
    $option = static function (string $value, string $label, string $current): string {
        $selected = $value === $current ? ' selected' : '';
        return '<option value="' . sala_h($value) . '"' . $selected . '>' . sala_h($label) . '</option>';
    };
    $kindOptions = $option('', 'Vyberte', $kind === '' ? '' : '—');
    foreach (sala_kind_labels() as $id => $label) {
        $kindOptions .= $option($id, $label, $kind);
    }
    $guestOptions = $option('', 'Nechcem uviesť', $guests);
    foreach (sala_guest_ranges() as $id => $label) {
        $guestOptions .= $option($id, $label, $guests);
    }
    $errorLine = static function (string $key) use ($parts): string {
        return '<p class="field-error" id="error-' . $key . '"' . $parts[$key]['hidden'] . '>' . sala_h($parts[$key]['message']) . '</p>';
    };
    $formHidden = $formError === '' ? ' hidden' : '';

    return '<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Dopyt sa neodoslal | Sála Kinekus</title>
<style>
body{margin:0;background:#f3eee6;color:#1b1612;font-family:"Segoe UI","Noto Sans",sans-serif;line-height:1.5}
main{max-width:36rem;margin:0 auto;padding:2rem 1.25rem 3rem}
h1{font-size:1.8rem;line-height:1.15;font-weight:500}
label{display:block;margin:0.9rem 0 0.25rem}
input,select,textarea{width:100%;box-sizing:border-box;padding:0.65rem 0.7rem;border:1px solid #ddd2c2;border-radius:0.6rem;background:#fff;font:inherit}
textarea{min-height:7rem}
.check{display:flex;gap:0.6rem;align-items:flex-start}
.check input{width:auto;margin-top:0.3rem}
button{margin-top:1rem;background:#14110e;color:#f7f3ec;border:0;border-radius:999px;padding:0.75rem 1.1rem;font:inherit;cursor:pointer}
.form-error,.field-error{color:#7a2e2a}
a{color:#7a5424}
</style>
</head>
<body>
<main>
<p>Nezáväzný dopyt</p>
<h1>Dopyt sa neodoslal.</h1>
<p>Text, ktorý ste napísali, ostal vo formulári. Súhlas treba znova označiť. <a href="/#termin">Späť na kalendár</a></p>
<form action="/api/inquiry.php" method="post" aria-describedby="error-form"' . ($formError !== '' ? ' aria-invalid="true"' : '') . '>
<p class="form-error" id="error-form"' . $formHidden . '>' . sala_h($formError) . '</p>
<p class="hp" hidden><label>Webová stránka firmy <input name="company_url" tabindex="-1" autocomplete="off"></label></p>
<label for="field-kind">Druh podujatia
<select id="field-kind" name="kind" required aria-describedby="error-kind"' . $parts['kind']['invalid'] . '>' . $kindOptions . '</select>
</label>
' . $errorLine('kind') . '
<label for="field-dates">Dátumy
<input id="field-dates" name="dates" required autocomplete="off" value="' . sala_h($values['dates']) . '" aria-describedby="error-dates"' . $parts['dates']['invalid'] . '>
</label>
' . $errorLine('dates') . '
<label for="field-guests">Počet hostí
<select id="field-guests" name="guests" aria-describedby="error-guests"' . $parts['guests']['invalid'] . '>' . $guestOptions . '</select>
</label>
' . $errorLine('guests') . '
<label for="field-name">Meno a priezvisko
<input id="field-name" name="name" autocomplete="name" required maxlength="120" value="' . sala_h($values['name']) . '" aria-describedby="error-name"' . $parts['name']['invalid'] . '>
</label>
' . $errorLine('name') . '
<label for="field-email">E-mail
<input id="field-email" name="email" type="email" autocomplete="email" required maxlength="160" value="' . sala_h($values['email']) . '" aria-describedby="error-email"' . $parts['email']['invalid'] . '>
</label>
' . $errorLine('email') . '
<label for="field-phone">Telefón
<input id="field-phone" name="phone" type="tel" autocomplete="tel" required maxlength="40" value="' . sala_h($values['phone']) . '" aria-describedby="error-phone"' . $parts['phone']['invalid'] . '>
</label>
' . $errorLine('phone') . '
<label for="field-message">Správa
<textarea id="field-message" name="message" maxlength="4000" aria-describedby="error-message"' . $parts['message']['invalid'] . '>' . sala_h($values['message']) . '</textarea>
</label>
' . $errorLine('message') . '
<label class="check" for="field-gdpr">
<input id="field-gdpr" type="checkbox" name="gdpr" value="1" required aria-describedby="error-gdpr"' . $parts['gdpr']['invalid'] . '>
<span>Súhlasím so spracovaním údajov z tohto formulára, aby ma Sála Kinekus mohla kontaktovať k nezáväznému dopytu.</span>
</label>
' . $errorLine('gdpr') . '
<button type="submit">Odoslať nezáväzný dopyt</button>
</form>
</main>
</body>
</html>
';
}
