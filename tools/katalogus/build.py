"""Komponenskatalógus összeállítása: az alkalmazás saját CSS-e + ikonkészlet + kézzel írt alapelemek + a helyi felületről kigyűjtött valódi elemek."""
import json, re, sys, html

ROOT = '/home/user/auto-news-riport/'
data = json.load(open(sys.argv[1], encoding='utf-8'))
out_artifact, out_repo = sys.argv[2], sys.argv[3]
css = open(ROOT + 'assets/app.css', encoding='utf-8').read()
version = re.search(r"XPERION_VERSION', '([^']+)'", open(ROOT + 'app/bootstrap.php', encoding='utf-8').read()).group(1)
sprite = data.get('sprite') or ''
icons = re.findall(r'<symbol id="i-([a-z0-9-]+)"', sprite)

def ic(i, cls='icon icon-sm'):
    return f'<svg class="{cls}" aria-hidden="true"><use href="#i-{i}"/></svg>'

def clean(h):
    h = re.sub(r'<script\b.*?</script>', '', h, flags=re.S)
    h = h.replace('autofocus', '')
    return h

def opened(h):
    """Megnyitott dialógus / menü statikus bemutatásra."""
    h = clean(h)
    h = re.sub(r'<dialog\b([^>]*)>', lambda m: '<dialog' + re.sub(r'\sopen(="")?', '', m.group(1)) + ' open class="kat-dlg">', h, count=1)
    h = re.sub(r'(<div class="pop[^"]*"[^>]*?)\shidden(="")?', r'\1', h, count=1)
    return h

TOKENS = re.findall(r'(--[a-z0-9-]+):', css[:css.index('}') + 1])

sections = []  # (id, cím, [blokkok])
def block(title, cls, where, body, note='', wide=False):
    return f'''<article class="kat-block{' kat-wide' if wide else ''}">
  <header class="kat-bh"><h3>{title}</h3>{f'<p>{note}</p>' if note else ''}<dl><div><dt>Osztály</dt><dd><code>{html.escape(cls)}</code></dd></div>{f'<div><dt>Hol</dt><dd>{where}</dd></div>' if where else ''}</dl></header>
  <div class="kat-stage">{body}</div>
</article>'''

# ---------- 1. Alapok ----------
sw = ''.join(f'<div class="kat-sw" data-tok="{t}"><span class="kat-chip" style="background:var({t})"></span><b>{t}</b><span class="kat-val"></span></div>'
             for t in TOKENS if not re.match(r'--(font|fs|ctl|row|pad|gap|nav|aside|topbar|ease|r-|shadow)', t))
sizes = ''.join(f'<div class="kat-sw" data-tok="{t}"><b>{t}</b><span class="kat-val"></span></div>' for t in TOKENS if re.match(r'--(font|fs|ctl|row|pad|gap|nav|aside|topbar|ease|r-)', t))
shadows = ''.join(f'<div class="kat-shadow" style="box-shadow:var({t});border-radius:var(--r-lg)"><code>{t}</code></div>' for t in ('--shadow-sm', '--shadow-md', '--shadow-lg'))
typo = '''<div class="page-head"><div><div class="page-title"><h1>Havi cikkek</h1><span class="badge b-ai">''' + ic('sparkle', 'icon') + '''AI által generált</span></div><p class="page-sub">Oldalcím (h1) és alcím (.page-sub). 2026. október · 14 cikk</p></div><div class="page-actions"><button class="btn btn-primary" type="button">''' + ic('plus') + '''Új ötlet</button></div></div>
<h2 class="sec-title">Szekciócím (.sec-title) <span class="meta">· 4 tétel</span></h2>
<p>Törzsszöveg: a havi gyűjtés a figyelt oldalakból és a kulcsszavakból dolgozik. <a href="#">Link a szövegben</a>.</p>
<p class="meta">Másodlagos szöveg (.meta): 2026. 11. 03. 14:32 · Teszt Anna</p>
<div class="field"><span class="help">Súgószöveg mező alatt (.help)</span><span class="error">Hibaszöveg (.error): adjon meg egy http(s)://-sel kezdődő címet.</span></div>'''
icongrid = ''.join(f'<div class="kat-ic">{ic(i, "icon")}<code>{i}</code></div>' for i in icons)
sections.append(('alapok', 'Alapok', [
    block('Színtokenek', ':root változók (világos és sötét téma)', 'mindenhol; a sötét téma a <code>prefers-color-scheme</code> és a <code>data-theme</code> szerint', f'<div class="kat-sws">{sw}</div>', 'Az értékek az éppen választott téma szerint látszanak (a fenti témaváltóval).', wide=True),
    block('Méretek, betű, sűrűség', ':root változók; kompakt mód: data-density="compact"', 'mindenhol', f'<div class="kat-sws kat-sizes">{sizes}</div><div class="kat-shadows">{shadows}</div>', wide=True),
    block('Tipográfia', '.page-head, .page-title, h1, .page-sub, .sec-title, .meta, .help, .error', 'minden oldal fejléce, kártyák', typo, wide=True),
    block(f'Ikonok ({len(icons)})', 'svg.icon > use[href="#i-…"]', 'gombok, menü, jelvények, csempék', f'<div class="kat-icons">{icongrid}</div>', 'Lucide-stílusú vonalikonok egy SVG-készletben; méret: .icon (20 px), .icon-sm, .icon-xs.', wide=True),
]))

