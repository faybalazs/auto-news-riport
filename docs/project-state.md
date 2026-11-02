# Projektállapot

- **Frissítve:** 2026-11-02
- **Forrás:** felhasználói közlés (claude.ai chat), a jóváhagyott specifikáció ([product.md](product.md)) és a repó kódja.

## Cél
Az eddig kézzel, chatben végzett havi sajtófigyelés és LinkedIn-posztkészítés automatizálása PHP-ban, a meglévő Google Sheetre építve.

## Funkciók
| Funkció | Állapot | Forrás |
|---|---|---|
| Alap: konfiguráció, zárolás, helyi napló, szárazfuttatás, cron-belépési pont | megvalósított, nem ellenőrzött élesben (PHPUnit + helyi böngészős teszt zöld) | 1. ütem, 1. lépés |
| Sheets-kapcsolat (szolgáltatásfiók, JWT OpenSSL-lel; olvasás, írás, új lap) | ellenőrzött élesben: olvasás („Kapcsolat ellenőrzése”) és írás, új lap („Munkalapok előkészítése”), a felhasználó futtatta, 2026-11-02 | product.md 5., 9. |
| Admin felület (kulcsos belépés, Vezérlőpult, Napló) | megvalósított, ellenőrzött helyi böngészős füstteszttel (1366 és 390 px, sötét mód) | product.md 9. |
| Munkalapok előkészítése (Beállítások, Napló, Posztötletek) | ellenőrzött élesben (a lapok létrejöttek, felhasználói visszajelzés, 2026-11-02) | product.md 4.2 |
| Havi gyűjtés és havi lap | részben: célhónap, idempotencia, források/kulcsszavak beolvasása; a gyűjtés és a lap létrehozása hátravan | product.md 6.1 |
| Havi e-mail jóváhagyással | tervezett | product.md 6.1, 6.4 |
| Óránkénti döntésfigyelés, élő feltöltés két nyelven | tervezett (most csak a Beállításokat olvassa) | product.md 6.2 |
| Posztgenerálás cikkekből és ötletekből | tervezett | product.md 6.3 |
| Poszt-előnézeti felület képpel és mockuppal | tervezett | product.md 6.5 |
| E-mailes válaszok feldolgozása | tervezett (3. ütem) | product.md 6.6 |

## Meglévő, repón kívüli elemek
| Elem | Állapot | Forrás |
|---|---|---|
| Google Sheet (havi lapok 2408–2609, LinkedIn posztok, LinkedIn szabályok) | kézzel vezetve, használatban | felhasználói közlés |
| Feltöltő MCP-szerver (ChatGPT-vel készült, távoli, OAuth) | használatban | felhasználói közlés, nem vizsgált |
| Xperion OAuth Bridge WordPress-plugin (PHP 7.4) az xperion.hu-n | a sajtópublikálás élesben tesztelve | felhasználói közlés, nem vizsgált |
| xperion-linkedin skill (claude.ai) | használatban, a szabályokat a Sheetből olvassa | felhasználói közlés |

## Kockázatok
- A sunserver PHP-verziója 8.2.35 (a Vezérlőpultról, 2026-11-02): megfelel.
- Az Apache `.htaccess` 2.4-es szintaxist használ; más webszervernél a `config.php` és a `storage/` védelmét ellenőrizni kell.
- A hibaértesítő e-mail még nincs kész (4. lépés): addig a hibák csak a Napló lapon és az admin Naplóban látszanak.

## Blokkolók
A [product.md](product.md) 11. pontjának 1–4. kérdése (a PHP-verzió már megvan: 8.2.35): cron a sunserveren, a feltöltő program adatai (URL, eszközök, OAuth), a fordítás felelőse, SMTP-adatok.

## Következő lépések
1. Döntés: marad-e a Google Sheet az ügyfél felülete (lásd a beszélgetést), és a 2. lépés tervének jóváhagyása.
2. 1. ütem 2. lépés: gyűjtés RSS-ből és listaoldalakról, dátum-, duplikátum- és kizáró szűrés (szárazon kipróbálható).
3. 1. ütem 3. lépés: Claude-értékelés, a havi lap létrehozása a 2609-es szerkezettel, összefoglaló.
