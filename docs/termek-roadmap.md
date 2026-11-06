# Termék-ütemterv: a Sajtófigyelésből termék

Készült: 2026-11-03, a 0.13.3 állapot alapján. Javaslat, nem jóváhagyott terv: a döntést igénylő pontok a végén. A szállítási ütemek (1–11. lépés) a [roadmap.md](roadmap.md)-ben vannak; ez a dokumentum az utána következő, termékesítési szakaszokat írja le.

## Cél
Egy olyan szolgáltatás, amelyet egy B2B cég (első körben magyar KKV, iparági szereplők) **saját maga** be tud állítani és használni: havonta figyeli a szakmai sajtót, a Claude-dal kiválogatja a releváns cikkeket, a döntések után magyarul és angolul kiteszi őket a cég weboldalára, LinkedIn-posztot és vezetői összefoglalót készít. Az Auris Dynamics Zrt. üzemelteti, előfizetéssel.

## Honnan indulunk

**Ami termékszintű már most:**
- Több ügyfél egy telepítésben, elválasztott adatokkal; szerepkörök (üzemeltető, ügyfél-adminisztrátor, tartalomkezelő, olvasó); műveleti napló.
- A teljes havi folyamat: gyűjtés → Claude-értékelés → döntés → feltöltés a weboldalra (HU/EN) → poszt képpel → jóváhagyás → vezetői összefoglaló → havi e-mail.
- Diagnosztika (SMTP, WordPress), futásállapot, érthető hibaüzenetek; felhasználói kézikönyv; automatikus telepítés GitHub Actionsszel; 110 automatikus teszt.

**Ami még egyetlen ügyfélre (az Xperionra) van szabva:**
- Az integrációs titkok (WordPress-belépés, SMTP, Claude-kulcs) a `config.php`-ban vannak, ügyfelenként kézzel → új ügyfelet csak az üzemeltető tud bekötni.
- A weboldal-feltöltés az Xperion saját WordPress-bővítményére (OAuth Bridge, Polylang-pár) épül; más weboldal nem köthető be.
- A posztkép arculata (színek, „xperion.hu” lábléc, „Xperion-szemszög” mező) és a Sheet-import Xperion-specifikus.
- Egyetlen globális futászár: ha egy ügyfél futása tart, a többi vár („Egy másik futás folyamatban van”). Néhány ügyfél felett ez szűk keresztmetszet.
- A meghívó és a jelszólink nem megy ki e-mailben; a LinkedIn-közzététel kézi.
- Megosztott tárhely, egyetlen éles környezet, tesztkörnyezet nincs; mentés és hibafigyelés a tárhelyé.

## Szakaszok

Becsült ráfordítás: Claude Code-munkával, a felhasználó jóváhagyásaival és éles próbáival együtt. Minden szakasz kis, önállóan kiadható pull requestekből áll, szárazfuttatással és tesztekkel, ahogy eddig.

### 0. Xperion éles stabilizálás · 1–2 hét
**Cél:** az egy ügyfél hónapokon át gond nélkül fusson; ez a referencia.
- A még nem igazolt éles részek végigpróbálása: havi e-mail az ügyfélnek, feltöltéskártya és visszavonás az ügyféllel, újragenerált összefoglaló.
- Tesztkörnyezet (aldomain, külön adatbázis) a telepítőben választható célként.
- Napi adatbázis-mentés és visszaállítási próba; uptime- és hibafigyelés (e-mail, ha a havi vagy az óránkénti futás nem fut le).
- A böngészős végponttól végpontig tesztek (most a munkamappában) a repóba és a CI-be.
- A képjog tisztázása az ügyféllel (nyitott blokkoló).

**Kész, ha:** két egymást követő hónap emberi beavatkozás nélkül lefut, és az ügyfél elfogadja.

### 1. Többügyfeles alap · 2–3 hét
**Cél:** második ügyfél bekötése kód- és `config.php`-módosítás nélkül.
- Ügyfelenkénti integrációs beállítások a felületen (weboldal, WordPress-belépés, e-mail feladó), az adatbázisban **titkosítva**; a `config.php`-ban csak a rendszer szintű kulcsok maradnak.
- Futászár és háttérfeladat-sor **ügyfelenként**: a futások párhuzamosan, újrapróbálással, megszakítva is folytathatóan mennek (a cron dolgozza fel a sort).
- Meghívó, jelszó-visszaállítás és értesítések e-mailben.
- Az Xperion-specifikus részek paraméterezése: posztkép-arculat ügyfelenként (logó, színek, lábléc), a „szemszög” mező általános neve, a Sheet-import opcionális modul.

**Kész, ha:** egy tesztügyfél a felületről, üzemeltetői segítséggel 1 óra alatt beállítható, és a két ügyfél havi futása egyszerre megy.

