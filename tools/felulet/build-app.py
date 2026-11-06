"""assets/app.css és assets/app.js összeállítása az Auris admin sablonból és a projektrészekből.
A sablon szakaszjelölőit keresi (nem sorszámokat), így újabb sablonverzióval is működik."""
import sys, re
S, SP, ROOT = sys.argv[1:4]
src = open(S, encoding='utf-8').read()
ver = re.search(r'v(\d+\.\d+\.\d+)', src).group(1) if re.search(r'Auris admin sablon v(\d+\.\d+\.\d+)', src) is None else re.search(r'Auris admin sablon v(\d+\.\d+\.\d+)', src).group(1)
L = src.split('\n')
def idx(pred, start=0):
    for i in range(start, len(L)):
        if pred(L[i]): return i
    raise SystemExit('nem található jelölő')
st = idx(lambda l: l.strip() == '<style>'); s18 = idx(lambda l: l.startswith('/* ================= 18.'), st); se = idx(lambda l: l.strip() == '</style>', s18)
css = '\n'.join(L[st+1:s18]) + '\n' + open(SP + '/app-extra.css', encoding='utf-8').read() + '\n' + '\n'.join(L[s18:se])
for x, y in [('--accent:#2563EB; --accent-hover:#1D4ED8; --accent-subtle:#EFF4FF; --accent-text:#1D4ED8;', '--accent:#2D388A; --accent-hover:#232C6E; --accent-subtle:#EEF0FA; --accent-text:#2D388A;'),
             ('--focus:#2563EB;', '--focus:#2D388A;'), ('--accent-subtle:#1C2A4A; --accent-text:#9DBBFD;', '--accent-subtle:#1A2350; --accent-text:#7FD3F5;'), ('--focus:#7FA6FA;', '--focus:#7FD3F5;')]:
    assert x in css, x; css = css.replace(x, y)
head_css = f'/*\n  Sajtófigyelés – felület stílusai.\n  Forrás: Auris admin sablon v{ver} (CSS 1–18.) és projektrész „P. PROJEKT” a legacy rétegben; fölötte a v2 design (tools/felulet/app-v2.css, Figma Make).\n  Generált fájl: a forrásrészek változásakor újra kell építeni.\n*/\n'
# v2 design: a régi stílusok a „legacy” rétegbe kerülnek, a Figma-alapú v2 stíluslap rétegen kívül, így mindig nyer
v2 = open(SP + '/app-v2.css', encoding='utf-8').read()
css = '@layer base, legacy;\n@layer legacy {\n' + css + '\n}\n\n' + v2
open(ROOT + '/assets/app.css', 'w', encoding='utf-8').write(head_css + css + '\n')
js0 = idx(lambda l: l.strip() == "'use strict';", se) + 1
s4 = idx(lambda l: l.startswith('   4. '), js0) - 1
s9 = idx(lambda l: l.startswith('   9. TÁBLÁZAT'), s4) - 1
s10 = idx(lambda l: l.startswith('   10. '), s9) - 1
core, dt = '\n'.join(L[js0:s4]), '\n'.join(L[s9:s10])
rep = [
 ("  function cell(c, r){\n    const v = r[c.key];", "  function cell(c, r){\n    if (c.render && c.type !== 'html') return c.render(r);\n    const v = r[c.key];"),
 ("const onPage = rows.filter(r => st.sel.has(r.id)).length, span = cols.length + 1 + (cfg.rowMenu ? 1 : 0);", "const sel = cfg.selectable !== false, onPage = rows.filter(r => st.sel.has(r.id)).length, span = cols.length + (sel ? 1 : 0) + (cfg.rowMenu ? 1 : 0);"),
 ("let h = `<table><caption class=\"sr-only\">${esc(cfg.caption)}</caption><thead><tr><th class=\"c-sel\" scope=\"col\"><input type=\"checkbox\" class=\"dt-all\" aria-label=\"Az oldal összes sorának kijelölése\" ${rows.length && onPage === rows.length ? 'checked' : ''} ${st.loading || !rows.length ? 'disabled' : ''}></th>`;",
  "let h = `<table><caption class=\"sr-only\">${esc(cfg.caption)}</caption><thead><tr>` + (sel ? `<th class=\"c-sel\" scope=\"col\"><input type=\"checkbox\" class=\"dt-all\" aria-label=\"Az oldal összes sorának kijelölése\" ${rows.length && onPage === rows.length ? 'checked' : ''} ${st.loading || !rows.length ? 'disabled' : ''}></th>` : '');"),
 ("""h += '<tr aria-hidden="true"><td class="c-sel"><span class="sk" style="width:20px"></span></td>' + cols""", """h += '<tr aria-hidden="true">' + (sel ? '<td class="c-sel"><span class="sk" style="width:20px"></span></td>' : '') + cols"""),
 ("""h += `<tr${rc ? ` class="${rc}"` : ''}><td class="c-sel"><input type="checkbox" class="dt-row" data-id="${esc(r.id)}" aria-label="Kijelölés: ${lbl}" ${s ? 'checked' : ''}></td>` + cols""",
  """h += `<tr${rc ? ` class="${rc}"` : ''}>` + (sel ? `<td class="c-sel"><input type="checkbox" class="dt-row" data-id="${esc(r.id)}" aria-label="Kijelölés: ${lbl}" ${s ? 'checked' : ''}></td>` : '') + cols"""),
 ("    load(ms = 700){ st.loading = true; render(); setTimeout(() => { st.loading = false; render(); }, ms); },", "    load(ms = 700){ st.loading = true; render(); setTimeout(() => { st.loading = false; render(); }, ms); },\n    setLoading(on){ st.loading = !!on; render(); },"),
 ("const rowMenu = $('#rowMenu'); let rmCtx = null;", "const rowMenu = $('#rowMenu') || document.createElement('div'); let rmCtx = null;"),
]
for x, y in rep:
    assert dt.count(x) == 1, x[:70]; dt = dt.replace(x, y)
head_js = f'/*\n  Sajtófigyelés – felület.\n  Forrás: Auris admin sablon v{ver} (JS 0–3., 9. és sorművelet-menü; DataTable kiegészítés: render, selectable:false, setLoading),\n  projektrész: 12–14. szekció. Generált fájl.\n*/\n'
js = head_js + "(() => {\n'use strict';\n" + core + '\n\n' + dt + '\n' + open(SP + '/app-pages.js', encoding='utf-8').read() + '\n})();\n'
open(ROOT + '/assets/app.js', 'w', encoding='utf-8').write(js)
print('sablon', ver, len(css), len(js))