# ---------- 2. Alapelemek ----------
btns = f'''<div class="kat-row"><button class="btn btn-primary" type="button">{ic('check')}Jóváhagyom</button><button class="btn btn-secondary" type="button">{ic('msg')}Módosítást kérek</button><button class="btn btn-ghost" type="button">{ic('chev-left')}Posztok</button><button class="btn btn-danger" type="button">{ic('trash')}Forrás törlése</button></div>
<div class="kat-row"><button class="btn btn-primary sm" type="button">{ic('plus')}Új forrás</button><button class="btn btn-secondary sm" type="button">{ic('flask')}Próbagyűjtés</button><button class="btn btn-ghost sm" type="button">Csak mentés</button><button class="btn btn-danger sm" type="button">Törlés</button></div>
<div class="kat-row"><button class="btn btn-primary" type="button" disabled title="Nincs kijelölt cikk">{ic('upload')}Feltöltés élesben (tiltva)</button>{clean(data['states'].get('busyBtn', ''))}<button class="btn btn-secondary" type="button" aria-haspopup="menu">{ic('sparkle')}AI-generálás{ic('chev-down')}</button></div>
<div class="kat-row"><button class="icon-btn" type="button" aria-label="Szerkesztés">{ic('edit')}</button><button class="icon-btn" type="button" aria-label="Szüneteltetés">{ic('eye-off')}</button><button class="icon-btn" type="button" aria-label="Törlés">{ic('trash')}</button><button class="icon-btn" type="button" aria-label="További műveletek">{ic('more')}</button><button class="linklike" type="button">több</button><a class="btn btn-ghost sm tw-link" href="#">Döntések{ic('chev-right')}</a></div>'''
badges = f'''<div class="kat-row"><span class="badge b-success">{ic('check','icon')}aktív</span><span class="badge b-warning">{ic('clock','icon')}Ügyfélnél</span><span class="badge b-danger">{ic('x','icon')}kizáró</span><span class="badge b-info">{ic('linkedin','icon')}Megjelent</span><span class="badge b-neutral">{ic('minus','icon')}szünetel</span><span class="badge b-ai">{ic('sparkle','icon')}AI által generált</span></div>
<div class="kat-row"><span class="tab-like">Döntésre vár <span class="cnt-badge">4</span></span><span>Források <span class="count">12</span></span><span class="avatar" aria-hidden="true">MP</span></div>
<div class="kat-row">{''.join(f'<span class="ic-tile {t}" aria-hidden="true">{ic(i, "icon")}</span>' for t, i in [('t-blue','list'),('t-green','upload'),('t-orange','send'),('t-purple','sparkle'),('t-pink','bulb'),('t-li','linkedin'),('t-success','check'),('t-warning','alert'),('t-error','x-circle'),('t-info','info')])}</div>'''
tips = f'''<div class="kat-row" style="padding-top:110px"><span>Alap súgó</span><span class="tip kat-tip-open"><button type="button" class="tip-btn" aria-label="Súgó: feltöltés" aria-describedby="kt1">{ic('info','icon')}</button><span class="tip-body" role="tooltip" id="kt1">A Claude megírja a Mehet cikkek magyar és angol címét és ajánlóját (cikkenként 2–3 cent).</span></span>
<span style="flex:1"></span><span>Sor végi (.tip-end)</span><span class="tip tip-end kat-tip-open"><button type="button" class="tip-btn" aria-label="Súgó: új-forrás-figyelő" aria-describedby="kt2">{ic('info','icon')}</button><span class="tip-body" role="tooltip" id="kt2">Keresésenként kb. 5–15 cent, 1–3 perc.</span></span></div>'''
forms = f'''<div class="grid-2">
<div class="field"><label for="kf1">Cím (hírlista vagy RSS)</label><input class="input" id="kf1" type="url" value="https://www.vemcogroup.com/blog/rss.xml"></div>
<div class="field"><label for="kf2">Név <span class="opt">(nem kötelező)</span></label><input class="input" id="kf2" placeholder="a webhely neve, ha üres"></div>
<div class="field"><label for="kf3">Hibás mező</label><input class="input" id="kf3" aria-invalid="true" value="vemco"><span class="error">A forrás címe http(s)://-sel kezdődő webcím legyen.</span></div>
<div class="field"><label for="kf4">Nyelv</label><select class="select" id="kf4"><option>mindegy</option><option>magyar</option><option>angol</option></select><span class="help">Súgószöveg a mező alatt.</span></div>
<div class="field full"><label for="kf5">Utasítás a szöveghez <span class="opt">(nem kötelező)</span></label><textarea class="input" id="kf5" rows="3">A bankfiókok vezetőinek szóljon; emelje ki a várakozási idő csökkenését.</textarea></div>
<div class="field"><div class="search">{ic('search','icon')}<label class="sr-only" for="kf6">Keresés</label><input class="input" id="kf6" type="search" placeholder="Keresés címben, leírásban…"></div></div>
<div class="field"><label for="kf7">Hány új forrást keressen?</label><input class="input" id="kf7" type="number" min="1" max="10" value="3" style="max-width:8rem"></div>
<div class="field full"><label class="choice"><input type="checkbox" checked> Aktív (a havi gyűjtés figyeli)</label><label class="choice"><input type="checkbox"> Kizáró kulcsszó (az ilyen témájú cikk kimarad)</label><label class="choice"><input type="radio" name="kr" checked> Jelenlegi nyitó sor</label><label class="choice"><input type="radio" name="kr"> Alternatív 1</label></div>
</div>'''
segs = f'''<div class="kat-row"><div class="seg" role="group" aria-label="Állapot"><button type="button" aria-pressed="true">Mind</button><button type="button" aria-pressed="false">Tervezet</button><button type="button" aria-pressed="false">Ügyfélnél</button><button type="button" aria-pressed="false">Jóváhagyva</button></div></div>
<div class="tabs" role="tablist" aria-label="Minta fülek"><button class="tab" role="tab" aria-selected="true" aria-controls="kp1" id="kt-1">{ic('eye')}Előnézet</button><button class="tab" role="tab" aria-selected="false" aria-controls="kp2" id="kt-2" tabindex="-1">{ic('edit')}Szerkesztés</button><button class="tab" role="tab" aria-selected="false" aria-controls="kp3" id="kt-3" tabindex="-1">{ic('clock')}Előzmények <span class="count">7</span></button></div>
<div id="kp1" role="tabpanel"><p class="meta" style="padding:12px 0">Az Előnézet fül tartalma.</p></div><div id="kp2" role="tabpanel" hidden><p class="meta" style="padding:12px 0">A Szerkesztés fül tartalma.</p></div><div id="kp3" role="tabpanel" hidden><p class="meta" style="padding:12px 0">Az Előzmények fül tartalma.</p></div>'''
banners = f'''<div class="stack">
<div class="banner bn-info" role="status">{ic('linkedin','icon')}<div class="b-body"><span><b>3 LinkedIn-cikk vár posztra.</b> Jelöljön ki egyet vagy többet, majd: Poszt készítése a kijelöltekből.</span></div></div>
<div class="banner bn-success" role="status">{ic('check','icon')}<div class="b-body"><span>A jelszó módosítva.</span></div></div>
<div class="banner bn-warning" role="status">{ic('alert','icon')}<div class="b-body"><b>Nincs beállítva alapkategória</b><span>A WordPress-bővítményben a magyar alapkategória üres.</span></div></div>
<div class="banner bn-error" role="alert">{ic('x-circle','icon')}<div class="b-body"><b>A WordPress elutasította a belépést (401)</b><span>Az alkalmazásjelszó hibás vagy visszavonták. Új alkalmazásjelszó: WordPress-admin → Felhasználók → Profil → Alkalmazásjelszavak.</span></div></div>
{clean(data['states'].get('workBanner', ''))}
</div>'''
toast = lambda t, i, m, act=False: f'<div class="toast t-{t}">{ic(i, "icon")}<span class="tmsg">{m}</span>{"<button type=\"button\" class=\"tact\">Visszavonás</button>" if act else ""}<button type="button" class="icon-btn tclose" aria-label="Értesítés bezárása">{ic("x")}</button></div>'
toasts = '<div class="toasts kat-static">' + toast('success', 'check', 'A forrás mentve. A következő havi gyűjtés már figyeli.') + toast('success', 'check', 'Mehet: 2 cikk. A következő óránkénti futás tölti fel.', True) + toast('info', 'info', 'A javaslat elvetve; a figyelő többé nem javasolja.') + toast('warning', 'alert', 'Legalább egy oszlopnak láthatónak kell maradnia.') + toast('error', 'x-circle', 'A Claude API nem érhető el (időtúllépés). Próbálja újra pár perc múlva.') + '</div>'
empties = f'''<div class="stack"><div class="empty-state"><b>Még nincs forrás</b><span>Az „Új forrás” gombbal vegye fel az elsőt (hírlista vagy RSS címe), vagy vegye át a Google Sheetből.</span></div>
<div class="card table-card"><div class="table-wrap"><table><thead><tr><th scope="col">ID</th><th scope="col">Téma és üzenet</th><th scope="col">Állapot</th></tr></thead><tbody><tr><td colspan="3" class="dt-empty-cell">Még nincs beküldött ötlet. Az „Új ötlet” gombbal küldheti be az elsőt.</td></tr></tbody></table></div></div>
<div class="card widget"><span class="sk" style="width:40%"></span><span class="sk" style="width:85%;margin-top:10px"></span><span class="sk" style="width:65%;margin-top:10px"></span><p class="meta" style="margin-top:12px">Betöltés: csontváz (.sk), gombban forgó jel (aria-busy).</p></div>
<div class="kat-row"><span class="spinner" aria-hidden="true"></span><span class="meta">Forgó jel (.spinner)</span></div></div>'''
sections.append(('alapelemek', 'Alapelemek', [
    block('Gombok', '.btn .btn-primary | .btn-secondary | .btn-ghost | .btn-danger, .sm; .icon-btn; .linklike; aria-busy', 'mindenhol; oldalfejléc jobb oldala, kártyák, dialógusok', btns, 'Egy képernyőn egy elsődleges gomb. Tiltott gombnál mindig van ok (title vagy aria-describedby).', wide=True),
    block('Jelvények, számlálók, ikoncsempék', '.badge .b-success|b-warning|b-danger|b-info|b-neutral|b-ai; .count; .cnt-badge; .ic-tile .t-*; .avatar', 'állapotok (cikk, poszt, forrás), widgetfejek, fülek', badges, 'Szín sosem egyedül jelez: ikon + szöveg is van.', wide=True),
    block('Súgó (i)', '.tip > button.tip-btn + .tip-body[role=tooltip]; .tip-end', 'gombsorok mellett, a gombok alatti magyarázó szöveg helyett', tips, 'Rámutatásra és fókuszra nyílik, Esc zárja; érintésen koppintásra. Itt nyitott állapotban látszik.', wide=True),
    block('Űrlapelemek', '.field > label + .input|.select|textarea; .opt; .help; .error; [aria-invalid]; .search; .choice; .grid-2', 'dialógusok, szerkesztő űrlapok, profil, ötlet', forms, wide=True),
    block('Szegmensválasztó és fülek', '.seg > button[aria-pressed]; .tabs > .tab[role=tab]', 'szűrők (posztok, cikknézetek), oldalon belüli fülek (poszt, források)', segs, 'A fülek ebben a katalógusban is kattinthatók.', wide=True),
    block('Sávok (bannerek)', '.banner .bn-info|bn-success|bn-warning|bn-error; .work-banner', 'oldal tetején: tájékoztatás, hiba, futásjelző', banners, wide=True),
    block('Értesítések (toast)', '.toasts > .toast.t-success|t-info|t-warning|t-error; .tact; .tclose', 'jobb alsó sarok; itt a helyén, statikusan', toasts, 'Hiba és visszavonható művelet toastja nem tűnik el magától.', wide=True),
    block('Üres állapot, töltés', '.empty-state; td.dt-empty-cell; .sk; .spinner', 'üres listák és táblák, töltés közben', empties, wide=True),
]))

