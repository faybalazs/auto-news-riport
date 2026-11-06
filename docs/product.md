# Követelmények: Xperion sajtófigyelés és LinkedIn-automatizálás (PHP)

> **Módosítás, 2026-11-02 (felhasználói döntés):** a rendszer **saját felülettel és adatbázissal** készül, a Google Sheet helyett. Az ügyfél és a munkatársai az alkalmazásban dolgoznak (döntések, jóváhagyás, ötletek, szabályok). Ahol ez a dokumentum Sheetet, munkalapot vagy oszlopot említ (4. pont, 6. pont), ott az alkalmazás megfelelő oldala és adattáblája értendő; a mezők és az állapotok változatlanok. A felület mintája: [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html). Új elemek: több ügyfél, felhasználókezelés szerepkörökkel, „Nem kell” döntés, „Új források” elnevezés. A Sheetből egyszeri adatátvétel lesz. A részletes, frissített fejlesztési terv a [roadmap.md](roadmap.md)-ben van.

Jóváhagyott specifikáció. A munkaszabályok az [AGENTS.md](../AGENTS.md)-ben, az aktuális állapot a [project-state.md](project-state.md)-ben van.

## 1. Cél

Havonta automatikusan lefusson az Xperion (xperion.hu) sajtófigyelése, az ügyfél a Google Sheetben döntsön, és a döntései alapján a rendszer:

1. a kijelölt cikkeket azonnal, élesben feltöltse az xperion.hu-ra,
2. a LinkedIn-posztra jelölt cikkekből és az ügyfél saját ötleteiből posztot generáljon,
3. a posztokat képpel és LinkedIn-mockuppal egy webes felületen elérhetővé tegye.

## 2. Döntések (a felhasználó jóváhagyta)

- **Futtatás:** PHP-alkalmazás a felhasználó saját szerverén (sunserver.hu), időzítve (cron a tárhely-vezérlőpultból).
- **Adatforrás és vezérlőpult:** a meglévő Google Sheet. Az ügyfél közvetlenül a megosztott táblában dolgozik.
- **Feltöltés:** a „Mehet” jelölésű cikkek **azonnal élesben** kerülnek fel (nem piszkozatként).
- **E-mail az ügyfélnek:** soha nem piszkozat. A havi levél csak akkor megy ki, ha a felhasználó külön jóváhagyja (lásd 6.4). Jóváhagyás után a rendszer közvetlenül elküldi.
- **LinkedIn-közzététel:** nem automatikus. A posztot Emi (az Xperion munkatársa) másolja ki a felületről, és a LinkedIn saját ütemezőjével időzíti.

## 3. Szereplők

- **Balázs** (Auris Dynamics): üzemeltető, ő hagyja jóvá a havi e-mailt.
- **Attila** (Berényi Attila, az Xperion ügyvezetője): ügyfél, ő jelöl a táblában vagy e-mailben válaszol.
- **Emi** (Xperion): a LinkedIn-oldal kezelője, ő viszi fel a posztötleteket és teszi közzé a posztokat.

Az e-mail-címeket ne írd a kódba: a „Beállítások” munkalapon vannak (6.1).

## 4. A Google Sheet

- **Táblázat ID:** `1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA`
- **Területi beállítás:** magyar, ezért a képletekben **pontosvessző** az elválasztó.
- **Hozzáférés:** Google Cloud szolgáltatásfiókkal (service account), a táblát a felhasználó megosztja a szolgáltatásfiók e-mail-címével szerkesztőként.

### 4.1 Meglévő munkalapok

**Figyelendő oldalak** (A–F): Forrás · Típus · Figyelt oldal (URL) · Fő témák · Aktív (igen/nem) · Megjegyzés
- Csak az `Aktív = igen` sorokat figyeld.
- A „próbaidőszak” megjegyzésű források cikkei a havi lapon külön blokkba kerülnek (4.2).
- A Vemco és a Qmatic HubSpot-blog: az RSS-feed a blog címe + `/rss.xml`. Ahol van RSS, azt használd a listaoldal helyett (a listaoldalak „Load more” gombja miatt).

