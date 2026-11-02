# Ütemterv

Frissítve: 2026-11-02, a saját felületre és adatbázisra váltás után (lásd [decisions.md](decisions.md)). Minden lépés egy kis pull request, szárazon kipróbálható állapotban. A felület mintája: [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html).

| # | Lépés | Állapot | Tartalom |
|---|---|---|---|
| 1 | Alap | kész, élesben ellenőrzött | konfiguráció, zárolás, napló, cron, admin felület, Sheets-kapcsolat (az adatátvételhez marad) |
| 2 | Adatbázis, ügyfelek, felhasználók | kész, élesben nem ellenőrzött | adatbázis és migrációk; ügyfél (tenant); belépés e-mail + jelszó; szerepkörök; meghívás (SMTP nélkül másolható meghívólinkkel is); a felület kerete a prototípus szerint |
| 3 | Adatátvétel a Sheetből | kész, valódi Sheettel nem ellenőrzött | ügyfelenkénti Sheet; ismételhető import: források, kulcsszavak, korábbi havi lapok linkjei (duplikátumszűrés), feltöltöttség, posztok; szabályok a megadott listából |
| 4 | Cikkgyűjtés | kész (szárazon), valódi forrásokon nem ellenőrzött | RSS és listaoldalak, dátum-, duplikátum- és kizáró szűrés; szárazfuttatás forrásonkénti kimutatással |
| 5 | Értékelés és Havi cikkek | kész, valódi Claude API-val nem ellenőrzött | Claude-értékelés (Desc, Javaslat, Indoklás), vezetői összefoglaló; Havi cikkek oldal döntésekkel (Mehet / LinkedIn poszt / Nem kell) |
| 6 | Áttekintés | megvalósított, nem ellenőrzött | időszakválasztó, teendő-widgetek, összefoglaló, KPI-k, megoszlás, források |
| 7 | Havi e-mail | megvalósított, nem ellenőrzött | generálás hónap szerint, szerkesztés, teszt e-mail, jóváhagyás után azonnali küldés SMTP-vel |
| 8 | Élő feltöltés | megvalósított, nem ellenőrzött | óránkénti futás: Mehet cikkek feltöltése két nyelven (OAuth Bridge vagy MCP), újrapróbálás |
| 9 | Posztok | tervezett | ötletek, generálás cikkekből és ötletekből a szabályok szerint, szerkesztés, jóváhagyás, előnézet és kép |
| 10 | Napló, ügyfelek oldal | tervezett | felhasználói műveletek naplója, új ügyfél beállítási lépései |
| – | E-mailes válaszok feldolgozása | később | az eredeti 3. ütem; a saját felület miatt valószínűleg nem kell |
| – | Automatikus LinkedIn-közzététel | most nem cél | LinkedIn API-jóváhagyás kell |

**Közös követelmények:** PHP 8.2 (a szerveren ellenőrizve), függőség nélküli éles kód, titkok a repón kívül, szárazfuttatás minden futásra, telepítés terminál nélkül, ügyfelenként elválasztott adatok (a szerver a belépett felhasználó tagságából dönti el, melyik ügyfél adatához fér hozzá).
