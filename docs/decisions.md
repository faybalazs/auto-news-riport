# Döntések

A dátum a rögzítés napja; a döntések a felhasználóval folytatott claude.ai-beszélgetésben születtek.

| Dátum | Döntés | Indok | Forrás | Állapot |
|---|---|---|---|---|
| 2026-11-02 | PHP-alkalmazás a sunserver.hu-n, cronnal (nem Google Apps Script) | a felülethez úgyis kell szerver; illeszkedik a tervezett AI webszerkesztő platformhoz | felhasználói döntés | aktív |
| 2026-11-02 | A Google Sheet a vezérlőpult és az adatforrás; az ügyfél a megosztott táblában dönt | a döntések automatikusan kiolvashatók | felhasználói döntés | felülírva (lásd lent: saját felület) |
| 2026-11-02 | A „Mehet” cikkek azonnal élesben kerülnek fel | felhasználói döntés | felhasználói döntés | aktív |
| 2026-11-02 | A havi e-mail csak külön jóváhagyás után megy ki, közvetlenül, piszkozat nélkül | az ügyfél felé az üzemeltető ellenőrzése marad | felhasználói döntés | aktív |
| 2026-11-02 | A LinkedIn-közzététel kézi (Emi, a LinkedIn saját ütemezőjével) | automatikus közzétételhez LinkedIn API-jóváhagyás kell | javaslat, elfogadva | aktív |
| 2026-11-02 | A posztszabályok egyetlen forrása a Sheet „LinkedIn szabályok” lapja | az ügyfél is szerkesztheti, kódolás nélkül | korábbi döntés | felülírva: a szabályok az alkalmazásban, a Posztszabályok oldalon élnek |
| 2026-11-02 | Az admin felület az Auris admin sablonra épül (belépés, Vezérlőpult, Napló), külön CSS/JS fájlban | a felhasználó kérése; egységes Auris-felületek | felhasználói döntés | aktív |
| 2026-11-02 | Admin belépés egyetlen kulccsal, munkamenet-sütivel (nem `?k=` az URL-ben); a cron külön kulcsot kap | a kulcs ne kerüljön böngészőelőzménybe; a két kulcs külön cserélhető | javaslat (1. lépés) | aktív |
| 2026-11-02 | Helyi napló (`storage/naplo.jsonl`) minden futásról, a Napló lapra csak éles, író futás kerül | a szárazfuttatás nem írhat a táblába; Sheets-hiba esetén is látszik a napló | javaslat (1. lépés) | aktív |
| 2026-11-02 | Alapértelmezett modellek: értékelés `claude-haiku-5-5`, poszt `claude-sonnet-5-5` (a Beállítások lapon felülírható) | költséghatékonyság: tömeges értékelés az olcsóbb modellel | javaslat, elfogadva | aktív |
| 2026-11-02 | **Saját felület és adatbázis a Google Sheet helyett**; a Sheetből csak egyszeri adatátvétel lesz (korábbi havi lapok linkjei a duplikátumszűréshez, források, kulcsszavak, posztok) | egy helyen dolgozik minden szereplő; jogosultságok; több ügyfél kiszolgálása | felhasználói döntés, a prototípus alapján (docs/terv/ugyfelfelulet-prototipus.html) | aktív |
| 2026-11-02 | Többügyfeles (multi-tenant) felépítés: ügyfelenként saját források, kulcsszavak, szabályok, felhasználók, weboldal-kapcsolat; ügyfélváltó a felső sávban | több ügyfél várható | felhasználói döntés | aktív |
| 2026-11-02 | Szerepkörök: Üzemeltető (minden ügyfél), Ügyfél-adminisztrátor, Tartalomkezelő, Olvasó; meghívás e-mailben | a prototípus jogosultságmátrixa szerint | felhasználói döntés | aktív |
| 2026-11-02 | Cikkdöntések: Mehet / LinkedIn poszt / Nem kell; a „próbaidőszakos” források neve a felületen „Új források” | a felhasználó kérése | felhasználói döntés | aktív |
| 2026-11-02 | A posztszabályok kiinduló tartalma a felhasználó által megadott 48 szabály (docs/adat/linkedin-szabalyok.tsv) | a Sheet „LinkedIn szabályok” lapjának aktuális állapota | felhasználói közlés | aktív |
| 2026-11-02 | Adatbázis: MySQL (a tárhely saját MySQL-e), automatikus migrációval | a felhasználó választása; a tárhely mentése kezeli | felhasználói döntés | aktív |
| 2026-11-02 | Belépés e-mail + jelszóval; az első üzemeltetői fiókot a `admin_key` beállítókulccsal lehet létrehozni; meghívó és jelszólink kézzel küldhető, amíg nincs SMTP | terminál nélküli telepítés; az SMTP még nincs meg | javaslat (2. lépés terve), jóváhagyva | aktív |
| 2026-11-02 | Ügyfelenként saját Google Sheet (az Ügyfelek oldalon megadva); az adatátvétel többször futtatható, upsert, nem duplikál; a posztszabályokat csak üres szabálylistánál tölti be | felhasználói kérés; több ügyfél; az alkalmazásban végzett szerkesztés ne vesszen el | felhasználói kérés, a megvalósítás javaslat | aktív |
| 2026-11-02 | Claude API közvetlen HTTP-vel (nem a PHP SDK-val) | a tárhelyen nincs Composer; az éles kód függőség nélküli (product.md 9.) | javaslat (5. lépés), a terv része | aktív |
| 2026-11-02 | Havi futás élesben csak az „Aktív” ügyfelekre; egy hónap meglévő, ha van összefoglalója vagy cikke | ne fusson félig beállított ügyfélre; az átvett hónapok ne keveredjenek új gyűjtéssel | javaslat (5. lépés) | aktív |
| 2026-11-02 | Sonnet 5.5-ös kéréseknél szerveroldali tartalék modell (`fallbacks: "default"`) | ha a biztonsági szűrő elutasítja, a kérés ne vesszen el | javaslat (5. lépés) | aktív |
