# Architektúra

PHP 8.1+ (a szerveren 8.2), MySQL/MariaDB, éles függőség nélkül (PDO, cURL, JSON, OpenSSL). Fejlesztéshez: Composer + PHPUnit (`vendor/` nem kerül a kiadásba).

## Belépési pontok

| Fájl | Mire | Védelem |
|---|---|---|
| `index.php` | Oldalak: `?p=attekintes`, `felhasznalok`, `ugyfelek`, `vezerlo`, `naplo`, `profil` (és a későbbi lépések helyőrzői); belépés nélkül: `belepes`, `meghivo&t=`, `jelszo&t=`, első indításkor beállítóoldal | munkamenet (e-mail + jelszó); jogosultság oldalanként; CSRF minden POST-on |
| `api.php` | JSON-műveletek: `?m=felhasznalok`, `meghivas`, `felhasznalo`, `letiltas`, `torles`, `link`, `ugyfel`, `jelszocsere`, `futtatas`, `atvetel`, `gyujtes`, `havifutas`, `dontes`, `googlekulcs` (szolgáltatásfiók-kulcs feltöltése, üzemeltető), `sheetlink` (az aktuális ügyfél táblalinkje, üzemeltető) | munkamenet + `X-CSRF-Token`; minden művelet a szerveren ellenőrzi a jogosultságot és az ügyfélhatárt |
| `cron.php` | Időzített futás: `?feladat=havi\|orankenti&k=<cron_key>[&dry=1]`, vagy `php cron.php havi [--dry]` | cron-kulcs; hibánál HTTP 500 |

Minden webes kérés elején a `Migrator` lefuttatja a még hiányzó adatbázis-migrációkat (`app/migrations/*.sql`, MySQL `GET_LOCK`-kal).

## Modulok (`app/src`, névtér `Xperion\`)

| Modul | Felelősség | Kapcsolódik |
|---|---|---|
| `Config` | `config.php` (először a webgyökér fölötti `xperion-config.php`), állapotlista értékek nélkül | `bootstrap.php` |
| `App` | Szolgáltatás-tároló: adatbázis, migrátor, felhasználó- és ügyfélkezelés, napló, futtató | minden belépési pont |
| `Db\Database`, `Db\Migrator` | PDO (utf8mb4, UTC, előkészített lekérdezések), sémafrissítés | `app/migrations` |
| `Auth\Roles` | Szerepkörök (Üzemeltető, Ügyfél-adminisztrátor, Tartalomkezelő, Olvasó) és jogosultságmátrix | `Access`, felület |
| `Auth\Access` | A kérés hozzáférési köre: felhasználó, aktuális ügyfél (csak a tagságokból), szerepkör, `can()` | minden oldal és API-művelet |
| `Auth\UserAdmin` | Meghívás, szerkesztés, letiltás, törlés/eltávolítás, linkek, jelszó; ügyfélhatárok érvényesítése | `TokenService`, `AuditLog` |
| `Auth\ClientAdmin` | Ügyfelek létrehozása, módosítása (üzemeltető) | `AuditLog` |
| `Auth\TokenService` | Egyszer használható meghívó (7 nap) és jelszólink (24 óra); csak SHA-256 lenyomat tárolva | `UserAdmin` |
| `Auth\Authenticator` | Belépés, hibás próbálkozások korlátozása (IP-nként 5 / 15 perc) | `index.php` |
| `Auth\AuditLog` | Ki, mit, mikor; ügyfelenként szűrve | Napló oldal |
| `Web\Session`, `Web\Http`, `Web\View` | Munkamenet, CSRF, biztonsági fejlécek (CSP), sablonmegjelenítés | belépési pontok |
| `Task\*`, `Log\LocalLog` | Futások (havi, óránkénti), zárolás, helyi futásnapló | `cron.php`, Vezérlőpult |
| `Import\SheetParser`, `Import\Url` | A Sheet lapjainak értelmezése (régi és 2609+ havi formátum, posztok, ötletek, kulcs–érték lapok), linkek egységesítése | `SheetImporter` |
| `Import\SheetImporter`, `Import\ImportService` | Adatátvétel az aktuális ügyfél saját Sheetjéből: szárazon számol, élesben tranzakcióban upsert; zárolás, futás- és műveleti napló | `api.php?m=atvetel`, Ügyfelek oldal |
| `Collect\Collector` | Egy forrás cikkei: RSS/Atom → a listaoldal által hirdetett hírcsatorna → HubSpot `/rss.xml` → listaoldal-elemzés; dátum nélküli cikknél a cikkoldal (legfeljebb 10) | `CollectService` |
| `Collect\FeedParser`, `Collect\Html`, `Collect\DateText` | Hírcsatorna- és HTML-elemzés, dátumfelismerés (angol, magyar, gépi; Budapest szerinti nap) | `Collector` |
| `Collect\CollectService` | Havi gyűjtés ügyfelenként: aktív források, hónapszűrés, duplikátum (az adatbázis cikkei + a futáson belül), kizáró kulcsszavak; most nem ment | `api.php?m=gyujtes`, `MonthlyTask` (cron, minden nem szüneteltetett ügyfél) |
| `Claude\ClaudeClient` | Messages API közvetlen HTTP-vel (Composer nélkül): `anthropic-version: 2026-10-21`, strukturált JSON-kimenet (`output_config.format`), effort, gyorsítótárazott rendszerprompt, Sonnet/Opus esetén szerveroldali tartalék (`fallbacks: "default"`); elutasítás és `max_tokens` hibaként | `Evaluator`, `Summarizer` |
| `Monthly\Evaluator`, `Monthly\Summarizer` | Értékelés 20-as csomagokban (leírás, javaslat, indoklás; ügyfélleírás, kulcsszavak, korábbi döntések és feltöltött címek a rendszerpromptban); vezetői összefoglaló | `MonthlyService` |
| `Monthly\MonthlyService` | Havi futás ügyfelenként: gyűjtés → értékelés → összefoglaló → (élesben) mentés tranzakcióban; idempotens (összefoglaló vagy cikk a hónapban = kész); Claude-kulcs nélkül azonnal hibát ad | `api.php?m=havifutas`, `MonthlyTask` (cron: Aktív ügyfelek) |
| `Monthly\OverviewService` | Áttekintés: hónapok, a hónap számai (előző hónap, 6 havi trend), döntések megoszlása, cikkek forrásonként, teendők szerepkör szerint; csak az aktuális ügyfél adatai | `index.php?p=attekintes` |
| `Monthly\ArticleService` | Havi listák, összefoglaló, döntések (csak az aktuális ügyfél cikkei, feltöltött cikk nem módosul, műveleti napló) | `index.php?p=cikkek`, `api.php?m=dontes` |
| `Google\*`, `Sheets\*` | Szolgáltatásfiókos Sheets-olvasás (`App::sheetsFor(id)`, ügyfelenként) | `ImportService` |

