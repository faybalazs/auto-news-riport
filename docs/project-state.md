# Projektállapot

- **Frissítve:** 2026-11-03 (kiadás: 0.13.0)
- **Forrás:** felhasználói közlés (claude.ai chat), a jóváhagyott specifikáció ([product.md](product.md)) és a repó kódja.

## Cél
Az eddig kézzel, chatben végzett havi sajtófigyelés és LinkedIn-posztkészítés automatizálása PHP-ban, **saját felülettel és adatbázissal**, több ügyfélre felkészítve (döntés: 2026-11-02; a Google Sheetből egyszeri adatátvétel lesz).

## Funkciók
| Funkció | Állapot | Forrás |
|---|---|---|
| Alap: konfiguráció, zárolás, futásnapló, szárazfuttatás, cron | ellenőrzött élesben (1. lépés, a felhasználó futtatta) | roadmap 1 |
| Adatbázis (MySQL) automatikus migrációval | megvalósított; ellenőrzött a sandboxban MariaDB 10.11-en (PHPUnit + böngészős végponttól végpontig teszt); élesben nem ellenőrzött | roadmap 2 |
| Első beállítás, belépés, meghívó és jelszólink, jelszócsere | megvalósított; ellenőrzött a sandboxban (böngészős teszt); élesben nem ellenőrzött | roadmap 2 |
| Ügyfelek, felhasználók, szerepkörök, ügyfélváltó, napló | megvalósított; az ügyfélelválasztás PHPUnit-tesztekkel és böngészőből (API-hívással) ellenőrizve a sandboxban | roadmap 2 |
| Adatátvétel ügyfelenkénti Google Sheetből (források, kulcsszavak, havi lapok cikkei, posztok, ötletek, szabályok, beállítások) | ellenőrzött: PHPUnit helyettesített Sheettel; élesben a felhasználó lefuttatta a valódi Sheettel (0.5.2, „Megy”) | roadmap 3 |
| Cikkgyűjtés (RSS, HubSpot, listaoldal; hónap-, duplikátum- és kizáró szűrés), egyelőre mentés nélkül | megvalósított; ellenőrzött mintaoldalakkal (PHPUnit) és helyi mintaforrásokkal böngészőből; valódi forrásoldalakon nem ellenőrzött | roadmap 4 |
| Havi futás: Claude-értékelés (Haiku 5.5), mentés; Havi cikkek oldal döntésekkel | élesben lefutott (2026. október, valódi forrásokkal és Claude-dal; a felhasználó az eredményből dolgozott); PHPUnit helyettesített Claude-dal | roadmap 5 |
| Vezetői összefoglaló tagoltan (bevezető; Újdonságok, Tények, számok, Trendek, forrásmegnevezéssel), Újragenerálás gomb (üzemeltető, ügyféladmin) | megvalósított (0.13.0); ellenőrzött PHPUnit-tal és böngészőből; valódi Claude-dal nem ellenőrzött, a szöveg minőségét a felhasználó még nem látta | decisions 2026-11-03 |
| Áttekintés valódi adatokkal (időszak, teendők, összefoglaló, KPI-k, megoszlások) | ellenőrzött: PHPUnit, böngésző; élesben a felhasználó kipróbálta (0.6.0, „Működik”) | roadmap 6 |
| Havi e-mail: generálás, szerkesztés, teszt e-mail, jóváhagyás utáni küldés; SMTP-kapcsolat ellenőrzése a Vezérlőpulton | megvalósított; ellenőrzött PHPUnit-tal és helyi teszt-SMTP-vel; az SMTP-kapcsolat élesben működik (a felhasználó: „smtp jó”, 2026-11-03); ügyfélnek küldött levél még nem ment | roadmap 7 |
| Feltöltés a weboldalra (Xperion OAuth Bridge 0.6.0, deploy_pair): HU/EN ajánlópár Claude-fordítással, óránként és kézzel; a Havi cikkeken az ügyféladmin és a tartalomkezelő is indíthatja; Publikálva állapot; visszavonás; törlés a listából visszaállítással; belépési diagnosztika | megvalósított (0.12.0); ellenőrzött PHPUnit-tal és böngészőből helyi hamis WordPress-szel; élesben: a kapcsolat az xperion.hu-val működik, a felhasználó teszt-cikkeket feltöltött („ment”, 2026-11-03, a Vezérlőpultról, 0.12.0 előtt); az új Havi cikkek-kártya, a visszavonás és a törlés élesben még nem kipróbált | roadmap 8 |
| LinkedIn-posztok: szabályok, ötletek, generálás (Opus 5.5), automatikus jóváhagyásra küldés, módosítási kérésre újraírás; kép rögzített sablonokból (5 elrendezés), szövege Claude nélkül javítható, „Új kép”; a forráscikkek szerkeszthetők; lista mini-mockuppal | megvalósított (0.10.1); PHPUnit és böngésző; élesben használatban (a felhasználó a valódi posztképekről adott visszajelzést, 2026-11-02) | roadmap 9 |
| Források és kulcsszavak; Napló szűrőkkel, CSV-vel; hibaértesítés e-mailben; megőrzés; Ügyfelek lista + tabos részletkártya | megvalósított; ellenőrzött PHPUnit-tal és böngészőből; élesben nem ellenőrzött | roadmap 10 |
| Felhasználói kézikönyv (ügyfél-adminisztrátor, tartalomkezelő): `app/resources/kezikonyv.html`, forrás: `docs/kezikonyv/`; az alkalmazásban a menü alján, új ablakban | elkészült (0.13.3); arculat az alkalmazás szerint; böngészőben ellenőrizve; a felhasználó még nem nézte át | – |
| Telepítés GitHub Actionsszel (`.github/workflows/telepites.yml`): kézi indítás, tesztek, FTPS-feltöltés a változott fájlokra, verzió-ellenőrzés | megvalósított, nem ellenőrzött (a beállítások – FTP-adatok, mappa, webcím – és az első futás a felhasználóra vár; a munkafolyamat csak a fő ágra kerülés után indítható) | decisions 2026-11-03 |
| Futások állapota a Vezérlőpulton (mi fut, mióta, melyik lépés; mentett eredmények); érthető hibaüzenet szerverhibánál is | megvalósított (0.10.3, 0.10.10); ellenőrzött PHPUnit-tal és böngészőből szimulált futással és hibákkal | – |

