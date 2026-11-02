# Xperion automatizálás

Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztgenerálás az Xperion (xperion.hu) számára, saját felülettel és MySQL-adatbázissal, több ügyfélre felkészítve.

- **Állapot:** 10. lépés kész (Napló, Források, ügyfélbeállítások) – a tervezett 1–10. lépés megvalósítva. Részletek: [docs/project-state.md](docs/project-state.md)
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

### 7. Havi e-mail az ügyfélnek
1. A tárhely vezérlőpultjában hozz létre egy postafiókot a feladónak (pl. `sajtofigyeles@…`). A `config.php` `smtp` részébe: `host` (a levelezőszerver neve), `port` és `secure` (`465` + `ssl`, vagy `587` + `tls`), `user` (általában a teljes e-mail-cím), `pass`, `from_email` (a postafiók címe). A **Vezérlőpult → Beállítások állapota** mutatja, ha rendben van.
2. **Ügyfelek → Havi e-mail beállításai:** címzett, másolat, és ha kell, a levél stílusa. Mentés.
3. A havi futás élesben megírja a levelet, és e-mailt küld neked (az ügyfél ekkor még semmit nem kap). Kézzel: **Vezérlőpult → Havi e-mail az ügyfélnek → E-mail generálása**.
4. **Szöveg szerkesztése**, ha kell; **Teszt e-mail nekem**: csak neked megy ki, így az SMTP is kipróbálható.
5. **Jóváhagyom, mehet:** a megerősítés után azonnal kimegy a címzettnek és a másolatnak, egyszer. Utána nem módosítható. Ha hiba van, a kártyán látszik, és javítás után újra megnyomható.

### 8. Élő feltöltés az xperion.hu-ra
1. **WordPress-admin → Bővítmények → Új hozzáadása → Bővítmény feltöltése:** az Xperion OAuth Bridge 0.6.0 ZIP-je, „Csere a feltöltöttre”. Utána a bővítmény saját próbája (a csomag `docs/TEST_REPORT.md` leírása).
2. **Felhasználók → Új felhasználó:** egy külön felhasználó (pl. `cikkfigyelo`) **Szerző** szerepkörrel. A profiljában **Alkalmazásjelszavak**: új jelszó (pl. „Sajtófigyelés”), és másold ki.
3. **Beállítások → Xperion sajtószemle:** a magyar és az angol alapkategória.
4. A `config.php` `wordpress` részébe az ügyfél weboldala szerint: `'xperion.hu' => ['user' => 'cikkfigyelo', 'app_password' => '<a kimásolt alkalmazásjelszó>']`. Az Ügyfelek oldalon az ügyfél weboldala legyen `xperion.hu`.
5. **Vezérlőpult → Feltöltés a weboldalra → Kapcsolat ellenőrzése:** minden sor legyen zöld (bővítmény 0.6.x, publikálhat, képet tölthet fel, két alapkategória).
6. **Feltöltés szárazon:** megmutatja a magyar és angol címet és ajánlót, semmit nem ír. Ha rendben van: **Feltöltés élesben** (vagy megvárod az óránkénti futást).

A „Mehet” cikk a következő óránkénti futással kikerül magyarul és angolul, a forráscikk képével és linkjével. A Havi cikkek oldalon látszik az állapota és a két link; hiba esetén az oka. Ami már kint van, nem kerül fel újra.

