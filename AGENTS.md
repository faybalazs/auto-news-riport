# Útmutató AI-ügynököknek: Xperion automatizálás

PHP-alkalmazás, amely havonta lefuttatja az Xperion (xperion.hu) sajtófigyelését a Google Sheetből, az ügyfél döntései alapján élesben feltölti a cikkeket, LinkedIn-posztokat generál, és egy webes felületen előnézettel elérhetővé teszi őket.

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
- **Google Sheet:** ID `1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA`; szerkezete a product.md 4. pontjában.
- **Posztszabályok:** a Sheet „LinkedIn szabályok” lapja. Nem a kódban és nem ebben a repóban élnek; mindig frissen olvasd be.
- **Minták:** a Sheet `2609` lapja (havi lap) és a „LinkedIn posztok” lap `LI-2609-01` sora (poszt).

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
- Még nincs kód és nincs tesztparancs. Az 1. ütemtől: PHPUnit a sandboxban (csak fejlesztői függőség), élesben pedig szárazfuttatás (`dry=1`) a kulccsal védett admin oldalon, a felhasználó szerverén.
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