# ---------- 3. Felugró elemek ----------
S = data['states']
pops = [('Ügyfélváltó menü', '.pop.menu (#wsMenu)', 'felső sáv, ügyfél neve', S.get('wsMenu')), ('Profilmenü', '.pop.right.menu (#profileMenu), .menu-head', 'felső sáv, avatar', S.get('profileMenu')),
        ('Sor ⋯ menü', '.pop.menu (#rowMenu), button[role=menuitem], .danger', 'táblázatsorok vége', S.get('rowMenu')), ('AI-generálás menü', '.pop.menu', 'poszt oldala, Jóváhagyom mellett', S.get('aiMenu'))]
dlgs = [('Megerősítés (törlés)', 'dialog .dlg-body + .dlg-foot, .btn-danger', 'visszafordíthatatlan művelet előtt', S.get('confirm')), ('Megerősítés szöveges válasszal', 'dialog + .field', 'Módosítást kérek', S.get('inputConfirm')),
        ('Űrlapdialógus: poszt készítése', 'dialog#fDlg (formBox), .dlg-intro', 'Havi cikkek → Poszt készítése a kijelöltekből', S.get('genDialog')), ('Űrlapdialógus: összefoglaló küldése', 'dialog#fDlg', 'Vezetői összefoglaló → Küldés', S.get('sumSend')),
        ('Szerkesztő dialógus: új forrás', 'dialog#srcDlg, .acts-row, próbagyűjtés', 'Források', S.get('srcDlg')), ('Meghívás', 'dialog#userDlg', 'Felhasználók', S.get('userDlg'))]
