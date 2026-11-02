/*
  Sajtófigyelés – felület.
  Forrás: Auris admin sablon v1.0.2 (JS 0–3., 9. és sorművelet-menü; DataTable kiegészítés: render, selectable:false, setLoading),
  projektrész: 12–14. szekció. Generált fájl.
*/
(() => {
'use strict';
/* ==================================================================
   0. SEGÉDEK
   ================================================================== */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const store = { get(k){ try{ return localStorage.getItem(k); }catch(e){ return null; } }, set(k,v){ try{ localStorage.setItem(k,v); }catch(e){} } };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const norm = s => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
const ic = (id, cls = 'icon icon-sm') => `<svg class="${cls}" aria-hidden="true"><use href="#i-${id}"/></svg>`;
const HU = {
  date: new Intl.DateTimeFormat('hu-HU', { year:'numeric', month:'2-digit', day:'2-digit' }),
  time: new Intl.DateTimeFormat('hu-HU', { hour:'2-digit', minute:'2-digit' }),
  num:  new Intl.NumberFormat('hu-HU', { maximumFractionDigits:0 })
};
const fmtDate = d => d ? HU.date.format(d) : '';
const fmtMoney = n => HU.num.format(n) + '\u00a0Ft';
const now = () => HU.time.format(new Date());
const pad = n => String(n).padStart(2,'0');
const today = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), d.getDate()); };
const sameDay = (a,b) => a && b && a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();
const addDays = (d,n) => new Date(d.getFullYear(), d.getMonth(), d.getDate()+n);
const fmtInput = d => d ? `${d.getFullYear()}.${pad(d.getMonth()+1)}.${pad(d.getDate())}.` : '';
function parseInput(s){
  const m = /^(\d{4})\.(\d{2})\.(\d{2})\.?$/.exec(String(s).trim()); if (!m) return null;
  const d = new Date(+m[1], +m[2]-1, +m[3]);
  return (d.getMonth() === +m[2]-1 && d.getDate() === +m[3]) ? d : null;
}
const fmtSize = b => b >= 1048576 ? (b/1048576).toFixed(1).replace('.',',') + ' MB' : Math.max(1, Math.round(b/1024)) + ' KB';
const parseMoney = s => { const d = String(s).replace(/\D/g,''); return d === '' ? null : +d; };
const MONTHS = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
const WD = ['H','K','Sze','Cs','P','Szo','V'];
const RANGE_LABEL = { today:'Ma', yesterday:'Tegnap', '7d':'Utolsó 7 nap', '30d':'Utolsó 30 nap', month:'Tárgyhó', prevmonth:'Előző hónap', year:'Idei év' };
function rangeFor(k){
  const t = today(), y = t.getFullYear(), m = t.getMonth();
  switch (k){
    case 'today': return [t, t];
    case 'yesterday': { const x = addDays(t,-1); return [x, x]; }
    case '7d': return [addDays(t,-6), t];
    case '30d': return [addDays(t,-29), t];
    case 'month': return [new Date(y,m,1), new Date(y,m+1,0)];
    case 'prevmonth': return [new Date(y,m-1,1), new Date(y,m,0)];
    case 'year': return [new Date(y,0,1), t];
    default: return [null, null];
  }
}
const root = document.documentElement, shell = $('#shell');

/* ==================================================================
   1. TOAST (siker / hiba / figyelmeztetés / info)
   ================================================================== */
const TOAST_ICON = { success:'check', error:'x-circle', warning:'alert', info:'info' };
function toast(msg, { type = 'success', action, actionLabel = 'Visszavonás', ms } = {}){
  const el = document.createElement('div');
  el.className = 'toast t-' + type;
  el.innerHTML = ic(TOAST_ICON[type], 'icon') + '<span class="tmsg"></span>';
  $('.tmsg', el).textContent = msg;
  if (action){ const b = document.createElement('button'); b.type = 'button'; b.className = 'tact'; b.textContent = actionLabel; b.onclick = () => { el.remove(); action(); }; el.append(b); }
  const x = document.createElement('button'); x.type = 'button'; x.className = 'icon-btn tclose'; x.setAttribute('aria-label','Értesítés bezárása'); x.innerHTML = ic('x'); x.onclick = () => el.remove(); el.append(x);
  $('#toasts').append(el);
  announce(action ? `${msg} ${actionLabel} elérhető az értesítésben.` : msg, type === 'error');
  // Hibaüzenet és műveletes (pl. Visszavonás) toast nem tűnik el magától (WCAG 2.2.1); a többi rámutatásra/fókuszra megáll.
  const dur = ms ?? (type === 'error' || action ? 0 : 6000);
  if (dur){
    let t, left = dur, start, paused = true;
    const run = () => { if (!paused) return; paused = false; start = Date.now(); t = setTimeout(() => el.remove(), left); };
    const pause = () => { if (paused) return; paused = true; clearTimeout(t); left -= Date.now() - start; };
    el.addEventListener('mouseenter', pause); el.addEventListener('mouseleave', () => { if (!el.contains(document.activeElement)) run(); });
    el.addEventListener('focusin', pause); el.addEventListener('focusout', e => { if (!el.contains(e.relatedTarget) && !el.matches(':hover')) run(); });
    run();
  }
}
// Állandó élő régiók: a dinamikusan beszúrt role=status elemet sok képernyőolvasó nem olvassa fel (WCAG 4.1.3)
function announce(msg, urgent){ const r = $(urgent ? '#liveAlert' : '#liveStatus'); r.textContent = ''; setTimeout(() => { r.textContent = msg; }, 60); }

/* ==================================================================
   2. POPOVER / MENÜ (Esc, kívül kattintás, fókusz vissza, nyilak)
   ================================================================== */
const openPops = new Set();
function closeAll(except){ openPops.forEach(p => { if (p !== except) p.close(false); }); }
function popover(btn, pop, { onOpen, focusFirst = true } = {}){
  const api = {
    open(){
      closeAll(api); pop.hidden = false; btn.setAttribute('aria-expanded','true'); openPops.add(api);
      onOpen && onOpen();
      if (focusFirst){ const f = pop.querySelector('[role=menuitem], input:not([type=hidden]), button'); f && f.focus(); }
    },
    close(ret = true){ if (pop.hidden) return; pop.hidden = true; btn.setAttribute('aria-expanded','false'); openPops.delete(api); if (ret) btn.focus(); },
    toggle(){ pop.hidden ? api.open() : api.close(); }
  };
  btn.addEventListener('click', e => { e.stopPropagation(); api.toggle(); });
  pop.addEventListener('click', e => e.stopPropagation());
  pop.addEventListener('keydown', e => {
    if (e.key === 'Escape'){ e.stopPropagation(); api.close(); }
    if (pop.getAttribute('role') === 'menu' && (e.key === 'ArrowDown' || e.key === 'ArrowUp')){
      e.preventDefault(); const it = $$('[role=menuitem]', pop), i = it.indexOf(document.activeElement);
      it[(i + (e.key === 'ArrowDown' ? 1 : -1) + it.length) % it.length].focus();
    }
  });
  return api;
}
document.addEventListener('click', () => closeAll());

/* ==================================================================
   3. MEGERŐSÍTŐ DIALÓGUS (Promise; fókusz a Mégse gombon)
   ================================================================== */
function confirmBox({ title, body = '', confirm, cancel = 'Mégse', danger = true, input = null }){
  return new Promise(res => {
    const dlg = $('#dlg'), ok = $('#dlgConfirm'), no = $('#dlgCancel'), ret = document.activeElement, inp = $('#dlgInput');
    $('#dlgTitle').textContent = title; $('#dlgDesc').textContent = body; $('#dlgDesc').hidden = !body;
    $('#dlgField').hidden = !input; if (input){ $('#dlgLabel').textContent = input.label; inp.value = input.value || ''; }
    ok.textContent = confirm; ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary'); no.textContent = cancel;
    let settled = false;
    const done = v => { if (settled) return; settled = true; ok.onclick = no.onclick = dlg.onclose = null; if (dlg.open) dlg.close(); if (ret && ret.isConnected) ret.focus(); res(v); };
    ok.onclick = () => done(input ? inp.value.trim() : true); no.onclick = () => done(false); dlg.onclose = () => done(false);
    inp.onkeydown = e => { if (e.key === 'Enter'){ e.preventDefault(); ok.click(); } };
    dlg.showModal(); if (input){ inp.focus(); inp.select(); } else no.focus();
  });
}


/* ==================================================================
   9. TÁBLÁZAT KOMPONENS (rendezés, lapozás, oldalméret, kijelölés,
      oszlopválasztó, export, skeleton, szűrt-üres állapot)
   ================================================================== */
