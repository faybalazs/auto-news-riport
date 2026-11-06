# Cikkfigyelő ↔ xperion.hu integráció (bővítmény 0.6.0)

A végpont mérvadó, teljes leírása a bővítmény saját dokumentációjában van: `wordpress-plugin/xperion-oauth-bridge/docs/PRESS_API.md`. Ez a fájl a cikkfigyelő oldali használatot foglalja össze.

## Folyamat cikkenként

1. A havi lapon az ügyfél által elfogadott sor.
2. A cikkfigyelő letölti a forráscikk szövegét, és a Claude API-val elkészíti a HU és EN címet és ajánlót (lásd lent).
3. `xperion_press_get_pair(key)` → ha `published`, a sor kész (nincs teendő).
4. `xperion_press_deploy_pair(...)` → a válasz `state` mezője: `published`.
5. A lapra visszaírja: WP HU/EN ID, a két URL, időpont, állapot. Hiba esetén az üzenetet.

## Végpont és hitelesítés

- `POST https://xperion.hu/wp-json/xperion-bridge/v1/press`
- `Authorization: Basic base64(felhasználó:alkalmazásjelszó)`, `Content-Type: application/json`
- A felhasználó legyen dedikált, nem admin (Szerző elég): kell hozzá `edit_posts`, `publish_posts`, `upload_files`.
- A titkok csak szerveroldali környezeti változókban: `XPERION_WP_BASE_URL`, `XPERION_WP_USER`, `XPERION_WP_APP_PASSWORD`, `ANTHROPIC_API_KEY`.

## Kulcs

`p-` + a normalizált forrás-URL SHA-256-jának első 20 hexa karaktere (pl. `p-3fa91c0d2b7e4a19c0d1`).
Normalizálás: kisbetűs host, `utm_*`, `fbclid`, `gclid` és `#…` törölve. Ugyanazt a normalizált URL-t küldd `source_url`-ként is.

## deploy_pair kérés

```json
{"jsonrpc":"2.0","id":"<uuid>","method":"tools/call","params":{"name":"xperion_press_deploy_pair","arguments":{
  "key":"p-3fa91c0d2b7e4a19c0d1",
  "source_url":"https://example.com/cikk",
  "date":"2026-10-15",
  "hu":{"title":"…","summary":"…"},
  "en":{"title":"…","summary":"…"}
}}}
```

Opcionális: `featured_image_url` (ha a cikkfigyelő már kinyerte a képet), `featured_media` (meglévő médiatári kép), `hu.category_id` / `en.category_id` (egyébként az admin alapértelmezése).

A válasz `result.content[0].text` mezője JSON-szöveg: `{"key","state","changed","posts":[{id,title,status,language,url,edit_url,featured_image,…}]}`.
Hibánál `result.isError: true`, az üzenet a `text`-ben. HTTP 401/403 = hitelesítési vagy jogosultsági hiba.

## Hibák és teendők

| Üzenet (eleje) | Teendő |
| --- | --- |
| Source URL already exists outside this pair | már fent van → a sor „duplikátum”, nincs ismétlés |
| Pair locked by a running operation | pár perc múlva `get_pair`, utána ismétlés |
| Publish failed; the other translation may already be live | `get_pair` → `partial` esetén `deploy_pair` újra (csak befejez) |
| Source page could not be fetched / No HTTPS og:image found | `featured_image_url` küldése, vagy kézi kép |
| Featured image is too small / not a JPEG… / larger than 8 MB | másik kép URL-je |
| Invalid or future article date / A valid HTTPS source URL is required | a sor adatának javítása |
| No category for hu/en | alapértelmezett kategória beállítása az adminban |
| External-link plugin is not applying the source URL | állj meg minden sorral, Page Links To ellenőrzése |

Időtúllépés vagy HTTP 5xx után soha ne ismételj vakon: előbb `get_pair`.

## Fordítás és ajánló a Claude API-val

`POST https://api.anthropic.com/v1/messages`, fejlécek: `x-api-key`, `anthropic-version: 2023-06-01`, `content-type: application/json`.
Modell: `claude-sonnet-5-5` (jobb minőség) vagy `claude-haiku-5-5` (olcsóbb). Havi néhány tucat cikknél a különbség csekély, ezért a Sonnet javasolt.

Rendszerprompt:

```text
Az Xperion (xperion.hu) weboldal sajtószemléjéhez írsz cikkajánlót. Az Xperion ügyfélélmény-, sorkezelési, digital signage- és látogatóelemzési megoldásokkal foglalkozik.
A forráscikk alapján készíts magyar és angol címet és ajánlót.
- Cím: a forráscikk címének hű, természetes fordítása az adott nyelven (az eredeti nyelvű cím maradhat változatlan), legfeljebb 120 karakter.
- Ajánló: 2–4 mondat, saját szavakkal, tárgyilagos hangnemben; mi a cikk lényege és miért érdekes ügyfélélmény- vagy retail-szempontból. Nincs szó szerinti idézet, link, emoji, felsorolás vagy formázás.
- Az angol változat brit angol helyesírással készül.
- Ne találj ki tényt, számot vagy nevet, ami nincs a forrásban.
Csak ezt a JSON-t add vissza, semmi mást:
{"hu":{"title":"…","summary":"…"},"en":{"title":"…","summary":"…"}}
```

Felhasználói üzenet: a forráscikk címe, dátuma, URL-je és szövege (legfeljebb kb. 6000 szó).
Ellenőrzés küldés előtt: érvényes JSON, mind a négy mező nem üres, cím ≤ 500 bájt, ajánló ≤ 10 000 bájt.
