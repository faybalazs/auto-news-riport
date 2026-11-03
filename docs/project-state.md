# Projektállapot

- **Frissítve:** 2026-11-02
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
| Havi futás: Claude-értékelés (Haiku 5.5), vezetői összefoglaló (Sonnet 5.5), mentés; Havi cikkek oldal döntésekkel | megvalósított; ellenőrzött helyettesített Claude-dal (PHPUnit: kérésforma, elutasítás, idempotencia, hiányos válasz, ügyfélhatár) és böngészőből mintaadatokkal; valódi Claude API-val nem ellenőrzött | roadmap 5 |
| Áttekintés valódi adatokkal: időszakválasztó, teendő-widgetek szerepkör szerint, vezetői összefoglaló, KPI-k trenddel, döntések megoszlása, cikkek forrásonként | ellenőrzött: PHPUnit és böngészős teszt; élesben a felhasználó kipróbálta (0.6.0, „Működik”) | roadmap 6 |
| Havi e-mail: generálás (havi futáskor és kézzel), értesítés csak az üzemeltetőnek, szerkesztés, teszt e-mail, jóváhagyás után azonnali egyszeri küldés SMTP-vel; címzettek és stílus ügyfelenként | megvalósított; ellenőrzött PHPUnit-tal (SMTP-protokoll forgatókönyvvel, helyettesített Claude és küldő) és böngészőből helyi teszt-SMTP-szerverrel (a kézbesített levél fejléce, szövege és HTML-je ellenőrizve); valódi SMTP-vel és Claude-dal nem ellenőrzött | roadmap 7 |
| Élő feltöltés (Xperion OAuth Bridge 0.6.0, deploy_pair): Mehet cikkek HU/EN ajánlópárként, Claude-fordítással, óránként és kézzel; kapcsolatpróba; hibakezelés a bővítmény táblája szerint | megvalósított; ellenőrzött PHPUnit-tal (a bővítmény válaszformáit utánzó helyettesítő: idempotencia, duplikátum, képhiba, 3 próba, 401 leállás) és böngészőből helyi hamis végponttal (valódi HTTP, Basic belépés); a kapcsolat az xperion.hu-val élesben ellenőrzött 2026-11-03-án (Kapcsolat ellenőrzése: bővítmény 0.6.0, Polylang, publikálhat, képet tölthet fel; a belépéshez a `wordpress/mu-plugins/xperion-auth-fejlec.php` 1.1.0 kiegészítő kell); a HU/EN alapkategória még nincs beállítva; éles feltöltés még nem futott | roadmap 8 |
| LinkedIn-posztok: Posztszabályok és Posztötletek szerkesztése; generálás indításra (a Havi cikkeken kijelölt cikkekből, vagy ötletből; Claude Opus 5.5) pótlással; a kész poszt magától jóváhagyásra megy; egyedi, Claude által tervezett kép (SVG → PNG); módosítási kérésre automatikus újraírás; előnézet, szerkesztés, előzmények | megvalósított; ellenőrzött PHPUnit-tal (kijelölés, jogosultság, automatikus jóváhagyásra küldés, újraírás, SVG-tisztítás, hibás kép esetén sablon) és böngészőből mintaadatokkal (kijelölés, egyedi kép, PNG-letöltés); valódi Claude-dal nem ellenőrzött | roadmap 9 |
| Források és kulcsszavak oldal; Napló szűrőkkel, CSV-vel, ügyfél-adminisztrátori nézettel; hibaértesítés e-mailben; megőrzés; Ügyfelek: beállítási lépések és ügyfélbeállítások | megvalósított; ellenőrzött PHPUnit-tal (jogosultság, ügyfélhatár, kategóriák, megőrzés, hibahorog) és böngészőből (üzemeltető asztali, ügyfél-adminisztrátor mobil nézetben); élesben nem ellenőrzött | roadmap 10 |
| Áttekintés adatokkal | tervezett (most üres állapot és beállítási teendők) | roadmap 6 |
| Havi e-mail, feltöltés, posztok | tervezett | roadmap 7–9 |

