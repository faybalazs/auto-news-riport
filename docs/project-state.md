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
| Havi futás: Claude-értékelés (Haiku 5.5), mentés; Havi cikkek oldal döntésekkel | megvalósított; ellenőrzött helyettesített Claude-dal (PHPUnit) és böngészőből mintaadatokkal; valódi Claude-dal nem ellenőrzött | roadmap 5 |
| Vezetői összefoglaló tagoltan (bevezető; Újdonságok, Tények, számok, Trendek, forrásmegnevezéssel), Újragenerálás gomb (üzemeltető, ügyféladmin) | megvalósított (0.13.0); ellenőrzött PHPUnit-tal és böngészőből; valódi Claude-dal nem ellenőrzött, a szöveg minőségét a felhasználó még nem látta | decisions 2026-11-03 |
| Áttekintés valódi adatokkal (időszak, teendők, összefoglaló, KPI-k, megoszlások) | ellenőrzött: PHPUnit, böngésző; élesben a felhasználó kipróbálta (0.6.0, „Működik”) | roadmap 6 |
| Havi e-mail: generálás, szerkesztés, teszt e-mail, jóváhagyás utáni küldés; SMTP-kapcsolat ellenőrzése a Vezérlőpulton | megvalósított; ellenőrzött PHPUnit-tal és helyi teszt-SMTP-vel; az SMTP-kapcsolat élesben működik (a felhasználó: „smtp jó”, 2026-11-03); ügyfélnek küldött levél még nem ment | roadmap 7 |
| Feltöltés a weboldalra (Xperion OAuth Bridge 0.6.0, deploy_pair): HU/EN ajánlópár Claude-fordítással, óránként és kézzel; a Havi cikkeken az ügyféladmin és a tartalomkezelő is indíthatja; Publikálva állapot; visszavonás; törlés a listából visszaállítással; belépési diagnosztika | megvalósított (0.12.0); ellenőrzött PHPUnit-tal és böngészőből helyi hamis WordPress-szel; élesben: a kapcsolat az xperion.hu-val működik, a felhasználó teszt-cikkeket feltöltött („ment”, 2026-11-03, a Vezérlőpultról, 0.12.0 előtt); az új Havi cikkek-kártya, a visszavonás és a törlés élesben még nem kipróbált | roadmap 8 |
| LinkedIn-posztok: szabályok, ötletek, generálás (Opus 5.5), automatikus jóváhagyásra küldés, módosítási kérésre újraírás; kép rögzített sablonokból (5 elrendezés), szövege Claude nélkül javítható, „Új kép”; a forráscikkek szerkeszthetők; lista mini-mockuppal | megvalósított (0.10.1); ellenőrzött PHPUnit-tal és böngészőből; valódi Claude-dal nem ellenőrzött | roadmap 9 |
| Források és kulcsszavak; Napló szűrőkkel, CSV-vel; hibaértesítés e-mailben; megőrzés; Ügyfelek lista + tabos részletkártya | megvalósított; ellenőrzött PHPUnit-tal és böngészőből; élesben nem ellenőrzött | roadmap 10 |
| Felhasználói kézikönyv (ügyfél-adminisztrátor, tartalomkezelő): `docs/kezikonyv/kezikonyv.html` | elkészült (0.13.2); szövege a 0.13.1 felületéről; a felhasználó még nem nézte át | – |
| Futások állapota a Vezérlőpulton (mi fut, mióta, melyik lépés; mentett eredmények); érthető hibaüzenet szerverhibánál is | megvalósított (0.10.3, 0.10.10); ellenőrzött PHPUnit-tal és böngészőből szimulált futással és hibákkal | – |

