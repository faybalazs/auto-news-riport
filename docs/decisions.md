# Döntések

A dátum a rögzítés napja; a döntések a felhasználóval folytatott claude.ai-beszélgetésben születtek.

| Dátum | Döntés | Indok | Forrás | Állapot |
|---|---|---|---|---|
| 2026-11-02 | PHP-alkalmazás a sunserver.hu-n, cronnal (nem Google Apps Script) | a felülethez úgyis kell szerver; illeszkedik a tervezett AI webszerkesztő platformhoz | felhasználói döntés | aktív |
| 2026-11-02 | A Google Sheet a vezérlőpult és az adatforrás; az ügyfél a megosztott táblában dönt | a döntések automatikusan kiolvashatók | felhasználói döntés | aktív |
| 2026-11-02 | A „Mehet” cikkek azonnal élesben kerülnek fel | felhasználói döntés | felhasználói döntés | aktív |
| 2026-11-02 | A havi e-mail csak külön jóváhagyás után megy ki, közvetlenül, piszkozat nélkül | az ügyfél felé az üzemeltető ellenőrzése marad | felhasználói döntés | aktív |
| 2026-11-02 | A LinkedIn-közzététel kézi (Emi, a LinkedIn saját ütemezőjével) | automatikus közzétételhez LinkedIn API-jóváhagyás kell | javaslat, elfogadva | aktív |
| 2026-11-02 | A posztszabályok egyetlen forrása a Sheet „LinkedIn szabályok” lapja | az ügyfél is szerkesztheti, kódolás nélkül | korábbi döntés | aktív |
| 2026-11-02 | Az admin felület az Auris admin sablonra épül (belépés, Vezérlőpult, Napló), külön CSS/JS fájlban | a felhasználó kérése; egységes Auris-felületek | felhasználói döntés | aktív |
| 2026-11-02 | Admin belépés egyetlen kulccsal, munkamenet-sütivel (nem `?k=` az URL-ben); a cron külön kulcsot kap | a kulcs ne kerüljön böngészőelőzménybe; a két kulcs külön cserélhető | javaslat (1. lépés) | aktív |
| 2026-11-02 | Helyi napló (`storage/naplo.jsonl`) minden futásról, a Napló lapra csak éles, író futás kerül | a szárazfuttatás nem írhat a táblába; Sheets-hiba esetén is látszik a napló | javaslat (1. lépés) | aktív |
| 2026-11-02 | Alapértelmezett modellek: értékelés `claude-haiku-5-5`, poszt `claude-sonnet-5-5` (a Beállítások lapon felülírható) | költséghatékonyság: tömeges értékelés az olcsóbb modellel | javaslat, elfogadva | aktív |