sections.append(('felugro', 'Menük és dialógusok', [block(t, c, w, f'<div class="kat-static">{opened(h)}</div>') for t, c, w, h in pops + dlgs if h]))

# ---------- 4. Összetett elemek ----------
shell = clean(S.get('shell', '')).replace('<!--TARTALOM-->', '<div class="kat-ph">Ide kerül az oldal tartalma (lásd az Oldalak részt).</div>')
comp = [
    block('Alkalmazáskeret', '.shell > .sidebar (.brand, .nav-item, .nav-group-title, .sidebar-foot, .nav-ext) + .main-col > .topbar (.workspace, .topbar-actions, .profile-btn) + main.content + .app-foot', 'minden belépés utáni oldal', f'<div class="kat-frame kat-shell">{shell}</div>', 'Az oldalsáv összecsukható (data-nav="closed"); mobilon a ☰ gomb nyitja.', wide=True),
    block('Táblázat kijelöléssel és tömeges műveletsávval', '.card.table-card > .card-views (.seg) + .bulkbar + .toolbar + .table-wrap > table + .pager; .dt-row; .c-act', 'Havi cikkek (LinkedIn poszt nézet, két cikk kijelölve)', f'<div class="kat-frame">{clean(S.get("bulk", ""))}</div>', wide=True),
    block('Futáskártya eredménnyel', '.card.widget, .acts-row, .result-meta, ol.run-lines, .mini-table', 'Vezérlőpult → Csak gyűjtés (szárazfuttatás)', f'<div class="kat-frame">{clean(S.get("collectRun", ""))}</div>', 'A tesztkörnyezetben a forrásoldalak nem érhetők el, ezért hibasorok is látszanak – ez a hibás állapot mintája is.', wide=True),
    block('Feltöltés kártya', '.card.widget#artUpload, .head-actions, .tip', 'Havi cikkek', f'<div class="kat-frame">{clean(S.get("upload", ""))}</div>', wide=True),
]
sections.append(('osszetett', 'Összetett elemek', comp))