## Meglévő, repón kívüli elemek
| Elem | Állapot | Forrás |
|---|---|---|
| Google Sheet (havi lapok 2408–2609, LinkedIn posztok, LinkedIn szabályok) | kézzel vezetve, használatban | felhasználói közlés |
| Feltöltő MCP-szerver (ChatGPT-vel készült, távoli, OAuth) | használatban | felhasználói közlés, nem vizsgált |
| Xperion OAuth Bridge WordPress-bővítmény 0.6.0 az xperion.hu-n | telepítve, működik (Kapcsolat ellenőrzése, 2026-11-03); HU/EN alapkategória beállítva; törlő művelete nincs | felhasználói közlés, Vezérlőpult |
| `wordpress/mu-plugins/xperion-auth-fejlec.php` 1.1.0 az xperion.hu `wp-content/mu-plugins` mappájában | telepítve, a belépés ezzel működik | felhasználói közlés, 2026-11-03 |
| auris-admin-sablon skill (claude.ai) | v1.6.0 feltöltve (a felület ebből épül) | a munkamenetben elérhető skill |
| auris-dontesbank skill (claude.ai) | 1.2 átadva 2026-11-03 (részletes beállítási lépések; a sajtófigyelés mai döntései); a feltöltés a felhasználóra vár | a skill-csomag (zip) |
| xperion-linkedin skill (claude.ai) | használatban, a szabályokat a Sheetből olvassa | felhasználói közlés |

## Kockázatok
- Az Xperion OAuth Bridge 0.6.0 beállítóoldala a kategórialistákat az admin Polylang-nyelvszűrője szerint szűri: más nyelvi szűrővel mentve a másik nyelv alapkategóriája elveszhet. Kerülőút: „Az összes nyelv megjelenítése”. Javítás a bővítményben (ChatGPT-s beszélgetés), ha a felhasználó kéri.
- A tárhely MySQL-verziója és a `pdo_mysql` bővítmény még nincs élesben ellenőrizve (a sémát MariaDB 10.11 és MySQL 5.7+ kompatibilisre írtuk).
- Az Apache `.htaccess` 2.4-es szintaxist használ; más webszervernél a `config.php` és a `storage/` védelmét ellenőrizni kell.
- A Claude-értékelés minősége valódi cikkeken még nincs mérve: az első havi futás javaslatait érdemes az ügyfél korábbi döntéseivel összevetni.
- A listaoldalak szerkezete forrásonként eltér: az általános felismerés egyes oldalakon kevés vagy hibás eredményt adhat; ezt a szárazfuttatás forrásonkénti kimutatása mutatja meg, és célzottan javítható.
- A bővítménynek nincs törlő művelete: a feltöltés visszavonásakor a bejegyzéseket a WordPressben kell törölni (Lomtárból is véglegesen), különben egy későbbi feltöltés kint lévőnek látja.
- A felület forrásai a `tools/felulet/` mappában vannak; az `assets/app.*` generált fájl, kézi szerkesztése a következő építéskor elveszik.

## Blokkolók
- A forráscikkek képeinek átvétele (képjog): Attilával tisztázandó (az átadócsomag nyitott kockázata).

## Terv
- Kattintható prototípus (v3, többügyfeles, a valós posztszabályokkal): [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html), publikálva: https://claude.ai/artifact/NuS6Vvt9oKrgHBjjJrvsxe. Állapot: a felhasználó átnézte, a saját felület mellett döntött.
- Frissített ütemterv: [roadmap.md](roadmap.md).

## Következő lépések
1. Felhasználó: a 0.13.0 feltöltése (teljes csere); a Havi cikkeken a **Feltöltés a weboldalra** kártya kipróbálása egy új cikkel (szövegek szárazon, majd élesen), a teszt-feltöltés visszavonása (⋯ → Feltöltés visszavonása); a vezetői összefoglaló **Újragenerálás**a az októberi hónapra, és a szöveg minőségének átnézése.
2. Éles próbák a még nem ellenőrzött részekre: havi futás valódi forrásokon és Claude-dal, havi e-mail valódi SMTP-vel, posztgenerálás és képtervezés.
3. A pull request átnézése és összevonása (merge) a fő ágba, ha az éles próbák rendben vannak; utána a 3. ütem (e-mailes válaszok feldolgozása) tervezése, ha kell.
