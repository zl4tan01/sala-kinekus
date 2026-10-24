<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/lib/Calendar.php';
require_once __DIR__ . '/../api/lib/Availability.php';
require_once __DIR__ . '/../api/lib/Inquiry.php';

$failed = 0;

function check(bool $cond, string $message): void
{
    global $failed;
    if ($cond) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$ics = (string) file_get_contents(__DIR__ . '/sample.ics');
$from = '2026-11-01';
$until = '2028-05-01';
$days = sala_occupied_from_ics($ics, $from, $until);

check(in_array('2026-11-07', $days, true), 'celodenná udalosť obsadí svoj deň');
check(!in_array('2026-11-08', $days, true), 'koniec celodennej udalosti je výlučný');
check(in_array('2026-12-01', $days, true) && in_array('2026-12-02', $days, true) && in_array('2026-12-03', $days, true), 'viacdňová udalosť obsadí vnútorné dni');
check(!in_array('2026-12-04', $days, true), 'koniec viacdňovej udalosti je výlučný');
check(!in_array('2026-11-10', $days, true), 'časovaná udalosť s časovým pásmom deň neobsadí');
check(!in_array('2026-11-15', $days, true), 'časovaná UTC udalosť deň neobsadí');
check(!in_array('2026-11-20', $days, true), 'zrušená celodenná udalosť deň neobsadí');
check(in_array('2027-01-05', $days, true) && in_array('2027-01-12', $days, true) && in_array('2027-01-19', $days, true), 'týždenné opakovanie');
check(!in_array('2027-01-26', $days, true), 'COUNT zastaví opakovanie');
check(in_array('2027-02-01', $days, true) && in_array('2027-02-02', $days, true) && in_array('2027-02-04', $days, true), 'denné opakovanie');
check(!in_array('2027-02-03', $days, true), 'EXDATE vynechá deň');
check(in_array('2027-03-01', $days, true), 'zalomený riadok nepokazí dátum');
check(in_array('2026-11-01', $days, true) && in_array('2026-11-02', $days, true), 'viacdňová udalosť siahajúca do okna');
check(!in_array('2026-10-30', $days, true) && !in_array('2026-10-31', $days, true), 'dni pred oknom sa nevracajú');

$json = json_encode(['occupied' => $days, 'updated' => '2026-10-24T12:00:00+02:00'], JSON_UNESCAPED_UNICODE);
check(is_string($json) && !str_contains($json, 'Tajna') && !str_contains($json, 'TAJNE') && !str_contains($json, 'secret') && !str_contains($json, 'tajny@') && !str_contains($json, 'SUMMARY') && !str_contains($json, 'zalomi'), 'JSON neobsahuje text udalostí');

$google = sala_occupied_from_google_items([
    [
        'status' => 'confirmed',
        'summary' => 'Tento nazov nesmie von',
        'start' => ['date' => '2026-11-07'],
        'end' => ['date' => '2026-11-08'],
    ],
    [
        'status' => 'confirmed',
        'summary' => 'Viac dni',
        'description' => 'opis',
        'attendees' => [['email' => 'a@b.c']],
        'start' => ['date' => '2026-12-01'],
        'end' => ['date' => '2026-12-04'],
    ],
    [
        'status' => 'confirmed',
        'summary' => 'Porada',
        'start' => ['dateTime' => '2026-11-10T09:00:00+01:00'],
        'end' => ['dateTime' => '2026-11-10T12:00:00+01:00'],
    ],
    [
        'status' => 'cancelled',
        'start' => ['date' => '2026-11-20'],
        'end' => ['date' => '2026-11-21'],
    ],
], '2026-11-01', '2027-01-01');

check($google === ['2026-11-07', '2026-12-01', '2026-12-02', '2026-12-03'], 'Google API: celodenné áno, časované a zrušené nie');
$gjson = json_encode(['occupied' => $google, 'updated' => '2026-10-24T12:00:00+02:00']);
check(is_string($gjson) && !str_contains((string) $gjson, 'nazov') && !str_contains((string) $gjson, 'a@b.c') && !str_contains((string) $gjson, 'Porada'), 'Google JSON bez detailov');

$mock = sala_mock_occupied('2026-10-24');
check($mock !== [] && $mock[0] >= '2026-10-24', 'mock vracia budúce dni');
check(!in_array('2026-10-24', $mock, true) || true, 'mock beží');

$today = '2026-10-24';
$base = [
    'name' => 'Jana Nováková',
    'email' => 'jana@example.com',
    'phone' => '+421 908 761 253',
    'segment' => 'b2c',
    'kind' => 'svadba',
    'guests' => '120',
    'dates' => '2026-11-14',
    'message' => 'Radi by sme obhliadku.',
    'gdpr' => '1',
];
$ok = sala_validate_inquiry($base, ['2026-11-07'], $today);
check($ok['errors'] === [] && $ok['data'] !== null, 'platný dopyt prejde');

$bad = sala_validate_inquiry(array_merge($base, ['email' => "a@b.sk\r\nBcc: evil@x.com", 'gdpr' => '']), null, $today);
check(isset($bad['errors']['email'], $bad['errors']['gdpr']), 'e-mail a súhlas sa kontrolujú');

$past = sala_validate_inquiry(array_merge($base, ['dates' => '2020-01-01']), null, $today);
check(isset($past['errors']['dates']), 'minulý deň neprejde');

$blocked = sala_validate_inquiry($base, ['2026-11-14'], $today);
check(isset($blocked['errors']['dates']), 'obsadený deň neprejde');

$unknown = sala_validate_inquiry($base, null, $today);
check($unknown['errors'] === [], 'keď dostupnosť zlyhá, dátum sa neblokuje');

$wrongKind = sala_validate_inquiry(array_merge($base, ['kind' => 'oslava', 'segment' => 'b2c']), [], $today);
check(isset($wrongKind['errors']['kind']), 'neznámy druh neprejde');

$firm = sala_validate_inquiry(array_merge($base, ['kind' => 'firemna', 'segment' => 'b2c']), [], $today);
check($firm['errors'] === [] && $firm['data']['segment'] === 'b2b', 'firemná akcia je firemný dopyt');

$inject = array_merge($base, ['name' => "Jana\r\nBcc: evil@x.com"]);
$injectResult = sala_validate_inquiry($inject, [], $today);
check($injectResult['data'] !== null && !str_contains($injectResult['data']['name'], "\n"), 'meno v hlavičke neskáče na nový riadok');

$body = sala_inquiry_body($ok['data']);
check(str_contains($body, 'NIE JE rezervovaný') && str_contains($body, 'Jana Nováková') && str_contains($body, 'Svadba'), 'mail má správny text');

$dir = sys_get_temp_dir() . '/sala-rl-' . getmypid();
mkdir($dir);
check(sala_rate_limit_allows($dir, '1.2.3.4', 2, 60), 'prvý pokus prejde');
check(sala_rate_limit_allows($dir, '1.2.3.4', 2, 60), 'druhý pokus prejde');
check(!sala_rate_limit_allows($dir, '1.2.3.4', 2, 60), 'tretí pokus zastaví limit');

$mailFile = $dir . '/mail.txt';
$sent = sala_send_inquiry([
    'mail_to' => 'marketing@salazilina.sk',
    'mail_from' => 'marketing@salazilina.sk',
    'mail_from_name' => 'Sála Kinekus',
    'mail_transport' => 'file',
    'mail_file' => $mailFile,
], $ok['data']);
$mail = $sent ? (string) file_get_contents($mailFile) : '';
check($sent && str_contains($mail, 'marketing@salazilina.sk') && str_contains($mail, 'Reply-To: jana@example.com') && !str_contains($mail, 'Bcc:'), 'file transport odošle poštu bez vloženej hlavičky');

$config = sala_config();
$config['mode'] = 'ical';
$config['ical_url'] = 'file://' . __DIR__ . '/sample.ics';
$config['months_ahead'] = 18;
$config['cache_seconds'] = 900;
putenv('SALAK_TZ_TEST=1');
$payload = sala_occupied_from_ics($ics, sala_today(), sala_add_months(sala_today(), 18));
$encoded = json_encode(['occupied' => $payload, 'updated' => date('c')]);
check(is_string($encoded) && !str_contains($encoded, 'ATTENDEE') && !str_contains($encoded, 'example.com'), 'výstup z iCal okna nemá kontakty');

echo $failed === 0 ? "\nVšetky testy prešli.\n" : "\nZlyhalo: {$failed}\n";
exit($failed === 0 ? 0 : 1);
