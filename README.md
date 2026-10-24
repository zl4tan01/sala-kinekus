# Sála Kinekus

Nový web sály na Rosinskej ceste 13 v Žiline. Nahrádza [salazilina.sk](https://www.salazilina.sk). Statické stránky (Astro) a PHP endpointy: dostupnosť z Google Kalendára, nezáväzný dopyt e-mailom a recenzie z Google Places.

Texty sú po slovensky. Fotografie pochádzajú z pôvodného webu a v repozitári sú už zmenšené do WebP a AVIF.

## Čo nahrať na Websupport

Na server ide **obsah priečinka `dist/`**, priamo do webového koreňa (tam, kde má byť `index.html`). Nie celý repozitár a nie `node_modules`.

```bash
npm ci
npm test
npm run build
```

Príkaz `npm run build` zloží HTML, CSS, obrázky aj `dist/api/` (PHP). Potom obsah `dist/` nahrajte cez FTP.

Po nahratí na serveri **vytvorte** `api/config.php` podľa `api/config.example.php`. Súbor `config.php` sa do gitu nedáva a build ho do `dist/` nekopíruje. Priečinok `api/cache/` musí byť zapisovateľný (PHP si tam ukladá dostupnosť na 15 minút a počítadlo odoslaní formulára).

Lokálny náhľad hotového buildu:

```bash
php -S 127.0.0.1:8080 -t dist
```

Bez `config.php` beží **ukážkový režim**: kalendár ukazuje vymyslené obsadené dni a na stránke je o tom veta. Formulár v ukážke e-mail neodošle, kým v konfigurácii nie je schránka.

## Kalendár

Verejný endpoint je `GET /api/availability.php`. Prehliadač dostane len toto:

```json
{"occupied":["2026-11-07"],"updated":"2026-10-24T18:00:00+02:00"}
```

Žiadne názvy akcií, opisy ani účastníkov. Obsadený je deň, na ktorom leží **celodenná** udalosť. Viacdňová celodenná udalosť obsadí dni od začiatku vrátane po koniec bez posledného dňa (takto to dáva Google). Udalosť s časom deň neobsadzuje. Zrušená udalosť tiež nie. Minulé dni sú v kalendári vypnuté.

Keď sa kalendár nepodarí načítať, stránka napíše „Dostupnosť sa nepodarilo načítať, napíšte nám“ a formulár sa dá odoslať aj tak.

Dá sa zapojiť jeden z dvoch zdrojov. V `config.php` nastavte `mode`.

### Tajná adresa iCal (jednoduchšie)

1. Otvorte Google Kalendár.
2. Nájdite kalendár **Sála Žilina - Kinekus**.
3. Nastavenia kalendára → **Integrovať kalendár** → **Tajná adresa vo formáte iCal**.
4. Celú adresu vložte do `ical_url` a nastavte `'mode' => 'ical'`.

Táto adresa je heslo ku kalendáru. Patrí len do `api/config.php` na serveri.

### Service account (Google Calendar API)

1. V Google Cloud vytvorte service account a stiahnite JSON kľúč.
2. V kalendári **Sála Žilina - Kinekus** (ID `s5ehtdjuripasjnt59qrui0su4@group.calendar.google.com`) ho zdieľajte s e-mailom service accountu, oprávnenie na zobrazenie všetkých podrobností udalostí.
3. JSON nahrajte mimo verejného webu, napríklad o priečinok vyššie, než je webový koreň.
4. V `config.php`: `'mode' => 'google'` a `'google_service_account_json' => '/absolútna/cesta/k/klicu.json'`.

Časové pásmo je `Europe/Prague`. Endpoint vracia približne 18 mesiacov dopredu a odpoveď si 15 minút pamätá. Keď Google práve neodpovie, ešte najviac 6 hodín vráti posledný úspešný zoznam.

## Formulár

`POST /api/inquiry.php` pošle dopyt na adresu z `mail_to` (predvolene `marketing@salazilina.sk`). Do kalendára **nezapisuje**. V texte správy je, že termín nie je rezervovaný, kým ho tím nepotvrdí.

Na Websupporte musí byť `mail_from` existujúca schránka na doméne, inak `mail()` poštu odmietne. Rozumná voľba je `marketing@salazilina.sk`.

Formulár má skryté pole proti robotom (honeypot) a limit odoslaní z jednej IP. Povinný je súhlas so spracovaním údajov.

Kým `config.php` nie je, `mode` je `mock`. Na skúšku odoslania bez pošty dajte `'mail_transport' => 'file'` a `'mail_file' => '/tmp/sala-dopyt.txt'`. Na ostrom webe nechajte `mail`.

## Recenzie z Google

`GET /api/reviews.php` číta Place Details (Places API New) pre profil **Sála Žilina**. Place ID `ChIJyXiJBeZZFEcRWa4yofV7rGg` je v `api/config.example.php` už vyplnené. Odpoveď si server pamätá asi 24 hodín v `api/cache/reviews.json`. Keď Google práve neodpovie, ešte 14 dní vráti posledný úspešný súčet.

Do `api/config.php` doplňte kľúč:

```php
'google_places_api_key' => 'váš kľúč Places API (New)',
```

Kľúč patrí len do `config.php` na serveri. Do verejného JSON sa nedostane. Na web idú len recenzie so 4 alebo 5 hviezdičkami, ktoré spomínajú konkrétnu vec (priestor, parkovanie, personál, organizáciu, svadbu, stužkovú, atmosféru). Najkratšie idú prvé. Text je pôvodné znenie, nie preklad Google, a kráti sa len výpustkou. Vedľa citácie je malý odkaz s počtom recenzií. Samotné priemerné hodnotenie sa na stránku nevypisuje. Opakované témy sú doslovné úryvky, nie naše nadpisy.

Kým kľúč nie je, endpoint číta `content/google-reviews.json`. Súbor v repozitári má `"sample": true` a v poli `instructions` je postup. Vzor sa na webe nezobrazí. Keď vkladáte recenzie ručne, nastavte `"sample": false`, doplňte `rating` a `userRatingCount` z profilu a pri každej recenzii nechajte `sample` vypnuté. Uprednostnite texty, ktoré spomínajú stužkovú, svadbu alebo parkovanie. Meno môže byť celé — web ukáže prvé slovo. Priečinok `dist/content/` má `.htaccess`, ktorý súbor zvonku zakáže; číta ho len PHP.

Na stránku nepridávame `aggregateRating`. Citácie ostanú skryté, kým nie sú z API alebo z upraveného JSON.

## Testy

`npm test` spustí `tests/run.php` na vzorovom `tests/sample.ics`. Overí celodennú udalosť, viacdňovú udalosť (koniec je výlučný), že časované udalosti deň neobsadia, a že sa do JSON nedostane názov, opis ani účastník. Samostatne prejde aj tvar odpovede Google Calendar API a validácia formulára.

## Obrázky znova

Originály sa sťahujú mimo repozitár. Znovu ich pripraviť:

```bash
ORIGINALS=/cesta/k/jpg node scripts/optimize-images.mjs
```

Skript zapíše `public/media/` a `src/data/media.json`.

## Štruktúra po nahratí

- `index.html` a podstránky (svadby, stužková, konferencie, catering, galéria, referencie, kontakt). Adresa `/oslavy/` presmeruje na úvod.
- `api/availability.php`, `api/inquiry.php`, `api/reviews.php`, `api/config.php`
- `content/google-reviews.json` (vzor, zvonku zakázaný)
- `sitemap-index.xml`, `robots.txt`
