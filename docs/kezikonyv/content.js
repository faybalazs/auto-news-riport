// Sajtófigyelés – felhasználói kézikönyv az ügyféloldali felhasználóknak (ügyfél-adminisztrátor, tartalomkezelő).
// Forrás: az alkalmazás kódja (0.17.0) és a helyi próbafelület, kitalált tesztadatokkal.
// Építés: python3 <auris-felhasznaloi-kezikonyv skill>/scripts/build.py docs/kezikonyv docs/kezikonyv/kezikonyv.html
const BRAND = {
  key: 'sajtofigyeles',
  name: 'Sajtófigyelés',
  docTitle: 'Sajtófigyelés',
  logo: 'img/logo.svg',                // az alkalmazás márkajele (oldalsáv bal felső sarka)
  mark: 'img/mark.svg',
  supportEmail: '',
  multiLang: false,
  mockLabel: 'Szemléltető kép',
  // az auris-admin-sablon színei és betűje, ahogy az alkalmazásban (Xperion-kék accent)
  colors: { primary: '#2D388A', accent: '#2D388A', accentHover: '#232C6E', accentText: '#2D388A' },
  font: { family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif', url: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' },
};

const T = (hu, en) => ({ hu, en });
const loc = (v, l) => {
  if (Array.isArray(v)) return v.map(x => loc(x, l));
  if (v && typeof v === 'object') {
    if ('hu' in v && 'en' in v && Object.keys(v).length === 2) return v[l];
    const o = {}; for (const k in v) o[k] = loc(v[k], l); return o;
  }
  return v;
};

const UI = {
  manual: T('Felhasználói kézikönyv', 'User manual'),
  search: T('Keresés a kézikönyvben', 'Search the manual'),
  searchPh: T('Keresés a kézikönyvben…', 'Search the manual…'),
  toc: T('Tartalomjegyzék', 'Contents'),
  overview: T('Bevezetés', 'Introduction'),
  home: T('Kezdőlap', 'Home'),
  switchSurface: T('Szerepkör váltása', 'Switch role'),
  homeEyebrow: T('Felhasználói kézikönyv · Sajtófigyelés', 'User manual · Press monitoring'),
  homeTitle: T('Üdvözöljük a Sajtófigyelés felületén', 'Welcome to Press monitoring'),
  homeLead: T('Ez a kézikönyv lépésről lépésre bemutatja a havi sajtófigyelés, a weboldalra feltöltés és a LinkedIn-posztok kezelését. Kérjük, válassza ki, melyik szerepkör illik Önre.', 'Step-by-step guide.'),
  start: T('Kezdés', 'Get started'),
  choose: T('Kérjük, válasszon az alábbi szerepkörök közül', 'Please choose a role'),
  overviewTitle: T('Bevezetés', 'Introduction'),
  openChapter: T('Fejezet megnyitása', 'Open chapter'),
  openManual: T('Kézikönyv megnyitása', 'Open manual'),
  helpTitle: T('Segítségre van szüksége?', 'Need help?'),
  helpText: T('Kérdés, hozzáférési probléma vagy hiba esetén forduljon az alkalmazás üzemeltetőjéhez (Auris Dynamics Zrt.).', 'Please contact the operator (Auris Dynamics).'),
  contact: T('Kapcsolat', 'Contact'),
  updated: T('Utolsó frissítés: 2026. november 3.', 'Last updated: 3 November 2026'),
  fig: T('kép', 'Figure'),
  tip: T('Tipp', 'Tip'), important: T('Fontos', 'Important'), warning: T('Figyelem', 'Warning'),
  goodToKnow: T('Jó tudni', 'Good to know'),
  otherSurface: T('Másik szerepkör', 'Other role'),
  open: T('Megnyitás', 'Open'),
  navigate: T('navigálás', 'navigate'),
  noResults: T('Nincs találat erre:', 'No results for'),
  next: T('Ezután', 'Next'),
  stuck: T('Ha elakad', 'If you get stuck'),
  enlarge: T('Kép megnyitása teljes méretben', 'Open full-size image'),
  copyright: T('© 2026 Auris Dynamics Zrt. Minden jog fenntartva.', '© 2026 Auris Dynamics Zrt. All rights reserved.'),
  footerMeta: T('Felhasználói kézikönyv 1.2 · Sajtófigyelés 0.17.0 · 2026. 11. 03.', 'User manual 1.1'),
};

const SHOT = 'Képernyőkép';
const IMG_SIZES = [[1600,1000],[1600,1000],[880,1120],[1600,1000],[1600,1000],[1600,988],[880,1412],[1600,693],[1600,1000],[880,580],[1600,1000],[1600,1000],[880,628],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[1600,1000],[880,412],[780,1688],[1600,1000],[1600,1000],[1600,1000]];
let FIGN = 0;
const fig = (caption, opts = {}) => { const n = ++FIGN; const [w, h] = IMG_SIZES[n - 1]; return { type: 'figure', label: SHOT, caption, src: `img/${String(n).padStart(2, '0')}.webp`, w, h, ...opts }; };
const who = text => ({ type: 'tip', label: 'Kinek szól?', text });
const bullets = arr => ({ type: 'list', items: arr.map(d => ({ t: '', d })) });
const stuck = items => ({ type: 'faq', title: true, items: items.map(([q, a]) => ({ q, a })) });