function DataTable(cfg){
  const wrap = cfg.table, pager = cfg.pager, key = 'adm-cols-' + cfg.id;
  const defHidden = () => new Set(cfg.columns.filter(c => c.hidden).map(c => c.key));
  let hidden; try { const s = store.get(key); hidden = s ? new Set(JSON.parse(s)) : defHidden(); } catch(e){ hidden = defHidden(); }
  const st = { sort:{ ...(cfg.sort || {}) }, page:1, size:cfg.pageSize || 25, sel:new Set(), pred:() => true, loading:false };
  let view = [], pages = 1;
  const colOf = k => cfg.columns.find(c => c.key === k);
  function compute(){
    view = cfg.rows.filter(st.pred);
    const c = colOf(st.sort.key);
    if (c){ const d = st.sort.dir === 'asc' ? 1 : -1; view.sort((a,b) => { const x = c.sortValue ? c.sortValue(a) : a[c.key], y = c.sortValue ? c.sortValue(b) : b[c.key]; return (typeof x === 'string' ? x.localeCompare(y, 'hu') : (x > y) - (x < y)) * d; }); }
  }
  function cell(c, r){
    if (c.render) return c.render(r);
    const v = r[c.key];
    switch (c.type){
      case 'link': return `<a class="cell-link" href="#szerkeszto" data-open="${esc(r.id)}">${esc(v)}</a>`;
      case 'money': return fmtMoney(v);
      case 'num': return HU.num.format(v);
      case 'date': return fmtDate(v);
      case 'datetime': return fmtDate(v) + ' ' + HU.time.format(v);
      case 'status': { const s = c.map[v]; return `<span class="badge ${s.cls}">${ic(s.icon,'icon')}${s.label}</span>`; }
      case 'file': return `<div class="file-cell"><span class="ftype ft-${r.kind}" aria-hidden="true">${r.ext.toUpperCase()}</span><a href="#dokumentumok" data-open="${esc(r.id)}">${esc(v)}</a></div>`;
      case 'size': return fmtSize(v);
      default: return esc(v);
    }
  }
  function pageList(){ const out = []; for (let i = 1; i <= pages; i++){ if (i === 1 || i === pages || Math.abs(i - st.page) <= 1) out.push(i); else if (out[out.length-1] !== '…') out.push('…'); } return out; }
  function render(){
    compute();
    pages = Math.max(1, Math.ceil(view.length / st.size)); st.page = Math.min(Math.max(1, st.page), pages);
    const cols = cfg.columns.filter(c => !hidden.has(c.key)), start = (st.page - 1) * st.size, rows = view.slice(start, start + st.size);
    const sel = cfg.selectable !== false, onPage = rows.filter(r => st.sel.has(r.id)).length, span = cols.length + (sel ? 1 : 0) + (cfg.rowMenu ? 1 : 0);
    let h = `<table><caption class="sr-only">${esc(cfg.caption)}</caption><thead><tr>` + (sel ? `<th class="c-sel" scope="col"><input type="checkbox" class="dt-all" aria-label="Az oldal összes sorának kijelölése" ${rows.length && onPage === rows.length ? 'checked' : ''} ${st.loading || !rows.length ? 'disabled' : ''}></th>` : '');
    cols.forEach(c => {
      const on = st.sort.key === c.key, aria = on ? (st.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none';
      const icn = on ? (st.sort.dir === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-updown';
      h += `<th scope="col" class="${c.align === 'right' ? 'num' : ''}" aria-sort="${aria}">${c.sortable === false ? esc(c.label) : `<button type="button" class="th-sort${on ? ' on' : ''}" data-sort="${c.key}">${esc(c.label)}${ic(icn, 'icon sort-ic')}<span class="sr-only">, rendezés</span></button>`}</th>`;
    });
    if (cfg.rowMenu) h += '<th class="c-act" scope="col"><span class="sr-only">Műveletek</span></th>';
    h += '</tr></thead><tbody>';
    if (st.loading){
      for (let i = 0; i < Math.min(st.size, 8); i++) h += '<tr aria-hidden="true">' + (sel ? '<td class="c-sel"><span class="sk" style="width:20px"></span></td>' : '') + cols.map((c,j) => `<td><span class="sk" style="width:${35 + ((i*7 + j*13) % 50)}%"></span></td>`).join('') + (cfg.rowMenu ? '<td></td>' : '') + '</tr>';
    } else if (!rows.length){
      h += `<tr><td colspan="${span}" class="dt-empty-cell">${cfg.emptyHtml()}</td></tr>`;
    } else rows.forEach(r => {
      const s = st.sel.has(r.id), lbl = esc(r[cfg.labelKey]);
      h += `<tr${s ? ' class="is-sel"' : ''}>` + (sel ? `<td class="c-sel"><input type="checkbox" class="dt-row" data-id="${esc(r.id)}" aria-label="Kijelölés: ${lbl}" ${s ? 'checked' : ''}></td>` : '') + cols.map(c => `<td class="${c.align === 'right' ? 'num' : ''}">${cell(c, r)}</td>`).join('') + (cfg.rowMenu ? `<td class="c-act"><button type="button" class="icon-btn row-more" data-id="${esc(r.id)}" aria-haspopup="menu" aria-expanded="false" aria-label="Műveletek: ${lbl}">${ic('more')}</button></td>` : '') + '</tr>';
    });
    wrap.innerHTML = h + '</tbody></table>';
    wrap.setAttribute('aria-busy', st.loading);
    const all = $('.dt-all', wrap); if (all) all.indeterminate = onPage > 0 && onPage < rows.length;
    const total = view.length;
    pager.innerHTML = `<label>Sorok oldalanként <select class="select sm dt-size">${[10,25,50,100].map(n => `<option ${n === st.size ? 'selected' : ''}>${n}</option>`).join('')}</select></label><span class="pg-info" aria-live="polite">${st.loading ? 'Betöltés…' : total ? `${start+1}–${start+rows.length} / ${total} találat` : '0 találat'}</span><nav class="pg-nav" aria-label="Lapozás"><button type="button" class="pg-btn" data-pg="prev" ${st.page <= 1 ? 'disabled' : ''} aria-label="Előző oldal">${ic('chev-left')}</button>${pageList().map(p => p === '…' ? '<span class="pg-gap" aria-hidden="true">…</span>' : `<button type="button" class="pg-btn" data-pg="${p}" ${p === st.page ? 'aria-current="page"' : ''} aria-label="${p}. oldal">${p}</button>`).join('')}<button type="button" class="pg-btn" data-pg="next" ${st.page >= pages ? 'disabled' : ''} aria-label="Következő oldal">${ic('chev-right')}</button></nav><label class="pg-jump">Ugrás <input type="number" class="input sm dt-jump" min="1" max="${pages}" value="${st.page}" inputmode="numeric" aria-label="Ugrás oldalra"> / ${pages}</label>`;
    cfg.onSelect && cfg.onSelect(st.sel.size);
    cfg.onRender && cfg.onRender(st.loading ? null : rows, st);
  }
  // események
  wrap.addEventListener('click', e => {
    const s = e.target.closest('[data-sort]');
    if (s){ const k = s.dataset.sort; st.sort = st.sort.key === k ? { key:k, dir: st.sort.dir === 'asc' ? 'desc' : 'asc' } : { key:k, dir: colOf(k).firstDir || 'asc' }; render(); $(`[data-sort="${k}"]`, wrap)?.focus(); return; }
    const rm = e.target.closest('.row-more');
    if (rm){ e.stopPropagation(); cfg.rowMenu(rm, cfg.rows.find(r => r.id === rm.dataset.id)); return; }
    const op = e.target.closest('[data-open]');
    if (op && cfg.onOpen) cfg.onOpen(cfg.rows.find(r => r.id === op.dataset.open));
  });
  wrap.addEventListener('change', e => {
    if (e.target.matches('.dt-all')){ const start = (st.page-1)*st.size; view.slice(start, start+st.size).forEach(r => e.target.checked ? st.sel.add(r.id) : st.sel.delete(r.id)); render(); $('.dt-all', wrap)?.focus(); }
    if (e.target.matches('.dt-row')){ const id = e.target.dataset.id; e.target.checked ? st.sel.add(id) : st.sel.delete(id); render(); $(`.dt-row[data-id="${id}"]`, wrap)?.focus(); }
  });
  pager.addEventListener('click', e => {
    const b = e.target.closest('[data-pg]'); if (!b || b.disabled) return;
    const v = b.dataset.pg; st.page = v === 'prev' ? st.page-1 : v === 'next' ? st.page+1 : +v; render();
    ($(`[data-pg="${v}"]:not(:disabled)`, pager) || $('.pg-nav [aria-current]', pager))?.focus();
  });
  pager.addEventListener('change', e => {
    if (e.target.matches('.dt-size')){ st.size = +e.target.value; st.page = 1; render(); $('.dt-size', pager).focus(); }
    if (e.target.matches('.dt-jump')){ st.page = Math.round(+e.target.value) || 1; render(); $('.dt-jump', pager).focus(); }
  });
  // oszlopválasztó
  function renderCols(){
    cfg.colPop.innerHTML = '<div class="pop-title">Megjelenített oszlopok</div>' + cfg.columns.filter(c => c.hideable !== false).map(c => `<label class="choice"><input type="checkbox" data-col="${c.key}" ${hidden.has(c.key) ? '' : 'checked'}> ${esc(c.label)}</label>`).join('') + '<hr role="separator"><button type="button" class="btn btn-ghost sm dt-reset">Alapértelmezett oszlopok</button>';
  }
  if (cfg.colBtn){
  cfg.colPop.addEventListener('change', e => {
    const k = e.target.dataset.col; if (!k) return;
    if (!e.target.checked && cfg.columns.filter(c => !hidden.has(c.key)).length <= 1){ e.target.checked = true; toast('Legalább egy oszlopnak láthatónak kell maradnia.', { type:'warning' }); return; }
    e.target.checked ? hidden.delete(k) : hidden.add(k); store.set(key, JSON.stringify([...hidden])); render();
  });
  cfg.colPop.addEventListener('click', e => { if (e.target.closest('.dt-reset')){ hidden = defHidden(); store.set(key, JSON.stringify([...hidden])); renderCols(); render(); } });
  popover(cfg.colBtn, cfg.colPop, { onOpen: renderCols });
  }
  // export (a szűrt nézetből, a látható oszlopokkal)
  if (cfg.exportBtn) cfg.exportBtn.addEventListener('click', () => {
    compute();
    const cols = cfg.columns.filter(c => !hidden.has(c.key));
    const val = (c, r) => { const v = r[c.key]; if (c.type === 'date') return fmtDate(v); if (c.type === 'datetime') return fmtDate(v) + ' ' + HU.time.format(v); if (c.type === 'status') return c.map[v].label; if (c.type === 'size') return fmtSize(v); return v; };
    const csv = [cols.map(c => c.label)].concat(view.map(r => cols.map(c => val(c, r)))).map(row => row.map(x => '"' + String(x).replace(/"/g,'""') + '"').join(';')).join('\r\n');
    try { const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type:'text/csv;charset=utf-8' })); const a = document.createElement('a'); a.href = url; a.download = cfg.id + '.csv'; document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 2000); } catch(e){}
    toast(`Exportálva: ${view.length} sor, ${cols.length} oszlop (CSV).`);
  });
  return {
    render,
    setPred(fn){ st.pred = fn; st.page = 1; render(); },
    load(ms = 700){ st.loading = true; render(); setTimeout(() => { st.loading = false; render(); }, ms); },
    setLoading(on){ st.loading = !!on; render(); },
    clearSel(){ st.sel.clear(); render(); },
    get selected(){ return [...st.sel]; },
    toggle(id, on){ on ? st.sel.add(id) : st.sel.delete(id); render(); },
    isSel: id => st.sel.has(id),
    selectMany(ids, on){ ids.forEach(id => on ? st.sel.add(id) : st.sel.delete(id)); render(); }
  };
}

/* Közös sorművelet-menü */
const rowMenu = $('#rowMenu') || document.createElement('div'); let rmCtx = null;
const ROW_ITEMS = [ { k:'open', l:'Megnyitás', i:'chev-right' }, { k:'dup', l:'Duplikálás', i:'dup' }, { k:'archive', l:'Archiválás', i:'archive' }, { k:'delete', l:'Törlés…', i:'trash', danger:true, sep:true } ];
function openRowMenu(btn, row, handlers, items = ROW_ITEMS){
  closeAll();
  rowMenu.innerHTML = items.map(it => (it.sep ? '<hr role="separator">' : '') + `<button role="menuitem" type="button" data-ract="${it.k}"${it.danger ? ' class="danger"' : ''}>${ic(it.i)}${esc(it.l)}</button>`).join('');
  rowMenu.hidden = false;
  const r = btn.getBoundingClientRect(), w = rowMenu.offsetWidth, h = rowMenu.offsetHeight;
  let top = r.bottom + 6; if (top + h > innerHeight - 8) top = r.top - h - 6;
  rowMenu.style.top = Math.max(8, top) + 'px'; rowMenu.style.left = Math.max(8, r.right - w) + 'px';
  btn.setAttribute('aria-expanded','true');
  const api = { close(ret = true){ rowMenu.hidden = true; btn.setAttribute('aria-expanded','false'); openPops.delete(api); rmCtx = null; if (ret && btn.isConnected) btn.focus(); } };
  rmCtx = { row, handlers, api }; openPops.add(api);
  $('[role=menuitem]', rowMenu).focus();
}
rowMenu.addEventListener('click', e => { e.stopPropagation(); const b = e.target.closest('[data-ract]'); if (!b || !rmCtx) return; const { row, handlers, api } = rmCtx; api.close(false); handlers[b.dataset.ract](row); });
rowMenu.addEventListener('keydown', e => {
  if (e.key === 'Escape' && rmCtx) rmCtx.api.close();
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp'){ e.preventDefault(); const it = $$('[role=menuitem]', rowMenu), i = it.indexOf(document.activeElement); it[(i + (e.key === 'ArrowDown' ? 1 : -1) + it.length) % it.length].focus(); }
});
window.addEventListener('scroll', () => { if (rmCtx) rmCtx.api.close(false); }, { passive:true });


/* ==================================================================
   12. ALKALMAZÁS: közös adatok, API-hívás, segédek
   ================================================================== */
const APP = (() => { const el = $('#appData'); try { return el ? JSON.parse(el.textContent) : {}; } catch(e){ return {}; } })();
const can = p => (APP.me?.perms || []).includes(p);
async function api(m, body = {}){
  const res = await fetch('api.php?m=' + encodeURIComponent(m), { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token':APP.csrf }, body:JSON.stringify(body) });
  if (res.status === 401){ location.href = 'index.php?p=belepes'; throw new Error('A munkamenet lejárt.'); }
  let d; try { d = await res.json(); } catch(e){ throw new Error(`A szerver nem adott értelmezhető választ (HTTP ${res.status}).`); }
  if (!res.ok || d.error) throw new Error(d.error || `HTTP ${res.status}`);
  return d;
}
const dtHu = s => { if (!s) return '–'; const d = new Date(String(s).replace(' ', 'T') + 'Z'); return isNaN(d) ? s : fmtDate(d) + ' ' + HU.time.format(d); };
function busy(btn, on){ on ? btn.setAttribute('aria-busy','true') : btn.removeAttribute('aria-busy'); btn.disabled = !!on; }
async function copyText(text, el){
  try { await navigator.clipboard.writeText(text); toast('Vágólapra másolva.'); }
  catch(e){ if (el){ const r = document.createRange(); r.selectNodeContents(el); const s = getSelection(); s.removeAllRanges(); s.addRange(r); } toast('A másolás nem sikerült; a szöveget kijelöltük, másolja kézzel.', { type:'warning' }); }
}
document.addEventListener('click', e => { const b = e.target.closest('[data-copy]'); if (b) copyText(b.dataset.copy, b.previousElementSibling); });

const REC = { 'Erősen javasolt':['b-success','arrow-up','Erősen javasolt'], 'Javasolt':['b-info','check','Javasolt'], 'Nem javasolt':['b-neutral','minus','Nem javasolt'], 'Nem javasolt (már fent)':['b-neutral','archive','Már fent van'] };
const REC_ORDER = ['Erősen javasolt','Javasolt','Nem javasolt','Nem javasolt (már fent)',''];
const recBadge = r => { const x = REC[r]; return x ? `<span class="badge ${x[0]}">${ic(x[1],'icon')}${esc(x[2])}</span>` : (r ? `<span class="badge b-neutral">${esc(r)}</span>` : '<span class="meta">–</span>'); };

/* ==================================================================
   12/A. BELÉPÉS, BEÁLLÍTÁS, JELSZÓ (belépés nélküli oldalak)
   ================================================================== */
$$('.pw-toggle').forEach(b => b.addEventListener('click', () => { const i = document.getElementById(b.getAttribute('aria-controls')), s = i.type === 'password'; i.type = s ? 'text' : 'password'; b.setAttribute('aria-pressed', s); b.setAttribute('aria-label', s ? 'Jelszó elrejtése' : 'Jelszó megjelenítése'); $('use', b).setAttribute('href', s ? '#i-eye-off' : '#i-eye'); }));
const lp = $('#l-pass');
if (lp){ ['keydown','keyup'].forEach(ev => lp.addEventListener(ev, e => { if (e.getModifierState) $('#capsWarn').hidden = !e.getModifierState('CapsLock'); })); lp.addEventListener('blur', () => { $('#capsWarn').hidden = true; }); }
$('#forgot')?.addEventListener('click', () => { const h = $('#forgotHelp'); h.hidden = !h.hidden; if (!h.hidden) announce(h.textContent); });
$$('form[data-form="auth"]').forEach(f => f.addEventListener('submit', e => {
  const bad = $$('input[required]', f).filter(i => !i.value.trim());
  const p1 = $('input[name="jelszo"]', f), p2 = $('input[name="jelszo2"]', f);
  if (bad.length){ e.preventDefault(); bad.forEach(i => i.setAttribute('aria-invalid','true')); bad[0].focus(); toast('Töltse ki a megjelölt mezőket.', { type:'error' }); return; }
  if (p1 && p2 && p1.value !== p2.value){ e.preventDefault(); p2.setAttribute('aria-invalid','true'); p2.focus(); toast('A két jelszó nem egyezik.', { type:'error' }); return; }
  if (p1 && p1.minLength > 0 && p1.value.length < p1.minLength){ e.preventDefault(); p1.setAttribute('aria-invalid','true'); p1.focus(); toast(`A jelszó legalább ${p1.minLength} karakter legyen.`, { type:'error' }); return; }
  const b = $('button[type=submit]', f); b && b.setAttribute('aria-busy','true');
}));
$('#formErr')?.focus();

/* ==================================================================
   12/B. FELHASZNÁLÓK
   ================================================================== */
if (APP.page === 'felhasznalok'){
  const R = APP.roles, USTAT = { aktiv:{ label:'Aktív', cls:'b-success', icon:'check' }, meghivva:{ label:'Meghívva', cls:'b-warning', icon:'send' }, letiltva:{ label:'Letiltva', cls:'b-neutral', icon:'x-circle' } };
  const RICON = { uzemelteto:['b-ai','key'], ugyfeladmin:['b-info','user'], tartalom:['b-success','edit'], olvaso:['b-neutral','eye'] };
  const rows = [];
  const roleOf = u => u.is_operator ? 'uzemelteto' : (u.memberships.find(m => m.client_id === APP.me.clientId) || u.memberships[0] || {}).role || 'olvaso';
  const setRows = list => rows.splice(0, rows.length, ...list.map(u => ({ ...u, id:String(u.id), role:roleOf(u), clientsLabel:u.is_operator ? 'Minden ügyfél' : u.memberships.map(m => m.client).join(', ') || '–' })));
  setRows(APP.data.users || []);
  const badgeRole = r => `<span class="badge ${RICON[r][0]}">${ic(RICON[r][1],'icon')}${esc(R.labels[r])}</span>`;
  const table = DataTable({
    id:'felhasznalok', caption:'Felhasználók', rows, labelKey:'name', table:$('#userTable'), pager:$('#userPager'), sort:{ key:'name', dir:'asc' }, pageSize:25,
    columns:[
      { key:'name', label:'Név', render:r => `<div class="user-cell"><span class="avatar" aria-hidden="true">${esc(r.name.split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase())}</span><div><b>${esc(r.name)}${String(r.id) === String(APP.me.id) ? ' <span class="meta">(Ön)</span>' : ''}</b><span class="meta">${esc(r.email)}</span></div></div>` },
      { key:'clientsLabel', label:'Ügyfél' },
      { key:'role', label:'Szerepkör', render:r => r.is_operator ? badgeRole('uzemelteto') : r.memberships.map(m => APP.me.operator && r.memberships.length > 1 ? `<div>${badgeRole(m.role)} <span class="meta">${esc(m.client)}</span></div>` : badgeRole(m.role)).join('') || '–' },
      { key:'status', label:'Állapot', type:'status', map:USTAT },
      { key:'last_login_at', label:'Utolsó belépés', render:r => dtHu(r.last_login_at), sortValue:r => r.last_login_at || '' }
    ],
    rowMenu:(btn, r) => {
      if (String(r.id) === String(APP.me.id)){ toast('A saját fiókját a Profil oldalon kezelheti.', { type:'info' }); return; }
      if (r.is_operator && !APP.me.operator){ toast('Az üzemeltető fiókját csak üzemeltető módosíthatja.', { type:'info' }); return; }
      const items = [{ k:'edit', l:'Szerkesztés', i:'edit' }];
      if (r.status !== 'letiltva') items.push({ k:'link', l:r.status === 'meghivva' ? 'Új meghívó link' : 'Jelszó-visszaállító link', i:'key' });
      items.push(r.status === 'letiltva' ? { k:'toggle', l:'Engedélyezés', i:'check' } : { k:'toggle', l:'Letiltás', i:'x-circle' });
      items.push({ k:'delete', l:APP.me.operator ? 'Törlés…' : 'Eltávolítás…', i:'trash', danger:true, sep:true });
      openRowMenu(btn, r, { edit:openDlg, link:issueLink, toggle:u => setDisabled([u], u.status !== 'letiltva'), delete:u => removeUsers([u]) }, items);
    },
    onSelect:n => { $('#uBulk').hidden = !n; $('#uToolbar').hidden = !!n; $('#uBulkCount').textContent = `${n} kiválasztva`; },
    emptyHtml:() => rows.length ? 'Nincs a szűrésnek megfelelő felhasználó.' : 'Még nincs felhasználó. A „Meghívás” gombbal hívhatja meg az elsőt.'
  });
  const pred = () => { const q = norm($('#uQ').value.trim()), r = $('#uRole').value, s = $('#uSt').value; table.setPred(u => (!q || norm(u.name + ' ' + u.email).includes(q)) && (!r || u.role === r || u.memberships.some(m => m.role === r)) && (!s || u.status === s)); };
  $('#uRole').innerHTML += Object.entries(R.labels).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join('');
  $('#uQ').addEventListener('input', pred); ['#uRole','#uSt'].forEach(s => $(s).addEventListener('change', pred));
  table.render();
  const refresh = d => { setRows(d.users); table.clearSel(); table.render(); };
  const ids = list => list.map(u => +u.id);
  async function setDisabled(list, disabled){
    try { const d = await api('letiltas', { ids:ids(list), disabled }); refresh(d); toast(`${list.length} felhasználó ${disabled ? 'letiltva: nem tud belépni' : 'engedélyezve'}.`, { action:() => api('letiltas', { ids:ids(list), disabled:!disabled }).then(refresh).catch(e => toast(e.message, { type:'error' })) }); }
    catch(e){ toast(e.message, { type:'error' }); }
  }
  async function removeUsers(list){
    const one = list.length === 1, op = APP.me.operator;
    const ok = await confirmBox({ title: one ? `${op ? 'Törli' : 'Eltávolítja'} ${list[0].name} fiókját?` : `${op ? 'Törli' : 'Eltávolítja'} a(z) ${list.length} felhasználót?`, body: op ? 'A fiók véglegesen törlődik; a korábbi műveletei a naplóban megmaradnak.' : 'A felhasználó nem fér hozzá többé az ügyfélhez. Ha más ügyfélnél is dolgozik, ott megmarad a hozzáférése.', confirm: one ? (op ? 'Felhasználó törlése' : 'Eltávolítás') : `${list.length} felhasználó ${op ? 'törlése' : 'eltávolítása'}` });
    if (!ok) return;
    try { const d = await api('torles', { ids:ids(list) }); refresh(d); toast(`${list.length} felhasználó ${op ? 'törölve' : 'eltávolítva'}.`); } catch(e){ toast(e.message, { type:'error' }); }
  }
  async function issueLink(u){
    try { const d = await api('link', { id:+u.id }); showLink(d.link, d.purpose === 'meghivas'); } catch(e){ toast(e.message, { type:'error' }); }
  }
  function showLink(link, invite){
    $('#linkDlgTitle').textContent = invite ? 'Meghívó link' : 'Jelszó-visszaállító link';
    $('#linkDlgDesc').textContent = invite ? 'Küldje el ezt a linket a felhasználónak (e-mailben vagy üzenetben). Egyszer használható, 7 napig érvényes.' : 'Küldje el ezt a linket a felhasználónak. Egyszer használható, 24 óráig érvényes.';
    $('#linkVal').textContent = link; $('#linkDlg').showModal(); $('#linkCopy').focus();
  }
  $('#linkCopy').addEventListener('click', () => copyText($('#linkVal').textContent, $('#linkVal')));
  $('#linkClose').addEventListener('click', () => $('#linkDlg').close());
  $('#uBulkDisable').addEventListener('click', () => { const l = rows.filter(u => table.selected.includes(u.id) && String(u.id) !== String(APP.me.id)); l.length ? setDisabled(l, true) : toast('Saját fiókját nem tilthatja le.', { type:'info' }); });
  $('#uBulkDelete').addEventListener('click', () => { const l = rows.filter(u => table.selected.includes(u.id) && String(u.id) !== String(APP.me.id)); l.length ? removeUsers(l) : toast('Saját fiókját nem törölheti.', { type:'info' }); });
  $('#uBulkClear').addEventListener('click', () => table.clearSel());
  let editing = null;
  function openDlg(u = null){
    editing = u;
    $('#userDlgTitle').textContent = u ? 'Felhasználó szerkesztése' : 'Felhasználó meghívása';
    $('#userDlgDesc').hidden = !!u;
    $('#userSubmit').textContent = u ? 'Mentés' : 'Meghívó létrehozása';
    $('#u-name').value = u?.name || ''; $('#u-email').value = u?.email || ''; $('#u-email').readOnly = !!u;
    const clients = APP.me.operator ? APP.clients : APP.clients.filter(c => c.id === APP.me.clientId);
    $('#u-client').innerHTML = clients.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    $('#u-client').value = String(APP.me.clientId ?? '');
    $('#u-client-f').hidden = clients.length < 2 && !APP.me.operator;
    const roles = Object.keys(R.labels).filter(k => APP.me.operator || k !== 'uzemelteto').filter(k => !u || (u.is_operator ? k === 'uzemelteto' : k !== 'uzemelteto'));
    const cur = u ? u.role : 'olvaso';
    $('#u-roles').innerHTML = roles.map(k => `<label class="open-opt"><input type="radio" name="urole" value="${k}"${k === cur ? ' checked' : ''}><span><b>${esc(R.labels[k])}</b><br><span class="meta">${esc(R.desc[k])}</span></span></label>`).join('');
    const syncClient = () => { $('#u-client-f').hidden = $('[name=urole]:checked')?.value === 'uzemelteto' || (clients.length < 2 && !APP.me.operator); };
    $$('[name=urole]').forEach(i => i.addEventListener('change', syncClient)); syncClient();
    $('#userErr').hidden = true; $$('#userForm .input').forEach(i => i.removeAttribute('aria-invalid'));
    $('#userDlg').showModal(); $('#u-name').focus();
  }
  $('#inviteBtn').addEventListener('click', () => openDlg());
  $('#userCancel').addEventListener('click', () => $('#userDlg').close());
  $('#userForm').addEventListener('submit', async e => {
    e.preventDefault();
    const name = $('#u-name').value.trim(), email = $('#u-email').value.trim(), role = $('[name=urole]:checked')?.value, client_id = +$('#u-client').value || null, bad = [];
    if (!name) bad.push([$('#u-name'), 'Adja meg a nevet.']);
    if (!/^\S+@\S+\.\S+$/.test(email)) bad.push([$('#u-email'), 'Adjon meg érvényes e-mail-címet.']);
    $$('#userForm .input').forEach(i => i.removeAttribute('aria-invalid'));
    if (bad.length){ bad.forEach(([i]) => i.setAttribute('aria-invalid','true')); $('#userErr').textContent = bad.map(b => b[1]).join(' '); $('#userErr').hidden = false; bad[0][0].focus(); return; }
    const b = $('#userSubmit'); busy(b, true);
    try {
      const d = editing ? await api('felhasznalo', { id:+editing.id, name, role, client_id }) : await api('meghivas', { name, email, role, client_id });
      $('#userDlg').close(); refresh(d);
      if (editing) toast(`${name} adatai mentve.`);
      else if (d.link) showLink(d.link, true);
      else toast(`${email} hozzáadva ehhez az ügyfélhez (már volt fiókja, a meglévő jelszavával lép be).`);
    } catch(err){ $('#userErr').textContent = err.message; $('#userErr').hidden = false; }
    finally { busy(b, false); }
  });
  const rk = Object.keys(R.labels);
  $('#roleMatrix').innerHTML = `<table class="matrix"><caption class="sr-only">Jogosultságok szerepkörönként</caption><thead><tr><th scope="col">Jogosultság</th>${rk.map(k => `<th scope="col">${esc(R.labels[k])}</th>`).join('')}</tr></thead><tbody>${Object.entries(R.perms).map(([p, l]) => `<tr><th scope="row" style="font-weight:500">${esc(l)}</th>${rk.map(k => (R.grants[k] || []).includes(p) ? `<td class="yes">${ic('check')}<span class="sr-only">igen</span></td>` : `<td class="no">${ic('minus')}<span class="sr-only">nem</span></td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

/* ==================================================================
   12/C. ÜGYFELEK (üzemeltető)
   ================================================================== */
if (APP.page === 'ugyfelek'){
  const CST = { aktiv:{ label:'Aktív', cls:'b-success', icon:'check' }, beallitas:{ label:'Beállítás alatt', cls:'b-warning', icon:'settings' }, szunetel:{ label:'Szüneteltetve', cls:'b-neutral', icon:'clock' } };
  const rows = [];
  const setRows = l => rows.splice(0, rows.length, ...l.map(c => ({ ...c, id:String(c.id) })));
  setRows(APP.data.clients || []);
  const table = DataTable({
    id:'ugyfelek', caption:'Ügyfelek', rows, labelKey:'name', selectable:false, table:$('#clientTable'), pager:$('#clientPager'), sort:{ key:'name', dir:'asc' }, pageSize:25,
    columns:[ { key:'name', label:'Ügyfél', render:r => `<b>${esc(r.name)}</b>${+r.id === APP.me.clientId ? ' <span class="meta">(kiválasztva)</span>' : ''}` }, { key:'status', label:'Állapot', type:'status', map:CST },
      { key:'users', label:'Felhasználók', type:'num', align:'right' }, { key:'website', label:'Weboldal', render:r => esc(r.website || '–') }, { key:'spreadsheet_id', label:'Google Sheet', render:r => r.spreadsheet_id ? `<span class="badge b-success">${ic('check','icon')}megadva</span>` : '<span class="meta">nincs</span>' }, { key:'articles', label:'Cikkek', type:'num', align:'right' }, { key:'created_at', label:'Létrehozva', render:r => dtHu(r.created_at), sortValue:r => r.created_at } ],
    rowMenu:(btn, r) => openRowMenu(btn, r, { edit:openDlg, switch:c => switchTo(c.id) }, [{ k:'switch', l:'Váltás erre az ügyfélre', i:'folder' }, { k:'edit', l:'Szerkesztés', i:'edit' }]),
    emptyHtml:() => 'Még nincs ügyfél. Az „Új ügyfél” gombbal hozza létre az elsőt.'
  });
  table.render();
  function switchTo(id){ const f = document.createElement('form'); f.method = 'post'; f.action = 'index.php?p=ugyfelvaltas'; f.innerHTML = `<input type="hidden" name="csrf" value="${esc(APP.csrf)}"><input type="hidden" name="client_id" value="${esc(id)}"><input type="hidden" name="vissza" value="ugyfelek">`; document.body.append(f); f.submit(); }
  let editing = null;
  function openDlg(c = null){
    editing = c; $('#clientDlgTitle').textContent = c ? 'Ügyfél szerkesztése' : 'Új ügyfél'; $('#clientSubmit').textContent = c ? 'Mentés' : 'Ügyfél létrehozása';
    $('#c-name').value = c?.name || ''; $('#c-site').value = c?.website || ''; $('#c-sheet').value = c?.spreadsheet_id ? 'https://docs.google.com/spreadsheets/d/' + c.spreadsheet_id + '/edit' : ''; $('#c-status').value = c?.status || 'beallitas'; $('#c-status-f').hidden = !c;
    $('#clientErr').hidden = true; $('#clientDlg').showModal(); $('#c-name').focus();
  }
  $('#clientAdd').addEventListener('click', () => openDlg());
  $('#clientCancel').addEventListener('click', () => $('#clientDlg').close());
  $('#clientForm').addEventListener('submit', async e => {
    e.preventDefault(); const name = $('#c-name').value.trim();
    if (!name){ $('#c-name').setAttribute('aria-invalid','true'); $('#clientErr').textContent = 'Adja meg az ügyfél nevét.'; $('#clientErr').hidden = false; $('#c-name').focus(); return; }
    const b = $('#clientSubmit'); busy(b, true);
    try {
      const d = await api('ugyfel', { id:editing ? +editing.id : null, name, website:$('#c-site').value.trim(), status:$('#c-status').value, sheet:$('#c-sheet').value.trim() });
      $('#clientDlg').close();
      if (!editing && !APP.clients.length){ location.reload(); return; } // első ügyfél: a felső sáv is frissüljön
      setRows(d.clients); table.render(); renderOnb(); renderImp(); toast(editing ? 'Ügyfél mentve.' : `${name} létrehozva. Következő lépés: felhasználók meghívása.`);
    } catch(err){ $('#clientErr').textContent = err.message; $('#clientErr').hidden = false; }
    finally { busy(b, false); }
  });
  function renderOnb(){
    const c = rows.find(r => +r.id === APP.me.clientId);
    if (!c){ $('#onbList').innerHTML = '<li><span>Hozzon létre egy ügyfelet, vagy váltson egyre.</span></li>'; return; }
    const steps = [['Alapadatok', 'név, weboldal', true], ['Felhasználók meghívása', 'ügyfél-adminisztrátor, tartalomkezelő', c.users > 0], ['Források és kulcsszavak', c.sources ? `${c.sources} forrás, ${c.keywords} kulcsszó` : 'adatátvétel a Google Sheetből, vagy kézzel', c.sources > 0], ['Weboldal-kapcsolat', 'feltöltés két nyelven, a 8. lépésben', false], ['Első szárazfuttatás', 'a 4. lépés után', false]];
    $('#onbList').innerHTML = steps.map(([t, s, d], i) => `<li class="${d ? 'done' : ''}"><span class="ck" aria-hidden="true">${d ? ic('check') : i + 1}</span><div><b>${t}</b><small>${s}</small></div><span class="sr-only">${d ? 'kész' : 'hátravan'}</span></li>`).join('');
  }
  renderOnb();
  // Adatátvétel a Google Sheetből (az aktuális ügyfélé)
  const LEVEL = { ok:['check','Kész'], info:['chev-right','Információ'], warn:['alert','Figyelmeztetés'], error:['x-circle','Hiba'], dry:['info','Szárazfuttatás'] };
  function renderImp(){
    const c = rows.find(r => +r.id === APP.me.clientId);
    $('#impKv').innerHTML = c ? [['Források', c.sources], ['Kulcsszavak', c.keywords], ['Cikkek', HU.num.format(c.articles)], ['Posztok', c.posts], ['Posztszabályok', c.rules]].map(([a, b]) => `<div><dt>${a}</dt><dd>${b}</dd></div>`).join('') : '';
    const open = $('#sheetOpen'); if (open){ open.hidden = !(c && c.spreadsheet_id); if (c && c.spreadsheet_id) open.href = `https://docs.google.com/spreadsheets/d/${encodeURIComponent(c.spreadsheet_id)}/edit`; }
  }
  $('#sheetSave')?.addEventListener('click', async () => {
    const b = $('#sheetSave'), v = $('#sheetInput').value.trim(); $('#sheetInput').removeAttribute('aria-invalid'); busy(b, true);
    try { const d = await api('sheetlink', { sheet:v }); setRows(d.clients); table.render(); renderImp(); $('#impLines').hidden = true; toast(v ? 'A tábla linkje mentve.' : 'A link törölve.'); }
    catch(e){ $('#sheetInput').setAttribute('aria-invalid','true'); impError(e.message); }
    finally { busy(b, false); }
  });
  $('#keyFile')?.addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) return;
    if (f.size > 20000){ impError('Ez a fájl túl nagy egy Google-kulcshoz. A szolgáltatásfiók JSON-kulcsát válassza.'); e.target.value = ''; return; }
    const rd = new FileReader();
    rd.onload = async () => {
      try {
        const d = await api('googlekulcs', { json:String(rd.result) });
        $('#keyState').textContent = 'Beállítva: feltöltve a felületen';
        $('#svcEmail').textContent = d.email; $('#svcCopy').dataset.copy = d.email; $('#svcBox').hidden = false; $('#impLines').hidden = true;
        toast('A Google-kulcs mentve. Most ossza meg a táblát a megjelenő címmel.');
      } catch(err){ impError(err.message); }
      finally { e.target.value = ''; }
    };
    rd.onerror = () => impError('A fájl nem olvasható.');
    rd.readAsText(f);
  });
  function impError(msg){
    $('#impLines').innerHTML = `<li class="l-error">${ic('x-circle')}<span><span class="sr-only">Hiba: </span>${esc(msg)}</span></li>`;
    $('#impLines').hidden = false;
  }
  async function runImp(dry){
    const c = rows.find(r => +r.id === APP.me.clientId);
    if (!c){ impError('Nincs kiválasztott ügyfél. Válasszon ügyfelet a felső sávban.'); return; }
    if (!c.spreadsheet_id){ impError('Ennél az ügyfélnél nincs mentve a tábla linkje. Másolja be a 2. lépés mezőjébe, és nyomja meg a „Link mentése” gombot.'); return; }
    if (!dry){ const ok = await confirmBox({ title:`Átveszi a(z) ${c.name} adatait a Google Sheetből?`, body:'A forrásokat, kulcsszavakat, a havi lapok cikkeit, a posztokat, az ötleteket és a beállításokat beírja az adatbázisba. Többször is futtatható: a meglévőt frissíti, nem duplikál. A szabályokat csak akkor tölti be, ha még nincs szabály.', confirm:'Adatok átvétele', danger:false }); if (!ok) return; }
    const b = dry ? $('#impDry') : $('#impLive'); busy(b, true);
    try {
      const r = await api('atvetel', { dry });
      $('#impLines').innerHTML = r.lines.map(l => { const L = LEVEL[l.level] || LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L[0])}<span><span class="sr-only">${L[1]}: </span>${esc(l.text)}</span></li>`; }).join('');
      $('#impLines').hidden = false;
      setRows(r.clients); table.render(); renderOnb();
      toast(r.ok ? (dry ? 'Szárazfuttatás kész. Nézze át az eredményt.' : 'Az adatok átvétele kész.') : 'Az adatátvétel nem sikerült. A részletek a kártyán.', { type:r.ok ? 'success' : 'error' });
    } catch(e){ impError(e.message); toast(e.message, { type:'error' }); }
    finally { busy(b, false); renderImp(); }
  }
  $('#impDry').addEventListener('click', () => runImp(true));
  $('#impLive').addEventListener('click', () => runImp(false));
  renderImp();
}

/* ==================================================================
   12/D. VEZÉRLŐPULT: futtatás, eredmény
   ================================================================== */
if (APP.page === 'vezerlo'){
  const LEVEL = { ok:['check','Kész'], info:['chev-right','Információ'], warn:['alert','Figyelmeztetés'], error:['x-circle','Hiba'], dry:['info','Szárazfuttatás'] };
  let running = false;
  $$('[data-run]').forEach(btn => btn.addEventListener('click', async () => {
    if (running) return; running = true;
    const card = btn.closest('[data-task]'), label = card.dataset.label;
    busy(btn, true); $$('[data-run]').forEach(b => { if (b !== btn) b.disabled = true; });
    try {
      const r = await api('futtatas', { task:btn.dataset.run, dry:btn.dataset.dry === '1' });
      $('#h-result').textContent = 'Eredmény: ' + r.label;
      $('#resultMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}OK</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`) + `<span class="badge ${r.dry ? 'b-info' : 'b-neutral'}">${ic(r.dry ? 'flask' : 'play','icon')}${r.dry ? 'Szárazfuttatás' : 'Éles futás'}</span><span>${esc(r.time_hu)} · ${HU.num.format(r.duration_ms)} ms</span>`;
      $('#resultLines').innerHTML = r.lines.map(l => { const L = LEVEL[l.level] || LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L[0])}<span><span class="sr-only">${L[1]}: </span>${esc(l.text)}</span></li>`; }).join('');
      $('#resultCard').hidden = false; $('#resultCard').focus();
      $('[data-last]', card).innerHTML = `<span>Legutóbb: ${esc(r.time_hu)} · ${r.dry ? 'száraz' : 'éles'}</span>` + (r.ok ? `<span class="badge b-success">${ic('check','icon')}OK</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`);
      toast(r.ok ? `${label}: kész.` : `${label}: hiba történt. A részletek az Eredmény kártyán.`, { type:r.ok ? 'success' : 'error' });
    } catch(e){ toast(`${label}: ${e.message}`, { type:'error' }); }
    finally { running = false; busy(btn, false); $$('[data-run]').forEach(b => b.disabled = false); }
  }));
  $('#resultClose').addEventListener('click', () => { $('#resultCard').hidden = true; $('#h-page').focus(); });
  // Cikkgyűjtés (szárazon)
  const colRows = [];
  const colTable = DataTable({ id:'gyujtes', caption:'Talált új cikkek', rows:colRows, labelKey:'title', selectable:false, table:$('#colItems'), pager:$('#colPager'), sort:{ key:'date', dir:'asc' }, pageSize:25,
    columns:[ { key:'date', label:'Dátum', render:r => esc(r.date ? r.date.replace(/-/g, '. ') + '.' : '–') }, { key:'source', label:'Forrás', render:r => esc(r.source) + (r.new_source ? ` <span class="badge b-ai">${ic('plus','icon')}új forrás</span>` : '') },
      { key:'title', label:'Cím és leírás', render:r => `<div class="cell-title"><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.title)}</a><span>${esc(r.desc || r.description)}</span></div>` },
      { key:'recommendation', label:'Javaslat', render:r => r.recommendation ? recBadge(r.recommendation) : '<span class="meta">–</span>', sortValue:r => REC_ORDER.indexOf(r.recommendation || '') },
      { key:'reason', label:'Indoklás', sortable:false, render:r => `<div class="cell-why">${esc(r.reason || '')}</div>` } ],
    emptyHtml:() => 'Ebben a hónapban nem találtam új cikket.' });
  async function collectRun(kind){
    const month = $('#colMonth').value, live = kind === 'live';
    if (live){ const ok = await confirmBox({ title:`Élesben futtatja a(z) ${$('#colMonth').selectedOptions[0].textContent} havi futást?`, body:'Begyűjti a cikkeket, a Claude értékeli őket, és a hónap listája a vezetői összefoglalóval bekerül a Havi cikkek közé. Egy hónapra csak egyszer készül lista.', confirm:'Havi futás élesben', danger:false }); if (!ok) return; }
    const b = $(kind === 'collect' ? '#colRun' : live ? '#monLive' : '#monDry'); busy(b, true); ['#colRun','#monDry','#monLive'].forEach(s => { if ($(s) !== b) $(s).disabled = true; });
    announce('A futás elindult, ez 1–3 percig tarthat…');
    try {
      const r = kind === 'collect' ? await api('gyujtes', { month }) : await api('havifutas', { month, dry:!live });
      const d = Object.assign({ sources:[], items:[] }, r.data || {});
      $('#colMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}Kész</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`) + `<span>${esc(r.time_hu)} · ${HU.num.format(Math.round(r.duration_ms / 1000))} s · ${d.items.length} új cikk</span>` + (r.ok ? '' : `<span>${esc(r.lines.filter(l => l.level === 'error').map(l => l.text).join(' '))}</span>`);
      $('#colSources').innerHTML = d.sources.length ? `<table><caption class="sr-only">Források</caption><thead><tr><th scope="col">Forrás</th><th scope="col">Mód</th><th scope="col" class="num">Talált</th><th scope="col" class="num">A hónapban</th><th scope="col" class="num">Duplikátum</th><th scope="col" class="num">Kizárt</th><th scope="col" class="num">Marad</th><th scope="col">Megjegyzés</th></tr></thead><tbody>${d.sources.map(s => `<tr><td>${esc(s.name || s.url)}</td><td>${esc(s.method)}</td><td class="num">${s.found}</td><td class="num">${s.in_month}</td><td class="num">${s.duplicate}</td><td class="num">${s.excluded}</td><td class="num"><b>${s.kept}</b></td><td>${s.error ? `<span class="badge b-warning">${ic('alert','icon')}${esc(s.error)}</span>` : (s.undated ? `<span class="meta">${s.undated} dátum nélkül</span>` : '')}</td></tr>`).join('')}</tbody></table>` : `<div class="empty-state"><b>Nincs aktív forrás</b><span>${esc(r.lines.map(l => l.text).join(' '))}</span></div>`;
      colRows.splice(0, colRows.length, ...d.items.map((x, i) => ({ ...x, id:String(i) }))); colTable.render();
      const sum = d.summary;
      $('#monSum').innerHTML = sum ? `<div class="widget-head" style="margin-top:16px"><div><h3 class="sub-h" style="margin:0">Vezetői összefoglaló${live ? '' : ' (szárazon, nem mentve)'}</h3></div><span class="badge b-ai">${ic('sparkle','icon')}AI által generált</span></div><dl class="kv">${(sum.rows || []).map(x => `<div><dt>${esc(x.label)}</dt><dd>${esc(x.value)}</dd></div>`).join('')}</dl><p class="help" style="margin-top:8px">${esc(sum.details || '')}</p>` : (d.exists ? `<div class="empty-state" style="margin-top:12px"><b>Ehhez a hónaphoz már van lista</b><span>A Havi cikkek oldalon látható.</span></div>` : '');
      $('#monSum').hidden = !$('#monSum').innerHTML;
      if (!d.sources) d.sources = [];
      $('#colOut').hidden = false;
      const lines = r.lines.filter(l => l.level === 'error').map(l => l.text).join(' ');
      if (r.ok && d.exists){ toast('Ehhez a hónaphoz már van lista; új listát nem készítettem. A Havi cikkek oldalon látható.', { type:'info' }); return; }
      toast(r.ok ? (kind === 'collect' ? `Gyűjtés kész: ${d.items.length} új cikk.` : live ? `A havi lista elkészült: ${d.items.length} cikk.` : `Szárazfuttatás kész: ${d.items.length} cikk értékelve, nem mentve.`) : 'A futás nem sikerült: ' + lines, { type:r.ok ? 'success' : 'error' });
    } catch(e){ toast(e.message, { type:'error' }); }
    finally { busy(b, false); ['#colRun','#monDry','#monLive'].forEach(s => { if ($(s)) $(s).disabled = false; }); }
  }
  // Feltöltés a weboldalra
  const UPST = { kint:['b-success','check','Kint'], szaraz:['b-info','flask','Szárazon kész'], duplikatum:['b-neutral','archive','Már fent volt'], hiba:['b-danger','x-circle','Hiba'], zarolt:['b-info','clock','Folyamatban'], reszben:['b-info','clock','Részben kint'], '':['b-danger','x-circle','Hiba'] };
  $('#wpCheck')?.addEventListener('click', async () => {
    const b = $('#wpCheck'); busy(b, true);
    try {
      const w = (await api('wpellenorzes')).wp; const yes = v => v ? `<span class="badge b-success">${ic('check','icon')}igen</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}nem</span>`;
      $('#wpKv').innerHTML = [['Webhely', esc(w.site)], ['Bővítmény', esc(w.version)], ['Nyelvkezelő', esc(w.language_engine || 'nincs')], ['Publikálhat', yes(w.can_publish)], ['Képet tölthet fel', yes(w.can_upload)],
        ['Magyar alapkategória', w.hu_category ? esc(w.hu_category) : '<span class="badge b-warning">nincs beállítva</span>'], ['Angol alapkategória', w.en_category ? esc(w.en_category) : '<span class="badge b-warning">nincs beállítva</span>']]
        .map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('');
      $('#wpKv').hidden = false;
      const ok = w.can_publish && w.can_upload && w.hu_category && w.en_category && w.language_engine && String(w.version).startsWith('0.6');
      toast(ok ? 'A WordPress-kapcsolat rendben van.' : 'A kapcsolat él, de nézze meg a sárga és piros sorokat.', { type:ok ? 'success' : 'warning' });
    } catch(e){ $('#wpKv').hidden = true; toast(e.message, { type:'error' }); }
    finally { busy(b, false); }
  });
  async function upRun(live){
    if (live){ const ok = await confirmBox({ title:'Élesben feltölti a Mehet cikkeket?', body:'A cikkek magyarul és angolul azonnal megjelennek a weboldalon, a forráscikk képével és linkjével. Ami már kint van, nem kerül fel újra.', confirm:'Feltöltés élesben', danger:false }); if (!ok) return; }
    const b = $(live ? '#upLive' : '#upDry'); busy(b, true); ['#upDry','#upLive','#wpCheck'].forEach(s => { if ($(s) !== b) $(s).disabled = true; });
    announce('A feltöltés elindult, cikkenként néhány másodperc…');
    try {
      const r = (await api('feltoltes', { dry:!live })).result; const items = (r.data && r.data.items) || [];
      $('#upMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}Kész</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`) + `<span class="badge ${r.dry ? 'b-info' : 'b-neutral'}">${ic(r.dry ? 'flask' : 'play','icon')}${r.dry ? 'szárazon' : 'élesben'}</span><span>${HU.num.format(Math.round(r.duration_ms / 1000))} s</span>`;
      $('#upLines').innerHTML = r.lines.map(l => { const L = LEVEL[l.level] || LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L[0])}<span><span class="sr-only">${L[1]}: </span>${esc(l.text)}</span></li>`; }).join('');
      $('#upItems').innerHTML = items.length ? `<table><caption class="sr-only">Feltöltött cikkek</caption><thead><tr><th scope="col">Cikk</th><th scope="col">Magyar cím és ajánló</th><th scope="col">Angol cím és ajánló</th><th scope="col">Állapot</th></tr></thead><tbody>${items.map(it => { const s = UPST[it.state] || UPST['']; return `<tr><td><div class="cell-title"><span>${esc(it.ref)}</span>${esc(it.title)}</div></td><td>${it.hu ? `<div class="cell-title"><b>${esc(it.hu.title)}</b><span>${esc(it.hu.summary)}</span></div>` : '–'}</td><td>${it.en ? `<div class="cell-title"><b>${esc(it.en.title)}</b><span>${esc(it.en.summary)}</span></div>` : '–'}</td><td><span class="badge ${s[0]}">${ic(s[1],'icon')}${s[2]}</span>${it.url_hu ? ` <a href="${esc(it.url_hu)}" target="_blank" rel="noopener">HU</a>` : ''}${it.url_en ? ` <a href="${esc(it.url_en)}" target="_blank" rel="noopener">EN</a>` : ''}${it.error ? `<div class="meta">${esc(it.error)}</div>` : ''}</td></tr>`; }).join('')}</tbody></table>` : '';
      $('#upItems').hidden = !items.length; $('#upOut').hidden = false;
      const up = items.filter(i => i.state === 'kint').length;
      toast(r.ok ? (r.dry ? `Szárazfuttatás kész: ${items.length} cikk, semmi nem került fel.` : `Feltöltés kész: ${up} cikk kint van.`) : 'A feltöltés hibával zárult. A részletek a kártyán.', { type:r.ok ? 'success' : 'error' });
    } catch(e){ toast(e.message, { type:'error' }); }
    finally { busy(b, false); ['#upDry','#upLive','#wpCheck'].forEach(s => { if ($(s)) $(s).disabled = false; }); }
  }
  $('#upDry')?.addEventListener('click', () => upRun(false));
  $('#upLive')?.addEventListener('click', () => upRun(true));
  $('#colRun')?.addEventListener('click', () => collectRun('collect'));
  $('#monDry')?.addEventListener('click', () => collectRun('dry'));
  $('#monLive')?.addEventListener('click', () => collectRun('live'));
}

/* ==================================================================
   12/G. HAVI CIKKEK: döntések soronként és tömegesen, visszavonással
   ================================================================== */
if (APP.page === 'cikkek' && $('#artTable')){
  const DEC = { '':'Nincs döntés', mehet:'Mehet', linkedin:'LinkedIn poszt', nemkell:'Nem kell' };
  const canDecide = can('decide');
  const rows = (APP.data.articles || []).map(a => ({ ...a, id:String(a.id) }));
  let view = 'todo';
  const isTodo = r => !r.decision && !r.uploaded && !String(r.recommendation || '').startsWith('Nem javasolt');
  const decCell = r => {
    if (r.uploaded) return `<span class="badge b-success">${ic('check','icon')}Feltöltve</span>`;
    if (!canDecide) return r.decision ? `<span class="badge b-neutral">${esc(DEC[r.decision])}</span>` : '<span class="meta">nincs döntés</span>';
    return `<label class="sr-only" for="dec-${r.id}">Döntés: ${esc(r.title)}</label><select class="select sm dec" id="dec-${r.id}" data-dec="${r.id}">${Object.entries(DEC).map(([k, v]) => `<option value="${k}"${(r.decision || '') === k ? ' selected' : ''}>${v}</option>`).join('')}</select>`;
  };
  const upCell = r => {
    if (r.uploaded) return r.url_hu || r.url_en ? `<div class="up-cell">${r.url_hu ? `<a href="${esc(r.url_hu)}" target="_blank" rel="noopener">HU</a>` : ''}${r.url_en ? `<a href="${esc(r.url_en)}" target="_blank" rel="noopener">EN</a>` : ''}</div>` : '';
    if (r.decision === 'mehet'){
      const st = r.wp_state || '';
      if (st === 'duplikatum') return `<span class="badge b-neutral" title="${esc(r.upload_error || '')}">${ic('archive','icon')}már fent volt a weboldalon</span>`;
      if (st === 'zarolt' || st === 'reszben') return `<span class="badge b-info" title="${esc(r.upload_error || '')}">${ic('clock','icon')}${st === 'reszben' ? 'részben kint, a következő futás befejezi' : 'folyamatban, a következő futás folytatja'}</span>`;
      if (st === 'hiba') return `<div class="up-err"><span class="badge b-danger">${ic('x-circle','icon')}feltöltési hiba${r.upload_attempts >= 3 ? ' (3 próba)' : ''}</span><span class="meta">${esc(r.upload_error || '')}</span>${can('ops') && r.upload_attempts >= 3 ? `<button class="btn btn-ghost sm" type="button" data-retry="${r.id}">${ic('refresh','icon icon-sm')}Újrapróbálás</button>` : ''}</div>`;
      return `<span class="badge b-warning">${ic('clock','icon')}feltöltésre vár</span>`;
    }
    if (r.upload_status) return `<span class="badge b-danger">${ic('x-circle','icon')}${esc(r.upload_status)}</span>`;
    if (r.decision === 'linkedin') return r.post_code ? `<span class="meta">${esc(r.post_code)}</span>` : `<span class="badge b-warning">${ic('clock','icon')}poszt készül</span>`;
    return '';
  };
  const table = DataTable({
    id:'cikkek', caption:'Havi cikkek', rows, labelKey:'title', selectable:canDecide, table:$('#artTable'), pager:$('#artPager'), sort:{ key:'no', dir:'asc' }, pageSize:50,
    columns:[
      { key:'no', label:'No', align:'right', render:r => r.no ?? '–', sortValue:r => (r.from_new_source ? 10000 : 0) + (r.no || 0) },
      { key:'source_name', label:'Forrás és dátum', render:r => `<div class="src-cell">${esc(r.source_name)}<span class="meta">${esc(r.published_on ? r.published_on.replace(/-/g, '. ') + '.' : '–')}</span>${r.from_new_source ? `<span class="badge b-ai">${ic('plus','icon')}új forrás</span>` : ''}</div>`, sortValue:r => r.published_on || '' },
      { key:'title', label:'Cím és leírás', render:r => `<div class="cell-title"><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.title)}</a><span>${esc(r.description || '')}</span></div>` },
      { key:'recommendation', label:'Javaslat és indoklás', render:r => `<div class="rec-cell">${recBadge(r.recommendation)}<span class="cell-why">${esc(r.reason || '')}</span></div>`, sortValue:r => REC_ORDER.indexOf(r.recommendation || '') },
      { key:'decision', label:'Ügyfél döntése', sortable:false, render:r => `<div class="dec-cell">${decCell(r)}${upCell(r)}</div>` }
    ],
    emptyHtml:() => view === 'todo' ? 'Minden javasolt cikkről döntött. Köszönjük!' : 'Nincs a szűrésnek megfelelő cikk.',
    onSelect:n => { $('#bulk').hidden = !n; $('#artToolbar').hidden = !!n; $('#bulkCount').textContent = `${n} kiválasztva`; },
    onRender:() => { $('#cntTodo').textContent = rows.filter(isTodo).length; }
  });
  const pred = () => {
    const q = norm($('#artQ').value.trim()), rec = $('#artRec').value;
    table.setPred(r => (!q || norm(`${r.title} ${r.description || ''} ${r.source_name}`).includes(q)) && (!rec || r.recommendation === rec) && ({ todo:isTodo(r), all:true, mehet:r.decision === 'mehet', linkedin:r.decision === 'linkedin', nemkell:r.decision === 'nemkell', new:!!r.from_new_source })[view]);
  };
  $$('#artViews [data-v]').forEach(b => b.addEventListener('click', () => { view = b.dataset.v; $$('#artViews [data-v]').forEach(x => x.setAttribute('aria-pressed', x === b)); pred(); }));
  $('#artQ').addEventListener('input', pred); $('#artRec').addEventListener('change', pred);
  $('#monthSel')?.addEventListener('change', e => { location.href = 'index.php?p=cikkek&honap=' + encodeURIComponent(e.target.value); });
  async function decide(list, val){
    const prev = list.filter(r => !r.uploaded).map(r => ({ id:+r.id, decision:r.decision || '' }));
    if (!prev.length){ toast('Feltöltött cikk döntése nem módosítható.', { type:'info' }); return; }
    try {
      await api('dontes', { changes:prev.map(p => ({ id:p.id, decision:val })) });
      list.forEach(r => { if (!r.uploaded) r.decision = val; }); table.clearSel(); table.render();
      const n = prev.length;
      const msg = !val ? `Döntés törölve: ${n} cikk.` : val === 'mehet' ? `Mehet: ${n} cikk. A következő óránkénti futás tölti fel.` : val === 'linkedin' ? `LinkedIn poszt: ${n} cikk. Poszt készül belőlük.` : `Nem kell: ${n} cikk.`;
      toast(msg, { action:async () => { try { await api('dontes', { changes:prev }); prev.forEach(p => { const r = rows.find(x => +x.id === p.id); if (r) r.decision = p.decision; }); table.render(); toast('Visszavonva.', { type:'info' }); } catch(e){ toast(e.message, { type:'error' }); } } });
    } catch(e){ toast(e.message, { type:'error' }); table.render(); }
  }
  $('#artTable').addEventListener('change', e => { const s = e.target.closest('[data-dec]'); if (!s) return; const r = rows.find(x => x.id === s.dataset.dec); decide([r], s.value).then(() => $(`#dec-${r.id}`)?.focus()); });
  $$('[data-bulk]').forEach(b => b.addEventListener('click', () => decide(rows.filter(r => table.selected.includes(r.id)), b.dataset.bulk)));
  $('#artTable').addEventListener('click', async e => {
    const b = e.target.closest('[data-retry]'); if (!b) return;
    busy(b, true);
    try { await api('feltoltesujra', { id:+b.dataset.retry }); const r = rows.find(x => x.id === b.dataset.retry); r.upload_attempts = 0; r.wp_state = ''; r.upload_error = ''; table.render(); toast('A következő óránkénti futás újra megpróbálja (vagy indítsa a Vezérlőpultról).'); }
    catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
  });
  $('#bulkClear').addEventListener('click', () => table.clearSel());
  pred();
}