**Kulcsszavak** (A–D): Kulcsszó · Témakör · Típus (Pozitív/Kizáró) · Nyelv (EN/HU)
- A kizáró kulcsszavas cikkek (pl. IPO, tax, M&A) nem kerülnek a listára.

**Havi lapok** (név: `YYMM`, pl. `2609` = 2026. szeptember)
- A régi lapok (2408–2608): No · Page · Date · Title · Desc · Link · Feltöltve (G oszlop, „Igen” = már fent van az xperion.hu-n).
- A legújabb minta a `2609` lap, ezt kövesd:
  - 1. sor: `Vezetői összefoglaló: 2026. szeptember`
  - 2. sor: kivonatos riport „Címke: érték” sorokkal, hosszú mondatok nélkül (pl. `Cikkek száma: 26`, `Legtöbb cikk: Vemco Group (14 db)`)
  - 3. sor: `Részletek:` + rövid szöveges összefoglaló
  - 4. sor: fejléc: No · Page · Date · Title · Desc · Link · Javaslat · Indoklás · Ügyfél döntése
  - 5. sortól: cikkek. Utánuk elválasztó sor: `Új források (próbaidőszak, …)` sárga háttérrel, alatta a próbaidőszakos források cikkei halványsárga háttérrel.
  - Javaslat értékei: `Erősen javasolt` / `Javasolt` / `Nem javasolt` (zöld / halványzöld / fehér feltételes formázás). Ha a cikk már fent van: `Nem javasolt (már fent)`.
  - Ügyfél döntése (legördülő): `Mehet` / `LinkedIn poszt` (kék / lila feltételes formázás).

**LinkedIn posztok** (A–Q): ID · Hónap · Státusz · Témakör · Forráscikkek · Nyitó sor · Alternatív nyitó 1 · Alternatív nyitó 2 · Kontextus · Kiemelések · Forráslink · Xperion szemszög · Zárás · Hashtagek · Teljes poszt · Kép · Megjegyzés
- ID formátum: `LI-YYMM-NN` (pl. `LI-2609-01`).
- Státusz: `Tervezet` / `Ügyfélnél` / `Jóváhagyva` / `Megjelent`.
- Teljes poszt (O) képlete: `=TEXTJOIN(CHAR(10)&CHAR(10);TRUE;F{sor};I{sor};J{sor};L{sor};M{sor};N{sor})` (a forráslink szándékosan nincs benne).
- Az első, kézzel készült sor (`LI-2609-01`) a minta.

**LinkedIn szabályok** (A–B): Megnevezés · Érték
- Ez a posztgenerálás **egyetlen mérvadó szabályforrása** (hangnem, szerkezet, hossz, hashtagek, poszttípusok, kerülendők, kép). Minden generálás előtt friss állapotában olvasd be, és add át a Claude API-nak. A felhasználó és az ügyfél szerkesztheti, ezért ne másold a kódba.

### 4.2 Új oszlopok és munkalapok, amiket létre kell hozni

Havi lapokon (2611-től) a 2609-es oszlopok után:
- J: Feltöltés (`Feltöltve` / `Hiba: …`)
- K: Feltöltött URL (HU)
- L: Feltöltött URL (EN)
- M: Poszt ID (a belőle készült LinkedIn-poszt azonosítója)

LinkedIn posztok lapon:
- R: Kép adatok (JSON: a kép címe, alcíme, opcionális számpélda; a felület ebből rajzolja a képet)
- S: Előnézet link

**Posztötletek** (új lap, Emi és Attila tölti): ID · Beküldő · Téma · Fő üzenet · Forrás- vagy fotólink · Poszttípus (szakmai / rendezvény / díj / partner / csapat) · Kívánt hét · Állapot (`Új` / `Elkészült` / `Jóváhagyva` / `Ütemezve` / `Elvetve`) · Előnézet link · Poszt ID · Megjegyzés

**Beállítások** (új lap, A: Kulcs, B: Érték), legalább:
- `ugyfel_email`, `ugyfel_cc`, `uzemelteto_email` (Balázs), `felulet_url`
- `honap_email_allapot`: `Jóváhagyásra vár` / `Mehet` / `Elküldve – <dátum>`
- `aktualis_honap` (pl. `2611`)
- `modell_ertekeles`, `modell_poszt` (Claude modellnevek, felülírhatók)

