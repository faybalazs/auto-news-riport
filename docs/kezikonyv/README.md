# Felhasználói kézikönyv (ügyféloldali szerepkörök)

A végleges kézikönyv: **`app/resources/kezikonyv.html`** – önálló, egyfájlos online kézikönyv (képek, logó beágyazva) az **ügyfél-adminisztrátor** és a **tartalomkezelő** szerepkörnek. Az alkalmazásban a bal oldali menü alján, a **Felhasználói kézikönyv** pontból nyílik meg új ablakban (`index.php?p=kezikonyv`, belépéshez kötve); e-mailben is elküldhető, böngészőben és iPaden megnyitható. Itt, a `docs/kezikonyv/` mappában a forrása van.

| Fájl | Mi ez |
|---|---|
| `content.js` | a kézikönyv szövege és szerkezete (két felület: `admin`, `tartalom`) |
| `img/NN.webp` | képernyőképek a `fig()` hívások sorrendjében (helyi próbafelület, kitalált tesztadatok: Minta Péter, Teszt Anna) |
| `img/logo.svg`, `img/mark.svg` | az alkalmazás márkajele (fejléc) |

Készült az auris-felhasznaloi-kezikonyv skill (1.2.0) módszerével; az arculat (szín, Inter betű, márkajel, lábléc) az alkalmazáséval egyezik. Újraépítés a skill `scripts/build.py` szkriptjével:

```
python3 -I <auris-felhasznaloi-kezikonyv skill>/scripts/build.py docs/kezikonyv app/resources/kezikonyv.html
```

Frissíteni kell, ha a felület feliratai, a szerepkörök jogai vagy a folyamat változik (a lábléc a kézikönyv és az alkalmazás verzióját és a dátumot mutatja: `UI.footerMeta`).
