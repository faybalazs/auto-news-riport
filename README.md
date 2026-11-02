# Xperion automatizálás

Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztgenerálás az Xperion (xperion.hu) számára, a meglévő Google Sheetre építve.

- **Állapot:** tervezés; kód még nincs. Részletek: [docs/project-state.md](docs/project-state.md)
- **Technológia (tervezett):** PHP 8.1+, függőség nélküli éles kód; Google Sheets API, Claude API, SMTP, a meglévő feltöltő MCP-szerver
- **Követelmények:** [docs/product.md](docs/product.md)
- **AI-ügynököknek:** [AGENTS.md](AGENTS.md)

## Telepítés
Terminál nélkül, grafikus felületeken: a lépéseket az 1. ütem végén kell ide megírni (Google szolgáltatásfiók, Claude API-kulcs, `config.php`, feltöltés a tárhelyre, cron a vezérlőpultban, első szárazfuttatás).

## Titkok
A `config.php` és minden kulcs a repón kívül marad; a repóban csak `config.example.php` lesz a változónevekkel.
