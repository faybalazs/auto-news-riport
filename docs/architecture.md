# Architektúra

PHP 8.1+, éles függőség nélkül (cURL, JSON, OpenSSL). Fejlesztéshez: Composer + PHPUnit (`vendor/` nem kerül a kiadásba).

## Belépési pontok

| Fájl | Mire | Védelem |
|---|---|---|
| `index.php` | Admin felület: belépés, Vezérlőpult (`#vezerlo`), Napló (`#naplo`) | admin kulcs → munkamenet-süti; 5 hibás próbálkozás / 15 perc / IP |
| `api.php` | Az admin JSON-végpontjai: `?muvelet=naplo` (GET), `?muvelet=futtatas` (POST) | munkamenet + CSRF-fejléc |
| `cron.php` | Időzített futás: `?feladat=havi\|orankenti&k=<cron_key>[&dry=1]`, vagy `php cron.php havi [--dry]` | cron-kulcs; hibánál HTTP 500 |

## Modulok (`app/src`, névtér `Xperion\`)

| Modul | Felelősség | Kapcsolódik |
|---|---|---|
| `Config` | `config.php` betöltése (először a webgyökér fölötti `xperion-config.php`), állapotlista az adminnak (értékek nélkül) | `bootstrap.php` |
| `App` | Szolgáltatás-tároló: HTTP, Google-hitelesítés, Sheets, napló, futtató | minden belépési pont |
| `Http\CurlHttpClient` | cURL, újrapróbálás 429/5xx/hálózati hibánál (1, 2, 4 s) | `HttpClient` interfész; tesztben `FakeHttpClient` |
| `Google\ServiceAccountAuth` | RS256 JWT OpenSSL-lel → access token, gyorsítótár: `storage/google-token.json` | Sheets |
| `Google\SheetsClient` | Lapok listája, values get/batchGet/update/append, batchUpdate, addSheet | `Sheets\*`, feladatok |
| `Sheets\SheetNames`, `SheetSchemas` | Lapnevek, az új lapok fejléce, alapbeállítások, legördülők | product.md 4. |
| `Sheets\Settings` | „Beállítások” lap olvasása/írása (kulcs → sor) | óránkénti futás |
| `Sheets\Source`, `Keyword` | „Figyelendő oldalak” és „Kulcsszavak” sorai | havi futás |
| `Task\Runner` | Feladat futtatása: zárolás (`storage/futas.lock`), hibafogás, helyi napló, éles író futásnál „Napló” lap | `Lock`, `Log\LocalLog` |
| `Task\TaskContext` | Szárazfuttatás-jelző, idő, jegyzőkönyv; `write()` szárazon csak leírja az írást | minden feladat |
| `Task\*Task` | `kapcsolat`, `elokeszites`, `havi`, `orankenti` | `Runner` |
| `Log\LocalLog` | `storage/naplo.jsonl`, minden futás (szárazon is); az admin Napló innen olvas | `Runner`, `api.php` |
| `Admin\Auth`, `Admin\Http` | Belépés, CSRF, biztonsági fejlécek (CSP, noindex) | `index.php`, `api.php` |

## Felület

`app/views/login.php`, `app/views/admin.php`, `assets/admin.css`, `assets/admin.js`: az Auris admin sablon (v1.0.2) részei, Xperion-színekkel. A projektre szabott CSS a „P. PROJEKT” szekcióban, a JS a 12–14. szekcióban van. A sablon DataTable-je két kiegészítést kapott: `selectable:false`, `setLoading()`.

## Tárhely és védelem

- `storage/` (futáskor jön létre, saját `.htaccess`-szel): napló, zár, token, belépési hibák.
- `.htaccess`: a `config*.php`, `*.json`, `*.md` stb. kívülről nem érhető el; az `app/`, `docs/`, `tests/` mappa tiltott.