// ---- Közös részek (mindkét szerepkörnél ugyanaz a szabály) ----
const LOGIN_STEPS = { type: 'steps', items: [
  { t: 'Meghívó link megnyitása.', d: 'Az ügyfél-adminisztrátortól vagy az üzemeltetőtől kapott meghívó link a jelszó beállítására szolgáló oldalt nyitja meg. A link 7 napig érvényes, és egyszer használható.' },
  { t: 'Jelszó beállítása.', d: 'A Jelszó és a Jelszó még egyszer mezőben ugyanaz a legalább 10 karakteres jelszó szükséges. A mentés után a fiók aktív, és megnyílik a felület.' },
  { t: 'Belépés.', d: 'A Belépés oldalon az E-mail-cím és a Jelszó megadása, majd a Belépés gomb nyitja meg az Áttekintést. Ha a Caps Lock be van kapcsolva, az oldal jelzi.' },
] };
const LOGIN_STUCK = stuck([
  ['A meghívó vagy a jelszólink „nem érvényes”?', 'A link lejárt vagy már felhasználták. Új linket az ügyfél-adminisztrátor vagy az üzemeltető ad.'],
  ['Elfelejtette a jelszavát?', 'Az Elfelejtett jelszó? hivatkozás leírja a teendőt: jelszó-visszaállító linket az ügyfél-adminisztrátor vagy az üzemeltető ad. A link 24 óráig érvényes.'],
  ['Nem látszik egy menüpont, amelyről a kézikönyv ír?', 'A menüpontok a szerepkörtől függenek. A hiányzó jogosultságot az ügyfél-adminisztrátor vagy az üzemeltető állítja be.'],
]);
const IDEA_BLOCKS = (p) => [
  { type: 'p', text: 'A Posztötletek oldalon saját posztötletek gyűjthetők: rendezvény, díj, partnerség, esettanulmány, csapat. Egy ötletből egy poszt készül a Posztszabályok szerint.' },
  { type: 'h3', id: p + '-idea-need', text: 'Az ötlethez készítse elő' },
  bullets(['A poszt témáját egy mondatban.', 'A fő üzenetet: tényeket, neveket, számokat. A poszt csak abból dolgozik, ami az ötletben szerepel.', 'Ha van, forrás- vagy fotólinket.', 'Ha fontos, a kívánt hetet.']),
  { type: 'h3', id: p + '-idea-create', text: 'Ötlet rögzítése és generálása' },
  { type: 'steps', items: [
    { t: 'Új ötlet megnyitása.', d: 'A Posztötletek oldal Új ötlet gombja nyitja meg az Új posztötlet űrlapot.' },
    { t: 'Adatok megadása.', d: 'A Poszttípus, a Téma és a Fő üzenet kitöltése kötelező (*). A Beküldő mező üresen a belépett felhasználó nevét kapja. A Forrás- vagy fotólink, a Kívánt hét és a Megjegyzés nem kötelező.' },
    { t: 'Mentés.', d: 'Az Ötlet mentése gomb rögzíti az ötletet Új állapotban. A mentett ötlet később is szerkeszthető.' },
    { t: 'Küldés posztgenerálásra.', d: 'A megnyitott ötlet Küldés posztgenerálásra gombja indítja a poszt elkészítését. Ez fél–egy percig tart. A kész poszt magától jóváhagyásra kerül (Ügyfélnél állapot), és az ötlet Elkészült állapotú lesz.' },
  ] },
  { type: 'important', text: 'A mentés nem azonos a generálással. Az ötlet mentése után poszt csak a Küldés posztgenerálásra gombbal készül.' },
];
const IDEA_STUCK = stuck([
  ['Nem menthető az ötlet?', 'A *-gal jelölt mezők (Poszttípus, Téma, Fő üzenet) kitöltése szükséges.'],
  ['Nem látszik a Küldés posztgenerálásra gomb?', 'Elvetett ötletből, illetve olyan ötletből, amelyhez már készült poszt, nem indítható újabb generálás. A kész poszt a Poszt megnyitása gombbal érhető el.'],
  ['Generálásra vár feliratot mutat az ötlet?', 'Az előző próba nem sikerült. Az óránkénti futás magától újrapróbálja.'],
]);
const RULE_BLOCKS = (p) => [
  { type: 'p', text: 'A Posztszabályok a posztgenerálás egyetlen szabályforrása: hangnem, szerkezet, hossz, hashtagek, poszttípusok, kerülendők és a kép szabályai. A módosítás a következő generálásnál már érvényes; a már elkészült posztokat nem írja át.' },
  { type: 'h3', id: p + '-rule-edit', text: 'Szabály felvétele és módosítása' },
  { type: 'steps', items: [
    { t: 'Új szabály megnyitása.', d: 'Az Új szabály gomb nyitja meg az űrlapot.' },
    { t: 'Megnevezés és érték megadása.', d: 'A Megnevezés a szabály neve (pl. Hangnem, Hossz, Kerülendő), az Érték a szabály szövege. Mindkét mező kötelező.' },
    { t: 'Mentés.', d: 'A Mentés gomb rögzíti a szabályt. A meglévő szabály a sor ceruza ikonjával szerkeszthető, a nyilakkal a sorrend módosítható, a kuka ikonnal törölhető.' },
  ] },
  { type: 'p', text: 'A keresőmező a megnevezésben és az értékben keres. A jobb felső sarokban a szabályok száma látszik.' },
];

