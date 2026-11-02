# Xperion automatizálás

Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztgenerálás az Xperion (xperion.hu) számára, saját felülettel és MySQL-adatbázissal, több ügyfélre felkészítve.

- **Állapot:** 6. lépés kész (Áttekintés valódi adatokkal). Részletek: [docs/project-state.md](docs/project-state.md)
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
- A Google-szolgáltatásfiók sorai (`google_service_account_file`, `_json`) üresen maradhatnak: a kulcsot az 5. pontban a felületen töltöd fel. Ha mégis itt adod meg, a `google_service_account_file` értéke a JSON-fájl neve, amelyet a `config.php` mellé töltöttél fel, pl. `'google-kulcs.json'`. A régi `spreadsheet_id` sor törölhető.

### 4. Első indítás
1. Nyisd meg böngészőben: `https://<domain>/xperion/`. A program létrehozza az adatbázistáblákat, és az **Első beállítás** oldal jelenik meg.
2. Add meg a beállítókulcsot, a neved, az e-mail-címed és egy legalább 10 karakteres jelszót. Ez lesz az üzemeltetői fiók.
3. **Ügyfelek → Új ügyfél**: hozd létre az Xperiont (név, weboldal).
4. **Felhasználók → Meghívás**: Attila (ügyfél-adminisztrátor), Emi (tartalomkezelő). A program meghívó linket ad; ezt küldd el nekik e-mailben vagy üzenetben. A link 7 napig érvényes, és egyszer használható.

### 5. Korábbi adatok átvétele a Google Sheetből (ügyfelenként)
1. A felső sávban (vagy a sor **⋯ → Váltás erre az ügyfélre** menüjével) válaszd ki az ügyfelet, majd **Ügyfelek →** görgess az **Adatátvétel a Google Sheetből** kártyához.
2. **1. Google-kulcs:** **Kulcs feltöltése** gomb, és válaszd ki a Google Cloudból letöltött JSON-fájlt (egyszer kell, minden ügyfélre érvényes).
3. **2. A tábla linkje:** másold be a tábla linkjét a böngésző címsorából, majd **Link mentése**.
4. **3. Megosztás:** a megjelenő e-mail-címet **Másolás**, majd a Google Sheetben **Megosztás**: add hozzá (olvasóként elég). A Google Cloud projektben a **Google Sheets API** legyen engedélyezve.
5. **Szárazfuttatás:** lapról lapra megmutatja, mit találna (forrás, kulcsszó, havi lapok cikkei, posztok, szabályok, beállítások), de nem ír semmit.
6. Ha rendben van: **Adatok átvétele**. Többször is futtatható: a meglévőt frissíti, nem duplikál. A posztszabályokat csak akkor tölti be, ha az ügyfélnél még nincs szabály.

### 6. Claude API-kulcs és havi futás
1. A Claude Console-ban (console.anthropic.com → API Keys) hozz létre egy kulcsot, és írd be a `config.php` `claude_api_key` sorába.
2. **Ügyfelek →** az ügyfél állapota legyen **Aktív** (szerkesztés): a havi cron csak az Aktív ügyfelekre fut élesben.
3. **Vezérlőpult → Havi futás és cikkgyűjtés:** válaszd a hónapot. **Csak gyűjtés:** Claude nélkül megmutatja a források eredményét. **Havi futás szárazon:** gyűjtés és Claude-értékelés mentés nélkül (havonta néhány tized cent). **Havi futás élesben:** a hónap listája és a vezetői összefoglaló bekerül a **Havi cikkek** közé; egy hónapra egyszer (az átvett korábbi hónapok már meglévőnek számítanak).
4. **Havi cikkek:** az ügyfél-adminisztrátor itt dönt (Mehet / LinkedIn poszt / Nem kell), soronként vagy több cikkre egyszerre, visszavonással.

### 7. Időzítés (cron)
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