**Napló** (új lap): Időbélyeg · Feladat · Eredmény (OK / Hiba) · Részletek. Minden futás ide is ír.

## 5. Architektúra

PHP-alkalmazás, moduláris felépítéssel, hogy később a tervezett többoldalas AI webszerkesztő platform modulja lehessen (több WordPress-oldal kiszolgálása).

- **Ütemezés:** két cron-belépési pont, mindkettő `?k=<titkos kulcs>` paraméterrel is meghívható a böngészőből:
  - havi: minden hónap 1-jén 06:00-kor (Europe/Budapest)
  - óránkénti: döntések figyelése, feltöltés, posztgenerálás, jóváhagyott e-mail küldése
- **Google Sheets API:** szolgáltatásfiókkal. A JWT-aláírást OpenSSL-lel oldd meg, ne használj Composer-függőséget (4. pont, Telepítés).
- **Claude API** (Messages API): cikkértékelés, összefoglaló, e-mail-szöveg, posztgenerálás, később e-mailes válaszok értelmezése. Strukturált JSON-kimenetet kérj, és validáld.
- **Feltöltés:** a meglévő, távoli MCP-szerveren keresztül (a felhasználó ChatGPT-vel készítette, OAuth-hitelesítéssel). Két út, a felhasználótól kapott adatok alapján válassz:
  1. a Claude API MCP connectora (`mcp_servers`, `authorization_token`); ilyenkor az OAuth-token beszerzését és frissítését nekünk kell kezelni,
  2. közvetlenül az xperion.hu-n futó **Xperion OAuth Bridge** WordPress-plugin (PHP 7.4) végpontjai, ha ez egyszerűbb és megbízhatóbb.
- **E-mail:** SMTP a sunserverről (beállítás a konfigurációban). Csak küldés, piszkozat nincs.
- **Webes felület:** ugyanebben az alkalmazásban (pl. `/posztok/`).

## 6. Folyamatok

### 6.1 Havi futás (hónap 1-je, az előző hónap cikkei)

1. Beolvassa a Figyelendő oldalakat és a Kulcsszavakat.
2. Lekéri az aktív források RSS-ét vagy listaoldalát, kiválasztja az előző hónap cikkeit (cím, dátum, link, rövid leírás).
3. Kiszűri a duplikátumokat: minden korábbi havi lap Link oszlopa, és a már feltöltött cikkek (régi lapokon G = „Igen”, új lapokon J = „Feltöltve”).
4. Kizáró kulcsszavak alapján szűr.
5. Claude API: minden cikkhez Desc (magyarul, 1–2 mondat), Javaslat és Indoklás. Mintaként kapja a Kulcsszavakat és az eddigi ügyféldöntéseket (Mehet, LinkedIn poszt, Feltöltve).
6. Létrehozza az új `YYMM` lapot a 2609-es szerkezettel, formázással és legördülőkkel (4.1, 4.2).
7. Claude API: vezetői összefoglaló (2–3. sor).
8. Claude API: az ügyfélnek szóló havi e-mail szövege, a mentett stílus szerint:
   - a csapat nevében (többes szám), tárgyilagos, lényegre törő
   - rövid bevezető + a lap linkje
   - újdonságok pontokban „Címke: leírás” formában
   - a hónap számai
   - ne ismételje, ami a táblázatból kiderül, és ne írjon formázási részleteket
9. A szöveget elmenti, a `honap_email_allapot` értéke `Jóváhagyásra vár` lesz, és **csak Balázsnak** küld értesítést az e-mail előnézetével és a jóváhagyás módjával.
10. Ha a lap már létezik, nem hozza létre újra (idempotens), hanem naplóz és leáll.

### 6.2 Óránkénti futás