const SURFACES = {
  admin: {
    label: 'Ügyfél-adminisztrátor',
    cardTitle: 'Ügyfél-adminisztrátor vagyok',
    cardImg: 'img/04.webp',
    cardText: 'Döntök a havi cikkekről, feltöltöm őket a weboldalra, jóváhagyom a LinkedIn-posztokat, kezelem a forrásokat és a munkatársaim hozzáférését.',
    short: 'Havi cikkek döntése és feltöltése, vezetői összefoglaló, posztok jóváhagyása, források, felhasználók és napló.',
    eyebrow: 'Felhasználói kézikönyv · Ügyfél-adminisztrátoroknak',
    title: 'Sajtófigyelés – ügyfél-adminisztrátor',
    lead: 'Ez a kézikönyv a Sajtófigyelés felület ügyfél-adminisztrátori használatához nyújt segítséget: a havi cikkek döntésétől és feltöltésétől a LinkedIn-posztok jóváhagyásán át a források és a felhasználók kezeléséig. Az egyes fejezetek önállóan is értelmezhetők.',
    readTime: 'Olvasási idő: kb. 20 perc',
    figLabel: 'kép',
    helpText: 'Kérdés, hozzáférési probléma vagy hiba esetén forduljon az alkalmazás üzemeltetőjéhez (Auris Dynamics Zrt.). Adja meg az érintett oldalt és a megjelenő üzenetet.',
    quickTitle: { id: 'a-modules', text: 'A kézikönyv fejezetei' },
    intro: [
      { type: 'quick' },
      { type: 'h3', id: 'a-flow', text: 'A havi munka menete' },
      { type: 'important', text: 'A cikkdöntés, a weboldalra feltöltés és a LinkedIn-közzététel **külön lépés**. A Mehet döntés önmagában nem tesz ki semmit a weboldalra, és a poszt jóváhagyása sem jelenti a LinkedInen való megjelenést.' },
      { type: 'steps', items: [
        { t: 'Havi lista.', d: 'Minden hónap elején a havi futás begyűjti a figyelt források cikkeit, a Claude értékeli őket, és elkészül a vezetői összefoglaló.' },
        { t: 'Döntés.', d: 'Az ügyfél-adminisztrátor a Havi cikkek oldalon cikkenként dönt: Mehet, LinkedIn poszt vagy Nem kell.' },
        { t: 'Feltöltés a weboldalra.', d: 'A Mehet cikkek magyar és angol ajánlóként kerülnek a weboldalra: az óránkénti futás magától, vagy kézzel a Feltöltés élesben gombbal. Utána Publikálva állapotúak.' },
        { t: 'Poszt készítése.', d: 'A LinkedIn poszt jelölésű cikkekből és a posztötletekből a Claude a Posztszabályok szerint posztot ír képpel. A kész poszt jóváhagyásra kerül.' },
        { t: 'Jóváhagyás.', d: 'Az ügyfél-adminisztrátor jóváhagyja a posztot, vagy módosítást kér; a módosítást a Claude azonnal elvégzi.' },
        { t: 'Közzététel.', d: 'A tartalomkezelő a jóváhagyott posztot a LinkedIn saját ütemezőjével teszi közzé, majd megjelentnek jelöli.' },
      ] },
      { type: 'h3', id: 'a-terms', text: 'A legfontosabb fogalmak' },
      { type: 'list', items: [
        { t: 'Javaslat:', d: 'a Claude értékelése cikkenként (Erősen javasolt, Javasolt, Nem javasolt). A javaslat nem azonos a döntéssel.' },
        { t: 'Döntés:', d: 'az ügyfél választása cikkenként: Mehet (a weboldalra), LinkedIn poszt (poszt készül belőle), Nem kell.' },
        { t: 'Publikálva:', d: 'a cikk magyar és angol ajánlója kint van a weboldalon. A Mehet döntés önmagában még nem Publikálva.' },
        { t: 'Szárazfuttatás:', d: 'próbafuttatás, amely elkészíti a szövegeket, de a weboldalra nem ír semmit.' },
        { t: '(i) súgó:', d: 'a gombok és mezők melletti kis (i) jel. Rámutatva vagy megérintve megmutatja, mit csinál a gomb, mennyi ideig tart és mennyibe kerül.' },
        { t: 'Poszt állapotai:', d: 'Tervezet, Ügyfélnél (jóváhagyásra vár), Jóváhagyva, Megjelent.' },
        { t: 'Szerepkörök:', d: 'ügyfél-adminisztrátor (dönt, jóváhagy, feltölt, felhasználókat hív meg), tartalomkezelő (posztötletek, szabályok, feltöltés, közzététel), olvasó (mindent lát, de nem módosít). Az üzemeltető (Auris Dynamics Zrt.) a futásokat és a beállításokat kezeli.' },
      ] },
    ],
    chapters: [
      { id: 'a-login', title: 'Belépés', summary: 'Meghívó, jelszó beállítása és belépés.', heading: 'Belépés és első lépések', blocks: [
        { type: 'p', text: 'A felület meghívóval érhető el. A meghívó linket az üzemeltető vagy egy másik ügyfél-adminisztrátor küldi. A webcím a meghívó linkben szerepel.' },
        who('Minden új felhasználónak.'),
        { type: 'h3', id: 'a-login-need', text: 'A belépéshez készítse elő' },
        bullets(['A meghívó linket (e-mailben vagy üzenetben kapja).', 'Egy legalább 10 karakteres jelszót.']),
        { type: 'h3', id: 'a-login-steps', text: 'Fiók aktiválása és belépés' },
        LOGIN_STEPS,
        fig('Belépés — e-mail-cím, jelszó és az Elfelejtett jelszó? hivatkozás'),
        { type: 'tip', label: 'A kézikönyv elérése', text: 'A kézikönyv a felület bal oldali menüjének alján, a Felhasználói kézikönyv pontból nyílik meg, új ablakban.' },
        { type: 'p', text: 'Több ügyfélhez rendelt felhasználónál a felső sáv ügyfélváltója mutatja és váltja az aktuális ügyfelet. A felület mindig csak a kiválasztott ügyfél adatait mutatja.' },
        LOGIN_STUCK,
        { type: 'next', text: 'Belépés után az Áttekintés oldal nyílik meg a teendőkkel.' },
      ] },
      { id: 'a-overview', title: 'Áttekintés', summary: 'Teendők, vezetői összefoglaló és a hónap számai.', heading: 'Áttekintés', blocks: [
        { type: 'p', text: 'Az Áttekintés a kezdőoldal. Fent a teendők, alatta a választott hónap vezetői összefoglalója, a hónap számai és a cikkek megoszlása látszik. A hónap a jobb felső választóval váltható.' },
        who('Ügyfél-adminisztrátornak.'),
        { type: 'h3', id: 'a-ov-todo', text: 'Teendők' },
        { type: 'p', text: 'A teendőcsempék a szerepkör szerint jelennek meg, számmal és egy gombbal a feladathoz. Ügyfél-adminisztrátornál jellemzően: Cikk vár döntésre (a megnevezett hónap száma; ha korábbi hónapban is maradt döntés nélküli cikk, azt a csempe külön írja), LinkedIn-poszt jóváhagyásra vár, Posztötlet generálásra vár.' },
        fig('Áttekintés — teendők és a vezetői összefoglaló'),
        { type: 'h3', id: 'a-ov-sum', text: 'Vezetői összefoglaló' },
        { type: 'p', text: 'Az összefoglaló egy rövid bevezetőből és három csoportból áll: Újdonságok, Tények, számok, Trendek. A pontok megnevezik a forrást. A cikkszámokat nem ismétli, azokat a hónap számai mutatják.' },
        { type: 'steps', items: [
          { t: 'Újragenerálás indítása.', d: 'Az összefoglaló kártyájának Újragenerálás gombja a hónap cikkeiből és a mostani döntésekből írja újra a szöveget. A gomb a Havi cikkek oldalon is megtalálható.' },
          { t: 'Megerősítés.', d: 'A megerősítés után 20–40 másodperc a készítés, utána az oldal újratöltődik. A meta sorban az „(újragenerálva)” jelölés és az időpont látszik.' },
        ] },
        { type: 'warning', text: 'Az újragenerálás felülírja a korábbi összefoglalót. A régi szöveg nem állítható vissza.' },
        { type: 'p', text: 'Ha egy hónaphoz még nincs összefoglaló (például a Google Sheetből átvett hónapoknál), az Összefoglaló készítése gombbal elkészíthető.' },
        { type: 'h3', id: 'a-ov-share', text: 'Mentés PDF-be és küldés e-mailben' },
        { type: 'steps', items: [
          { t: 'Mentés PDF-be.', d: 'Az összefoglaló kártyájának PDF gombja új ablakban nyomtatható nézetet nyit. Ott a Nyomtatás / mentés PDF-be gomb után a nyomtatóválasztóban a Mentés PDF-ként a célállomás. iPaden: Megosztás → Nyomtatás, majd a megosztás gombbal menthető a Fájlokba.' },
          { t: 'Küldés e-mailben.', d: 'A Küldés gomb ablakában a Címzett mező kötelező (több cím vesszővel elválasztva), a Másolat és a Kísérőszöveg nem kötelező. A levél az összefoglalót és a hónap cikkeinek linkjét tartalmazza.' },
          { t: 'Elküldés.', d: 'A Küldés gomb azonnal elküldi a levelet, egyszer. A küldés a Naplóba is bekerül (Vezetői összefoglaló elküldve).' },
        ] },
        fig('Vezetői összefoglaló küldése — címzett, másolat és kísérőszöveg'),
        fig('Áttekintés — a hónap számai: cikkek, erősen javasolt, Mehet és LinkedIn poszt jelölés, előző hónaphoz mérve'),
        stuck([
          ['Nem látszik az Újragenerálás vagy a Küldés gomb?', 'A gombok csak ügyfél-adminisztrátornak és az üzemeltetőnek jelennek meg. A PDF mindenkinek elérhető.'],
          ['Hibaüzenet jelenik meg az újragenerálásnál?', 'Az üzenet megnevezi az okot. Ismétlődő hiba esetén az üzemeltető tud segíteni.'],
        ]),
        { type: 'next', text: 'A döntésre váró cikkek a Cikk vár döntésre csempe Döntések gombjával nyithatók meg.' },
      ] },
      { id: 'a-articles', title: 'Havi cikkek – döntés', summary: 'Döntés cikkenként és tömegesen; törlés a listából.', heading: 'Havi cikkek – döntés', blocks: [
        { type: 'p', text: 'A Havi cikkek oldal a hónap begyűjtött cikkeit mutatja a Claude javaslatával és indoklásával. Itt dönt az ügyfél-adminisztrátor arról, melyik cikk kerüljön a weboldalra, melyikből készüljön LinkedIn-poszt, és melyik nem kell.' },
        who('Ügyfél-adminisztrátornak. A többi szerepkör a döntéseket csak látja.'),
        { type: 'h3', id: 'a-art-views', text: 'Nézetek és keresés' },
        { type: 'p', text: 'A nézetek: Döntésre vár, Összes, Mehet, Publikálva, LinkedIn poszt, Nem kell, Új források. A Döntésre vár nézet a döntés nélküli, nem „Nem javasolt” cikkeket mutatja. A keresőmező címben és leírásban keres, a Minden javaslat választó a javaslat szerint szűr. A hónap a lap tetején váltható.' },
        fig('Havi cikkek — összes cikk javaslattal, indoklással és döntéssel'),
        { type: 'h3', id: 'a-art-decide', text: 'Döntés' },
        { type: 'steps', items: [
          { t: 'Döntés cikkenként.', d: 'Az Ügyfél döntése oszlop választójában: Nincs döntés, Mehet, LinkedIn poszt, Nem kell. A választás azonnal mentődik.' },
          { t: 'Tömeges döntés.', d: 'A sorok elején lévő jelölőnégyzetekkel több cikk kijelölhető. A megjelenő sávban a Mehet, a LinkedIn poszt, a Nem kell és a Döntés törlése gomb egyszerre állítja be a kijelölteket.' },
          { t: 'Visszavonás.', d: 'Minden döntés után megjelenő értesítés Visszavonás gombja visszaállítja az előző állapotot.' },
        ] },
        fig('Havi cikkek — két kijelölt cikk és a tömeges döntés sávja'),
        { type: 'important', text: 'A Mehet döntés nem teszi ki azonnal a cikket. A feltöltést az óránkénti futás végzi, vagy kézzel a Feltöltés élesben gomb (következő fejezet).' },
        { type: 'h3', id: 'a-art-li', text: 'Poszt készítése cikkekből' },
        { type: 'p', text: 'A LinkedIn poszt jelölésű cikkek közül a kijelöltekből a Poszt készítése a kijelöltekből gomb indít posztot. Egy cikkből a cikkre épülő poszt készül (a cikk linkje az első kommentbe való). Több cikkből egyetlen önálló, hírszerű poszt készül a cikkek tényeiből, forrásmegjelölés és link nélkül.' },
        { type: 'steps', items: [
          { t: 'Cikkek kijelölése.', d: 'A LinkedIn poszt nézetben a sorok elején jelölje ki a cikkeket (legfeljebb 6), majd a sávban a Poszt készítése a kijelöltekből gomb.' },
          { t: 'Utasítás megadása.', d: 'Az ablakban az Utasítás a szöveghez (például: „A bankfiókok vezetőinek szóljon.”) és az Utasítás a képhez (például: „Kulcsszám elrendezés a 38%-os adattal.”) nem kötelező. A Posztszabályok és a cikkek tényei mindig érvényesek; az utasítás ezeken belül irányít.' },
          { t: 'Poszt készítése.', d: 'A Poszt készítése gomb után 1–2 perc a készítés. A kész poszt magától jóváhagyásra kerül, az utasítások a poszt adatlapján látszanak, és újragenerálásnál is érvényesek maradnak.' },
        ] },
        fig('Poszt készítése — a kijelölt cikkek, utasítás a szöveghez és a képhez'),
        { type: 'h3', id: 'a-art-delete', text: 'Törlés a listából' },
        { type: 'p', text: 'A sor ⋯ menüjének Törlés a listából pontja eltünteti a cikket a havi listából. A megjelenő értesítés Visszavonás gombja visszaállítja. A törlés a Naplóba is bekerül. Publikált cikk nem törölhető, csak a feltöltés visszavonása után.' },
        stuck([
          ['Nem módosítható egy cikk döntése?', 'A Publikálva állapotú cikk döntése zárolt. Módosításhoz előbb a feltöltést kell visszavonni (⋯ menü → Feltöltés visszavonása).'],
          ['Hiányzik egy cikk a Döntésre vár nézetből?', 'A „Nem javasolt” cikkek nem kerülnek ebbe a nézetbe; az Összes nézetben láthatók.'],
          ['Mit jelent az „új forrás” jelölés?', 'A cikk próbaidőszakos forrásból származik. A forrás a Források oldalon véglegesíthető vagy szüneteltethető.'],
        ]),
        { type: 'next', text: 'A Mehet cikkek a Feltöltés a weboldalra kártyán vagy az óránkénti futással kerülnek a weboldalra.' },
      ] },
      { id: 'a-upload', title: 'Feltöltés a weboldalra', summary: 'Szövegek elkészítése, élesítés, Publikálva állapot, visszavonás.', heading: 'Feltöltés a weboldalra', blocks: [
        { type: 'p', text: 'A Havi cikkek oldal Feltöltés a weboldalra kártyája a Mehet cikkeket teszi ki a weboldalra magyar és angol ajánlóként, a forráscikkre mutató linkkel. A kártya fejléce mutatja, hány Mehet cikk vár feltöltésre (minden hónapból).' },
        who('Ügyfél-adminisztrátornak és tartalomkezelőnek.'),
        { type: 'h3', id: 'a-up-need', text: 'A feltöltéshez készítse elő' },
        bullets(['Legalább egy Mehet döntésű cikket.', 'A weboldal-kapcsolatot; ezt az üzemeltető állítja be. Amíg nincs beállítva, a kártya jelzi, és a gombok tiltottak.']),
        { type: 'h3', id: 'a-up-steps', text: 'Szövegek és élesítés' },
        { type: 'steps', items: [
          { t: 'Szövegek elkészítése.', d: 'A Szövegek elkészítése (szárazon) gomb megíratja a Claude-dal a Mehet cikkek magyar és angol címét és ajánlóját (cikkenként 2–3 cent). A weboldalra nem kerül semmi.' },
          { t: 'Szövegek átnézése.', d: 'Az eredmény a kártya alatt táblázatban, a Havi cikkek sorában pedig az Elkészült szöveg (HU / EN) résznél olvasható.' },
          { t: 'Feltöltés élesben.', d: 'A Feltöltés élesben gomb a megerősítés után kiteszi a cikkeket a weboldalra, a kész szöveggel (újra nem fizet érte). Futásonként legfeljebb 10 cikk kerül fel.' },
          { t: 'Eredmény ellenőrzése.', d: 'A feltöltött cikk Publikálva jelvényt és HU, EN linket kap, és a Publikálva nézetben jelenik meg.' },
        ] },
        { type: 'important', text: 'A szárazfuttatás nem azonos a feltöltéssel: a weboldalra csak a Feltöltés élesben gomb vagy az óránkénti futás ír. Ami már kint van, nem kerül fel újra.' },
        fig('Feltöltés a weboldalra — szárazfuttatás eredménye magyar és angol szöveggel'),
        { type: 'h3', id: 'a-up-revert', text: 'Feltöltés visszavonása' },
        { type: 'p', text: 'A Publikálva nézetben a sor ⋯ menüjének Feltöltés visszavonása… pontja a cikket újra Döntésre vár állapotba teszi. Az elkészült szöveg megmarad, egy későbbi feltöltésnél újra felhasználható.' },
        fig('Havi cikkek, Publikálva nézet — a sor ⋯ menüje a Feltöltés visszavonása… ponttal'),
        { type: 'warning', text: 'A visszavonás a weboldalról nem töröl. A magyar és az angol bejegyzést a WordPress-adminban kell törölni: Bejegyzések, majd a Lomtár fülön Végleges törlés. Ha a program szerint a bejegyzés még kint van, a visszavonás előtt külön megerősítést kér.' },
        fig('Feltöltés visszavonása — megerősítő ablak'),
        stuck([
          ['Tiltottak a feltöltés gombjai?', 'A weboldal-kapcsolat még nincs beállítva ennél az ügyfélnél, vagy éppen fut egy másik feltöltés. A kapcsolatot az üzemeltető állítja be.'],
          ['Hiba jelvény áll egy cikk mellett?', 'A jelvény alatt olvasható az ok. A program a cikket legfeljebb háromszor próbálja; utána az üzemeltető indíthatja újra.'],
          ['„Már fent volt a weboldalon” jelzést kapott a cikk?', 'A cikk forráslinkje már szerepel egy korábbi bejegyzésben. A program nem tölti fel újra.'],
        ]),
        { type: 'next', text: 'A publikált cikkek a Publikálva nézetben és a weboldal sajtószemle-rovatában láthatók.' },
      ] },
      { id: 'a-posts', title: 'LinkedIn-posztok – jóváhagyás', summary: 'Posztok áttekintése, jóváhagyás, módosítás kérése, új kép.', heading: 'LinkedIn-posztok – jóváhagyás', blocks: [
        { type: 'p', text: 'A LinkedIn-posztok oldal a cikkekből és a posztötletekből készült posztokat mutatja kártyákon, képpel. Az állapot szerinti szűrő: Mind, Tervezet, Ügyfélnél, Jóváhagyva, Megjelent.' },
        who('Ügyfél-adminisztrátornak.'),
        fig('LinkedIn-posztok — posztkártyák állapottal és a posztra váró cikkek jelzése'),
        { type: 'h3', id: 'a-post-approve', text: 'Poszt jóváhagyása' },
        { type: 'steps', items: [
          { t: 'Poszt megnyitása.', d: 'Az Ügyfélnél állapotú poszt kártyájára kattintva nyílik meg a poszt oldala. Új poszt elkészültekor az ügyfél-adminisztrátor e-mailt is kap.' },
          { t: 'Előnézet ellenőrzése.', d: 'Az Előnézet fül LinkedIn-szerű képet mutat. A Zárt nézet a „…több” előtti részt, a Kibontott nézet a teljes szöveget mutatja.' },
          { t: 'Nyitó sor választása.', d: 'A jobb oldali Nyitó sor kártyán a Jelenlegi mellett két alternatíva választható; a választott nyitó sor azonnal mentődik.' },
          { t: 'Döntés.', d: 'A Jóváhagyom gomb jóváhagyja a posztot; a tartalomkezelő e-mailt kap a közzétételről. A Módosítást kérek gomb a kérés leírását kéri.' },
        ] },
        fig('Poszt oldala — előnézet, nyitó sor, forráscikkek, valamint a Módosítást kérek és a Jóváhagyom gomb'),
        { type: 'h3', id: 'a-post-change', text: 'Módosítás kérése' },
        { type: 'p', text: 'A Módosítást kérek ablakban röviden leírható a kérés; vonatkozhat a szövegre és a képre is. A Claude a kérés szerint azonnal újraírja a posztot (1–2 perc), és a poszt újra jóváhagyásra kerül.' },
        fig('Módosítás kérése — a kérés szövege és a Módosítás kérése gomb'),
        { type: 'h3', id: 'a-post-more', text: 'Forráscikkek, új kép, törlés' },
        { type: 'list', items: [
          { t: 'Forráscikkek:', d: 'a jobb oldali kártyán kijelölhető, melyik cikkekből készüljön a poszt. A Mentés és újraírás új szöveget kér, a Csak mentés csak a kijelölést rögzíti.' },
          { t: 'Új kép:', d: 'a gomb vagy a ⋯ menü Új kép készítése… pontja új képet készíttet; kérés is megadható (pl. „kevesebb szöveg”).' },
          { t: 'Szöveg másolása, Kép letöltése (PNG):', d: 'a közzétételhez szükséges szöveg és a 1200×1200-as kép.' },
          { t: 'Poszt törlése…:', d: 'a ⋯ menüben. A szöveg, a kép és az előzmények véglegesen törlődnek; a forráscikkek és az ötlet megmaradnak.' },
        ] },
        stuck([
          ['Nem látszik a Jóváhagyom gomb?', 'A gomb csak Ügyfélnél állapotú posztnál jelenik meg.'],
          ['Nem szerkeszthető a jóváhagyott poszt?', 'A jóváhagyott posztot csak az üzemeltető módosíthatja, a megjelent posztot senki.'],
          ['Az újraírás nem sikerült?', 'A kérés rögzítve marad, az óránkénti futás újrapróbálja.'],
        ]),
        { type: 'next', text: 'A jóváhagyott posztot a tartalomkezelő teszi közzé a LinkedInen, majd megjelentnek jelöli.' },
      ] },
      { id: 'a-ideas', title: 'Posztötletek', summary: 'Saját ötletek rögzítése és generálása.', heading: 'Posztötletek', blocks: [
        ...IDEA_BLOCKS('a'),
        fig('Posztötletek — ötletek listája típussal és állapottal'),
        fig('Új posztötlet — beküldő, poszttípus, téma, fő üzenet és nem kötelező mezők'),
        { type: 'p', text: 'Az ötlet állapota: Új, Elkészült, Jóváhagyva, Ütemezve, Elvetve. Az Elvetés gomb félreteszi az ötletet, a Törlés véglegesen törli.' },
        IDEA_STUCK,
        { type: 'next', text: 'A generált poszt a LinkedIn-posztok oldalon, Ügyfélnél állapotban jelenik meg.' },
      ] },
      { id: 'a-rules', title: 'Posztszabályok', summary: 'A posztgenerálás szabályai.', heading: 'Posztszabályok', blocks: [
        ...RULE_BLOCKS('a'),
        fig('Posztszabályok — megnevezés, érték és a sorműveletek'),
        stuck([['Nem változott egy már elkészült poszt?', 'A szabály a következő generálásnál érvényes. Meglévő poszthoz a poszt ⋯ menüjének Újragenerálás a szabályok szerint pontja használható.']]),
        { type: 'next', text: 'A következő posztgenerálás már az új szabályokkal készül.' },
      ] },
      { id: 'a-sources', title: 'Források és kulcsszavak', summary: 'Figyelt oldalak és kulcsszavak.', heading: 'Források és kulcsszavak', blocks: [
        { type: 'p', text: 'A havi gyűjtés a Források oldalon megadott figyelt oldalakból (hírlista vagy RSS) és a kulcsszavakból dolgozik. A kizáró kulcsszóval jelölt témájú cikkek kimaradnak.' },
        who('Ügyfél-adminisztrátornak.'),
        { type: 'h3', id: 'a-src-add', text: 'Forrás felvétele' },
        { type: 'steps', items: [
          { t: 'Új forrás megnyitása.', d: 'A Források fül Új forrás gombja nyitja meg az űrlapot.' },
          { t: 'Adatok megadása.', d: 'A Cím (hírlista vagy RSS) kötelező, http(s)-sel kezdődő webcím. A Név üresen a webhely nevét kapja. A Típus, a Témák és a Megjegyzés nem kötelező.' },
          { t: 'Állapot beállítása.', d: 'Az Aktív jelölés esetén a havi gyűjtés figyeli a forrást. Az Új forrás jelölés próbaidőszakot jelent: a Havi cikkekben a forrás cikkei külön jelölést kapnak.' },
          { t: 'Próbagyűjtés és mentés.', d: 'A Próbagyűjtés megmutatja, hány cikket talál a forrás, és melyik a legutóbbi néhány; nem ment semmit. A Mentés rögzíti a forrást.' },
        ] },
        fig('Források — figyelt oldalak típussal, állapottal és cikkszámmal'),
        { type: 'h3', id: 'a-src-find', text: 'Új források keresése (új-forrás-figyelő)' },
        { type: 'p', text: 'A Javasolt források fülön a Claude a kulcsszavak és a figyelt források témái alapján a weben olyan hírforrásokat keres, amelyek még nincsenek a listában. Minden találat próbagyűjtésen megy át; csak a működő forrás kerül a javaslatok közé. Forrás csak az Ön döntésével kerül be.' },
        { type: 'steps', items: [
          { t: 'Keresés indítása.', d: 'A Javasolt források fül Új források keresése gombja megkérdezi, hány új forrást keressen (1–10; a szám megmarad a következő keresésre). A Keresés indítása után 1–3 perc a keresés, költsége kb. 5–15 cent.' },
          { t: 'Javaslatok átnézése.', d: 'Soronként: a forrás neve és címe, miért javasolt, típus és témák, valamint a próbagyűjtés eredménye (hány cikket talált, és a legutóbbi néhány cím).' },
          { t: 'Felvétel.', d: 'A Felvétel gomb aktív, „új forrás” jelölésű figyelt forrásként veszi fel; a következő havi gyűjtés már figyeli, a cikkei a Havi cikkek Új források nézetében külön látszanak.' },
          { t: 'Elvetés.', d: 'Az Elvetés gomb eltünteti a javaslatot, és a figyelő ezt a forrást többé nem javasolja.' },
        ] },
        fig('Javasolt források — próbagyűjtéssel ellenőrzött javaslatok, Felvétel és Elvetés'),
        { type: 'h3', id: 'a-src-kw', text: 'Kulcsszavak' },
        { type: 'p', text: 'A Kulcsszavak fülön az Új kulcsszó gomb nyitja meg az űrlapot: Kulcsszó (kötelező), Téma, Nyelv (mindegy, magyar, angol) és a Kizáró kulcsszó jelölés. A kizáró kulcsszó témájába tartozó cikk kimarad a havi listából.' },
        stuck([
          ['A próbagyűjtés nem talál cikket?', 'A webcím nem hírlistára vagy RSS-re mutat, vagy az oldal nem érhető el. Érdemes a hírlista vagy az RSS pontos címét megadni.'],
          ['Szünetel egy forrás?', 'Az Aktív jelölés nélküli forrást a havi gyűjtés nem figyeli. A korábbi cikkei megmaradnak.'],
          ['Nem talált új forrást a keresés?', 'Ritkább témánál kevés működő, még nem figyelt forrás akad. Érdemes több kulcsszót és témát megadni, vagy később újra keresni.'],
        ]),
        { type: 'next', text: 'A következő havi futás már az új forrásokkal és kulcsszavakkal gyűjt.' },
      ] },
      { id: 'a-users', title: 'Felhasználók', summary: 'Munkatársak meghívása, letiltása, jelszólink.', heading: 'Felhasználók', blocks: [
        { type: 'p', text: 'A Felhasználók oldalon az ügyfél-adminisztrátor meghívja a munkatársait, és kezeli a hozzáférésüket. Csak a saját ügyfél felhasználói látszanak.' },
        who('Ügyfél-adminisztrátornak.'),
        { type: 'h3', id: 'a-usr-need', text: 'A meghíváshoz készítse elő' },
        bullets(['A munkatárs nevét és e-mail-címét.', 'A szerepkört: Ügyfél-adminisztrátor, Tartalomkezelő vagy Olvasó.']),
        { type: 'h3', id: 'a-usr-invite', text: 'Meghívás' },
        { type: 'steps', items: [
          { t: 'Meghívás megnyitása.', d: 'A Meghívás gomb nyitja meg a Felhasználó meghívása ablakot.' },
          { t: 'Adatok és szerepkör megadása.', d: 'A Név, az E-mail-cím és a Szerepkör megadása szükséges. A szerepkörök alatt rövid leírás áll arról, mit tehet az adott szerepkör.' },
          { t: 'Meghívó létrehozása.', d: 'A Meghívó létrehozása gomb után a Meghívó link ablak a Másolás gombbal másolható linket ad.' },
          { t: 'Link elküldése.', d: 'A linket e-mailben vagy üzenetben kell elküldeni a munkatársnak. A link 7 napig érvényes, és egyszer használható.' },
        ] },
        { type: 'important', text: 'A program a meghívót nem küldi el e-mailben. A link elküldése az ügyfél-adminisztrátor feladata.' },
        fig('Felhasználó meghívása — név, e-mail-cím és szerepkör'),
        { type: 'h3', id: 'a-usr-manage', text: 'Hozzáférés kezelése' },
        { type: 'p', text: 'A sor ⋯ menüje: Szerkesztés, Új meghívó link (még nem aktivált felhasználónál) vagy Jelszó-visszaállító link, Letiltás vagy Engedélyezés, Eltávolítás…. A letiltott felhasználó nem tud belépni, a fiókja megmarad. A Szerepkörök és jogosultságok táblázat az oldal alján mutatja, melyik szerepkör mit tehet.' },
        stuck([
          ['Elfelejtette a jelszavát egy munkatárs?', 'A sor ⋯ menüjének Jelszó-visszaállító link pontja új linket ad; a link 24 óráig érvényes.'],
          ['Lejárt a meghívó link?', 'A sor ⋯ menüjének Új meghívó link pontja új linket ad.'],
        ]),
        { type: 'next', text: 'A munkatárs a link megnyitása után jelszót állít be, és a szerepköre szerinti menüpontokat látja.' },
      ] },
      { id: 'a-log', title: 'Napló', summary: 'Ki, mit, mikor döntött vagy módosított.', heading: 'Napló', blocks: [
        { type: 'p', text: 'A Napló az ügyfél felhasználóinak műveleteit mutatja: ki, mit, mikor döntött, hagyott jóvá vagy módosított. A megőrzés 2 év.' },
        who('Ügyfél-adminisztrátornak.'),
        { type: 'p', text: 'A szűrők: kereső, Mindenki (felhasználó), Minden művelet (kategória: Döntések, Cikkek, Posztok, Havi e-mail, Feltöltés, Források, Futások, Felhasználók, Belépés, Beállítások), Ettől, Eddig. A Letöltés (CSV) a szűrt listát menti táblázatkezelőbe.' },
        fig('Napló — időpont, felhasználó, kategória, művelet és részletek'),
        stuck([['Nem látszik egy régi művelet?', 'A napló 2 évig őrzi a műveleteket. A dátumszűrők törlése után a teljes időszak látszik.']]),
        { type: 'next', text: 'A letöltött CSV-fájl táblázatkezelőben szűrhető és archiválható.' },
      ] },
      { id: 'a-profile', title: 'Fiók és jelszó', summary: 'Saját adatok és jelszócsere.', heading: 'Fiók és jelszó', blocks: [
        { type: 'p', text: 'A Profil oldal a jobb felső profilmenü Jelszó módosítása pontjával nyílik meg. A Fiók kártya a nevet, az e-mail-címet, a szerepkört és az utolsó belépést mutatja.' },
        who('Minden felhasználónak.'),
        { type: 'steps', items: [
          { t: 'Jelenlegi jelszó megadása.', d: 'A Jelenlegi jelszó mezőben a mostani jelszó szükséges.' },
          { t: 'Új jelszó megadása.', d: 'Az Új jelszó és az Új jelszó még egyszer mezőben ugyanaz a legalább 10 karakteres jelszó szükséges.' },
          { t: 'Mentés.', d: 'A Jelszó mentése gomb rögzíti az új jelszót.' },
        ] },
        fig('Profil — jelszó módosítása és a fiók adatai'),
        { type: 'next', text: 'A következő belépéskor az új jelszó érvényes.' },
      ] },
    ],
  },
  tartalom: {
    label: 'Tartalomkezelő',
    cardTitle: 'Tartalomkezelő vagyok',
    cardImg: 'img/22.webp',
    cardText: 'Posztötleteket küldök be, kezelem a posztszabályokat, feltöltöm a jóváhagyott cikkeket a weboldalra, és közzéteszem a jóváhagyott posztokat a LinkedInen.',
    short: 'Posztötletek, posztszabályok, feltöltés a weboldalra, posztok szerkesztése és közzététele.',
    eyebrow: 'Felhasználói kézikönyv · Tartalomkezelőknek',
    title: 'Sajtófigyelés – tartalomkezelő',
    lead: 'Ez a kézikönyv a Sajtófigyelés felület tartalomkezelői használatához nyújt segítséget: a posztötletektől a jóváhagyott posztok LinkedIn-közzétételéig, valamint a cikkek weboldalra töltéséhez. Az egyes fejezetek önállóan is értelmezhetők.',
    readTime: 'Olvasási idő: kb. 12 perc',
    figLabel: 'kép',
    helpText: 'Kérdés vagy hiba esetén forduljon az ügyfél-adminisztrátorhoz, vagy az alkalmazás üzemeltetőjéhez (Auris Dynamics Zrt.). Adja meg az érintett oldalt és a megjelenő üzenetet.',
    quickTitle: { id: 't-modules', text: 'A kézikönyv fejezetei' },
    intro: [
      { type: 'quick' },
      { type: 'h3', id: 't-flow', text: 'A tartalomkezelő feladatai' },
      { type: 'important', text: 'A cikkekről és a posztok jóváhagyásáról az ügyfél-adminisztrátor dönt. A tartalomkezelő a döntések után dolgozik: feltölt, szerkeszt, közzétesz.' },
      { type: 'steps', items: [
        { t: 'Posztötletek és szabályok.', d: 'A tartalomkezelő saját posztötleteket küld generálásra, és karbantartja a Posztszabályokat.' },
        { t: 'Feltöltés a weboldalra.', d: 'A Mehet döntésű cikkeket a Havi cikkek oldalon feltölti; ezt az óránkénti futás magától is elvégzi.' },
        { t: 'Posztok szerkesztése.', d: 'A jóváhagyás előtti posztokat szükség esetén szerkeszti.' },
        { t: 'Közzététel.', d: 'A jóváhagyott posztot a LinkedIn saját ütemezőjével közzéteszi, majd a felületen megjelentnek jelöli.' },
      ] },
      { type: 'h3', id: 't-terms', text: 'A legfontosabb fogalmak' },
      { type: 'list', items: [
        { t: 'Poszt állapotai:', d: 'Tervezet, Ügyfélnél (jóváhagyásra vár), Jóváhagyva, Megjelent. A Jóváhagyva nem azonos a Megjelenttel: a megjelenést a tartalomkezelő jelöli.' },
        { t: 'Publikálva:', d: 'a cikk magyar és angol ajánlója kint van a weboldalon.' },
        { t: 'Szárazfuttatás:', d: 'próbafuttatás, amely elkészíti a szövegeket, de a weboldalra nem ír.' },
      ] },
    ],
    chapters: [
      { id: 't-login', title: 'Belépés', summary: 'Meghívó, jelszó beállítása és belépés.', heading: 'Belépés és első lépések', blocks: [
        { type: 'p', text: 'A felület meghívóval érhető el. A meghívó linket az ügyfél-adminisztrátor küldi. A webcím a meghívó linkben szerepel.' },
        who('Minden új felhasználónak.'),
        { type: 'h3', id: 't-login-steps', text: 'Fiók aktiválása és belépés' },
        LOGIN_STEPS,
        fig('Belépés — e-mail-cím, jelszó és az Elfelejtett jelszó? hivatkozás'),
        { type: 'p', text: 'A saját jelszó a jobb felső profilmenü Jelszó módosítása pontjával módosítható.' },
        { type: 'tip', label: 'A kézikönyv elérése', text: 'A kézikönyv a felület bal oldali menüjének alján, a Felhasználói kézikönyv pontból nyílik meg, új ablakban.' },
        LOGIN_STUCK,
        { type: 'next', text: 'Belépés után az Áttekintés oldal nyílik meg a tartalomkezelő teendőivel.' },
      ] },
      { id: 't-overview', title: 'Áttekintés', summary: 'A tartalomkezelő teendői.', heading: 'Áttekintés', blocks: [
        { type: 'p', text: 'Az Áttekintés fent a teendőket mutatja, alatta a hónap vezetői összefoglalóját és számait. Tartalomkezelőnél jellemző teendő: Közzétehető a LinkedInen, Posztötlet generálásra vár, Feltöltésre vár.' },
        who('Tartalomkezelőnek.'),
        fig('Áttekintés — a tartalomkezelő teendői és a vezetői összefoglaló'),
        { type: 'p', text: 'A vezetői összefoglalót az ügyfél-adminisztrátor generálhatja újra; tartalomkezelőnél a gomb nem jelenik meg.' },
        { type: 'next', text: 'A teendőcsempék gombjai a megfelelő oldalra visznek.' },
      ] },
      { id: 't-upload', title: 'Feltöltés a weboldalra', summary: 'Mehet cikkek szövegei és élesítése.', heading: 'Havi cikkek és feltöltés', blocks: [
        { type: 'p', text: 'A Havi cikkek oldalon a tartalomkezelő látja a hónap cikkeit és az ügyfél-adminisztrátor döntéseit. A döntéseket nem módosíthatja, a Mehet cikkeket viszont feltöltheti a weboldalra.' },
        who('Tartalomkezelőnek.'),
        { type: 'steps', items: [
          { t: 'Mehet cikkek megnyitása.', d: 'A Mehet nézet a feltöltésre váró cikkeket mutatja.' },
          { t: 'Szövegek elkészítése.', d: 'A Feltöltés a weboldalra kártya Szövegek elkészítése (szárazon) gombja megíratja a magyar és angol címet és ajánlót. A weboldalra nem kerül semmi.' },
          { t: 'Feltöltés élesben.', d: 'A Feltöltés élesben gomb a megerősítés után kiteszi a cikkeket; utána Publikálva állapotúak, HU és EN linkkel.' },
        ] },
        fig('Havi cikkek — a Feltöltés a weboldalra kártya és a Mehet nézet'),
        { type: 'warning', text: 'A sor ⋯ menüjének Feltöltés visszavonása… pontja a weboldalról nem töröl: a bejegyzéseket a WordPress-adminban kell törölni (Bejegyzések, majd Lomtár → Végleges törlés).' },
        stuck([
          ['Nem választható döntés a cikkeknél?', 'A döntés az ügyfél-adminisztrátor feladata; tartalomkezelőnél a döntés csak olvasható.'],
          ['Tiltottak a feltöltés gombjai?', 'A weboldal-kapcsolat még nincs beállítva, vagy éppen fut egy másik feltöltés.'],
        ]),
        { type: 'next', text: 'A publikált cikkek a Publikálva nézetben láthatók.' },
      ] },
      { id: 't-publish', title: 'Posztok közzététele', summary: 'Jóváhagyott poszt kimásolása és megjelentnek jelölése.', heading: 'LinkedIn-posztok közzététele', blocks: [
        { type: 'p', text: 'A jóváhagyott posztot a tartalomkezelő teszi közzé a LinkedIn saját ütemezőjével. A felület ehhez adja a szöveget, a képet és a forráslinket. Jóváhagyáskor a tartalomkezelő e-mailt kap.' },
        who('Tartalomkezelőnek.'),
        { type: 'h3', id: 't-pub-need', text: 'A közzétételhez készítse elő' },
        bullets(['Belépést a cég LinkedIn-oldalának kezelőjeként.', 'Egy Jóváhagyva állapotú posztot.']),
        { type: 'h3', id: 't-pub-steps', text: 'Közzététel' },
        { type: 'steps', items: [
          { t: 'Poszt megnyitása.', d: 'A LinkedIn-posztok oldalon a Jóváhagyva szűrő mutatja a közzétehető posztokat.' },
          { t: 'Szöveg és kép átvétele.', d: 'A Szöveg másolása gomb a teljes posztszöveget másolja. A Kép letöltése (PNG) a 1200×1200-as képet menti. Ha van forráslink, a Forráslink (első komment) gomb másolja.' },
          { t: 'Közzététel a LinkedInen.', d: 'A LinkedIn-oldalon új bejegyzésként a szöveg és a kép beillesztése, majd azonnali közzététel vagy időzítés. A forráslink az első kommentbe kerül.' },
          { t: 'Megjelentnek jelölés.', d: 'A felületen a Megjelent a LinkedInen gomb és a megerősítés után a poszt Megjelent állapotú lesz.' },
        ] },
        { type: 'important', text: 'A Megjelent a LinkedInen gomb csak jelölés: a felület nem tesz közzé semmit a LinkedInen.' },
        fig('Jóváhagyott poszt — előnézet, Szöveg másolása, Kép letöltése és a Megjelent a LinkedInen gomb'),
        fig('Megjelentnek jelölés — megerősítő ablak'),
        { type: 'p', text: 'Telefonon a posztlista egy hasábban jelenik meg; a menü a bal felső ikonnal nyílik.' },
        fig('LinkedIn-posztok telefonon — posztkártyák állapottal'),
        stuck([
          ['Nem látszik a Megjelent a LinkedInen gomb?', 'A gomb csak Jóváhagyva állapotú posztnál jelenik meg.'],
          ['A kép nem töltődik le iPaden?', 'A képre hosszan koppintva a Kép mentése paranccsal is elmenthető.'],
        ]),
        { type: 'next', text: 'A megjelent poszt a Megjelent szűrőben látható, és tovább nem szerkeszthető.' },
      ] },
      { id: 't-edit', title: 'Posztok szerkesztése', summary: 'Szöveg és kép javítása jóváhagyás előtt.', heading: 'Posztok szerkesztése', blocks: [
        { type: 'p', text: 'A Tervezet és az Ügyfélnél állapotú poszt a Szerkesztés fülön mezőnként javítható. A jóváhagyott posztot csak az üzemeltető módosíthatja, a megjelentet senki.' },
        who('Tartalomkezelőnek.'),
        { type: 'steps', items: [
          { t: 'Szerkesztés fül megnyitása.', d: 'A poszt oldalán a Szerkesztés fül mutatja a poszt részeit: Témakör, Nyitó sor, Alternatív nyitó 1 és 2, Kontextus, Kiemelések, Szemszög, Zárás, Hashtagek, Forráslink.' },
          { t: 'Kép szövegének javítása.', d: 'A Kép címe (új sor = sortörés a képen) és a Kép alcíme mező javítása azonnal újrarajzolja a képet; ehhez nem kell új képet készíteni.' },
          { t: 'Mentés.', d: 'A Módosítások mentése gomb rögzíti a változást, az Elvetés visszaállítja a mentett állapotot.' },
        ] },
        fig('Poszt szerkesztése — a poszt részei mezőnként'),
        { type: 'p', text: 'A ⋯ menü Újragenerálás a szabályok szerint pontja az egész posztot újraírja; a kézi módosítások ekkor felülíródnak. Az ablakban az utasítás a szöveghez és a képhez módosítható. Az Új kép készítése… új képet készíttet.' },
        { type: 'next', text: 'A javított poszt az ügyfél-adminisztrátor jóváhagyására vár.' },
      ] },
      { id: 't-ideas', title: 'Posztötletek', summary: 'Saját ötletek rögzítése és generálása.', heading: 'Posztötletek', blocks: [
        ...IDEA_BLOCKS('t'),
        fig('Posztötletek — ötletek listája típussal és állapottal'),
        IDEA_STUCK,
        { type: 'next', text: 'A generált poszt az ügyfél-adminisztrátorhoz kerül jóváhagyásra.' },
      ] },
      { id: 't-rules', title: 'Posztszabályok', summary: 'A posztgenerálás szabályai.', heading: 'Posztszabályok', blocks: [
        ...RULE_BLOCKS('t'),
        fig('Posztszabályok — megnevezés, érték és a sorműveletek'),
        { type: 'next', text: 'A következő posztgenerálás már az új szabályokkal készül.' },
      ] },
    ],
  },
};
