# Terminológia

A felület és a dokumentáció egységes szóhasználata (0.13.3). A Google Sheet korszakából átvett fogalmak a táblázat végén.

| Kifejezés | Jelentés | Használat |
|---|---|---|
| Havi lista | egy hónap begyűjtött, értékelt cikkei a Havi cikkek oldalon | |
| Javaslat | a Claude értékelése: `Erősen javasolt` / `Javasolt` / `Nem javasolt` / `Nem javasolt (már fent)` | a rendszer javaslata, **nem azonos a döntéssel** |
| Ügyfél döntése | `Mehet` / `LinkedIn poszt` / `Nem kell` (vagy nincs döntés) | pontosan ezekkel az értékekkel |
| Mehet | a cikk feltöltendő a weboldalra, magyarul és angolul | a Mehet önmagában még nem Publikálva |
| Publikálva | a cikk magyar és angol ajánlója kint van a weboldalon (régebben „Feltöltve”, „Kint”) | |
| Szövegek elkészítése / szárazfuttatás | futás írás, feltöltés és e-mail nélkül (`dry=1`); feltöltésnél a HU/EN szöveg elkészül, de a weboldalra nem kerül semmi | angolul: dry run |
| Feltöltés visszavonása | a cikk újra „Döntésre vár”; a weboldalról **nem** töröl | |
| Vezetői összefoglaló | a hónap rövid bevezetője és csoportjai: Újdonságok, Tények, számok, Trendek | cikkstatisztika nélkül |
| Poszt állapotai | Tervezet → Ügyfélnél (jóváhagyásra) → Jóváhagyva → Megjelent | a Jóváhagyva nem azonos a Megjelenttel |
| Poszt ID | `LI-YYMM-NN` (pl. `LI-2610-01`) | |
| Posztötlet ID | `OT-YYMM-NN` | |
| Posztszabályok | a posztgenerálás egyetlen szabályforrása, az alkalmazásban, ügyfelenként | régebben „LinkedIn szabályok” lap, „skill set” |
| Zárt / kibontott nézet | a poszt a „…több” előtt levágva / teljes egészében | |
| Próbaidőszakos (új) forrás | „Új forrás” jelölésű forrás; cikkei a Havi cikkekben külön jelölést kapnak | |
| Szerepkörök | Üzemeltető (Auris Dynamics Zrt.), Ügyfél-adminisztrátor, Tartalomkezelő, Olvasó | a felületen és a kézikönyvben így |
| Alkalmazásjelszó | a WordPress-profilban külön létrehozott, 24 karakteres jelszó a gépi belépéshez | **nem azonos** a WordPress-belépés jelszavával |
| Belépési kiegészítő | `wordpress/mu-plugins/xperion-auth-fejlec.php`; a WordPressbe kerül, a kiszűrt belépési fejlécet pótolja | |
| Üzemeltető | Balázs (Auris Dynamics Zrt.) | |
| Ügyfél | Xperion; ügyfél-adminisztrátor: Berényi Attila | |
| LinkedIn-kezelő | Emi (Xperion), tartalomkezelő szerepkörben | |

## A Google Sheet korszakából (csak az adatátvételhez)

| Kifejezés | Jelentés |
|---|---|
| Havi lap | a Sheet `YYMM` nevű munkalapja egy hónap cikkeivel (pl. `2609`) |
| Feltöltve | régi lapokon G = „Igen”, újakon J = „Feltöltve”; az alkalmazásban: Publikálva |
| LinkedIn szabályok lap | a posztszabályok forrása az átvétel előtt |