### 9. LinkedIn-posztok
1. **Posztszabályok:** a Sheetből átvett szabályok itt szerkeszthetők (új, szerkesztés, törlés, sorrend). Minden generálás a friss állapotot használja.
2. **Poszt a cikkekből:** Attila a Havi cikkek oldalon „LinkedIn poszt” döntést ad (akár több lépésben). Ha kész, a **LinkedIn poszt** nézetben kijelöli a cikkeket, és megnyomja a **Poszt készítése a kijelöltekből** gombot: egy cikkből egy poszt, több cikkből egy közös poszt készül. Addig semmi nem generálódik.
3. **Poszt ötletből:** **Posztötletek → Új ötlet**, mentés, majd **Küldés posztgenerálásra**.
4. A generálás 1–2 perc (Claude Opus 5.5: szöveg és egyedi kép, posztonként kb. 25–40 cent). A kész poszt **magától jóváhagyásra kerül** (Ügyfélnél), Attila és az üzemeltető e-mailt kap. Ha a generálás nem sikerül, az óránkénti futás újrapróbálja.
5. **A poszt oldala:** előnézet (zárt és kibontott nézet) a képpel, **Szöveg másolása**, **Kép letöltése (PNG)**, **Új kép** (kéréssel is, pl. „a három pontos elrendezéssel”), forráslink az első kommenthez. A Szerkesztés fülön mezőnként javítható; a kép címe, alcíme és számai is: mentéskor a kép azonnal, ingyen újrarajzolódik. A **Forráscikkek** kártyán módosítható, mely cikkekből készüljön (Csak mentés, vagy Mentés és újraírás).
6. **Jóváhagyás:** Attila **Jóváhagyom** vagy **Módosítást kérek** (megjegyzéssel: a Claude azonnal újraírja, és újra jóváhagyásra kerül) → jóváhagyás után Emi e-mailt kap, kimásolja, időzíti a LinkedInen, majd **Megjelent a LinkedInen**.
7. Az előnézet fejlécéhez (nem kötelező) a Sheet „Beállítások” lapján: `linkedin_nev`, `linkedin_kovetok`. Más modell: `modell_linkedin` (pl. `claude-sonnet-5-5`).

### 10. Források, ügyfélbeállítások, Napló
- **Források:** a figyelt oldalak (hírlista vagy RSS) és a kulcsszavak itt is szerkeszthetők; új forrásnál a **Próbagyűjtés** megmutatja, mit talál. Egy új ügyfél így Google Sheet nélkül is elindulhat.
- **Ügyfelek → Beállítási lépések:** egy új ügyfél teendői sorban, a hiányzóknál linkkel. **Ügyfélbeállítások:** a LinkedIn-előnézet neve és követőszáma, és az egyes feladatok Claude-modellje.
- **Napló:** szűrés ügyfélre, felhasználóra, művelettípusra és időszakra; **Letöltés (CSV)** Excelhez. Attila a saját ügyfele műveleteit látja. Ha egy időzített futás hibával zárul, e-mailt kapsz.

### 11. Időzítés (cron)
A tárhely vezérlőpultjában két időzített feladat kell; a pontos címeket a **Vezérlőpult → Időzítés (cron)** kártyán másolhatod:
- **Havi futás:** minden hónap 1-jén 06:00-kor (Europe/Budapest).
- **Óránkénti futás:** minden órában.

Ha a vezérlőpult csak parancsot fogad el, az URL-t egy `wget -q -O - "<cím>"` parancsba kell tenni; ezt a vezérlőpult súgója szokta mutatni.

### Elfelejtett jelszó
A jelszólink e-mailben még nem megy ki; az adminisztrátor ad új linket: **Felhasználók → sor ⋯ menüje → Jelszó-visszaállító link** (24 óráig érvényes).

## Titkok
A `config.php` és minden kulcs a repón kívül marad; a repóban csak a `config.example.php` van a változónevekkel. A `.htaccess` a konfigurációt, a JSON-fájlokat és a belső mappákat kívülről elérhetetlenné teszi.

## Fejlesztés
`composer install`, majd `vendor/bin/phpunit`. Az adatbázistesztek valódi MySQL/MariaDB-t használnak (`XP_TEST_DB_HOST`, `XP_TEST_DB_USER`, `XP_TEST_DB_PASS`, `XP_TEST_DB_NAME`; alapértelmezés: localhost, xp/xp, xp_test); ha nincs adatbázis, kimaradnak. A külső szolgáltatások (Google, Claude, SMTP) helyettesítővel futnak.
