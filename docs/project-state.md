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
| Adatátvétel ügyfelenkénti Google Sheetből (források, kulcsszavak, havi lapok cikkei, posztok, ötletek, szabályok, beállítások) | megvalósított; ellenőrzött a sandboxban MariaDB-n helyettesített Sheettel (PHPUnit, az ügyfélelválasztással és az ismételhetőséggel együtt); valódi Sheettel nem ellenőrzött | roadmap 3 |
| Cikkgyűjtés (RSS, HubSpot, listaoldal; hónap-, duplikátum- és kizáró szűrés), egyelőre mentés nélkül | megvalósított; ellenőrzött mintaoldalakkal (PHPUnit) és helyi mintaforrásokkal böngészőből; valódi forrásoldalakon nem ellenőrzött | roadmap 4 |
| Értékelés és Havi cikkek | tervezett | roadmap 5 |
| Áttekintés adatokkal | tervezett (most üres állapot és beállítási teendők) | roadmap 6 |
| Havi e-mail, feltöltés, posztok | tervezett | roadmap 7–9 |

## Meglévő, repón kívüli elemek
| Elem | Állapot | Forrás |
|---|---|---|
| Google Sheet (havi lapok 2408–2609, LinkedIn posztok, LinkedIn szabályok) | kézzel vezetve, használatban | felhasználói közlés |
| Feltöltő MCP-szerver (ChatGPT-vel készült, távoli, OAuth) | használatban | felhasználói közlés, nem vizsgált |
| Xperion OAuth Bridge WordPress-plugin (PHP 7.4) az xperion.hu-n | a sajtópublikálás élesben tesztelve | felhasználói közlés, nem vizsgált |
| xperion-linkedin skill (claude.ai) | használatban, a szabályokat a Sheetből olvassa | felhasználói közlés |

## Kockázatok
- A tárhely MySQL-verziója és a `pdo_mysql` bővítmény még nincs élesben ellenőrizve (a sémát MariaDB 10.11 és MySQL 5.7+ kompatibilisre írtuk).
- Az Apache `.htaccess` 2.4-es szintaxist használ; más webszervernél a `config.php` és a `storage/` védelmét ellenőrizni kell.
- A listaoldalak szerkezete forrásonként eltér: az általános felismerés egyes oldalakon kevés vagy hibás eredményt adhat; ezt a szárazfuttatás forrásonkénti kimutatása mutatja meg, és célzottan javítható.
- Amíg nincs SMTP (7. lépés), a meghívó és a jelszólinket kézzel kell elküldeni; a hibaértesítő e-mail is ekkor jön.

## Blokkolók
A [product.md](product.md) 11. pontjának 1–4. kérdése (a PHP-verzió már megvan: 8.2.35): cron a sunserveren, a feltöltő program adatai (URL, eszközök, OAuth), a fordítás felelőse, SMTP-adatok.

## Terv
- Kattintható prototípus (v3, többügyfeles, a valós posztszabályokkal): [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html), publikálva: https://claude.ai/artifact/NuS6Vvt9oKrgHBjjJrvsxe. Állapot: a felhasználó átnézte, a saját felület mellett döntött.
- Frissített ütemterv: [roadmap.md](roadmap.md).

## Következő lépések
1. Felhasználó: feltöltés (0.4.0); Ügyfelek → Adatátvétel (szárazon, majd élesben); Vezérlőpult → Cikkgyűjtés (2026. október, szárazon); az eredmény (forrásonkénti tábla) visszajelzése.
2. A szárazfuttatás alapján a nem működő források javítása.
3. 5. lépés: Claude-értékelés (Desc, Javaslat, Indoklás), a havi lista mentése, Havi cikkek oldal döntésekkel; ehhez Claude API-kulcs kell.
