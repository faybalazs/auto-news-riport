# Komponenskatalógus

`docs/terv/komponens-katalogus.html`: az alkalmazás összes felületi eleme egy oldalon, újratervezéshez (az app saját `assets/app.css`-ével, ikonkészletével, kitalált tesztadatokkal).

Újragenerálás (fejlesztői gépen / sandboxban, futó helyi próbafelülettel a 127.0.0.1:8766 címen és a tesztfelhasználókkal):

```
node tools/katalogus/extract.js <munkamappa>/data.json
python3 -I tools/katalogus/build.py <munkamappa>/data.json <munkamappa>/artifact.html docs/terv/komponens-katalogus.html
```

Az `extract.js` belép, végigjárja az oldalakat, megnyitja a menüket és dialógusokat, és kimenti a renderelt HTML-t; a `build.py` ebből és a kézzel írt alapelemekből (gombok, jelvények, űrlapelemek, sávok, toastok) rakja össze az oldalt. Felületváltozás után érdemes újragenerálni.
