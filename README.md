# Sála Kinekus

Nový web sály na Rosinskej ceste 13 v Žiline. Nahrádza [salazilina.sk](https://www.salazilina.sk). Statické stránky (Astro) a PHP endpointy: dostupnosť z Google Kalendára, nezáväzný dopyt e-mailom a recenzie z Google Places.

## TODO pre majiteľa

Doplňte, keď budete mať podklady. Kým polia ostanú prázdne, web číslo ani mená dodávateľov nevymýšľa.

- **Ceny.** V `content/pricing.json` pri svadbe, stužkovej, firmách a cateringu vyplňte `from`, napríklad `od 800 €`. Prázdny reťazec znamená, že blok „Koľko to stojí“ ukáže len vysvetlenie, čo cenu ovplyvňuje, a vetu „Cenovú ponuku vám pošleme“.
- **Fotky.** Karta Svadby na úvode je `svadba-23`, karta Stužkové `stuz-03`. Hlavný obrázok stránky svadieb je `svadba-20`, stužkovej `stuz-07`, firiem `konf-01`. Náhľad 360° je `tour-poster`, záber z virtuálnej prehliadky hlavnej sály. Stock `svadba-hero` a `stuzkova-hero` sa na webe nepoužívajú.
- **Podmienky stužkovej.** Pravidlá a škody sa na web nepíšu. Majiteľ ich rieši osobne pri obhliadke.
- **Ďalšie Google recenzie.** Postup je nižšie v časti Recenzie z Google. Pridávajte len doslovný text z profilu.

## Čo je na webe z kontaktov

Primárny kontakt je `+421 908 761 253` a `marketing@salazilina.sk`. Telefón `041 555 55 10` je vedľajší. `info@salazilina.sk` je len v pätičke. WhatsApp je v pätičke, lebo mobilné číslo existuje. Pracovné hodiny sú na Kontakte a v pätičke. Lehota odpovede na webe nie je. Text je „Ozveme sa vám“. Po odoslaní formulára je veta „Ak sa vám neozveme, zavolajte na +421 908 761 253.“

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

Žiadne názvy akcií, opisy ani účastníkov. Obsadený je deň, na ktorom leží **celodenná** udalosť. Viacdňová celodenná udalosť obsadí dni od začiatku vrátane po koniec bez posledného dňa (takto to dáva Google). Udalosť s časom deň neobsadzuje. Zrušená udalosť tiež nie. Dnešný deň a minulé dni sú v kalendári nedostupné. Počet hostí vo formulári je nepovinný rozsah.

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

Kľúč patrí len do `config.php` na serveri. Do verejného JSON sa nedostane. Kým kľúč nie je, endpoint číta `content/google-reviews.json`. V tom súbore je päť recenzií z profilu, doslovne. Na úvod idú Aneta, Tatiana a Jaroslav. Emilka a Erika sú na stránke stužkovej. Priemer profilu ani celkový počet recenzií sa na webe nezobrazujú, ani keď ich Google vráti. Pri mene konkrétnej recenzie môže zostať jej vlastný počet hviezdičiek. Vety s menom zamestnanca sa vynechajú. V hero je odkaz „Recenzie na Google“, v sekcii veta „Recenzie z Google profilu Sála Žilina“.

Ďalšiu recenziu pridáte do poľa `reviews`. Nový objekt má `author` (krstné meno), `rating` (4 alebo 5 pri tej recenzii), `text` (doslovne z Google, bez prepisovania), `url` (nechajte `listingUrl`) a `section`: `general` na úvod, `stuzkova` na stránku stužkovej. Ak má byť v hero, pri formulári alebo medzi dlaždicami, úryvok v `hero`, `formQuote`, `sectionQuotes` alebo `themes` musí byť doslovná časť niektorej recenzie. Postup je aj v poli `instructions` v tom istom súbore. Priečinok `dist/content/` má `.htaccess`, ktorý súbor zvonku zakáže; číta ho len PHP.

Keď doplníte `google_places_api_key`, tento súbor sa obíde a recenzie sa načítajú z Places API. Na stránku nepridávame `aggregateRating` ani priemer a celkový počet z profilu. Odkaz v adrese profilu `0x471459e6058978c9:0x68ac7bf5a132ae59` je to isté miesto ako Place ID `ChIJyXiJBeZZFEcRWa4yofV7rGg`.

## Mapa

Sekcia „Kde nás nájdete“ je na úvode aj na kontakte. Náhľad nie je obrázok zvonku. Je to lokálna karta s adresou a tlačidlom „Zobraziť mapu“. Vložená Google mapa (`output=embed`) sa načíta až po tomto kliknutí. Dovtedy prehliadač Google nevolá. „Navigovať“ ostáva a ide na `https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8`. Na serveri nie je Content-Security-Policy; keby ste ju doplnili, `frame-src` musí povoliť `https://www.google.com/maps` a tiež `https://tourmkr.com` pre prehliadku.

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
- `content/google-reviews.json` a `content/pricing.json` (zvonku zakázané)
- `sitemap-index.xml`, `robots.txt`
