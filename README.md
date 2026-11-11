# Sála Kinekus

Nový web sály na Rosinskej ceste 13 v Žiline. Nahrádza [salazilina.sk](https://www.salazilina.sk). Statické stránky (Astro) a PHP endpointy: dostupnosť z Google Kalendára, nezáväzný dopyt e-mailom a recenzie z Google Places.

## TODO pre majiteľa

Doplňte, keď budete mať podklady. Kým polia ostanú prázdne, web číslo ani mená dodávateľov nevymýšľa.

- **Ceny.** V `content/pricing.json` pri svadbe, stužkovej, firmách a cateringu vyplňte `from`, napríklad `od 800 €`. Prázdny reťazec znamená, že blok „Koľko to stojí“ ukáže vetu „Cenu pošleme v ponuke podľa vašej akcie.“
- **Fotky.** Karta Svadby na úvode je `svadba-23`, karta Stužkové `stuz-03`. Hlavný obrázok stránky svadieb je `svadba-20`, stužkovej `stuz-07`, firiem `konf-01`. Náhľad 360° je `tour-poster`, záber z virtuálnej prehliadky hlavnej sály. Stock `svadba-hero` a `stuzkova-hero` sa na webe nepoužívajú.
- **Podmienky stužkovej.** Pravidlá a škody sa na web nepíšu. Majiteľ ich rieši osobne pri obhliadke.
- **Ďalšie Google recenzie.** Postup je nižšie v časti Recenzie z Google. Pridávajte len doslovný text z profilu.

## Rady

Články sú súbory `content/rady/*.md`. Nový článok je nový súbor. Komponenty, menu ani sitemap sa kvôli nemu needitujú. Po uložení znova spustite `npm run build`. Adresa článku je `/rady/` plus názov súboru bez `.md`, napríklad `content/rady/moj-text.md` je `https://www.salazilina.sk/rady/moj-text/`.

Vo hlavičke súboru vyplňte:

- `title` — nadpis H1 na stránke
- `seoTitle` — `<title>`, najviac 60 znakov
- `description` — perex na karte a meta popis, 40 až 155 znakov
- `published` a `updated` — dátum `RRRR-MM-DD`, na webe sa ukáže ako `25. 10. 2026`
- `author` — predvolene `Tím Sály Kinekus`
- `image` — id fotky z `src/data/media.json` (galéria alebo pomenovaný záber, napríklad `svadba-20`)
- `order` — poradie na `/rady/`
- `featured` — `true` zaradí článok medzi tri na úvode. Ak sú označené menej ako tri, doplnia sa ďalšie podľa `order`
- `related` — zoznam existujúcich slugov. Preklep build zastaví
- `summary` — dve až štyri vety do bloku „V skratke“
- `faq` — tri až päť otázok a odpovedí. Tie isté texty idú do JSON-LD

Telo článku je Markdown pod hlavičkou. Nadpis H1 do tela nedávajte, vykreslí ho šablóna. Podnadpisy H2 formulujte ako otázky a hneď pod ne dajte krátku odpoveď. Čas čítania sa počíta sám. `/llms.txt` aj sitemap sa pri builde doplnia.

Podmienky stužkovej, škody a dohľad sa do článkov nepíšu. Majiteľ ich rieši osobne pri obhliadke.

## Čo je na webe z kontaktov

Primárny kontakt je `+421 908 761 253` a `marketing@salazilina.sk`. Telefón `041 555 55 10` je vedľajší. `info@salazilina.sk` je len v pätičke. WhatsApp odkaz na webe nie je. Pracovné hodiny sú na Kontakte a v pätičke. Lehota odpovede na webe nie je. Text je „Ozveme sa vám“. Po odoslaní formulára je veta „Ak sa vám neozveme, zavolajte na +421 908 761 253.“

Texty sú po slovensky. Fotografie pochádzajú z pôvodného webu a v repozitári sú už zmenšené do WebP a AVIF.

Farby sú žltá `#F2BC21`, tmavá `#2C2C2B` a červenooranžová `#FC5502` len v hviezde loga. Písmo je Montserrat (náhrada za Proxima Nova), zásoba `Arial, Helvetica, sans-serif`. Súbory woff2 sú v `public/fonts/montserrat/`, vrátane latin-ext, váhy 400, 600 a 800. Licencia je SIL OFL v `public/fonts/montserrat/OFL.txt`. Písmo sa nenaťahuje z Google Fonts. Logo je v `public/brand/` a na svetlom aj tmavom podklade má rovnakú kresbu, mení sa len výplň.

## Čo nahrať na Websupport

Na server ide **obsah priečinka `dist/`**, priamo do webového koreňa (tam, kde má byť `index.html`). Nie celý repozitár a nie `node_modules`.

```bash
npm ci
npm test
npm run build
```

Príkaz `npm run build` zloží HTML, CSS, obrázky aj `dist/api/` (PHP). Potom obsah `dist/` nahrajte cez FTP.

Po nahratí na serveri **vytvorte** `api/config.php` podľa `api/config.example.php`. Súbor `config.php` sa do gitu nedáva a build ho do `dist/` nekopíruje. Priečinok `api/cache/` musí byť pre PHP zapisovateľný. PHP si tam počíta odoslania formulára. Ak priečinok zapisovateľný nie je, formulár dopyt neodošle a vráti „Dopyt sa teraz nepodarilo prijať. Zavolajte na +421 908 761 253.“ Limit sa v tom prípade nevypne potichu.

Lokálny náhľad hotového buildu:

```bash
php -S 127.0.0.1:8080 -t dist
```

Bez `config.php` formulár e-mail neodošle, kým v konfigurácii nie je schránka. Web neukazuje, ktoré dni sú voľné.

## Termíny

Návštevník na webe nevidí voľné ani obsadené dni. Do formulára napíše preferovaný termín voľným textom. Odoslanie dopytu deň nerezervuje. Či sa deň dá, povie tím sály v odpovedi. Endpoint `/api/availability.php` na webe nie je. Súbory `api/lib/Calendar.php` a `api/lib/Availability.php` ostanú v repozitári pre prípadné interné použitie, ale žiadna verejná stránka ani JSON ich nevolá.

## Formulár

`POST /api/inquiry.php` pošle dopyt na adresu z `mail_to` (predvolene `marketing@salazilina.sk`). Do žiadneho kalendára nezapisuje a obsadenosť neoveruje. V texte správy je, že termín nie je rezervovaný, kým ho tím nepotvrdí. Preferovaný termín je text od návštevníka.

Na Websupporte musí byť `mail_from` existujúca schránka na doméne, inak `mail()` poštu odmietne. Rozumná voľba je `marketing@salazilina.sk`.

Formulár má skryté pole proti robotom (honeypot) a limit odoslaní z jednej IP. Povinný je súhlas so spracovaním údajov. Pošta ide cez `mail()` s odosielateľom obálky `-f` z `mail_from`. Keď prehliadač nemá JavaScript a odoslanie zlyhá, vráti sa stránka s textom chyby a s poliami, ktoré návštevník vyplnil. Úspešný dopyt bez JavaScriptu presmeruje na `/?odoslane=1#termin`.

## Pred ostrým spustením

Tieto kroky build nerobí. Canonical adresy ostávajú `https://www.salazilina.sk`.

- **Presmerovanie.** Starý web je indexovaný bez `www`. Pri spustení nastavte 301 z `http://` na `https://` a z `https://salazilina.sk` na `https://www.salazilina.sk`.
- **Kalendár na webe nie je.** Voľné dni sa návštevníkom neukazujú. Ak by sa interný prehľad niekedy znova zapínal, režim `ical` neberie výnimky `RECURRENCE-ID` a týždenné `BYDAY` rozbalí najviac 160 týždňov od začiatku série. Režim `google` (`singleEvents=true`) tieto výnimky berie. Verejný web ho aj tak nesmie vystaviť.
- **Právne údaje, ktoré musí doplniť majiteľ.** Na web ich nedávajte odhadom. Chýba:
  - IČO a zápis v registri (zákon č. 22/2004 Z. z. o elektronickom obchode)
  - právny základ spracúvania údajov z formulára
  - doba uchovávania
  - práva dotknutých osôb (prístup, výmaz, námietka)
  - možnosť sťažnosti na Úrad na ochranu osobných údajov SR

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

Sekcia „Kde nás nájdete“ je na úvode aj na kontakte. Mapa Google s označením Sála Žilina sa načíta hneď, bez kľúča, `loading="lazy"`. „Navigovať“ ide na `https://maps.app.goo.gl/nT8hn4QGFAZ5ZP8K8`. Na serveri nie je Content-Security-Policy; keby ste ju doplnili, `frame-src` musí povoliť `https://www.google.com/maps` a tiež `https://tourmkr.com` pre prehliadku.

## Testy

`npm test` spustí `tests/run.php` na vzorovom `tests/sample.ics` a potom `tests/rady.mjs`. PHP overí celodennú udalosť, viacdňovú udalosť (koniec je výlučný), že časované udalosti deň neobsadia, a že sa do JSON nedostane názov, opis ani účastník. Samostatne prejde aj tvar odpovede Google Calendar API a validácia formulára. Kontrola HTML rád (H1, JSON-LD, sitemap, odkazy) beží, keď existuje `dist/rady/`. Po úprave článkov teda `npm run build && npm test`.

## Obrázky znova

Originály sa sťahujú mimo repozitár. Znovu ich pripraviť:

```bash
ORIGINALS=/cesta/k/jpg node scripts/optimize-images.mjs
```

Skript zapíše `public/media/` a `src/data/media.json`.

## Štruktúra po nahratí

- `index.html` a podstránky (svadby, stužková, konferencie, catering, galéria, referencie, rady, kontakt). Adresa `/oslavy/` presmeruje na úvod.
- `llms.txt` — krátky prehľad webu a odkazy na rady. `robots.txt` ostáva ako je (`Allow: /`, `Disallow: /api/`).
- `api/inquiry.php`, `api/reviews.php`, `api/config.php`. Endpoint dostupnosti na webe nie je.
- `content/google-reviews.json` a `content/pricing.json` (zvonku zakázané)
- `sitemap-index.xml`, `robots.txt`
