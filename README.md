# Xperion automatizálás

Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztgenerálás az Xperion (xperion.hu) számára, a meglévő Google Sheetre építve.

- **Állapot:** 1. ütem, 1. lépés kész (alap, Sheets-kapcsolat, admin felület). Részletek: [docs/project-state.md](docs/project-state.md)
- **Technológia:** PHP 8.1+, függőség nélküli éles kód; Google Sheets API, Claude API, SMTP, a meglévő feltöltő program
- **Követelmények:** [docs/product.md](docs/product.md) · **Felépítés:** [docs/architecture.md](docs/architecture.md)
- **AI-ügynököknek:** [AGENTS.md](AGENTS.md)

## Telepítés (terminál nélkül)

Az alábbi lépések az 1. lépés állapotát írják le; a Claude API-kulcs és az SMTP a későbbi lépéseknél kerül ide.

### 1. Google szolgáltatásfiók
1. A Google Cloud Console-ban (console.cloud.google.com) hozz létre egy projektet, vagy válassz egy meglévőt.
2. Kapcsold be a **Google Sheets API**-t a projektben.
3. Hozz létre egy **szolgáltatásfiókot** (Service account), majd nála egy **JSON-kulcsot**. A böngésző letölt egy `.json` fájlt.
4. Nyisd meg a Google Sheetet, **Megosztás**, és add hozzá a szolgáltatásfiók e-mail-címét (`…@….iam.gserviceaccount.com`) **szerkesztőként**.

### 2. A program letöltése
1. A GitHubon nyisd meg a repót, válaszd ki a kiadási ágat, majd **Code → Download ZIP**.
2. A tárhely fájlkezelőjében hozz létre egy mappát (pl. `public_html/xperion/`), töltsd fel a ZIP-et, és csomagold ki ott. A ZIP egy almappát tartalmaz: annak a *tartalma* kerüljön az `xperion/` mappába.

### 3. `config.php`
1. A fájlkezelőben másold le a `config.example.php`-t `config.php` néven, és nyisd meg szerkesztésre.
2. Töltsd ki:
   - `admin_key` és `cron_key`: két különböző, legalább 16 karakteres véletlen szöveg (pl. jelszógenerátorból).
   - `google_service_account_json`: nyisd meg a letöltött JSON-fájlt, és a teljes tartalmát másold az idézőjelek közé. Egyszeres idézőjelet (`'…'`) használj, mert a JSON-ban dupla idézőjelek vannak.
3. Mentsd el. (Ha a tárhelyen van a webgyökér fölötti mappa, a fájl oda is kerülhet `xperion-config.php` néven; így még biztonságosabb.)

### 4. Első próba
1. Nyisd meg böngészőben: `https://<domain>/xperion/`, és lépj be az `admin_key` értékével.
2. A **Beállítások állapota** kártyán a konfiguráció, a kulcsok és a szolgáltatásfiók legyen zöld.
3. **Kapcsolat ellenőrzése → Ellenőrzés.** Ha hibát ír, az üzenet megmondja, mi a gond (pl. nincs megosztva a tábla).
4. **Munkalapok előkészítése → Szárazfuttatás**, majd ha rendben van, **Éles futtatás**: létrejön a Beállítások, a Napló és a Posztötletek lap.

### 5. Időzítés (cron)
A tárhely vezérlőpultjában (cron / időzített feladatok) két feladat kell. A pontos címeket az admin felület **Időzítés (cron)** kártyájáról másolhatod:
- **Havi futás:** minden hónap 1-jén 06:00-kor (Europe/Budapest).
- **Óránkénti futás:** minden órában.

Ha a vezérlőpult csak parancsot fogad el, az URL-t egy `wget -q -O - "<cím>"` vagy `curl -s "<cím>"` parancsba kell tenni; ezt a vezérlőpult súgója szokta mutatni.

## Titkok
A `config.php` és minden kulcs a repón kívül marad; a repóban csak a `config.example.php` van a változónevekkel. A `.htaccess` a konfigurációt, a JSON-fájlokat és a belső mappákat kívülről elérhetetlenné teszi.

## Fejlesztés
`composer install`, majd `vendor/bin/phpunit`. A külső szolgáltatások (Google, Claude, SMTP) a tesztekben helyettesítővel futnak.