# ---------- 5. Oldalak ----------
P = data['pages']
pages = [('belepes', 'Belépés', 'index.php?p=belepes', None, 'Belépés nélküli oldalak kerete (.auth, .auth-side, .auth-main); ugyanígy néz ki a meghívó és a jelszó-visszaállítás.'),
         ('attekintes', 'Áttekintés', 'index.php?p=attekintes', P.get('attekintes'), 'Teendőcsempék (.todo-tiles .todo-w), vezetői összefoglaló (.sum-groups), KPI-k (.kpis .kpi), megoszlás (.stack-bar), forrásonkénti sávdiagram (.hbar) és minitábla.'),
         ('cikkek', 'Havi cikkek', 'index.php?p=cikkek&honap=2610', P.get('cikkek'), 'Összefoglaló, feltöltés kártya, nézetválasztó a táblázat fejlécében, szűrők, táblázat döntésválasztóval, lapozó.'),
         ('posztok', 'LinkedIn-posztok', 'index.php?p=posztok', P.get('posztok'), 'Posztkártyák mini LinkedIn-előnézettel (.post-card .mini-li), állapotszűrő, lapozó.'),
         ('poszt', 'Egy poszt (jóváhagyásra vár)', 'index.php?p=poszt&kod=LI-2610-01', P.get('poszt'), 'LinkedIn-előnézet (.li-card), fülek (Előnézet, Szerkesztés, Adatok, Előzmények), jobb oldalon Közzétételhez, Nyitó sor (.open-choices), Forráscikkek (.arts-list).'),
         ('poszt-jovahagyott', 'Egy poszt (jóváhagyva)', 'index.php?p=poszt&kod=LI-2610-02', P.get('poszt-jovahagyott'), 'A tartalomkezelő nézőpontja: Megjelent a LinkedInen gomb.'),
         ('otletek', 'Posztötletek', 'index.php?p=otletek', P.get('otletek'), 'Lista szűrőkkel, állapotjelvényekkel.'),
         ('otlet', 'Új posztötlet', 'index.php?p=otlet', P.get('otlet'), 'Hosszú űrlap két oszlopban, rögzített mentéssáv.'),
         ('szabalyok', 'Posztszabályok', 'index.php?p=szabalyok', P.get('szabalyok'), 'Rendezhető szabálylista sorműveletekkel.'),
         ('forrasok', 'Források és kulcsszavak', 'index.php?p=forrasok', P.get('forrasok'), 'Három fül: Források, Kulcsszavak, Javasolt források (új-forrás-figyelő).'),
         ('felhasznalok', 'Felhasználók', 'index.php?p=felhasznalok', P.get('felhasznalok'), 'Felhasználólista szerepkörökkel, meghívás.'),
         ('ugyfelek', 'Ügyfelek', 'index.php?p=ugyfelek', P.get('ugyfelek'), 'Lista + alatta tabos részletkártya (.detail-card): beállítási lépések (.checklist), adatátvétel, havi e-mail, beállítások.'),
         ('vezerlo', 'Vezérlőpult', 'index.php?p=vezerlo', P.get('vezerlo'), 'Futások állapota (.rs-list), havi futás, feltöltés, havi e-mail előnézettel, rendszerállapot.'),
         ('naplo', 'Napló', 'index.php?p=naplo', P.get('naplo'), 'Szűrősor (dátum, felhasználó, kategória), naplótábla, CSV-letöltés.'),
         ('profil', 'Profil', 'index.php?p=profil', P.get('profil'), 'Jelszómódosítás és fiókadatok.')]
