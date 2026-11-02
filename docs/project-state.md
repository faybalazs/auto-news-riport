# Projektállapot

- **Frissítve:** 2026-11-02
- **Forrás:** felhasználói közlés (claude.ai chat) és a jóváhagyott specifikáció ([product.md](product.md)). Kód még nincs a repóban.

## Cél
Az eddig kézzel, chatben végzett havi sajtófigyelés és LinkedIn-posztkészítés automatizálása PHP-ban, a meglévő Google Sheetre építve.

## Funkciók
| Funkció | Állapot | Forrás |
|---|---|---|
| Havi gyűjtés és havi lap | tervezett | product.md 6.1 |
| Havi e-mail jóváhagyással | tervezett | product.md 6.1, 6.4 |
| Óránkénti döntésfigyelés, élő feltöltés két nyelven | tervezett | product.md 6.2 |
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

## Blokkolók
A [product.md](product.md) 11. pontjának 1–4. kérdése: PHP-verzió és cron a sunserveren, a feltöltő program adatai (URL, eszközök, OAuth), a fordítás felelőse, SMTP-adatok.

## Következő lépések
1. A blokkoló kérdések megválaszolása (felhasználó).
2. Rövid terv az 1. ütemre, jóváhagyással.
3. Sheets-kapcsolat és a szárazfuttatásos admin oldal.
