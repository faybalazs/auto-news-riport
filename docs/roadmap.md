# Ütemterv

A termékké alakítás javasolt szakaszai (többügyfeles alap, önkiszolgáló bevezetés, integrációk, üzleti réteg): [termek-roadmap.md](termek-roadmap.md).

Frissítve: 2026-11-05 (0.19.1); a saját felületre és adatbázisra váltás 2026-11-02-án történt (lásd [decisions.md](decisions.md)). Minden lépés egy kis pull request, szárazon kipróbálható állapotban. A felület mintája: [terv/ugyfelfelulet-prototipus.html](terv/ugyfelfelulet-prototipus.html).

| # | Lépés | Állapot | Tartalom |
|---|---|---|---|
| 1 | Alap | kész, élesben ellenőrzött | konfiguráció, zárolás, napló, cron, admin felület, Sheets-kapcsolat (az adatátvételhez marad) |
| 2 | Adatbázis, ügyfelek, felhasználók | kész, élesben nem ellenőrzött | adatbázis és migrációk; ügyfél (tenant); belépés e-mail + jelszó; szerepkörök; meghívás (SMTP nélkül másolható meghívólinkkel is); a felület kerete a prototípus szerint |
| 3 | Adatátvétel a Sheetből | kész, élesben ellenőrzött (a felhasználó, 0.5.2) | ügyfelenkénti Sheet; ismételhető import: források, kulcsszavak, korábbi havi lapok linkjei (duplikátumszűrés), feltöltöttség, posztok; szabályok a megadott listából |
| 4 | Cikkgyűjtés | kész (szárazon), valódi forrásokon nem ellenőrzött | RSS és listaoldalak, dátum-, duplikátum- és kizáró szűrés; szárazfuttatás forrásonkénti kimutatással |
| 5 | Értékelés és Havi cikkek | kész, élesben lefutott (2026. október) | Claude-értékelés (Desc, Javaslat, Indoklás), vezetői összefoglaló; Havi cikkek oldal döntésekkel (Mehet / LinkedIn poszt / Nem kell) |
| 6 | Áttekintés | kész, élesben ellenőrzött (a felhasználó, 0.6.0; a 0.13.1 javítása után is) | időszakválasztó, teendő-widgetek, összefoglaló, KPI-k, megoszlás, források |
| 7 | Havi e-mail | megvalósított; az SMTP-kapcsolat élesben működik, ügyfélnek még nem ment levél | generálás hónap szerint, szerkesztés, teszt e-mail, jóváhagyás után azonnali küldés SMTP-vel |
| 8 | Élő feltöltés | kész, élesben ellenőrzött (teszt-feltöltés az xperion.hu-ra, 2026-11-03) | óránkénti futás és kézi indítás: Mehet cikkek feltöltése két nyelven az OAuth Bridge `deploy_pair` végpontján, újrapróbálás; belépés a `wordpress/mu-plugins/xperion-auth-fejlec.php` kiegészítővel |
| 9 | Posztok | kész, élesben használatban | ötletek, generálás cikkekből és ötletekből a szabályok szerint, szerkesztés, jóváhagyás, előnézet és kép |
| 10 | Napló, ügyfelek oldal | megvalósított, nem ellenőrzött | felhasználói műveletek naplója, új ügyfél beállítási lépései |
| 11 | Ügyféloldali bővítések (0.10–0.13) | megvalósított; részben élesben kipróbált | futásállapot, SMTP- és WordPress-diagnosztika; feltöltés a Havi cikkekről az ügyfélnek is, Publikálva állapot, visszavonás, törlés; tagolt vezetői összefoglaló újragenerálással; lábléc; felhasználói kézikönyv a menüből |
| 12 | Megújult felület (v2) | megvalósított (0.19.0–0.19.1), élesben telepítve | Figma Make terv pixelpontosan, sötét téma, kompakt mód, globális kereső, értesítés-csengő, kézikönyv 1.3 |
| – | E-mailes válaszok feldolgozása | később | az eredeti 3. ütem; a saját felület miatt valószínűleg nem kell |
| – | Automatikus LinkedIn-közzététel | most nem cél | LinkedIn API-jóváhagyás kell |

**Közös követelmények:** PHP 8.2 (a szerveren ellenőrizve), függőség nélküli éles kód, titkok a repón kívül, szárazfuttatás minden futásra, telepítés terminál nélkül, ügyfelenként elválasztott adatok (a szerver a belépett felhasználó tagságából dönti el, melyik ügyfél adatához fér hozzá).