pg = []
for pid, t, url, h, note in pages:
    h = re.sub(r'\bname="([^"]+)"', lambda m: f'name="{m.group(1)}-{pid}"', clean(h or ''))  # oldalanként külön rádiócsoport
    body = f'<div class="kat-frame kat-auth">{clean(S.get("auth", ""))}</div>' if pid == 'belepes' else f'<div class="kat-frame"><main class="content kat-content">{h}</main></div>'
    pg.append(f'<article class="kat-block kat-wide kat-page" id="o-{pid}"><header class="kat-bh"><h3>{t}</h3><p>{note}</p><dl><div><dt>Cím</dt><dd><code>{html.escape(url)}</code></dd></div></dl></header>{body}</article>')
sections.append(('oldalak', 'Oldalak', pg))

KAT_CSS = '''
/* Katalógus-keret: az alkalmazás tokenjeit használja, hogy mindkét témában olvasható legyen */
.kat{ --kat-max:1400px; }
.kat-top{ position:sticky; top:env(safe-area-inset-top,0px); z-index:60; background:var(--surface); border-bottom:1px solid var(--border); }
body::before{ content:""; position:fixed; inset:0 0 auto; height:env(safe-area-inset-top,0px); z-index:61; background:var(--surface); }
.kat-top-in, .kat-main{ max-width:var(--kat-max); margin:0 auto; padding-left:max(16px, env(safe-area-inset-left,0px)); padding-right:max(16px, env(safe-area-inset-right,0px)); }
.kat-top-in{ display:flex; flex-wrap:wrap; align-items:center; gap:8px 16px; padding-block:10px; }
.kat-top-in b{ font-size:var(--fs); }
.kat-top-in .meta{ margin:0; }
.kat-toc{ display:flex; flex-wrap:wrap; gap:4px; flex:1; min-width:0; }
.kat-toc a{ padding:6px 10px; border-radius:var(--r-md); color:var(--text-2); text-decoration:none; font-size:var(--fs-sm); font-weight:500; }
.kat-toc a:hover{ background:var(--surface-subtle); color:var(--text); }
.kat-main{ padding-block:24px 64px; display:flex; flex-direction:column; gap:48px; }
.kat-intro{ max-width:72ch; display:flex; flex-direction:column; gap:8px; }
.kat-intro h1{ margin:0; font-size:28px; letter-spacing:-.01em; text-wrap:balance; }
.kat-intro p{ margin:0; color:var(--text-2); line-height:1.6; }
.kat-sec{ display:flex; flex-direction:column; gap:20px; scroll-margin-top:72px; }
.kat-sec > h2{ margin:0; font-size:22px; padding-bottom:8px; border-bottom:2px solid var(--text); }
.kat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%, 520px), 1fr)); gap:20px; align-items:start; }
.kat-block{ min-width:0; display:flex; flex-direction:column; gap:12px; }
.kat-wide{ grid-column:1 / -1; }
.kat-bh h3{ margin:0; font-size:var(--fs); }
.kat-bh p{ margin:4px 0 0; color:var(--text-2); font-size:var(--fs-sm); max-width:90ch; }
.kat-bh dl{ margin:6px 0 0; display:flex; flex-direction:column; gap:2px; font-size:var(--fs-xs); color:var(--text-3); }
.kat-bh dl div{ display:flex; gap:6px; }
.kat-bh dt{ font-weight:600; flex:none; }
.kat-bh dd{ margin:0; min-width:0; overflow-wrap:anywhere; }
.kat-bh code, .kat-ic code, .kat-sw b, .kat-shadow code{ font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
.kat-stage{ background:var(--bg); border:1px dashed var(--border-strong); border-radius:var(--r-lg); padding:20px; min-width:0; overflow-x:auto; display:flex; flex-direction:column; gap:12px; }
.kat-row{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.kat-sws{ display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:8px; }
.kat-sw{ display:grid; grid-template-columns:36px 1fr; grid-template-rows:auto auto; column-gap:10px; align-items:center; padding:8px; background:var(--surface); border:1px solid var(--border); border-radius:var(--r-md); }
.kat-sizes .kat-sw{ grid-template-columns:1fr; }
.kat-chip{ grid-row:1 / 3; width:36px; height:36px; border-radius:var(--r-sm); border:1px solid var(--border); }
.kat-val{ font-size:var(--fs-xs); color:var(--text-3); overflow-wrap:anywhere; }
.kat-shadows{ display:flex; flex-wrap:wrap; gap:16px; }
.kat-shadow{ background:var(--surface); padding:20px 24px; }
.kat-icons{ display:grid; grid-template-columns:repeat(auto-fill, minmax(104px, 1fr)); gap:6px; }
.kat-ic{ display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 4px; background:var(--surface); border:1px solid var(--border); border-radius:var(--r-md); color:var(--text); }
.kat-ic code{ color:var(--text-3); }
.kat-tip-open .tip-body{ opacity:1 !important; visibility:visible !important; }
.kat-static{ display:flex; flex-direction:column; gap:12px; align-items:flex-start; }
.kat-static .pop{ position:static !important; display:block; }
.kat-static .toasts, .toasts.kat-static{ position:static; width:min(400px, 100%); }
dialog.kat-dlg{ position:static; margin:0; display:block; max-width:100%; }
.kat-frame{ background:var(--bg); border:1px solid var(--border-strong); border-radius:var(--r-lg); overflow:auto; min-width:0; }
.kat-frame > .card, .kat-frame > section, .kat-frame > div:not(.shell):not(.auth){ margin:16px; }
.kat-content{ max-width:none !important; margin:0 !important; }
.kat-shell .shell{ min-height:0; height:640px; }
.kat-shell .sidebar{ position:relative; height:auto; }
.kat-ph{ border:2px dashed var(--border-strong); border-radius:var(--r-lg); padding:48px 24px; text-align:center; color:var(--text-3); }
.kat-auth .auth{ min-height:640px; }
.kat-page .kat-frame{ max-height:none; }
@media (max-width:640px){ .kat-stage{ padding:12px; } .kat-top-in{ padding-block:8px; } }
'''

