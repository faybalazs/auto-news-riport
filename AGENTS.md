# Útmutató AI-ügynököknek: Xperion automatizálás

PHP-alkalmazás saját felülettel és MySQL-adatbázissal (több ügyfélre): havonta begyűjti és a Claude-dal értékelteti az ügyfél (elsőként az Xperion, xperion.hu) sajtófigyelésének cikkeit, az ügyfél döntései alapján magyarul és angolul feltölti őket a weboldalra, havi e-mailt és LinkedIn-posztokat készít.

## Indulás
1. [docs/project-state.md](docs/project-state.md): aktuális állapot, blokkolók, következő lépések.
2. A feladat szerint, célzottan:
   - [docs/product.md](docs/product.md): követelmények és elfogadási feltételek (a megfelelő szakaszt olvasd, ne az egészet)
   - [docs/roadmap.md](docs/roadmap.md): ütemek
   - [docs/decisions.md](docs/decisions.md): érvényes döntések
   - [docs/terminology.md](docs/terminology.md): a magyar szakkifejezések és jelentésük
3. [docs/architecture.md](docs/architecture.md): modulok, belépési pontok, adatbázis.
4. Ha kell: `tools/felulet/` (a felület forrásai és építése; `app-v2.css` a v2 design rétege a régi fölött), `docs/kezikonyv/` (felhasználói kézikönyv forrása; kimenet `app/resources/kezikonyv.html`), `wordpress/mu-plugins/` (a WordPressbe kerülő belépési kiegészítő), `docs/integracio/` (WordPress-bővítmény API).

## Mérvadó források
- **Követelmények:** docs/product.md. Ha a kód vagy egy kérés ellentmond neki, jelezd, ne dönts helyette.
- **Adatok:** a saját felület és MySQL-adatbázis (döntés: 2026-11-02). A Google Sheet (ID `1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA`, szerkezete a product.md 4. pontjában) csak az egyszeri adatátvétel forrása.
- **Posztszabályok:** az alkalmazás Posztszabályok oldalán, ügyfelenként az adatbázisban (a Sheet „LinkedIn szabályok” lapjából átvéve).
- **Minták:** a Sheet `2609` lapja (havi lap), a „LinkedIn posztok” lap `LI-2609-01` sora (poszt), a jóváhagyott posztkép `docs/terv/poszt-kep-minta.png`.

## Munkaszabályok (a felhasználó tartós kérései)
- A felhasználó (Balázs, Auris Dynamics) iPaden dolgozik, **nincs terminálja**. Neki szóló lépés csak grafikus felületen lehet: böngésző, GitHub webes felülete, tárhely-vezérlőpult. Parancssori utasítást ne adj neki.
- Ha egy lépésnek több útja van, kérdezz rá, és jelezd, melyik a költséghatékonyabb.
- Beállítási lépés a felhasználónak csak részletesen: pontos menüútvonal és mezőnevek; minden szakkifejezésnél (pl. alkalmazásjelszó, szolgáltatásfiók, cron-kulcs) mi az, hol készül, miben tér el a hasonlótól; hogyan néz ki a helyes érték; hova kerül pontosan, mintasorral; a gyakori tévesztés előre; és miből látszik, hogy sikerült (decisions.md, 2026-11-03).
- Kommunikáció magyarul. Kódazonosítók angolul, a felhasználónak és az ügyfélnek szóló szövegek (felület, e-mail, napló, README) magyarul.
- Mielőtt sok kódot írsz, rövid tervet kérj jóvá. Kis pull requestekben dolgozz, rövid magyar összefoglalóval.
- Titkok (`config.php`, API-kulcsok, tokenek, szolgáltatásfiók JSON, `.env`) soha nem kerülnek a repóba; csak `config.example.php` a változónevekkel.
- Ügyfélnek e-mail csak a felhasználó jóváhagyása után megy, piszkozat nélkül. Cikk élesbe csak „Mehet” jelölés után kerül.
- Éles szerverre nem telepítesz. A kiadást a felhasználó indítja: GitHub → Actions → **Telepítés élesbe** (`.github/workflows/telepites.yml`, kézi), vagy ZIP a tárhely fájlkezelőjével. A munkafolyamatot te nem futtatod.

