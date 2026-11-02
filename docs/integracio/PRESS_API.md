# Sajtószemle-API 0.6.0

A magyar–angol sajtószemle-bejegyzések (cikkajánlók) kezelése. A bejegyzés a forráscikkre mutat (Page Links To, új lapon), a két nyelvi változat Polylang- vagy WPML-párt alkot, és kiemelt képet kap. A szerkesztő-API (`API.md`) ettől független.

## Végpont és hitelesítés

`POST /wp-json/xperion-bridge/v1/press` (permalink nélkül: `/?rest_route=/xperion-bridge/v1/press`), HTTPS-en, WordPress-alkalmazásjelszóval.

A hívó felhasználónak `edit_posts` jog kell; publikáláshoz `publish_posts`, képimporthoz `upload_files`. Egy dedikált, nem admin felhasználó (Szerző) elegendő. A jelszó csak szerveroldali környezeti változóban legyen.

Boríték: JSON-RPC 2.0, `method: "tools/call"`. A válasz HTTP-állapota a művelet hibájánál is 200: a sikert a `result.isError` mutatja, az adat a `result.content[0].text` JSON-szövegében van. Hitelesítési vagy jogosultsági hibánál a WordPress saját 401/403-as REST-válasza jön.

## Eszközök

| Név | Elérés | Argumentumok | Hatás |
| --- | --- | --- | --- |
| `xperion_press_context` | MCP + REST | – | Csak olvas: nyelvi rendszer, kategóriák nyelvvel, alapkategóriák, `can_publish`, `can_upload`, a legutóbbi 20 pár. |
| `xperion_press_get_pair` | MCP + REST | `key` | Csak olvas: egy pár állapota (`none`, `draft`, `partial`, `published`, `conflict`) és bejegyzései. |
| `xperion_press_save_pair` | MCP + REST | `key`, `source_url`, `date`, `hu`, `en`, opcionálisan `featured_media` / `featured_image_url` | Piszkozatpár létrehozása vagy frissítése. Publikált tagot nem ír felül. |
| `xperion_press_publish_pair` | MCP + REST | `key` | A piszkozatpár ellenőrzése és publikálása. Publikált tagot átugor. |
| `xperion_press_deploy_pair` | **csak közvetlen REST** | mint a `save_pair` | Mentés, ellenőrzés és publikálás egy hívásban. Az ügyfél által már jóváhagyott cikkekhez (cikkfigyelő). |

A `deploy_pair` szándékosan nem szerepel az MCP eszközlistájában, és a közvetítő sem továbbítja. A ChatGPT-kapcsolaton a mentés → ellenőrzés → publikálás útja marad.

## Argumentumok

| Mező | Szabály |
| --- | --- |
| `key` | `^[a-z0-9-]{3,80}$`, a pár stabil azonosítója. Javasolt: `p-` + a normalizált forrás-URL SHA-256-jának első 20 hexa karaktere. |
| `source_url` | Csak `https`, felhasználónév és jelszó nélkül. Ugyanez az URL más bejegyzésen nem szerepelhet (pontos egyezés). |
| `date` | `YYYY-MM-DD`, nem jövőbeli. A bejegyzés dátuma ezen a napon 12:00 (helyi idő). |
| `hu`, `en` | `title` (≤ 500 bájt), `summary` (≤ 10 000 bájt), opcionális `category_id`. Egyszerű szövegként tárolódik: link és formázás nem marad meg. |
| `category_id` | Ha hiányzik, az adminban beállított alapkategória (Beállítások → Xperion sajtószemle). A kategória nyelvének egyeznie kell. |
| `featured_media` | Meglévő, olvasható médiatári kép azonosítója. |
| `featured_image_url` | HTTPS kép-URL, amelyet a bővítmény a médiatárba importál. |

**Kiemelt kép, sorrendben:**