/* ==================================================================
   12/H. ÁTTEKINTÉS: időszakváltás, forrásnézet (diagram / táblázat)
   ================================================================== */
if (APP.page === 'attekintes'){
  $('#period')?.addEventListener('change', e => { location.href = 'index.php?p=attekintes&honap=' + encodeURIComponent(e.target.value); });
  $$('#srcView button').forEach(b => b.addEventListener('click', () => {
    $$('#srcView button').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    $('#srcBars').hidden = b.dataset.sv !== 'chart'; $('#srcTable').hidden = b.dataset.sv !== 'table';
  }));
}

/* ==================================================================
   12/I. HAVI E-MAIL (Vezérlőpult): generálás, szerkesztés, teszt, jóváhagyás és küldés
   ================================================================== */
if (APP.page === 'vezerlo' && $('#mailCard')){
  const M = APP.data.mail || {}; let email = M.email || null; let editing = false;
  const sel = $('#mailMonth');
  sel.innerHTML = (M.months || []).map(m => `<option value="${esc(m.month)}">${esc(m.label)}</option>`).join('');
  const st = M.settings || {};
  $('#mailMeta').textContent = st.to ? `Címzett: ${st.to}${st.cc ? ' · másolat: ' + st.cc : ''} · csak jóváhagyás után megy ki, piszkozat nélkül` : 'Nincs megadva címzett (Ügyfelek → Havi e-mail beállításai) · csak jóváhagyás után megy ki';
  const BADGE = { jovahagyasra_var:['b-warning','clock'], kuldes:['b-info','send'], elkuldve:['b-success','check'], hiba:['b-danger','x-circle'] };
  const showErr = msg => { $('#mailErr').hidden = !msg; $('#mailErrText').textContent = msg || ''; };
  function render(){
    const has = !!email, sent = has && (email.status === 'elkuldve' || email.status === 'kuldes');
    $('#mailNone').hidden = (M.months || []).length > 0;
    $('#mailEmpty').hidden = !(M.months || []).length || has;
    $('#mailPrev').hidden = !has; $('#mailActs').hidden = !has || sent;
    $('#mailGen').disabled = !(M.months || []).length || sent;
    $('#mailGen').innerHTML = ic('sparkle','icon icon-sm') + (has ? 'Újragenerálás' : 'E-mail generálása');
    if (!has){ $('#mailStatus').innerHTML = ''; return; }
    const b = BADGE[email.status] || ['b-neutral','info'];
    $('#mailStatus').innerHTML = `<span class="badge ${b[0]}">${ic(b[1],'icon')}${esc(email.status_label)}${email.status === 'elkuldve' && email.sent_at_hu ? ' · ' + esc(email.sent_at_hu) : ''}</span>`;
    $('#mailSubj').textContent = email.subject; $('#mailBody').textContent = email.body;
    $('#mailBody').hidden = editing; $('#mailEditWrap').hidden = !editing;
    ['mailApprove','mailEditBtn','mailTest'].forEach(id => $('#' + id).hidden = editing);
    ['mailSave','mailCancel'].forEach(id => $('#' + id).hidden = !editing);
    // A gombok mindig aktívak: ha valami hiányzik, a szerver pontos üzenete jelenik meg a kártyán.
    if (email.status === 'hiba') showErr('A legutóbbi küldés nem sikerült: ' + email.error);
    if (email.status === 'elkuldve') $('#mailMeta').textContent = `Elküldve: ${email.sent_at_hu} → ${email.sent_to}`;
  }
  async function load(){
    showErr('');
    try { const d = await api('email', { month: sel.value }); email = d.email; editing = false; render(); } catch(e){ showErr(e.message); }
  }
  sel.addEventListener('change', load);
  $('#mailGen').addEventListener('click', async () => {
    if (email){ const ok = await confirmBox({ title:'Újra megírja a levelet?', body:'A jelenlegi szöveg (a kézi módosításokkal együtt) felülíródik. Az ügyfél nem kap semmit.', confirm:'Újragenerálás', danger:false }); if (!ok) return; }
    const btn = $('#mailGen'); busy(btn, true); showErr('');
    try { const d = await api('emailgen', { month: sel.value }); email = d.row; editing = false; render(); toast('A levél elkészült. Nézze át, és ha rendben van: Jóváhagyom, mehet.'); }
    catch(e){ showErr(e.message); } finally { busy(btn, false); render(); }
  });
  $('#mailEditBtn').addEventListener('click', () => { editing = true; $('#mailEditSubj').value = email.subject; $('#mailEdit').value = email.body; render(); $('#mailEdit').focus(); });
  $('#mailCancel').addEventListener('click', () => { editing = false; render(); });
  $('#mailSave').addEventListener('click', async () => {
    const btn = $('#mailSave'); busy(btn, true);
    try { const d = await api('emailmentes', { month: sel.value, subject: $('#mailEditSubj').value, body: $('#mailEdit').value }); email = d.email; editing = false; render(); toast('A levél módosítása mentve.'); }
    catch(e){ showErr(e.message); } finally { busy(btn, false); }
  });
  $('#mailTest').addEventListener('click', async () => {
    const btn = $('#mailTest'); busy(btn, true); showErr('');
    try { const d = await api('emailteszt', { month: sel.value }); toast(`Teszt e-mail elküldve ide: ${d.to}`); }
    catch(e){ showErr(e.message); } finally { busy(btn, false); render(); }
  });
  $('#mailApprove').addEventListener('click', async () => {
    const ok = await confirmBox({ title:'Elküldi a levelet az ügyfélnek?', body:`Címzett: ${st.to}${st.cc ? '\nMásolat: ' + st.cc : ''}\nTárgy: ${email.subject}\n\nA levél azonnal kimegy, és utána nem módosítható.`, confirm:'Küldés most', danger:false });
    if (!ok) return;
    const btn = $('#mailApprove'); busy(btn, true); showErr('');
    try { const d = await api('emailkuldes', { month: sel.value }); email = d.email; toast('A levél elment az ügyfélnek.'); }
    catch(e){ showErr(e.message); await load(); } finally { busy(btn, false); render(); }
  });
  render();
}
if (APP.page === 'ugyfelek' && $('#msSave')){
  $('#msSave').addEventListener('click', async () => {
    const btn = $('#msSave'); busy(btn, true);
    try { const d = await api('emailbeallitas', { to: $('#msTo').value, cc: $('#msCc').value, style: $('#msStyle').value }); $('#msTo').value = d.settings.to; $('#msCc').value = d.settings.cc; toast('A havi e-mail beállításai mentve.'); }
    catch(e){ toast(e.message, { type:'error' }); } finally { busy(btn, false); }
  });
}