1. **E-mail jóváhagyás:** ha `honap_email_allapot = Mehet`, elküldi a havi levelet az ügyfélnek (`ugyfel_email`, `ugyfel_cc`), és az állapotot `Elküldve – <dátum>`-ra állítja. Ha nincs jóváhagyás, nem küld semmit.
2. **Feltöltés:** az aktuális havi lap `Mehet` sorai, ahol J még üres: élesben feltölti két nyelven (HU, EN), majd kitölti a J–L oszlopot. Hiba esetén `Hiba: <ok>` a J-be, értesítés Balázsnak, és a következő futás újrapróbálja (legfeljebb 3-szor).
3. **Poszt cikkekből:** a havi lap `LinkedIn poszt` sorai, ahol M még üres. Alapértelmezés: egy havi poszt az összes jelölt cikkből (közös gondolatra építve, a szabályok szerint). Kitölti a LinkedIn posztok lap új sorát `Tervezet` státusszal, és a cikkek M oszlopába beírja a Poszt ID-t.
4. **Poszt ötletből:** a Posztötletek `Új` sorai: soronként egy poszt; az ötlet állapota `Elkészült`, mellé az Előnézet link és a Poszt ID.

### 6.3 Posztgenerálás

- A Claude API megkapja: a LinkedIn szabályok lap teljes tartalmát, a forráscikkeket vagy az ötletet, és mintának a `LI-2609-01` sort.
- Kimenet JSON-ban, a LinkedIn posztok lap mezői szerint (F–N, Q), plusz a kép adatai (R).
- A teljes posztot nem a PHP rakja össze: az O oszlopba a képlet kerül (4.1).
- A külső link nem kerül a posztszövegbe (K oszlopba megy).

### 6.4 A havi e-mail jóváhagyása

- Balázs a Beállítások lapon a `honap_email_allapot` értékét `Mehet`-re állítja.
- A következő óránkénti futás elküldi. Piszkozat sehol nem keletkezik.

### 6.5 Webes felület

- `/posztok/`: lista (ID, téma, státusz, dátum), legfrissebb elöl.
- `/posztok/<ID>`: két nézet egymás alatt vagy fülön:
  - **Zárt nézet:** LinkedIn-hírfolyam kártya, a szöveg a „…több” előtt levágva.
  - **Kibontott nézet:** a teljes poszt.
  - Fejléc: Xperion-logó, „Xperion Ltd.”, követőszám; alul a kép, a LinkedIn reakciósávjához hasonló gombsor (dekoráció).
  - Gombok: „Szöveg másolása” (vágólap), „Kép letöltése (PNG)”.
- **Kép:** 1200 × 1200 px sablon, böngészőben rajzolva (Canvas) a Kép adatok JSON-ból, PNG-ként letölthető. Színek a logóból: `#2D388A` → `#00AEEF` színátmenet, sötétkék háttér; rövid cím, alcím, opcionális „mért → valós” számpélda „Illusztratív példa” címkével; lent `xperion.hu`.
- Hozzáférés: titkos kulcsos link vagy HTTP basic auth; `noindex`.
- Mobilon is olvasható, sötét módot követi.

### 6.6 E-mailes válaszok feldolgozása (3. ütem)

- IMAP-postafiók figyelése, csak az ügyfél címéről, csak a havi levél szálában.
- Claude API kiolvassa a döntéseket (sorszám, cím vagy link alapján), és beírja az Ügyfél döntése oszlopba.
- Ha a válasz nem egyértelmű (nem létező sorszám, ellentmondás), nem módosít semmit, csak értesíti Balázst.
- A táblában az ügyfél által megadott döntést soha nem írja felül.

## 7. Ütemezés (szállítás)

**1. ütem: havi futás**
- [ ] Sheets-kapcsolat (olvasás, írás, új lap formázással)
- [ ] Gyűjtés RSS-ből és listaoldalakról, dátum- és duplikátumszűrés
- [ ] Claude-értékelés, havi lap, összefoglaló
- [ ] Havi e-mail jóváhagyásos küldéssel (6.4)
- [ ] Napló, értesítés Balázsnak

**2. ütem: döntések, feltöltés, posztok, felület**
- [ ] Óránkénti futás (6.2 1–4. pont)
- [ ] Élő feltöltés két nyelven, visszaírás a táblába
- [ ] Posztgenerálás cikkekből és ötletekből
- [ ] Webes felület képpel és mockuppal

**3. ütem**
- [ ] E-mailes válaszok feldolgozása (6.6)

## 8. Elfogadási feltételek

