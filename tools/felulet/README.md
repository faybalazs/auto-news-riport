# A felület forrásai és építése

Az `assets/app.css` és az `assets/app.js` **generált** fájl: ne kézzel szerkeszd őket, hanem az itteni forrásokat, majd építsd újra.

| Fájl | Mi ez |
|---|---|
| `app-extra.css` | a projekt saját stílusai („P. PROJEKT” rész), a sablon CSS 18. szakasza elé kerül |
| `app-pages.js` | a projekt oldalankénti szkriptjei (12–14. szekció) |
| `build-app.py` | összeállító: a sablon szakaszjelölői alapján kivágja a közös részeket, Xperion-színekre cseréli az accentet, és hozzáfűzi a projektrészeket |

Építés (fejlesztői gépen vagy a Claude-munkamenetben, a repó gyökeréből):

```
python3 -I tools/felulet/build-app.py <auris-admin-sablon skill mappa>/assets/admin-sablon.html tools/felulet .
node --check assets/app.js
```

A sablon az auris-admin-sablon skillből jön (a felület jelenleg a v1.6.0-ból épül; a generált fájlok fejléce mutatja a verziót). Újabb sablonverziónál ugyanígy építs újra. Ellenőrzés után: PHPUnit és a skill `scripts/app-ellenorzes.js` felületellenőrzője.

Ez a mappa nem kell a tárhelyre; ha mégis feltöltődik, a `tools/.htaccess` kívülről elzárja.