/* ==================================================================
   12/E. NAPLÓ
   ================================================================== */
if (APP.page === 'naplo'){
  const audit = (APP.data.audit || []).map((r, i) => ({ ...r, id:'a' + i, i }));
  const runs = (APP.data.runs || []).map((r, i) => ({ ...r, id:'r' + i, i, mode:r.dry ? 'dry' : 'live', res:r.ok ? 'ok' : 'err', trig:{ admin:'Felület', cron:'Cron', cli:'Parancssor' }[r.trigger] || r.trigger }));
  const at = DataTable({ id:'audit', caption:'Felhasználói műveletek', rows:audit, labelKey:'action', selectable:false, table:$('#auditTable'), pager:$('#auditPager'), sort:{ key:'created_at', dir:'desc' }, pageSize:25,
    columns:[ { key:'created_at', label:'Időpont', render:r => dtHu(r.created_at), sortValue:r => -r.i, firstDir:'desc' }, { key:'who', label:'Ki' }, { key:'action', label:'Művelet' }, { key:'client', label:'Ügyfél', render:r => esc(r.client || '–') }, { key:'details', label:'Részletek', sortable:false } ],
    emptyHtml:() => 'Még nincs művelet.' });
  const rt = DataTable({ id:'futasok', caption:'Futások', rows:runs, labelKey:'label', selectable:false, table:$('#runTable'), pager:$('#runPager'), sort:{ key:'time', dir:'desc' }, pageSize:25,
    columns:[ { key:'time', label:'Időpont', render:r => esc(r.time_hu), sortValue:r => -r.i, firstDir:'desc' }, { key:'label', label:'Feladat' },
      { key:'mode', label:'Mód', type:'status', map:{ dry:{ label:'Száraz', cls:'b-info', icon:'flask' }, live:{ label:'Éles', cls:'b-neutral', icon:'play' } } }, { key:'trig', label:'Indító' },
      { key:'res', label:'Eredmény', type:'status', map:{ ok:{ label:'OK', cls:'b-success', icon:'check' }, err:{ label:'Hiba', cls:'b-danger', icon:'x-circle' } } }, { key:'details', label:'Részletek', sortable:false } ],
    emptyHtml:() => 'Még nem volt futás.' });
  at.render(); rt.render();
  $('#aQ').addEventListener('input', e => { const q = norm(e.target.value.trim()); at.setPred(r => !q || norm(`${r.who} ${r.action} ${r.details} ${r.client || ''}`).includes(q)); });
  $('#rQ').addEventListener('input', e => { const q = norm(e.target.value.trim()); rt.setPred(r => !q || norm(`${r.label} ${r.details} ${r.trig}`).includes(q)); });
  const tabs = $$('[role=tab]');
  const sel = t => tabs.forEach(x => { const on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; $('#' + x.getAttribute('aria-controls')).hidden = !on; });
  tabs.forEach(t => { t.addEventListener('click', () => sel(t)); t.addEventListener('keydown', e => { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft'){ const o = tabs.find(x => x !== t); sel(o); o.focus(); } }); });
}

/* ==================================================================
   12/F. PROFIL: jelszócsere
   ================================================================== */
if (APP.page === 'profil'){
  $('#pwForm').addEventListener('submit', async e => {
    e.preventDefault();
    const cur = $('#pw-cur'), n1 = $('#pw-new'), n2 = $('#pw-new2'); [cur, n1, n2].forEach(i => i.removeAttribute('aria-invalid'));
    const fail = (el, msg) => { el.setAttribute('aria-invalid','true'); el.focus(); toast(msg, { type:'error' }); };
    if (!cur.value) return fail(cur, 'Adja meg a jelenlegi jelszavát.');
    if (n1.value.length < 10) return fail(n1, 'Az új jelszó legalább 10 karakter legyen.');
    if (n1.value !== n2.value) return fail(n2, 'A két új jelszó nem egyezik.');
    const b = $('#pwSave'); busy(b, true);
    try { await api('jelszocsere', { current:cur.value, new:n1.value, new2:n2.value }); e.target.reset(); toast('A jelszó módosítva.'); }
    catch(err){ toast(err.message, { type:'error' }); }
    finally { busy(b, false); }
  });
}

/* ==================================================================
   13–14. SHELL: téma, sűrűség, oldalsáv, mobilmenü, menük, billentyűk
   ================================================================== */
const themes = ['system','light','dark'], themeIcons = { system:'#i-monitor', light:'#i-sun', dark:'#i-moon' }, themeNames = { system:'rendszer', light:'világos', dark:'sötét' };
function applyTheme(t){ t === 'system' ? root.removeAttribute('data-theme') : root.setAttribute('data-theme', t); const ti = $('#themeIcon'); if (!ti) return; ti.setAttribute('href', themeIcons[t]); $('#themeBtn').title = 'Téma: ' + themeNames[t]; $('#themeBtn').setAttribute('aria-label', 'Téma váltása, jelenleg: ' + themeNames[t]); }
let theme = store.get('xp-theme') || 'system'; applyTheme(theme);
if (shell){
  $('#themeBtn').addEventListener('click', () => { theme = themes[(themes.indexOf(theme) + 1) % 3]; applyTheme(theme); store.set('xp-theme', theme); });
  const applyDensity = d => { root.setAttribute('data-density', d); $('#densityBtn').setAttribute('aria-pressed', d === 'compact'); };
  let density = store.get('xp-density') || 'comfortable'; applyDensity(density);
  $('#densityBtn').addEventListener('click', () => { density = density === 'compact' ? 'comfortable' : 'compact'; applyDensity(density); store.set('xp-density', density); });
  const setNav = open => { shell.dataset.nav = open ? 'open' : 'closed'; $('#collapseBtn').setAttribute('aria-expanded', open); $('#collapseBtn').title = open ? 'Menü összecsukása' : 'Menü kinyitása'; $('#collapseLabel').textContent = open ? 'Összecsukás' : 'Kinyitás'; $('#collapseIcon').style.transform = open ? '' : 'rotate(180deg)'; };
  const savedNav = store.get('xp-nav'); setNav(savedNav ? savedNav === 'open' : innerWidth >= 1024);
  $('#collapseBtn').addEventListener('click', () => { const o = shell.dataset.nav !== 'open'; setNav(o); store.set('xp-nav', o ? 'open' : 'closed'); });
  const mq = matchMedia('(max-width:767px)');
  const syncInert = () => { const m = mq.matches, o = shell.dataset.mobileOpen === 'true'; $('#sidebar').inert = m && !o; $('.main-col').inert = m && o; };
  const setMobile = open => { shell.dataset.mobileOpen = open; $('#menuToggle').setAttribute('aria-expanded', open); syncInert(); (open ? $('#sidebar .nav-item') : $('#menuToggle')).focus(); };
  mq.addEventListener('change', () => { shell.dataset.mobileOpen = false; $('#menuToggle').setAttribute('aria-expanded', false); syncInert(); });
  syncInert();
  $('#menuToggle').addEventListener('click', () => setMobile(true));
  $('#scrim').addEventListener('click', () => setMobile(false));
  const navTip = document.createElement('div'); navTip.className = 'nav-tip'; navTip.setAttribute('aria-hidden', 'true'); navTip.hidden = true; document.body.append(navTip);
  $('#sidebar').addEventListener('focusin', e => { const a = e.target.closest('.nav-item'); if (!a || shell.dataset.nav !== 'closed' || mq.matches || !a.matches(':focus-visible')) return; const r = a.getBoundingClientRect(); navTip.textContent = a.title; navTip.style.top = (r.top + r.height / 2) + 'px'; navTip.style.left = (r.right + 8) + 'px'; navTip.hidden = false; });
  $('#sidebar').addEventListener('focusout', () => { navTip.hidden = true; });
  $('#skipLink').addEventListener('click', e => { e.preventDefault(); $('#h-page')?.focus(); });
  popover($('#profileBtn'), $('#profileMenu'));
  popover($('#wsBtn'), $('#wsMenu'));
  document.addEventListener('keydown', e => { if (e.key !== 'Escape' || $('dialog[open]')) return; navTip.hidden = true; if (shell.dataset.mobileOpen === 'true') setMobile(false); closeAll(); });
}

})();
