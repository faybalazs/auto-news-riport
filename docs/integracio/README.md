# WordPress-integráció (Xperion OAuth Bridge 0.6.0)

A felhasználó által átadott csomagból (Xperion_cikkfigyelo_atadas_2026-11-02_v3.zip), titkok nélkül:

- [PRESS_API.md](PRESS_API.md): a bővítmény sajtószemle-végpontjának mérvadó leírása (eszközök, argumentumok, hibák).
- [wp-bridge-api.md](wp-bridge-api.md): a cikkfigyelő oldali használat, a kulcsképzés és a fordítás rendszerpromptja.
- [integration-task.md](integration-task.md): az eredeti integrációs feladat. A Google Sheet-es visszaírás helyett itt az adatbázis `articles` táblájába ír a program, a környezeti változók helyett a `config.php` `wordpress` részéből olvas (decisions.md, 2026-11-02).

A megvalósítás: `app/src/Press/` (lásd architecture.md). A bővítmény forrása nem része ennek a repónak.