## Commitok (ebből készül a release notes és a kézikönyv-frissítés)
- **Egy változás = egy commit.** Több funkciót vagy javítást ne vonj össze egy commitba.
- **Cím:** `Típus(Terület): mit lát a felhasználó` magyarul, egy sorban. Típus: `Új`, `Változás`, `Javítás` (az ügyfél is látja) vagy `Belső` (refaktor, teszt, dokumentáció, eszköz, telepítés; a release notes-ba nem kerül). Terület: a menüpont neve (Áttekintés, Havi cikkek, Posztok, Posztötletek, Posztszabályok, Források, Felhasználók, Ügyfelek, Vezérlőpult, Napló, Profil, Belépés, Kézikönyv, Rendszer).
  Példa: `Új(Posztok): lapozó a posztlistán, oldalanként 12 poszt`
- **Törzs:** 1–3 mondat, mit és miért; utána a záró sorok (a `Co-Authored-By` elé):
  - `Kiadasi-jegyzet:` egy mondat az ügyfél felhasználóinak, egyszerű nyelven (a `Belső` típusnál elhagyható).
  - `Kezikonyv:` az érintett fejezetek azonosítói a `docs/kezikonyv/content.js`-ből (pl. `a-posts, t-edit`), vagy `nem érinti`.
  - `Szerepkor:` kit érint (ügyfél-adminisztrátor, tartalomkezelő, olvasó, üzemeltető).
  - `Ellenorzes:` hogyan ellenőrizted (pl. `PHPUnit; böngészős próba`), vagy `nem ellenőrzött`.
- **Kiadás:** külön commit `Kiadás X.Y.Z` címmel (verzió a `app/bootstrap.php`-ban + CHANGELOG + szükség szerint kézikönyv). Két kiadás közötti release notes: a két `Kiadás` commit közötti nem `Belső` commitok `Kiadasi-jegyzet` sorai, típus szerint csoportosítva; a kézikönyv teendőlistája a `Kezikonyv` sorokból.
- Példa teljes üzenet:
  ```
  Új(Posztok): lapozó a posztlistán, oldalanként 12 poszt

  Sok poszt esetén a lista túl hosszú volt; Előző/Következő gombok és darabszám.

  Kiadasi-jegyzet: A LinkedIn-posztok listája lapozható; oldalanként 12 poszt látszik.
  Kezikonyv: a-posts, t-posts
  Szerepkor: ügyfél-adminisztrátor, tartalomkezelő
  Ellenorzes: böngészős próba 15 poszttal
  ```

## Ellenőrzés
- Tesztek: `vendor/bin/phpunit` (az adatbázistesztekhez fusson a MariaDB: `service mariadb start`). Felület: a `tools/felulet/` forrásait szerkeszd, utána építs (`tools/felulet/README.md`), és futtasd az auris-admin-sablon skill `scripts/app-ellenorzes.js` felületellenőrzőjét. Élesben a szárazfuttatás a Vezérlőpulton.
- A sandbox hálózata korlátozott: a Google Sheets, a Claude API, az MCP-szerver és az SMTP élő hívását itt nem lehet ellenőrizni. Ezeket helyettesítővel teszteld, és „megvalósított, nem ellenőrzött” állapotban jelöld, amíg a felhasználó élesben ki nem próbálja.
- Bizonyíték nélkül semmi nem „ellenőrzött”.

## Lezárás (érdemi feladat végén)
- docs/project-state.md: funkcióállapot, kockázatok, következő 1–3 lépés.
- docs/decisions.md: csak új érdemi döntés.
- CHANGELOG.md: kiadásnál verzió, dátum, csomag, módosult fájlok, ellenőrzöttség. Ebből látja a felhasználó, mit kell feltöltenie.
- docs/architecture.md: modul- vagy belépésipont-változásnál.
- Kiadásnál `app/bootstrap.php` (`XPERION_VERSION`, `XPERION_RELEASE`) és a CHANGELOG együtt. Felületváltozásnál a kézikönyv (`docs/kezikonyv/`) érintett fejezete és képe is frissül.
- **Napzárás:** csak kérésre („nap vége”, „zárjuk a napot”, „frissítsd a projektet”), a fenti lépésekkel; a végén egy mondatban, mi frissült.

## Állapotok
tervezett · megvalósított, nem ellenőrzött · ellenőrzött (módszerrel) · ügyfél által elfogadott (forrással)
