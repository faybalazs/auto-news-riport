/*
  Xperion automatizálás – admin felület.
  Forrás: Auris admin sablon v1.0.2 (JS 0–3. és 9. szekció változatlanul, a DataTable
  két kiegészítéssel: selectable:false és setLoading()). A projektre szabott rész: 12–14.
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


/* ==================================================================
   12/E. BELÉPÉS (kulccsal; a szerver hitelesít, itt csak kényelmi funkciók)
   ================================================================== */
function initPwToggles(){
  $$('.pw-toggle').forEach(b => b.addEventListener('click', () => {
    const inp = document.getElementById(b.getAttribute('aria-controls')), show = inp.type === 'password';
    inp.type = show ? 'text' : 'password'; b.setAttribute('aria-pressed', show);
    b.setAttribute('aria-label', show ? 'Kulcs elrejtése' : 'Kulcs megjelenítése');
    $('use', b).setAttribute('href', show ? '#i-eye-off' : '#i-eye');
  }));
}
function initLogin(){
  const form = $('#loginForm'); if (!form) return false;
  initPwToggles();
  const k = $('#l-key');
  ['keydown','keyup'].forEach(ev => k.addEventListener(ev, e => { if (e.getModifierState) $('#capsWarn').hidden = !e.getModifierState('CapsLock'); }));
  k.addEventListener('blur', () => { $('#capsWarn').hidden = true; });
  form.addEventListener('submit', e => {
    if (!k.value.trim()){ e.preventDefault(); k.setAttribute('aria-invalid','true'); k.focus(); toast('Adja meg a hozzáférési kulcsot.', { type:'error' }); return; }
    $('#loginBtn').setAttribute('aria-busy','true');
  });
  const err = $('#loginErr'); if (err) err.focus();
  return true;
}