1. `featured_media`, ha meg van adva.
2. `featured_image_url`, ha meg van adva.
3. A forrásoldal `og:image:secure_url`, `og:image`, `og:image:url`, `twitter:image` vagy `twitter:image:src` metacímkéje.

Import: `wp_safe_remote_get` / `download_url` (belső hálózati címek tiltva), legfeljebb 3 átirányítás, a forrásoldalból legfeljebb 1 MB olvasva. Csak JPEG, PNG, WebP vagy GIF, legfeljebb 8 MB, legalább 200×100 px. Az importált kép `_xob_source_image` metában tárolja az eredeti URL-t, így ugyanaz az URL másodszor nem töltődik fel. Az alt szöveg a magyar cím. A kép a két nyelvi változat közös kiemelt képe.

## deploy_pair

```json
{"jsonrpc":"2.0","id":"8f3c…","method":"tools/call","params":{"name":"xperion_press_deploy_pair","arguments":{
  "key":"p-3fa91c0d2b7e4a19c0d1",
  "source_url":"https://example.com/cikk",
  "date":"2026-10-15",
  "hu":{"title":"…","summary":"…"},
  "en":{"title":"…","summary":"…"}}}}
```

| Kiinduló állapot | Viselkedés | Válasz |
| --- | --- | --- |
| `none` vagy `draft` | mentés + ellenőrzés + publikálás | `state: published`, `changed: true` |
| `partial` | csak a hiányzó tag publikálása, tartalomfrissítés nélkül | `state: published`, `changed: true`, `note` |
| `published` | semmi | `state: published`, `changed: false` |

A válasz `posts` tömbje bejegyzésenként: `id`, `title`, `summary`, `status`, `date`, `language`, `categories`, `featured_media`, `featured_image` (közepes méretű kép URL-je), `source_url`, `url`, `edit_url`.

## Hibák

| Üzenet eleje | Jelentés | Hívó teendője |
| --- | --- | --- |
| `Source URL already exists outside this pair.` | a cikk már fent van | duplikátumként kezelni, nincs ismétlés |
| `Pair locked by a running operation.` | futó vagy megszakadt írás | néhány perc múlva `get_pair`, utána ismétlés |
| `Publish failed; the other translation may already be live.` | részleges publikálás | `get_pair` → `partial` esetén `deploy_pair` újra |
| `Source page could not be fetched…`, `No HTTPS og:image found…` | nincs automatikus kép | `featured_image_url` vagy `featured_media` küldése |
| `Featured image is too small…`, `…not a JPEG, PNG, WebP or GIF.`, `…larger than 8 MB.` | a kép nem használható | másik kép |
| `Invalid or future article date.`, `A valid HTTPS source URL is required.` | bemeneti hiba | adatjavítás |
| `No category for …`, `Category language does not match …` | kategóriahiba | alapkategória beállítása vagy helyes `category_id` |
| `Only existing drafts can be updated…` | publikált pár mentése | nincs teendő, állapot-ellenőrzés |
| `External-link plugin is not applying the source URL…` | a Page Links To nem működik | a teljes futás leállítása, a bővítmény ellenőrzése |

Időtúllépés vagy HTTP 5xx után ne ismételj vakon: előbb `get_pair`.

## Zárolás és napló

Az író műveletek páronként zárolnak (`xob_press_lock_<sha256(key)>`). A zárolás 10 perc után lejár, és a következő kérés atomikusan átveszi. A sajtó- és a szövegmodul (`includes/lock.php`) így viselkedik. A kártya- és a szerkesztőmodul ugyanazt a `xob_legacy_lock_<id>` nevet használja, de lejárat nélkül. Minden bridge-zárolás látható és kézzel feloldható a Beállítások → Xperion sajtószemle oldalon.

Az író műveletek eredménye (időpont, felhasználó, művelet, kulcs, siker, üzenet) a `xob_press_log` opcióba kerül (utolsó 200, autoload nélkül), és az adminoldalon látszik. Jelszó vagy kérés-törzs nem kerül bele.
