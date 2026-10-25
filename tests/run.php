<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/lib/Calendar.php';
require_once __DIR__ . '/../api/lib/Availability.php';
require_once __DIR__ . '/../api/lib/Inquiry.php';
require_once __DIR__ . '/../api/lib/Reviews.php';

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

$todayDate = sala_validate_inquiry(array_merge($base, ['dates' => $today]), null, $today);
check(isset($todayDate['errors']['dates']), 'dnešný deň neprejde');

$optionalGuests = sala_validate_inquiry(array_merge($base, ['guests' => '', 'dates' => '28. 10. 2026']), null, $today);
check(
    $optionalGuests['errors'] === []
        && $optionalGuests['data']['guests'] === null
        && $optionalGuests['data']['dates'] === ['2026-10-28'],
    'prázdny rozsah hostí a slovenský dátum prejdú'
);

$rangeGuests = sala_validate_inquiry(array_merge($base, ['guests' => '100-150']), [], $today);
check($rangeGuests['errors'] === [] && $rangeGuests['data']['guests'] === '100-150', 'rozsah hostí prejde');

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
check(sala_rate_limit_allows($dir . '/chybajuci', '9.9.9.9', 2, 60) === null, 'nezapisovateľná cache vráti null');
$errorHtml = sala_inquiry_error_html([
    'name' => 'Jana <script>',
    'email' => 'jana@example.com',
    'phone' => '0908761253',
    'dates' => '28. 10. 2026',
    'guests' => '50-100',
    'kind' => 'svadba',
    'message' => "Ahoj\n<b>svadba</b>",
    'gdpr' => '1',
], ['name' => 'Napíšte meno a priezvisko.', 'form' => 'Skúste znova.']);
check(
    str_contains($errorHtml, 'Jana &lt;script&gt;')
    && !str_contains($errorHtml, '<script>')
    && str_contains($errorHtml, '28. 10. 2026')
    && str_contains($errorHtml, 'Ahoj')
    && str_contains($errorHtml, '&lt;b&gt;svadba&lt;/b&gt;')
    && str_contains($errorHtml, 'value="svadba" selected')
    && str_contains($errorHtml, 'aria-invalid="true"')
    && str_contains($errorHtml, 'aria-describedby="error-name"')
    && str_contains($errorHtml, 'aria-describedby="error-message"')
    && str_contains($errorHtml, 'Skúste znova.')
    && !preg_match('/name="gdpr"[^>]*checked/', $errorHtml),
    'chybová stránka formulára drží text a escapuje ho'
);

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

check(sala_review_first_name('Jana Nováková') === 'Jana', 'krstné meno');
check(sala_review_first_name('A') === '', 'jedno písmeno sa nepoužije');
$long = 'Krásna sála ' . str_repeat('s parketom ', 40);
$excerpt = sala_review_excerpt($long);
check(str_starts_with($excerpt, 'Krásna sála') && str_ends_with($excerpt, '…') && mb_strlen($excerpt) <= 281, 'úryvok nechá pôvodný začiatok');