### 2. Önkiszolgáló bevezetés · 2 hét
**Cél:** az ügyfél maga indul el.
- Bevezető varázsló: cégprofil → ajánlott források és kulcsszavak (a Claude javasolja a profil alapján, próbagyűjtéssel ellenőrizve) → posztszabály-sablon iparág szerint → első próbahónap szárazon.
- Próbaidőszak (pl. 30 nap, 1 hónap feldolgozás) és a lejárat kezelése.
- Kézikönyv-bővítés: ügyfél-adminisztrátori beállítások, üzemeltetői kézikönyv; súgólinkek a felületen az adott fejezethez.

**Kész, ha:** egy új ügyfél üzemeltető nélkül eljut az első kész havi listáig.

### 3. Integrációk · 3–4 hét
**Cél:** ne csak az Xperion-féle weboldalra lehessen dolgozni.
- **Általános WordPress-csatoló:** a szabványos WordPress REST-tel (bejegyzés, kiemelt kép, kategória), Polylang/WPML esetén nyelvpárral; az OAuth Bridge és a belépési kiegészítő telepíthető csomagként, leírással.
- **LinkedIn:** közvetlen közzététel és ütemezés a céges oldalra (a LinkedIn Community Management API jóváhagyása kell), vagy köztes megoldásként ütemező-integráció (pl. Buffer).
- Havi riport letöltése PDF-ben az ügyfél vezetőinek; értesítések Teams/Slack-be.
- Később, igény szerint: más CMS (Webflow, HubSpot).

**Kész, ha:** egy nem Xperion-féle WordPress-oldal 15 perc alatt beköthető, és a jóváhagyott poszt a felületről időzíthető.

### 4. Üzleti réteg · 2–3 hét
**Cél:** el lehessen adni és számlázni.
- Csomagok és korlátok (források száma, feldolgozott cikk, poszt/hó, felhasználók), a Claude-költség ügyfelenkénti mérése (a tokenhasználat már naplózott) és havi kimutatása.
- Előfizetés és számlázás: online fizetés (pl. Stripe vagy Barion) és számla (Számlázz.hu).
- Üzemeltetői konzol: minden ügyfél állapota egy nézetben (utolsó futás, hibák, költség, lejárat).
- Biztonság és jog: kétlépcsős belépés, belépési korlát (részben megvan), ÁSZF, adatkezelési tájékoztató, adatfeldolgozói szerződés (DPA), adatmegőrzés és -törlés ügyfélkilépéskor.

**Kész, ha:** egy ügyfél próbaidő után magától előfizet, a számla automatikusan elkészül, és a havi Claude-költség ügyfelenként látszik.

### 5. Növekedés · folyamatos
- Tanuló relevancia: az ügyfél korábbi döntései beépülnek az értékelésbe (kevesebb „Nem kell”).
- Versenytárs- és témafigyelés, negyedéves trendriport, hangulatelemzés.
- Angol felület (exportpiac), saját arculatú (white label) változat ügynökségeknek.
- Teljesítmény: ha az ügyfélszám vagy a futásidő megkívánja, költözés VPS-re vagy menedzselt PHP-tárhelyre (külön döntés, lásd lent).

## Sorrend és ráfordítás összesen
0 → 1 → 2 a minimum ahhoz, hogy egy második fizető ügyfél bevezethető legyen: **kb. 5–7 hét**. A 3–4. szakasszal együtt, az önálló értékesítéshez: **kb. 10–14 hét**. A legnagyobb kockázatú tétel a LinkedIn API-jóváhagyás (hetekig tarthat, nem tőlünk függ), ezért érdemes korán beadni.

## Mérőszámok
- Bevezetési idő új ügyfélnél (cél: 1 óra alatt, később önkiszolgálva).
- Havi futás sikeressége beavatkozás nélkül (cél: 95% felett).
- „Mehet” + „LinkedIn poszt” döntések aránya a javasoltakon belül (a relevancia minősége).
- Claude-költség ügyfelenként és hónaponként, a csomagárhoz mérve.
- Elkészült és megjelent posztok száma havonta.

## Döntést igénylő pontok (Balázs)
1. **Célpiac és árazás:** kinek szól elsőként (iparág, cégméret), milyen csomagok és árak.
2. **Termék neve és webcíme:** a „Sajtófigyelés” munkanév marad-e.
3. **Tárhely:** a sunserver.hu marad-e a termékhez is, és mikor költözzünk (VPS vagy menedzselt tárhely havi díjjal), ha az ügyfélszám nő.
4. **LinkedIn:** beadjuk-e az API-hozzáférési kérelmet az Auris Dynamics Zrt. nevében, vagy előbb az ütemező-integráció.
5. **Jog:** a forráscikkek képeinek és ajánlóinak felhasználása (képjog), ÁSZF és adatkezelés jogi átnézése.
6. **Fizetés:** melyik fizetési szolgáltató és számlázó.

**Javasolt első lépés:** a 0. szakasz befejezése és párhuzamosan az 1–2. döntés, mert a többi szakasz ezekre épül.