JS = '''
(function(){
  var root = document.documentElement;
  function setTheme(v){ if (v === 'system') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', v);
    document.querySelectorAll('#katTheme button').forEach(function(b){ b.setAttribute('aria-pressed', String(b.dataset.t === v)); }); paint(); }
  function paint(){ var cs = getComputedStyle(root); document.querySelectorAll('.kat-sw').forEach(function(s){ s.querySelector('.kat-val').textContent = cs.getPropertyValue(s.dataset.tok).trim(); }); }
  document.querySelectorAll('#katTheme button').forEach(function(b){ b.addEventListener('click', function(){ setTheme(b.dataset.t); }); });
  document.getElementById('katDensity').addEventListener('click', function(e){ var on = e.currentTarget.getAttribute('aria-pressed') !== 'true'; e.currentTarget.setAttribute('aria-pressed', String(on)); if (on) root.setAttribute('data-density', 'compact'); else root.removeAttribute('data-density'); paint(); });
  if (window.matchMedia) matchMedia('(prefers-color-scheme: dark)').addEventListener('change', paint);
  /* fülek és szegmensválasztók: a katalógusban is kattinthatók (a saját blokkjukon belül) */
  document.addEventListener('click', function(e){
    var t = e.target.closest('[role=tab]');
    if (t){ var scope = t.closest('.kat-stage, .kat-frame') || document, list = t.closest('[role=tablist]');
      list.querySelectorAll('[role=tab]').forEach(function(x){ var on = x === t; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; var id = x.getAttribute('aria-controls'), p = id && scope.querySelector('[id="' + id + '"]'); if (p) p.hidden = !on; }); }
    var s = e.target.closest('.seg button[aria-pressed]');
    if (s){ s.parentElement.querySelectorAll('button[aria-pressed]').forEach(function(x){ x.setAttribute('aria-pressed', String(x === s)); }); }
    var a = e.target.closest('a[href]'); if (a && !/^#/.test(a.getAttribute('href'))) e.preventDefault();
  });
  document.addEventListener('submit', function(e){ e.preventDefault(); });
  paint();
})();
'''