if (!initLogin()){

/* ==================================================================
   12. VEZÉRLŐPULT: feladatok futtatása, eredmény
   ================================================================== */
const DATA = JSON.parse($('#appData').textContent);
async function api(params, body){
  const url = DATA.api + '?' + new URLSearchParams(params);
  const res = await fetch(url, body ? { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token':DATA.csrf }, body:JSON.stringify(body), credentials:'same-origin' } : { credentials:'same-origin' });
  if (res.status === 401){ location.reload(); throw new Error('A munkamenet lejárt, lépjen be újra.'); }
  let data; try { data = await res.json(); } catch(e){ throw new Error(`A szerver nem adott értelmezhető választ (HTTP ${res.status}).`); }
  if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}
const LEVEL = {
  ok:{ i:'check', sr:'Kész' }, info:{ i:'chev-right', sr:'Információ' }, warn:{ i:'alert', sr:'Figyelmeztetés' },
  error:{ i:'x-circle', sr:'Hiba' }, dry:{ i:'info', sr:'Szárazfuttatás' }
};
function renderResult(r){
  const card = $('#resultCard');
  $('#h-result').textContent = `Eredmény: ${r.label}`;
  $('#resultMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}OK</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`)
    + `<span class="badge ${r.dry ? 'b-info' : 'b-neutral'}">${ic(r.dry ? 'flask' : 'play','icon')}${r.dry ? 'Szárazfuttatás' : 'Éles futás'}</span>`
    + `<span>${esc(r.time_hu)} · ${HU.num.format(r.duration_ms)} ms</span>`;
  $('#resultLines').innerHTML = r.lines.length ? r.lines.map(l => { const L = LEVEL[l.level] || LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L.i)}<span><span class="sr-only">${L.sr}: </span>${esc(l.text)}</span></li>`; }).join('') : `<li class="l-info">${ic('info')}<span>Nincs részlet.</span></li>`;
  card.hidden = false;
}
function setLast(taskId, r){
  const el = $(`[data-task="${taskId}"] [data-last]`); if (!el) return;
  el.innerHTML = `<span>Legutóbb: ${esc(r.time_hu)} · ${r.dry ? 'száraz' : 'éles'}</span>` + (r.ok ? `<span class="badge b-success">${ic('check','icon')}OK</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`);
}
let running = false;
$$('[data-run]').forEach(btn => btn.addEventListener('click', async () => {
  if (running) return;
  const card = btn.closest('[data-task]'), id = btn.dataset.run, dry = btn.dataset.dry === '1', label = card.dataset.label;
  if (!dry && card.dataset.readonly !== '1'){
    const ok = await confirmBox({ title:`Élesben futtatja: ${label}?`, body:'Az éles futás írhat a Google Sheetbe. Ha nem biztos benne, előbb futtassa szárazon.', confirm:'Éles futtatás', danger:false });
    if (!ok) return;
  }
  running = true; btn.setAttribute('aria-busy','true'); $$('[data-run]').forEach(b => b.disabled = b !== btn);
  announce(`${label} fut…`);
  try {
    const r = await api({ muvelet:'futtatas' }, { feladat:id, dry });
    renderResult(r); setLast(id, r); loaded.naplo = false;
    toast(r.ok ? `${label}: kész${r.dry ? ' (szárazfuttatás)' : ''}.` : `${label}: hiba történt. A részletek az Eredmény kártyán.`, { type: r.ok ? 'success' : 'error' });
    $('#resultCard').focus({ preventScroll:false });
  } catch(e){
    toast(`${label}: ${e.message}`, { type:'error' });
  } finally {
    running = false; btn.removeAttribute('aria-busy'); $$('[data-run]').forEach(b => b.disabled = false);
  }
}));
$('#resultClose').addEventListener('click', () => { $('#resultCard').hidden = true; $('#h-vezerlo').focus(); });
$$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(b.dataset.copy); toast('A cím a vágólapra került.'); }
  catch(e){ toast('A másolás nem sikerült. Jelölje ki és másolja kézzel.', { type:'warning' }); }
}));

/* ==================================================================
   12/B. NAPLÓ: táblázat a helyi naplóból
   ================================================================== */
const TRIGGER = { admin:'Admin', cron:'Cron', cli:'Parancssor' };
const logRows = [];
const logTable = DataTable({
  id:'naplo', caption:'Futások naplója', rows:logRows, labelKey:'label', selectable:false,
  table:$('#logTable'), pager:$('#logPager'), exportBtn:$('#logExport'),
  sort:{ key:'time', dir:'desc' }, pageSize:25,
  columns:[
    { key:'time', label:'Időpont', firstDir:'desc', sortValue:r => r.ts },
    { key:'label', label:'Feladat' },
    { key:'mode', label:'Mód', type:'status', map:{ dry:{ label:'Száraz', cls:'b-info', icon:'flask' }, live:{ label:'Éles', cls:'b-neutral', icon:'play' } } },
    { key:'trigger', label:'Indító' },
    { key:'result', label:'Eredmény', type:'status', map:{ ok:{ label:'OK', cls:'b-success', icon:'check' }, err:{ label:'Hiba', cls:'b-danger', icon:'x-circle' } } },
    { key:'details', label:'Részletek', sortable:false },
    { key:'duration', label:'Időtartam (ms)', type:'num', align:'right' }
  ],
  emptyHtml:() => logRows.length ? 'Nincs a szűrésnek megfelelő futás.' : 'Még nem volt futás. Indítson egyet a Vezérlőpulton.'
});
function logPred(){
  const q = norm($('#logq').value.trim()), ok = $('#logOk').value, mode = $('#logMode').value;
  logTable.setPred(r => (!q || norm(r.label + ' ' + r.details + ' ' + r.trigger).includes(q)) && (!ok || r.result === ok) && (!mode || r.mode === mode));
}
['input','change'].forEach(ev => ['#logq','#logOk','#logMode'].forEach(s => $(s).addEventListener(ev, logPred)));
async function loadLog(){
  logTable.setLoading(true);
  try {
    const d = await api({ muvelet:'naplo' });
    logRows.splice(0, logRows.length, ...d.entries.map((e, i) => ({
      id:String(i), time:e.time_hu, ts:e.time, label:e.label, mode:e.dry ? 'dry' : 'live', trigger:TRIGGER[e.trigger] || e.trigger,
      result:e.ok ? 'ok' : 'err', details:e.details, duration:e.duration_ms
    })));
  } catch(e){ toast('A napló betöltése nem sikerült: ' + e.message, { type:'error' }); }
  logTable.setLoading(false);
}
$('#logRefresh').addEventListener('click', async () => { const b = $('#logRefresh'); b.setAttribute('aria-busy','true'); await loadLog(); b.removeAttribute('aria-busy'); announce(`Napló frissítve: ${logRows.length} bejegyzés.`); });

/* ==================================================================
   13. NÉZETVÁLTÁS (#vezerlo, #naplo)
   ================================================================== */
const views = { vezerlo:$('#v-vezerlo'), naplo:$('#v-naplo') };
const TITLES = { vezerlo:'Vezérlőpult', naplo:'Napló' };
const loaded = {};
let currentView = null;
function show(name){
  closeAll();
  Object.entries(views).forEach(([k, v]) => v.hidden = k !== name);
  $$('#sidebar [data-nav]').forEach(a => a.dataset.nav.split(' ').includes(name) ? a.setAttribute('aria-current','page') : a.removeAttribute('aria-current'));
  const first = currentView === null; currentView = name;
  document.title = TITLES[name] + ' · Xperion automatizálás';
  if (!loaded[name]){ loaded[name] = true; if (name === 'naplo') loadLog(); }
  if (!first){ window.scrollTo(0, 0); $('h1', views[name]).focus({ preventScroll:true }); }
}
function route(){ show(views[location.hash.slice(1)] ? location.hash.slice(1) : 'vezerlo'); }
window.addEventListener('hashchange', route);

/* ==================================================================
   14. SHELL: téma, sűrűség, oldalsáv, mobilmenü, billentyűk
   ================================================================== */
const themes = ['system','light','dark'], themeIcons = { system:'#i-monitor', light:'#i-sun', dark:'#i-moon' }, themeNames = { system:'rendszer', light:'világos', dark:'sötét' };
function applyTheme(t){ t === 'system' ? root.removeAttribute('data-theme') : root.setAttribute('data-theme', t); $('#themeIcon').setAttribute('href', themeIcons[t]); $('#themeBtn').title = 'Téma: ' + themeNames[t]; $('#themeBtn').setAttribute('aria-label', 'Téma váltása, jelenleg: ' + themeNames[t]); }
let theme = store.get('adm-theme') || 'system'; applyTheme(theme);
$('#themeBtn').addEventListener('click', () => { theme = themes[(themes.indexOf(theme) + 1) % 3]; applyTheme(theme); store.set('adm-theme', theme); });
function applyDensity(d){ root.setAttribute('data-density', d); $('#densityBtn').setAttribute('aria-pressed', d === 'compact'); }
let density = store.get('adm-density') || 'comfortable'; applyDensity(density);
$('#densityBtn').addEventListener('click', () => { density = density === 'compact' ? 'comfortable' : 'compact'; applyDensity(density); store.set('adm-density', density); });
function setNav(open){ shell.dataset.nav = open ? 'open' : 'closed'; $('#collapseBtn').setAttribute('aria-expanded', open); $('#collapseBtn').title = open ? 'Menü összecsukása' : 'Menü kinyitása'; $('#collapseLabel').textContent = open ? 'Összecsukás' : 'Kinyitás'; $('#collapseIcon').style.transform = open ? '' : 'rotate(180deg)'; }
const savedNav = store.get('adm-nav'); setNav(savedNav ? savedNav === 'open' : innerWidth >= 1024);
$('#collapseBtn').addEventListener('click', () => { const o = shell.dataset.nav !== 'open'; setNav(o); store.set('adm-nav', o ? 'open' : 'closed'); });
const mqMobile = matchMedia('(max-width:767px)');
function syncInert(){ const m = mqMobile.matches, o = shell.dataset.mobileOpen === 'true'; $('#sidebar').inert = m && !o; $('.main-col').inert = m && o; }
function setMobile(open){ shell.dataset.mobileOpen = open; $('#menuToggle').setAttribute('aria-expanded', open); syncInert(); (open ? $('#sidebar .nav-item') : $('#menuToggle')).focus(); }
mqMobile.addEventListener('change', () => { shell.dataset.mobileOpen = false; $('#menuToggle').setAttribute('aria-expanded', false); syncInert(); });
syncInert();
const navTip = document.createElement('div'); navTip.className = 'nav-tip'; navTip.setAttribute('aria-hidden', 'true'); navTip.hidden = true; document.body.append(navTip);
$('#sidebar').addEventListener('focusin', e => {
  const a = e.target.closest('.nav-item');
  if (!a || shell.dataset.nav !== 'closed' || mqMobile.matches || !a.matches(':focus-visible')) return;
  const r = a.getBoundingClientRect(); navTip.textContent = a.title; navTip.style.top = (r.top + r.height / 2) + 'px'; navTip.style.left = (r.right + 8) + 'px'; navTip.hidden = false;
});
$('#sidebar').addEventListener('focusout', () => { navTip.hidden = true; });
$('#skipLink').addEventListener('click', e => { e.preventDefault(); const h = currentView && $('h1', views[currentView]); h && h.focus(); });
$('#menuToggle').addEventListener('click', () => setMobile(true));
$('#scrim').addEventListener('click', () => setMobile(false));
$$('#sidebar a.nav-item').forEach(a => a.addEventListener('click', () => { if (mqMobile.matches){ shell.dataset.mobileOpen = false; $('#menuToggle').setAttribute('aria-expanded', false); syncInert(); } }));
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape' || $('dialog[open]')) return;
  navTip.hidden = true;
  if (shell.dataset.mobileOpen === 'true') setMobile(false);
  closeAll();
});

/* ==================================================================
   INDULÁS
   ================================================================== */
route();
}
})();
