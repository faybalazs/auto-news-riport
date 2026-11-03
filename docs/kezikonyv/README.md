# Felhasználói kézikönyv (ügyféloldali szerepkörök)

`kezikonyv.html`: önálló, egyfájlos online kézikönyv (képek beágyazva) az **ügyfél-adminisztrátor** és a **tartalomkezelő** szerepkörnek. Böngészőben vagy iPaden megnyitható, e-mailben elküldhető, tárhelyre feltölthető.

| Fájl | Mi ez |
|---|---|
| `content.js` | a kézikönyv szövege és szerkezete (két felület: `admin`, `tartalom`) |
| `img/NN.webp` | képernyőképek a `fig()` hívások sorrendjében (helyi próbafelület, kitalált tesztadatok: Minta Péter, Teszt Anna) |
| `kezikonyv.html` | generált fájl |

Készült az auris-felhasznaloi-kezikonyv skill (1.1.1) módszerével. Újraépítés a skill `scripts/build.py` szkriptjével:

```
python3 -I <auris-felhasznaloi-kezikonyv skill>/scripts/build.py docs/kezikonyv docs/kezikonyv/kezikonyv.html
```

Frissíteni kell, ha a felület feliratai, a szerepkörök jogai vagy a folyamat változik (az alkalmazás verziója a lábléc „Sajtófigyelés 0.13” felirata).