## Meglévő, repón kívüli elemek
| Elem | Állapot | Forrás |
|---|---|---|
| Google Sheet (havi lapok 2408–2609, LinkedIn posztok, LinkedIn szabályok) | az adatátvétel forrása (átvéve, 0.5.2); a munka azóta az alkalmazásban folyik | decisions 2026-11-02 |
| Feltöltő MCP-szerver (ChatGPT-vel készült, távoli, OAuth) | használatban | felhasználói közlés, nem vizsgált |
| Xperion OAuth Bridge WordPress-bővítmény 0.6.0 az xperion.hu-n | telepítve, működik (Kapcsolat ellenőrzése, 2026-11-03); HU/EN alapkategória beállítva; törlő művelete nincs | felhasználói közlés, Vezérlőpult |
| `wordpress/mu-plugins/xperion-auth-fejlec.php` 1.1.0 az xperion.hu `wp-content/mu-plugins` mappájában | telepítve, a belépés ezzel működik | felhasználói közlés, 2026-11-03 |
| Skillek (claude.ai): auris-admin-sablon, auris-felhasznaloi-kezikonyv | telepítve: sablon v1.6.0 (a felület ebből épül); átadva, feltöltésre vár: sablon v1.7.0 (lábléc, külső menüpont – addig a `tools/felulet/app-extra.css` adja), kézikönyv 1.2.0 | munkamenet, skill-csomagok |
| auris-dontesbank skill (claude.ai) | 1.2 átadva 2026-11-03 (részletes beállítási lépések; a sajtófigyelés mai döntései); a feltöltés a felhasználóra vár | a skill-csomag (zip) |
| xperion-linkedin skill (claude.ai) | használatban, a szabályokat a Sheetből olvassa | felhasználói közlés |

## Kockázatok
- Az Xperion OAuth Bridge 0.6.0 beállítóoldala a kategórialistákat az admin Polylang-nyelvszűrője szerint szűri: más nyelvi szűrővel mentve a másik nyelv alapkategóriája elveszhet. Kerülőút: „Az összes nyelv megjelenítése”. Javítás a bővítményben (ChatGPT-s beszélgetés), ha a felhasználó kéri.
- Az Apache `.htaccess` 2.4-es szintaxist használ; tárhelyváltásnál a `config.php`, az `app/` és a `storage/` védelmét ellenőrizni kell.
- A Claude-értékelés és a tagolt összefoglaló minőségét érdemes havonta az ügyfél döntéseivel és visszajelzésével összevetni (az első éles összefoglalót a felhasználó gyengének találta, ezért készült a 0.13.0).
- A listaoldalak szerkezete forrásonként eltér: az általános felismerés egyes oldalakon kevés vagy hibás eredményt adhat; ezt a szárazfuttatás forrásonkénti kimutatása mutatja meg, és célzottan javítható.
- A bővítménynek nincs törlő művelete: a feltöltés visszavonásakor a bejegyzéseket a WordPressben kell törölni (Lomtárból is véglegesen), különben egy későbbi feltöltés kint lévőnek látja.
- A felület forrásai a `tools/felulet/` mappában vannak; az `assets/app.*` generált fájl, kézi szerkesztése a következő építéskor elveszik.

## Blokkolók
- A forráscikkek képeinek átvétele (képjog): Attilával tisztázandó (az átadócsomag nyitott kockázata).

## Terv
- Kattintható prototípus (v3, többügyfeles, a valós posztszabályokkal): [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html), publikálva: https://claude.ai/artifact/NuS6Vvt9oKrgHBjjJrvsxe. Állapot: a felhasználó átnézte, a saját felület mellett döntött.
- Frissített ütemterv: [roadmap.md](roadmap.md).

## Következő lépések
1. Felhasználó: a 0.13.3 feltöltése (teljes ZIP; a lábléc mutatja a verziót); a felhasználói kézikönyv átnézése a menüből; a skillcsomagok (admin sablon 1.7.0, kézikönyv 1.2.0, döntésbank 1.2) feltöltése.
2. Éles próbák: a Havi cikkek feltöltéskártyája és a visszavonás az ügyféllel; az októberi összefoglaló **Újragenerálás**a és a szöveg átnézése; az első havi e-mail az ügyfélnek (jóváhagyás után).
3. A pull request átnézése és összevonása (merge) a fő ágba, ha az éles próbák rendben vannak.
