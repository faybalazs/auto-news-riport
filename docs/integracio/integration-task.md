# Feladat: cikkfigyelő → xperion.hu automatikus publikálás

Állapot: tervezett. Előfeltétel: `docs/architecture.md` elkészült, és Balázs jóváhagyta az alábbiak megvalósítási tervét.

## Cél
Az ügyfél által elfogadott cikkek a havi lapról emberi beavatkozás nélkül kikerüljenek az xperion.hu-ra magyar–angol ajánlópárként, kiemelt képpel és forráslinkkel. Az eredmény visszaíródik a lapra. Az ügyfélnek szóló e-mail továbbra is csak Balázs jóváhagyása után megy ki (meglévő viselkedés, nem változik).

## Folyamat soronként
1. **Kiválasztás:** csak az ügyfél által elfogadottként jelölt, még nem publikált sorok. Az „elfogadott” jelölés pontos módját a meglévő kódból és lapból kell kiolvasni; ha nem egyértelmű, kérdezz.
2. **Kulcs és URL:** a forrás-URL normalizálása (kisbetűs host; `utm_*`, `fbclid`, `gclid`, `#…` törölve), kulcs = `p-` + SHA-256 első 20 hexa karaktere. A normalizált URL megy `source_url`-ként.
3. **Állapot:** `xperion_press_get_pair(key)`. Ha `published`, a sor kész: csak visszaírás.
4. **Forrásszöveg:** a cikk letöltése és a főszöveg kinyerése (ha a cikkfigyelő ezt már tudja, azt használd).
5. **Fordítás és ajánló:** Claude API, a `docs/wp-bridge-api.md` rendszerpromptjával. Csak JSON-választ fogadj el; ellenőrizd: négy nem üres mező, cím ≤ 500 bájt, ajánló ≤ 10 000 bájt. Hibánál a sor `hiba`, nincs WordPress-hívás.
6. **Telepítés:** `xperion_press_deploy_pair` (kulcs, URL, dátum, hu, en). Kép: a bővítmény a forrás `og:image`-ét tölti be; ha a cikkfigyelő már kinyerte a kép URL-jét, `featured_image_url`-ként küldheted.
7. **Visszaírás a lapra:** állapot, WP HU/EN ID, HU/EN URL, időpont, hibaüzenet (titok nélkül).

## Laposzlopok (a meglévők mögé, az ügyfélnek küldött kivonatot nem érinthetik)
Kulcs · WP állapot (`kint` / `duplikátum` / `hiba` / `zárolt` / `részben kint`) · HU cím · HU ajánló · EN cím · EN ajánló · WP HU ID · WP EN ID · HU URL · EN URL · Publikálva (időpont) · Utolsó hiba

## Konfiguráció (környezeti változók)
`XPERION_WP_BASE_URL`, `XPERION_WP_USER`, `XPERION_WP_APP_PASSWORD`, `ANTHROPIC_API_KEY`, `XPERION_CLAUDE_MODEL` (alap: `claude-sonnet-5-5`; olcsóbb: `claude-haiku-5-5`), `XPERION_DRY_RUN` (alap: `1`).

## Biztonsági szabályok
- `XPERION_DRY_RUN=1`: fordítás és ellenőrzés fut, WordPress-hívás csak `get_pair`, a lapra a fordítás és „dry-run” állapot kerül. Éles mód csak Balázs jóváhagyása után.
- Soronként sorban, párhuzamosság nélkül; párok között 1 mp szünet.
- Időtúllépés vagy HTTP 5xx után soha nincs vak ismétlés: előbb `get_pair`.
- HTTP 401/403, vagy „External-link plugin is not applying the source URL” → az egész futás megáll, riasztás Balázsnak.
- A hibák besorolása és teendői: `docs/wp-bridge-api.md`, „Hibák és teendők”.
- Futásnapló: időpont, kulcs, művelet, eredmény; jelszó, `Authorization` fejléc és teljes kérés-törzs nélkül.

## Elfogadási feltételek
1. Dry-run egy valós havi lapon: minden elfogadott sorhoz érvényes HU/EN cím és ajánló, kulcs, WordPress-írás nélkül.
2. Éles futás egy cikkel: a pár mindkét nyelven kint van, kiemelt képpel, forráslinkkel, Polylang-párosítással; a lap visszaírása helyes.
3. Ugyanazon sor újrafuttatása nem hoz létre duplikátumot (`changed: false`).
4. A WordPress-admin „Xperion sajtószemle” naplójában látszik a művelet.

## Nem része
Ügyfél-e-mail logikájának módosítása; a WordPress-bővítmény módosítása (külön kérésre); képjogi döntés (Balázs és Attila).
