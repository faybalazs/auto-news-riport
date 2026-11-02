# Xperion automatizálás

Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztgenerálás az Xperion (xperion.hu) számára, saját felülettel és MySQL-adatbázissal, több ügyfélre felkészítve.

- **Állapot:** 2. lépés kész (adatbázis, ügyfelek, felhasználók, belépés). Részletek: [docs/project-state.md](docs/project-state.md)
- **Technológia:** PHP 8.1+ (a szerveren 8.2), MySQL, függőség nélküli éles kód; Claude API, SMTP, a meglévő feltöltő program; a Google Sheetből egyszeri adatátvétel
- **Követelmények:** [docs/product.md](docs/product.md) · **Felépítés:** [docs/architecture.md](docs/architecture.md)
- **AI-ügynököknek:** [AGENTS.md](AGENTS.md)

## Telepítés (terminál nélkül)

### 1. MySQL-adatbázis
A tárhely vezérlőpultjában hozz létre egy MySQL-adatbázist és hozzá egy felhasználót, minden jogosultsággal az adatbázisra (kódolás: utf8mb4). Jegyezd fel a gépnevet (általában `localhost`), az adatbázis nevét, a felhasználót és a jelszót.

### 2. A program feltöltése
1. A GitHubon nyisd meg a repót, válaszd ki a kiadási ágat, majd **Code → Download ZIP**.
2. A tárhely fájlkezelőjében töltsd fel és csomagold ki (pl. `public_html/xperion/`). A ZIP egy almappát tartalmaz: annak a *tartalma* kerüljön a célmappába. Frissítéskor a `config.php`-t és a `storage` mappát ne írd felül.

### 3. `config.php`
Másold le a `config.example.php`-t `config.php` néven, és töltsd ki:
- `admin_key`: beállítókulcs, legalább 16 karakteres véletlen szöveg. Csak az első belépéshez kell.
- `cron_key`: másik, legalább 16 karakteres véletlen szöveg.
- `db`: a MySQL-adatbázis adatai az 1. pontból.
- A Google-szolgáltatásfiók (`google_service_account_file` vagy `_json`) csak a korábbi adatok egyszeri átvételéhez kell; ha már be van állítva, maradhat.

### 4. Első indítás
1. Nyisd meg böngészőben: `https://<domain>/xperion/`. A program létrehozza az adatbázistáblákat, és az **Első beállítás** oldal jelenik meg.
2. Add meg a beállítókulcsot, a neved, az e-mail-címed és egy legalább 10 karakteres jelszót. Ez lesz az üzemeltetői fiók.
3. **Ügyfelek → Új ügyfél**: hozd létre az Xperiont (név, weboldal).
4. **Felhasználók → Meghívás**: Attila (ügyfél-adminisztrátor), Emi (tartalomkezelő). A program meghívó linket ad; ezt küldd el nekik e-mailben vagy üzenetben. A link 7 napig érvényes, és egyszer használható.

### 5. Időzítés (cron)
A tárhely vezérlőpultjában két időzített feladat kell; a pontos címeket a **Vezérlőpult → Időzítés (cron)** kártyán másolhatod:
- **Havi futás:** minden hónap 1-jén 06:00-kor (Europe/Budapest).
- **Óránkénti futás:** minden órában.

Ha a vezérlőpult csak parancsot fogad el, az URL-t egy `wget -q -O - "<cím>"` parancsba kell tenni; ezt a vezérlőpult súgója szokta mutatni.

### Elfelejtett jelszó
Amíg nincs e-mail-küldés (7. lépés), az adminisztrátor ad új linket: **Felhasználók → sor ⋯ menüje → Jelszó-visszaállító link** (24 óráig érvényes).

## Titkok
A `config.php` és minden kulcs a repón kívül marad; a repóban csak a `config.example.php` van a változónevekkel. A `.htaccess` a konfigurációt, a JSON-fájlokat és a belső mappákat kívülről elérhetetlenné teszi.

## Fejlesztés
`composer install`, majd `vendor/bin/phpunit`. Az adatbázistesztek valódi MySQL/MariaDB-t használnak (`XP_TEST_DB_HOST`, `XP_TEST_DB_USER`, `XP_TEST_DB_PASS`, `XP_TEST_DB_NAME`; alapértelmezés: localhost, xp/xp, xp_test); ha nincs adatbázis, kimaradnak. A külső szolgáltatások (Google, Claude, SMTP) helyettesítővel futnak.