secs_html = ''.join(f'<section class="kat-sec" id="{sid}" aria-labelledby="h-{sid}"><h2 id="h-{sid}">{t}</h2><div class="kat-grid">{"".join(blocks)}</div></section>' for sid, t, blocks in sections)
toc = ''.join(f'<a href="#{sid}">{t}</a>' for sid, t, _ in sections)
page_toc = ' · '.join(f'<a href="#o-{pid}">{t}</a>' for pid, t, *_ in pages)
body = f'''{sprite}
<div class="kat">
<header class="kat-top"><div class="kat-top-in"><b>Sajtófigyelés · komponensek</b><nav class="kat-toc" aria-label="Tartalom">{toc}</nav>
<div class="seg" role="group" aria-label="Téma" id="katTheme"><button type="button" data-t="system" aria-pressed="true">Rendszer</button><button type="button" data-t="light" aria-pressed="false">Világos</button><button type="button" data-t="dark" aria-pressed="false">Sötét</button></div>
<button class="btn btn-secondary sm" type="button" id="katDensity" aria-pressed="false">{ic('rows')}Kompakt</button></div></header>
<div class="kat-main">
<div class="kat-intro"><h1>Sajtófigyelés – a felület összes eleme</h1>
<p>Ez az oldal az alkalmazás ({version}) minden felületi elemét egy helyen mutatja, újratervezéshez. Az elemek az alkalmazás saját stíluslapjával jelennek meg, a valódi felületről kigyűjtve, kitalált tesztadatokkal (Minta Péter, Teszt Anna, példacikkek).</p>
<p>Minden blokk megnevezi az elem osztályait és azt, hol használjuk. Az újratervezés a színtokenek és ezeknek az osztályoknak a szintjén vihető vissza a kódba. Fent a téma (világos, sötét) és a sűrűség (kényelmes, kompakt) váltható.</p>
<p class="meta">Oldalak: {page_toc}</p></div>
{secs_html}
</div></div>'''

head_common = f'''<title>Sajtófigyelés komponenskatalógus</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<style>{css}</style>
<style>{KAT_CSS}</style>'''
open(out_artifact, 'w', encoding='utf-8').write(head_common + '\n' + body + f'\n<script>{JS}</script>\n')
open(out_repo, 'w', encoding='utf-8').write(f'''<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
{head_common}
</head>
<body>
{body}
<script>{JS}</script>
</body>
</html>
''')
print('kész', len(icons), 'ikon', len(TOKENS), 'token')