## Meglévő, repón kívüli elemek
| Elem | Állapot | Forrás |
|---|---|---|
| Google Sheet (havi lapok 2408–2609, LinkedIn posztok, LinkedIn szabályok) | kézzel vezetve, használatban | felhasználói közlés |
| Feltöltő MCP-szerver (ChatGPT-vel készült, távoli, OAuth) | használatban | felhasználói közlés, nem vizsgált |
| Xperion OAuth Bridge WordPress-plugin (PHP 7.4) az xperion.hu-n | 0.6.0 átadva (forrás és API-leírás: docs/integracio/); a telepítés és próbája a felhasználóra vár; a 0.6.0 új részei a bővítmény saját jelentése szerint még nem futottak PHP alatt | átadócsomag, 2026-11-02 |
| auris-admin-sablon skill (claude.ai) | v1.6.0 átadva 2026-11-03 (az app elemei bekerültek: futásállapot, folyamatnapló, teendő-csempe, lépéslista, választókártya, javítós hibabanner, tabos részletkártya; új alkalmazás-ellenőrző szkript); a feltöltés a felhasználóra vár | a skill-csomag (zip) |
| xperion-linkedin skill (claude.ai) | használatban, a szabályokat a Sheetből olvassa | felhasználói közlés |

## Kockázatok
- A tárhely MySQL-verziója és a `pdo_mysql` bővítmény még nincs élesben ellenőrizve (a sémát MariaDB 10.11 és MySQL 5.7+ kompatibilisre írtuk).
- Az Apache `.htaccess` 2.4-es szintaxist használ; más webszervernél a `config.php` és a `storage/` védelmét ellenőrizni kell.
- A Claude-értékelés minősége valódi cikkeken még nincs mérve: az első havi futás javaslatait érdemes az ügyfél korábbi döntéseivel összevetni.
- A listaoldalak szerkezete forrásonként eltér: az általános felismerés egyes oldalakon kevés vagy hibás eredményt adhat; ezt a szárazfuttatás forrásonkénti kimutatása mutatja meg, és célzottan javítható.
- Amíg nincs SMTP (7. lépés), a meghívó és a jelszólinket kézzel kell elküldeni; a hibaértesítő e-mail is ekkor jön.

## Blokkolók
- Élő feltöltés: a bővítmény 0.6.0 telepítése és próbája az xperion.hu-n, külön Szerző felhasználó alkalmazásjelszóval, HU/EN alapkategória (README 8.).
- A forráscikkek képeinek átvétele (képjog): Attilával tisztázandó (az átadócsomag nyitott kockázata).

## Terv
- Kattintható prototípus (v3, többügyfeles, a valós posztszabályokkal): [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html), publikálva: https://claude.ai/artifact/NuS6Vvt9oKrgHBjjJrvsxe. Állapot: a felhasználó átnézte, a saját felület mellett döntött.
- Frissített ütemterv: [roadmap.md](roadmap.md).

## Következő lépések
1. Felhasználó: feltöltés (0.10.1); a config.php-ban az ismétlődő `smtp` rész törlése (a Vezérlőpult kiírja); egy poszt képének újrakészítése (**Új kép**) és a minta szerinti minőség ellenőrzése; Ügyfelek → Beállítási lépések végigjárása az Xperionnál; Források átnézése; Napló kipróbálása (Attilával is).
2. Éles próbák a még nem ellenőrzött részekre: havi futás valódi forrásokon és Claude-dal, havi e-mail valódi SMTP-vel, élő feltöltés az xperion.hu-ra, posztgenerálás és képtervezés.
3. A pull request átnézése és összevonása (merge) a fő ágba, ha az éles próbák rendben vannak; utána a 3. ütem (e-mailes válaszok feldolgozása) tervezése, ha kell.