- A havi futás a `2610` lapot hozza létre a 2609-es szerkezettel; ugyanaz a cikk nem kerül fel kétszer, és a már feltöltött cikk nem jelenik meg újra.
- A második havi futás ugyanarra a hónapra nem hoz létre új lapot.
- Jóváhagyás nélkül az ügyfél nem kap e-mailt; `Mehet` után a következő órában megkapja, egyszer.
- `Mehet` jelölés után a cikk élőben megjelenik az xperion.hu-n mindkét nyelven, és a táblában ott van a két URL.
- `LinkedIn poszt` jelölés után a LinkedIn posztok lapon megjelenik a poszt `Tervezet` státusszal, működő képlettel, és a felületen zárt és kibontott nézetben, letölthető képpel látható.
- A szabályok lap módosítása a következő generálásnál már érvényesül.
- Minden hiba a Napló lapra és Balázsnak e-mailben is eljut; egy hibás cikk nem állítja meg a többit.
- Két egyidejű futás nem fut egymásra (zárolás).

## 9. Technikai keretek

- **PHP:** cél PHP 8.1+, de ellenőrizd a szerver verzióját (nyitott kérdés). A WordPress-plugin PHP 7.4-en fut, de az ettől független.
- **Nincs Composer a szerveren:** az éles kód függőség nélkül fusson (cURL, JSON, OpenSSL, DOM). Ha mégis kell külső csomag, a `vendor/` mappa kerüljön a kiadási csomagba. Fejlesztéshez és teszthez a sandboxban használhatsz Composert és PHPUnitot.
- **Titkok:** `config.php` a webgyökéren kívül (vagy `.htaccess`-szel védve), a repóba csak `config.example.php` kerül. Kulcsok: Claude API-kulcs, Google szolgáltatásfiók JSON, SMTP, MCP/OAuth adatok, a felület titkos kulcsa.
- **Szárazfuttatás:** `dry=1` paraméterrel minden feladat lefut írás, feltöltés és e-mail nélkül, és kiírja, mit csinálna. Ez a fő tesztelési mód, mert a felhasználó csak böngészőből tud futtatni.
- **Admin oldal:** egyszerű, kulccsal védett oldal gombokkal: havi futás (szárazon / élesben), óránkénti futás, utolsó napló sorok.
- **Időzóna:** Europe/Budapest; a felületen magyar dátumformátum.
- **Hibatűrés:** újrapróbálás exponenciális várakozással a külső hívásoknál; a Claude JSON-kimenetét séma szerint ellenőrizd.
- **Telepítés terminál nélkül:**
  - a kiadás egy ZIP, amit a felhasználó a tárhely fájlkezelőjével tölt fel, vagy
  - GitHub Actions FTP/SFTP-deploy, a titkokat a GitHub webes felületén adja meg.
- **README:** lépésről lépésre, kizárólag grafikus felületekkel: Google Cloud szolgáltatásfiók és a tábla megosztása, Claude API-kulcs, `config.php` kitöltése, feltöltés a szerverre, cron beállítása a tárhely-vezérlőpultban, első szárazfuttatás.

## 10. Nem célok (most)

- Automatikus LinkedIn-közzététel: LinkedIn API-jóváhagyás kell hozzá, külön ütemben.
- A többoldalas AI webszerkesztő platform felülete: most csak a moduláris felépítés legyen meg.
- WordPress-oldalak tartalmának szerkesztése a cikkfeltöltésen túl.

## 11. Nyitott kérdések (a felhasználótól kell)

Blokkolók, az 1–2. ütem előtt:
1. A sunserver PHP-verziója, és van-e cron a vezérlőpultban.
2. A feltöltő MCP-szerver URL-je, az eszközeinek listája és paraméterei, az OAuth adatai (token-végpont, kliens), vagy az Xperion OAuth Bridge végpontjai.
3. A két nyelvű feltöltésnél a fordítást a feltöltő program végzi, vagy a Claude API-nak kell elkészítenie a másik nyelvű változatot.
4. SMTP-adatok és a feladó címe.

Nem blokkolók:
5. IMAP-postafiók a 3. ütemhez.
6. A felület végleges címe (pl. `https://sunserver.hu/xperion/posztok/`).

Ha valamelyik hiányzik, kérdezd meg a felhasználót, és addig dolgozz azon a részen, amihez nem kell.
