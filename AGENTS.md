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
- Éles szerverre nem telepítesz. A kiadást a felhasználó tölti fel (ZIP a tárhely fájlkezelőjével, vagy általa jóváhagyott GitHub Actions).

## Ellenőrzés
- Tesztek: `vendor/bin/phpunit` (az adatbázistesztekhez fusson a MariaDB: `service mariadb start`). Felület: a `tools/felulet/` forrásait szerkeszd, utána építs (`tools/felulet/README.md`), és futtasd az auris-admin-sablon skill `scripts/app-ellenorzes.js` felületellenőrzőjét. Élesben a szárazfuttatás a Vezérlőpulton.
- A sandbox hálózata korlátozott: a Google Sheets, a Claude API, az MCP-szerver és az SMTP élő hívását itt nem lehet ellenőrizni. Ezeket helyettesítővel teszteld, és „megvalósított, nem ellenőrzött” állapotban jelöld, amíg a felhasználó élesben ki nem próbálja.
- Bizonyíték nélkül semmi nem „ellenőrzött”.

## Lezárás (érdemi feladat végén)
- docs/project-state.md: funkcióállapot, kockázatok, következő 1–3 lépés.
- docs/decisions.md: csak új érdemi döntés.
- CHANGELOG.md: kiadásnál verzió, dátum, csomag, módosult fájlok, ellenőrzöttség. Ebből látja a felhasználó, mit kell feltöltenie.
- docs/architecture.md: modul- vagy belépésipont-változásnál.
- **Napzárás:** csak kérésre („nap vége”, „zárjuk a napot”, „frissítsd a projektet”), a fenti lépésekkel; a végén egy mondatban, mi frissült.

## Állapotok
tervezett · megvalósított, nem ellenőrzött · ellenőrzött (módszerrel) · ügyfél által elfogadott (forrással)