$places = sala_reviews_from_places([
    'rating' => 4.4,
    'userRatingCount' => 36,
    'googleMapsUri' => 'https://maps.google.com/?cid=7542539770840395353',
    'reviews' => [[
        'rating' => 5,
        'text' => ['text' => 'Translated hall text that must stay hidden.'],
        'originalText' => ['text' => 'Krásna sála s parketom a pódiom.'],
        'authorAttribution' => ['displayName' => 'Jana Nováková'],
        'googleMapsUri' => 'https://maps.google.com/maps/reviews/data=!example',
    ]],
], 'ChIJyXiJBeZZFEcRWa4yofV7rGg', 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8');
$placesJson = json_encode($places, JSON_UNESCAPED_UNICODE);
check(
    is_string($placesJson)
    && $places['reviews'][0]['author'] === 'Jana'
    && str_starts_with($places['reviews'][0]['text'], 'Krásna sála')
    && str_starts_with($places['hero']['text'], 'Krásna sála')
    && !str_contains($placesJson, 'Translated')
    && !str_contains($placesJson, 'TAJNY-KLUC')
    && !isset($places['proofLabel'], $places['count'], $places['countLabel'], $places['ratingLabel'])
    && !str_contains($placesJson, '4,4')
    && !str_contains($placesJson, '4.4')
    && !str_contains($placesJson, '36')
    && $places['themes'] === [],
    'Places berie pôvodný text a priemer profilu sa do odpovede nedostane'
);

$hiddenSample = sala_reviews_from_manual(['sample' => true, 'reviews' => [['author' => 'Eva', 'rating' => 5, 'text' => 'Veľké parkovisko pred sálou na svadbu.']]]);
check($hiddenSample === ['available' => false], 'vzorový súbor sa nezobrazí');
$staff = sala_review_without_staff('Najkrajšia sála v Žiline. Veľmi chválim komunikáciu s pani Macku, ktorá pomohla.');
check($staff === 'Najkrajšia sála v Žiline.' && !str_contains($staff, 'Mack'), 'veta s menom zamestnanca vypadne');

$manual = sala_reviews_from_manual([
    'sample' => false,
    'listingUrl' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
    'writeReviewUrl' => 'https://search.google.com/local/writereview?placeid=ChIJyXiJBeZZFEcRWa4yofV7rGg',
    'rating' => 4.4,
    'userRatingCount' => 36,
    'reviews' => [[
        'author' => 'Meno',
        'rating' => 5,
        'text' => 'Sem vložte doslovný text recenzie z Google. Tento vzorový záznam sa na webe nezobrazí.',
        'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
    ]],
]);
check($manual['available'] === false, 'vzorová veta ani samotný súčet sa nezobrazia');

$curated = sala_reviews_from_manual([
    'sample' => false,
    'listingUrl' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
    'writeReviewUrl' => 'https://search.google.com/local/writereview?placeid=ChIJyXiJBeZZFEcRWa4yofV7rGg',
    'rating' => 4.4,
    'userRatingCount' => 36,
    'reviews' => [
        [
            'author' => 'Eva Dlhá',
            'rating' => 5,
            'text' => 'Na stužkovej nám pomohol personál a organizácia bola pokojná, parkovanie pred sálou bolo veľké a priestor na tanec ostal voľný po celý večer.',
            'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
        ],
        [
            'author' => 'Peter',
            'rating' => 5,
            'text' => 'Parkovanie pred sálou je naozaj veľké.',
            'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
        ],
        [
            'author' => 'Mia',
            'rating' => 3,
            'text' => 'Parkovanie pred sálou je naozaj veľké a svadba bola krásna.',
            'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
        ],
        [
            'author' => 'Leo',
            'rating' => 5,
            'text' => 'Odporúčam všetkým.',
            'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
        ],
        [
            'author' => 'Hana',
            'rating' => 4,
            'text' => 'Svadba v priestrannej sále, parkovanie hneď pred vchodom.',
            'url' => 'https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8',
        ],
    ],
]);
$curatedJson = json_encode($curated, JSON_UNESCAPED_UNICODE);
$themeBlob = implode("\n", array_column($curated['reviews'], 'text'));
$themesOk = true;
foreach ($curated['themes'] as $theme) {
    if (!str_contains($themeBlob, $theme['text'])) {
        $themesOk = false;
    }
}
check(
    is_string($curatedJson)
    && $curated['hero']['text'] === 'Parkovanie pred sálou je naozaj veľké.'
    && $curated['hero']['author'] === 'Peter'
    && str_starts_with($curated['formQuote']['text'], 'Svadba v priestrannej sále')
    && $curated['reviews'][0]['author'] === 'Peter'
    && count($curated['reviews']) === 3
    && count($curated['themes']) === 2
    && $themesOk
    && !isset($curated['proofLabel'], $curated['count'], $curated['countLabel'], $curated['ratingLabel'])
    && !str_contains($curatedJson, '4,4')
    && !str_contains($curatedJson, '36')
    && !str_contains($curatedJson, 'Odporúčam')
    && !str_contains($curatedJson, 'Mia'),
    'najkratšie konkrétne citácie idú prvé a témy sú doslovné'
);

$public = sala_reviews_public([
    'google_places_api_key' => '',
    'google_place_id' => 'ChIJyXiJBeZZFEcRWa4yofV7rGg',
    'google_reviews_file' => dirname(__DIR__) . '/content/google-reviews.json',
]);
$publicJson = json_encode($public, JSON_UNESCAPED_UNICODE);
$themeBlob = '';
foreach (array_merge($public['reviews'] ?? [], $public['sections']['stuzkova'] ?? []) as $review) {
    $themeBlob .= $review['text'] . "\n";
}
$liveThemes = true;
foreach ($public['themes'] ?? [] as $theme) {
    if (!str_contains($themeBlob, $theme['text'])) {
        $liveThemes = false;
    }
}
check(
    is_string($publicJson)
    && ($public['available'] ?? null) === true
    && $public['hero']['author'] === 'Aneta'
    && $public['hero']['text'] === 'Najkrajšia sála v Žiline. Všetko bolo pripravené podľa našich predstáv.'
    && $public['formQuote']['author'] === 'Jaroslav'
    && $public['formQuote']['text'] === 'Veľmi pekná sála na spoločenské akcie, sbadba, ples, oslava, stužková. Odporúčam, pre väčšie akcie nad 70 ľudí.'
    && array_column($public['reviews'], 'author') === ['Aneta', 'Tatiana', 'Jaroslav']
    && array_column($public['sections']['stuzkova'], 'author') === ['Emilka', 'Erika']
    && $public['sectionQuotes']['stuzkova']['author'] === 'Erika'
    && !isset($public['proofLabel'], $public['count'], $public['countLabel'], $public['userRatingCount'])
    && !str_contains($publicJson, '4,4')
    && !str_contains($publicJson, '4.4')
    && !str_contains($publicJson, '36')
    && $liveThemes
    && !str_contains($publicJson, 'Mack')
    && !str_contains($publicJson, 'Video DJ')
    && !str_contains($publicJson, '27×')
    && !str_contains($publicJson, 'google_places_api_key')
    && !str_contains($publicJson, 'AIza')
    && str_contains($public['sections']['stuzkova'][0]['text'], 'veľké parkovisko')
    && str_contains($public['sections']['stuzkova'][1]['text'], 'na parkovisku dostatok miesta'),
    'živý súbor má päť recenzií bez mena zamestnanca a bez rozpisu'
);

echo $failed === 0 ? "\nVšetky testy prešli.\n" : "\nZlyhalo: {$failed}\n";
exit($failed === 0 ? 0 : 1);