## Adatbázis

**001_alap:** `clients`, `users`, `memberships` (felhasználó × ügyfél × szerepkör), `user_tokens`, `login_attempts`, `audit_log`, `schema_migrations`. Az üzemeltető `users.is_operator = 1`, tagság nélkül minden ügyfélhez hozzáfér. A későbbi ügyféladatok (források, cikkek, posztok, szabályok) `client_id`-vel kapcsolódnak, és csak az `Access` által engedett ügyfélre kérdezhetők le.

**002_ugyfeladatok:** `clients.spreadsheet_id`; `sources`, `keywords`, `articles` (egyedi: ügyfél + egységesített link lenyomata), `posts` (ügyfél + kód), `post_ideas`, `post_rules`, `client_settings`. Mind `client_id`-vel, ügyfél törlésekor kaszkádolva.

**003_havi_futas:** `month_reports` (ügyfél + hónap: összefoglaló sorai, részletek, statisztika, modell); `articles.evaluated_by`.

## Felület

`app/views/layout.php` (app shell), `app/views/auth/*` (belépés nélküli oldalak), `app/views/pages/*`; `assets/app.css`, `assets/app.js`: az Auris admin sablon v1.0.2 részei Xperion-színekkel, generált fájlok (sablon CSS 1–18., JS 0–3., 9. és sorművelet-menü; projektrész: „P. PROJEKT” CSS és 12–14. JS-szekció). A kattintható terv: `docs/terv/ugyfelfelulet-prototipus.html`.

## Tárhely és védelem

- `storage/` (futáskor jön létre, saját `.htaccess`-szel): futásnapló, zár, Google-token, a felületen feltöltött szolgáltatásfiók-kulcs (`google-service-account.json`, 0600; elsőbbséget élvez a `config.php` beállításával szemben).
- `.htaccess`: a `config*.php`, `*.json`, `*.md` stb. kívülről nem elérhető; az `app/`, `docs/`, `tests/` mappa tiltott.
