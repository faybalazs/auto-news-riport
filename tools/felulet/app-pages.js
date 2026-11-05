
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
/* (i) súgó: Esc rejti a nyitott buborékot (WCAG 1.4.13), érintésnél a második koppintás bezárja */
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.activeElement?.classList.contains('tip-btn')) document.activeElement.blur(); });
document.addEventListener('click', e => { const b = e.target.closest('.tip-btn'); if (b && b.dataset.open === '1'){ b.blur(); b.dataset.open = ''; } else if (b) b.dataset.open = '1'; });
/* formBox segédei (a sablon űrlapellenőrzése nélkül): kötelező mezők */
function validatables(form){ return $$('[required]', form); }
function validate(el){ const ok = String(el.value || '').trim() !== ''; el.setAttribute('aria-invalid', String(!ok)); return ok; }
/* Posztgenerálás utasításokkal: a szöveghez és a képhez külön (a szabályokat és a tényeket nem írhatják felül) */
async function genDialog({ title, intro = '', confirm, text = '', image = '' }){
  const fd = await formBox({ title, confirm, html:`${intro ? `<p class="dlg-intro">${intro}</p>` : ''}
    <div class="field"><label for="g-text">Utasítás a szöveghez <span class="opt">(nem kötelező)</span></label><textarea class="input" id="g-text" name="instr_text" rows="3" placeholder="pl. A bankfiókok vezetőinek szóljon; emelje ki a várakozási idő csökkenését; rövidebb legyen.">${esc(text)}</textarea></div>
    <div class="field"><label for="g-image">Utasítás a képhez <span class="opt">(nem kötelező)</span></label><textarea class="input" id="g-image" name="instr_image" rows="2" placeholder="pl. Kulcsszám elrendezés a 38%-os adattal; kevesebb szöveg.">${esc(image)}</textarea></div>
    <p class="help">A Posztszabályok és a cikkek tényei mindig érvényesek; az utasítás ezeken belül irányít. A poszthoz mentődik, és újragenerálásnál is érvényes marad.</p>` });
  return fd ? { instr_text:String(fd.get('instr_text') || ''), instr_image:String(fd.get('instr_image') || '') } : null;
}

/* Feltöltés a weboldalra: közös futtató a Vezérlőpultnak és a Havi cikkeknek */
const UP_LEVEL = { ok:['check','Kész'], info:['chev-right','Információ'], warn:['alert','Figyelmeztetés'], error:['x-circle','Hiba'], dry:['info','Szárazfuttatás'] };
const UPST = { kint:['b-success','check','Publikálva'], szaraz:['b-info','flask','Szöveg kész'], duplikatum:['b-neutral','archive','Már fent volt'], hiba:['b-danger','x-circle','Hiba'], zarolt:['b-info','clock','Folyamatban'], reszben:['b-info','clock','Részben kint'], '':['b-danger','x-circle','Hiba'] };
async function uploadRun(live, { lock = [], onDone } = {}){
  if (live){ const ok = await confirmBox({ title:'Élesben feltölti a Mehet cikkeket?', body:'A cikkek magyarul és angolul azonnal megjelennek a weboldalon, a forráscikk képével és linkjével, és „Publikálva” állapotba kerülnek. Ami már kint van, nem kerül fel újra.', confirm:'Feltöltés élesben', danger:false }); if (!ok) return; }
  const b = $(live ? '#upLive' : '#upDry'); busy(b, true); const others = ['#upDry','#upLive', ...lock].filter(s => $(s) && $(s) !== b); others.forEach(s => { $(s).disabled = true; });
  announce(live ? 'A feltöltés elindult, cikkenként néhány másodperc…' : 'A szövegek készülnek, cikkenként néhány másodperc…');
  try {
    const r = (await api('feltoltes', { dry:!live })).result; const items = (r.data && r.data.items) || [];
    $('#upMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}Kész</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`) + `<span class="badge ${r.dry ? 'b-info' : 'b-neutral'}">${ic(r.dry ? 'flask' : 'play','icon')}${r.dry ? 'szárazon' : 'élesben'}</span><span>${HU.num.format(Math.round(r.duration_ms / 1000))} s</span>`;
    $('#upLines').innerHTML = r.lines.map(l => { const L = UP_LEVEL[l.level] || UP_LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L[0])}<span><span class="sr-only">${L[1]}: </span>${esc(l.text)}</span></li>`; }).join('');
    $('#upItems').innerHTML = items.length ? `<table><caption class="sr-only">Feltöltött cikkek</caption><thead><tr><th scope="col">Cikk</th><th scope="col">Magyar cím és ajánló</th><th scope="col">Angol cím és ajánló</th><th scope="col">Állapot</th></tr></thead><tbody>${items.map(it => { const st = UPST[it.state] || UPST['']; return `<tr><td><div class="cell-title"><span>${esc(it.ref)}</span>${esc(it.title)}</div></td><td>${it.hu ? `<div class="cell-title"><b>${esc(it.hu.title)}</b><span>${esc(it.hu.summary)}</span></div>` : '–'}</td><td>${it.en ? `<div class="cell-title"><b>${esc(it.en.title)}</b><span>${esc(it.en.summary)}</span></div>` : '–'}</td><td><span class="badge ${st[0]}">${ic(st[1],'icon')}${st[2]}</span>${it.url_hu ? ` <a href="${esc(it.url_hu)}" target="_blank" rel="noopener">HU</a>` : ''}${it.url_en ? ` <a href="${esc(it.url_en)}" target="_blank" rel="noopener">EN</a>` : ''}${it.error ? `<div class="meta">${esc(it.error)}</div>` : ''}</td></tr>`; }).join('')}</tbody></table>` : '';
    $('#upItems').hidden = !items.length; $('#upOut').hidden = false;
    const up = items.filter(i => i.state === 'kint').length;
    toast(r.ok ? (r.dry ? `A szövegek elkészültek: ${items.length} cikk. A weboldalra nem került semmi.` : `Feltöltés kész: ${up} cikk publikálva.`) : 'A feltöltés hibával zárult. A részletek a kártyán.', { type:r.ok ? 'success' : 'error' });
    onDone && onDone(r, items);
  } catch(e){ toast(e.message, { type:'error' }); }
  finally { busy(b, false); others.forEach(s => { if ($(s)) $(s).disabled = false; }); }
}
/* Vezetői összefoglaló újragenerálása (Havi cikkek, Áttekintés) */
$('#sumRegen')?.addEventListener('click', async e => {
  const b = e.currentTarget, again = /Újra/.test(b.textContent);
  if (!await confirmBox({ title: again ? 'Újragenerálja a vezetői összefoglalót?' : 'Elkészíti a vezetői összefoglalót?', body:`A Claude a hónap cikkeiből és a mostani döntésekből írja meg: rövid bevezető, majd Újdonságok, Tények, számok és Trendek, konkrét forrásokkal. ${again ? 'A mostani szöveg felülíródik. ' : ''}Néhány cent, 20–40 másodperc.`, confirm: again ? 'Újragenerálás' : 'Összefoglaló készítése', danger:false })) return;
  busy(b, true); announce('Az összefoglaló készül, 20–40 másodperc…');
  try { await api('osszefoglalo', { month:b.dataset.month }); toast('Az összefoglaló elkészült.'); setTimeout(() => location.reload(), 600); }
  catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
});
const SUM_IC = { 'Újdonságok':['bulb','orange'], 'Tények, számok':['chart','blue'], 'Trendek':['arrow-up','green'] };
/* Vezetői összefoglaló küldése e-mailben (a felhasználó adja meg a címzettet; egyszer megy ki) */
$('#sumSend')?.addEventListener('click', async e => {
  const b = e.currentTarget;
  const fd = await formBox({ title:'Vezetői összefoglaló küldése', confirm:'Küldés', html:`
    <div class="field"><label for="ss-to">Címzett</label><input class="input" id="ss-to" name="to" type="text" inputmode="email" autocomplete="off" required placeholder="nev@ceg.hu (több cím vesszővel)"></div>
    <div class="field"><label for="ss-cc">Másolat <span class="opt">(nem kötelező)</span></label><input class="input" id="ss-cc" name="cc" type="text" inputmode="email" autocomplete="off"></div>
    <div class="field"><label for="ss-note">Kísérőszöveg <span class="opt">(nem kötelező)</span></label><textarea class="input" id="ss-note" name="note" rows="3" placeholder="pl. Kedves Kollégák! Küldöm az októberi összefoglalót."></textarea></div>
    <p class="help">A levél az összefoglaló teljes szövegét és a hónap cikkeinek linkjét tartalmazza. Elküldés után nem vonható vissza; a Naplóban látszik.</p>` });
  if (!fd) return;
  busy(b, true);
  try { const d = await api('osszefoglalokuldes', { month:b.dataset.month, to:fd.get('to'), cc:fd.get('cc'), note:fd.get('note') }); toast('Elküldve: ' + d.to.join(', ')); }
  catch(err){ toast(err.message, { type:'error' }); } finally { busy(b, false); }
});
const sumHtml = sum => sum ? `${sum.details ? `<p class="sum-lead">${esc(sum.details)}</p>` : ''}${(sum.sections || []).length ? `<div class="sum-groups">${sum.sections.map(g => `<div class="sum-group"><h3>${SUM_IC[g.title] ? `<span class="ic-tile t-${SUM_IC[g.title][1]}" aria-hidden="true">${ic(SUM_IC[g.title][0],'icon')}</span>` : ''}${esc(g.title)}</h3><ul>${g.points.map(p => `<li>${esc(p)}</li>`).join('')}</ul></div>`).join('')}</div>` : ''}${(sum.rows || []).length ? `<dl class="kv">${sum.rows.map(x => `<div><dt>${esc(x.label)}</dt><dd>${esc(x.value)}</dd></div>`).join('')}</dl>` : ''}` : '';
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
  // A beállítási lépéseket a szerver rajzolja (valódi állapottal); változás után újratöltéskor frissül.
  function renderOnb(){}
  $('#csSave')?.addEventListener('click', async () => {
    const b = $('#csSave'); busy(b, true);
    try { const settings = Object.fromEntries(['linkedin_nev','linkedin_kovetok','modell_ertekeles','modell_poszt','modell_linkedin','modell_feltoltes','alap_kep'].map(k => [k, $('#cs-' + k).value])); await api('ugyfelbeallitas', { settings }); toast('Az ügyfélbeállítások mentve.'); }
    catch(e){ toast(e.message, { type:'error' }); } finally { busy(b, false); }
  });
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
  $('#impDry')?.addEventListener('click', () => runImp(true));
  $('#impLive')?.addEventListener('click', () => runImp(false));
  // Kiválasztott ügyfél: tabok (a hash vagy a legutóbb nyitott tab szerint)
  if ($('#cdTabs')){
    const tabs = $$('#cdTabs [role=tab]'), key = 'xp-cd-tab';
    const sel = (t, focus) => { tabs.forEach(x => { const on = x === t; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; $('#' + x.getAttribute('aria-controls')).hidden = !on; }); try { localStorage.setItem(key, t.id); } catch(e){} if (focus) t.focus(); };
    tabs.forEach((t, i) => { t.addEventListener('click', () => { sel(t); history.replaceState(null, '', '#' + t.id.replace('tab-', '')); });
      t.addEventListener('keydown', e => { const k = { ArrowRight:1, ArrowLeft:-1 }[e.key]; if (k){ e.preventDefault(); sel(tabs[(i + k + tabs.length) % tabs.length], true); } if (e.key === 'Home'){ e.preventDefault(); sel(tabs[0], true); } if (e.key === 'End'){ e.preventDefault(); sel(tabs[tabs.length - 1], true); } }); });
    let start = location.hash ? $('#tab-' + location.hash.slice(1)) : null;
    if (!start){ try { start = $('#' + localStorage.getItem(key)); } catch(e){} }
    if (start && tabs.includes(start)) sel(start);
    if (location.hash && start) $('.client-detail').scrollIntoView({ block:'start' });
    window.addEventListener('hashchange', () => { const t = $('#tab-' + location.hash.slice(1)); if (t && tabs.includes(t)){ sel(t); $('.client-detail').scrollIntoView({ block:'start' }); } });
  }
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
  // Futások állapota: mi fut most (mióta, ki, melyik lépésnél), legutóbbi eredmények; frissül magától
  const RUN_BTNS = '[data-run], #colRun, #monDry, #monLive, #upDry, #upLive';
  let RS = APP.data.run || { running:null, results:[] }, wasRunning = !!RS.running, rsTimer = null, tick = null;
  function showResult(r){
    $('#h-result').textContent = 'Eredmény: ' + r.label;
    $('#resultMeta').innerHTML = (r.ok ? `<span class="badge b-success">${ic('check','icon')}OK</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}Hiba</span>`) + `<span class="badge ${r.dry ? 'b-info' : 'b-neutral'}">${ic(r.dry ? 'flask' : 'play','icon')}${r.dry ? 'szárazon' : 'élesben'}</span><span class="meta">${esc(r.finished_hu || '')} · ${esc(r.duration_hu || '')} · ${esc(r.by || '')}</span>`;
    $('#resultLines').innerHTML = (r.lines || []).map(l => { const L = LEVEL[l.level] || LEVEL.info; return `<li class="l-${esc(l.level)}">${ic(L[0])}<span><span class="sr-only">${L[1]}: </span>${esc(l.text)}</span></li>`; }).join('');
    $('#resultCard').hidden = false; $('#resultCard').focus();
  }
  function renderRS(){
    const r = RS.running;
    $('#rsBadge').innerHTML = r ? `<span class="badge b-info">${ic('clock','icon')}fut</span>` : `<span class="badge b-success">${ic('check','icon')}nincs futás</span>`;
    $('#rsNow').hidden = !r;
    if (r){
      const el = (r.elapsed || 0) + (tick ? Math.round((Date.now() - tick) / 1000) : 0);
      const dur = el < 60 ? `${el} mp` : `${Math.floor(el / 60)} perc ${el % 60} mp`;
      $('#rsNow').innerHTML = `<span class="spinner" aria-hidden="true"></span><div><b>Fut: ${esc(r.label || r.task || 'futás')}${r.dry ? ' (szárazon)' : ''}</b><span class="meta">${esc(r.started_hu ? r.started_hu + '-kor indult, ' : '')}${dur} óta · indította: ${esc(r.by || '–')}</span>${r.step ? `<span class="rs-step">Most: ${esc(r.step)}</span>` : ''}<span class="meta">Nyugodtan navigálhat máshová: a futás a szerveren folytatódik, az eredményt itt találja.</span></div>`;
    }
    $$(RUN_BTNS).forEach(b => { if (b.dataset.base === undefined) b.dataset.base = b.disabled ? '1' : '0'; if (!b.hasAttribute('aria-busy')){ b.disabled = !!r || b.dataset.base === '1'; b.title = r ? 'Már fut egy feladat; várja meg a végét.' : ''; } });
    const res = RS.results || [];
    $('#rsList').innerHTML = res.length ? res.map((x, i) => `<li><span class="badge ${x.ok ? 'b-success' : 'b-danger'}">${ic(x.ok ? 'check' : 'x-circle','icon')}${x.ok ? 'OK' : 'Hiba'}</span><div><b>${esc(x.label)}</b>${x.dry ? ' <span class="meta">(szárazon)</span>' : ''}<span class="meta">${esc(x.finished_hu)} · ${esc(x.duration_hu)} · ${esc(x.by)}</span></div><button class="btn btn-ghost sm" type="button" data-rs="${i}">Eredmény${ic('chev-right')}</button></li>`).join('') : '<li class="meta">Még nincs mentett futáseredmény.</li>';
  }
  async function pollRS(){
    try {
      RS = await api('futasallapot'); tick = Date.now();
      if (wasRunning && !RS.running && RS.results && RS.results[0]){ const r = RS.results[0]; toast(`Lefutott: ${r.label} – ${r.ok ? 'rendben' : 'hibával'}.`, { type:r.ok ? 'success' : 'error', action:() => showResult(r), actionLabel:'Eredmény' }); }
      wasRunning = !!RS.running; renderRS();
    } catch(e){}
    clearTimeout(rsTimer); rsTimer = setTimeout(pollRS, RS.running ? 3000 : 20000);
  }
  $('#rsList').addEventListener('click', e => { const b = e.target.closest('[data-rs]'); if (b) showResult(RS.results[+b.dataset.rs]); });
  setInterval(() => { if (RS.running) renderRS(); }, 1000);
  tick = Date.now(); renderRS(); rsTimer = setTimeout(pollRS, RS.running ? 3000 : 20000);
  // saját indításnál azonnal frissüljön
  document.addEventListener('click', e => { if (e.target.closest(RUN_BTNS)) setTimeout(pollRS, 1200); }, true);
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
      $('#monSum').innerHTML = sum ? `<div class="widget-head" style="margin-top:16px"><div><h3 class="sub-h" style="margin:0">Vezetői összefoglaló${live ? '' : ' (szárazon, nem mentve)'}</h3></div><span class="badge b-ai">${ic('sparkle','icon')}AI által generált</span></div><div class="sum-text">${sumHtml(sum)}</div>` : (d.exists ? `<div class="empty-state" style="margin-top:12px"><b>Ehhez a hónaphoz már van lista</b><span>A Havi cikkek oldalon látható.</span></div>` : '');
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
  $('#wpCheck')?.addEventListener('click', async () => {
    const b = $('#wpCheck'); busy(b, true); $('#wpErr').hidden = true;
    try {
      const w = (await api('wpellenorzes')).wp; const yes = v => v ? `<span class="badge b-success">${ic('check','icon')}igen</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}nem</span>`;
      $('#wpKv').innerHTML = [['Webhely', esc(w.site)], ['Bővítmény', esc(w.version)], ['Nyelvkezelő', esc(w.language_engine || 'nincs')], ['Publikálhat', yes(w.can_publish)], ['Képet tölthet fel', yes(w.can_upload)],
        ['Magyar alapkategória', w.hu_category ? esc(w.hu_category) : '<span class="badge b-warning">nincs beállítva</span>'], ['Angol alapkategória', w.en_category ? esc(w.en_category) : '<span class="badge b-warning">nincs beállítva</span>']]
        .map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('');
      $('#wpKv').hidden = false;
      const ok = w.can_publish && w.can_upload && w.hu_category && w.en_category && w.language_engine && String(w.version).startsWith('0.6');
      toast(ok ? 'A WordPress-kapcsolat rendben van.' : 'A kapcsolat él, de nézze meg a sárga és piros sorokat.', { type:ok ? 'success' : 'warning' });
    } catch(e){
      // a szerver a másolandó részt ``` jelek közé teszi: azt másolható dobozba emeljük
      const m = e.message.match(/\n```\n([\s\S]*?)\n```\n?/);
      $('#wpKv').hidden = true; $('#wpErrText').textContent = m ? e.message.replace(m[0], '\n').trim() : e.message; $('#wpErr').hidden = false;
      $('#wpErrHelper').hidden = !e.message.includes('xperion-auth-fejlec.php') || e.message.includes('telepítve van');
      $('#wpErrCopy').innerHTML = m ? `<div class="copy-box"><code style="white-space:pre-wrap;overflow-wrap:anywhere">${esc(m[1])}</code><button class="btn btn-ghost sm" type="button" data-copy="${esc(m[1])}">${ic('copy')}Másolás</button></div>` : '';
      toast('A WordPress-kapcsolat nem működik; az ok és a teendő a kártyán.', { type:'error' });
    }
    finally { busy(b, false); }
  });
  const upRun = live => uploadRun(live, { lock:['#wpCheck'] });
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
    if (r.uploaded) return `<span class="badge b-success" title="A döntés a feltöltés visszavonása után módosítható (a sor ⋯ menüje).">${ic('check','icon')}Publikálva</span>`;
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
      return `<span class="badge b-warning">${ic('clock','icon')}feltöltésre vár</span>${r.hu_title ? `<details class="up-text"><summary>Elkészült szöveg (HU / EN)</summary><div class="cell-title"><b>${esc(r.hu_title)}</b><span>${esc(r.hu_summary || '')}</span></div><div class="cell-title"><b>${esc(r.en_title || '')}</b><span>${esc(r.en_summary || '')}</span></div></details>` : ''}`;
    }
    if (r.upload_status) return `<span class="badge b-danger">${ic('x-circle','icon')}${esc(r.upload_status)}</span>`;
    if (r.decision === 'linkedin') return r.post_code ? `<a class="badge b-ai" href="index.php?p=poszt&amp;kod=${encodeURIComponent(r.post_code)}">${ic('linkedin','icon')}${esc(r.post_code)}</a>`
      : `<span class="badge b-warning">${ic('clock','icon')}posztra vár</span>`;
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
    rowMenu:(can('decide') || can('upload')) ? (btn, r) => {
      const items = [];
      if (r.uploaded && can('upload')) items.push({ k:'revert', l:'Feltöltés visszavonása…', i:'refresh' });
      if (!r.uploaded && can('decide')) items.push({ k:'del', l:'Törlés a listából', i:'trash', danger:true });
      if (!items.length){ toast(r.uploaded ? 'Publikált cikk: a feltöltést a feltöltési joggal rendelkező felhasználó vonhatja vissza.' : 'Ehhez a cikkhez nincs elérhető művelet.', { type:'info' }); return; }
      openRowMenu(btn, r, { revert:revertUpload, del:removeArticle }, items);
    } : undefined,
    onSelect:n => { $('#bulk').hidden = !n; $('#artToolbar').hidden = !!n; $('#bulkCount').textContent = `${n} kiválasztva`; const lp = postable(); $('#bulkPost').hidden = !can('decide') || !lp.length; $('#bulkPost').lastChild.textContent = lp.length > 1 ? `Poszt készítése a kijelöltekből (${lp.length})` : 'Poszt készítése ebből a cikkből'; },
    onRender:() => { $('#cntTodo').textContent = rows.filter(isTodo).length; const w = rows.filter(r => r.decision === 'linkedin' && !r.post_code).length; $('#liWait').textContent = w; $('#liHint').hidden = !(can('decide') && w && view === 'linkedin'); }
  });
  function postable(){ try { return rows.filter(r => table.selected.includes(r.id) && r.decision === 'linkedin' && !r.post_code); } catch(e){ return []; } }
  $('#bulkPost').addEventListener('click', async () => {
    const list = postable(); if (!list.length){ toast('A kijelöltek között nincs posztra váró „LinkedIn poszt” cikk.', { type:'info' }); return; }
    const skipped = table.selected.length - list.length;
    const ins = await genDialog({ title: list.length > 1 ? `Egy önálló poszt ${list.length} cikkből` : 'Poszt ebből a cikkből', confirm:'Poszt készítése',
      intro:`${list.map(r => '• ' + esc(r.title)).join('<br>')}${skipped ? `<br><br>${skipped} kijelölt cikk kimarad (nincs „LinkedIn poszt” jelölése, vagy már van posztja).` : ''}<br><br>${list.length > 1 ? 'A cikkek tényeiből egyetlen önálló, hírszerű poszt készül, forrásmegjelölés és link nélkül.' : 'A poszt a cikkből készül; a cikk linkje az első kommentbe való.'} A Claude a Posztszabályok szerint megírja a szöveget és megtervezi a képet (1–2 perc), és a poszt jóváhagyásra kerül.` });
    if (!ins) return;
    const b = $('#bulkPost'); busy(b, true); announce('A poszt és a kép készül, ez 1–2 percig tart…');
    try { const d = await api('posztkeres', { kind:'cikkek', ref:list.map(r => r.id).join(','), ...ins }); location.href = 'index.php?p=poszt&kod=' + encodeURIComponent(d.code); }
    catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
  });
  const pred = () => {
    const q = norm($('#artQ').value.trim()), rec = $('#artRec').value;
    table.setPred(r => (!q || norm(`${r.title} ${r.description || ''} ${r.source_name}`).includes(q)) && (!rec || r.recommendation === rec) && ({ todo:isTodo(r), all:true, mehet:r.decision === 'mehet' && !r.uploaded, publikalva:!!r.uploaded, linkedin:r.decision === 'linkedin', nemkell:r.decision === 'nemkell', new:!!r.from_new_source })[view]);
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
  // Törlés a listából, visszavonással
  async function removeArticle(r){
    try {
      await api('cikktorles', { id:+r.id });
      const at = rows.indexOf(r); rows.splice(at, 1); pred();
      toast(`Törölve a listából: ${r.title}`, { action:async () => { try { const d = await api('cikkvissza', { id:+r.id }); rows.splice(at, 0, { ...r, ...(d.article || {}), id:String(r.id) }); pred(); toast('Visszaállítva.', { type:'info' }); } catch(e){ toast(e.message, { type:'error' }); } } });
    } catch(e){ toast(e.message, { type:'error' }); }
  }
  // Feltöltés visszavonása: a weboldalról nem töröl; ha a WordPress szerint még kint van, megerősítést kér
  async function revertUpload(r){
    if (!await confirmBox({ title:'Visszavonja a feltöltést?', body:`„${r.title}”\n\nA cikk újra „Döntésre vár” állapotba kerül. A program a weboldalról NEM töröl: a magyar és az angol bejegyzést a WordPress-adminban kell törölni (Bejegyzések, majd a Lomtár fülön „Végleges törlés”).`, confirm:'Feltöltés visszavonása', danger:true })) return;
    try {
      let d = await api('feltoltesvissza', { id:+r.id });
      if (d.needsConfirm){
        if (!await confirmBox({ title:'A bejegyzés még kint van a weboldalon', body:d.message, confirm:'Visszavonás így is', danger:true })) return;
        d = await api('feltoltesvissza', { id:+r.id, force:true });
      }
      Object.assign(r, { uploaded:0, decision:'', upload_status:'', wp_state:'', url_hu:'', url_en:'', upload_error:'', upload_attempts:0 }); pred();
      toast('A feltöltés visszavonva: a cikk újra döntésre vár.');
    } catch(e){ toast(e.message, { type:'error' }); }
  }
  // Feltöltés a Havi cikkek oldalról
  const upDone = (r, items) => {
    items.forEach(it => { const row = rows.find(x => +x.id === it.id); if (!row) return;
      if (it.state === 'kint'){ Object.assign(row, { uploaded:1, url_hu:it.url_hu || '', url_en:it.url_en || '', wp_state:'kint', upload_error:'' }); }
      else if (it.hu){ Object.assign(row, { hu_title:it.hu.title, hu_summary:it.hu.summary, en_title:it.en ? it.en.title : '', en_summary:it.en ? it.en.summary : '' }); }
      if (it.error){ row.wp_state = it.state || 'hiba'; row.upload_error = it.error; } });
    if (!r.dry && $('#upWait')) $('#upWait').textContent = Math.max(0, (+$('#upWait').textContent) - items.filter(i => i.state === 'kint').length);
    pred();
  };
  $('#upDry')?.addEventListener('click', () => uploadRun(false, { onDone:upDone }));
  $('#upLive')?.addEventListener('click', () => uploadRun(true, { onDone:upDone }));
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
    $('#mailGen').title = !(M.months || []).length ? 'Még nincs lezárt hónap, amelyből a levél elkészülhet.' : sent ? 'Ez a levél már elment; új levél a következő hónap után készül.' : '';
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
  $('#smtpProbe')?.addEventListener('click', async () => {
    const b = $('#smtpProbe'); busy(b, true); announce('Az SMTP-kapcsolat ellenőrzése folyamatban…'); $('#smtpOut').hidden = true;
    try {
      const d = await api('smtpproba'); const ok = d.rows.find(r => r.ok);
      const rows = d.rows.map(r => `<tr><td>${esc(r.host)}</td><td class="num">${r.port}</td><td>${esc(r.secure)}${r.verify ? '' : ' <span class="meta">(tanúsítvány-ellenőrzés nélkül)</span>'}</td><td>${r.ok ? `<span class="badge b-success">${ic('check','icon')}működik</span>` : `<span class="badge b-danger">${ic('x-circle','icon')}nem</span> <span class="meta">${esc(r.error)}</span>`}${r.cert && r.cert.length ? `<div class="meta">Tanúsítvány: ${esc(r.cert.slice(0, 4).join(', '))}</div>` : ''}</td></tr>`).join('');
      const snippet = ok ? `'host' => '${ok.host}',\n'port' => ${ok.port},\n'secure' => '${ok.secure}',` + (ok.verify ? '' : `\n'verify_peer' => false,`) : '';
      $('#smtpOut').innerHTML = (d.duplicate ? `<div class="banner bn-error" role="alert">${ic('alert','icon')}<div class="b-body"><span>A config.php-ban kétszer szerepel az „smtp” rész; az alsó érvényes. Töröld a fölöslegeset.</span></div></div>` : '')
        + `<div class="table-wrap mini-table" style="border:1px solid var(--border);border-radius:var(--r-lg);margin-top:12px"><table><caption class="sr-only">SMTP-próbák</caption><thead><tr><th scope="col">Szerver</th><th scope="col" class="num">Port</th><th scope="col">Titkosítás</th><th scope="col">Eredmény</th></tr></thead><tbody>${rows}</tbody></table></div>`
        + (ok ? `<p class="help" style="margin-top:12px"><b>Működő beállítás.</b> A config.php „smtp” részében ezeket a sorokat írd át így (a user, pass, from_email maradhat):</p><div class="copy-box"><code style="white-space:pre">${esc(snippet)}</code><button class="btn btn-ghost sm" type="button" data-copy="${esc(snippet)}">${ic('copy')}Másolás</button></div>`
              : `<p class="help" style="margin-top:12px"><b>Egyik változat sem működött.</b> A hibák alapján: ha mindenhol időtúllépés vagy elutasítás van, a tárhely tiltja a kimenő levelezést – kérdezd meg a tárhely ügyfélszolgálatát, melyik SMTP-szervert és portot használhatják a PHP-programok. Ha belépési hiba van, a felhasználó vagy a jelszó hibás.</p>`);
      $('#smtpOut').hidden = false; toast(ok ? 'Találtam működő beállítást.' : 'Nem találtam működő beállítást; a részletek a kártyán.', { type: ok ? 'success' : 'error' });
    } catch(e){ toast(e.message, { type:'error' }); } finally { busy(b, false); }
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
   12/J. POSZTSZABÁLYOK: lista, felvétel, szerkesztés, törlés, sorrend
   ================================================================== */
if (APP.page === 'szabalyok' && $('#ruleTable')){
  let rules = APP.data.rules || [], editing = null;
  const render = () => {
    const q = norm($('#ruleQ').value.trim()), list = rules.filter(r => !q || norm(r.name + ' ' + r.value).includes(q));
    $('#ruleCount').textContent = `${list.length} / ${rules.length} szabály`;
    $('#ruleTable').innerHTML = list.length ? `<table class="rules-table"><caption class="sr-only">Posztszabályok</caption><thead><tr><th scope="col" class="num">#</th><th scope="col">Megnevezés</th><th scope="col">Érték</th><th scope="col"><span class="sr-only">Műveletek</span></th></tr></thead><tbody>${list.map(r => { const i = rules.indexOf(r); return `<tr><td class="num">${i + 1}</td><td><b>${esc(r.name)}</b></td><td class="rule-val">${esc(r.value)}</td><td class="rule-acts"><button class="icon-btn" type="button" data-rmove="${r.id}" data-dir="-1" aria-label="${esc(r.name)}: feljebb"${i === 0 || q ? ` disabled title="${q ? 'Kereséskor a sorrend nem módosítható' : 'Ez az első szabály'}"` : ''}>${ic('arrow-up')}</button><button class="icon-btn" type="button" data-rmove="${r.id}" data-dir="1" aria-label="${esc(r.name)}: lejjebb"${i === rules.length - 1 || q ? ` disabled title="${q ? 'Kereséskor a sorrend nem módosítható' : 'Ez az utolsó szabály'}"` : ''}>${ic('arrow-down')}</button><button class="icon-btn" type="button" data-redit="${r.id}" aria-label="${esc(r.name)} szerkesztése">${ic('edit')}</button><button class="icon-btn" type="button" data-rdel="${r.id}" aria-label="${esc(r.name)} törlése">${ic('trash')}</button></td></tr>`; }).join('')}</tbody></table>`
      : `<div class="empty-state"><b>${rules.length ? 'Nincs találat' : 'Még nincs szabály'}</b><span>${rules.length ? 'Módosítsa a keresést.' : 'Az „Új szabály” gombbal vegye fel az elsőt, vagy vegye át a Google Sheetből (Ügyfelek → Adatátvétel).'}</span></div>`;
  };
  const open = r => { editing = r; $('#ruleDlgTitle').textContent = r ? 'Szabály szerkesztése' : 'Új szabály'; $('#r-name').value = r?.name || ''; $('#r-value').value = r?.value || ''; $('#ruleErr').hidden = true; $('#ruleDlg').showModal(); $('#r-name').focus(); };
  $('#ruleAdd').addEventListener('click', () => open(null));
  $('#ruleCancel').addEventListener('click', () => $('#ruleDlg').close());
  $('#ruleForm').addEventListener('submit', async e => {
    e.preventDefault(); const b = $('#ruleSubmit'); busy(b, true);
    try { rules = (await api('szabaly', { id:editing?.id ?? null, name:$('#r-name').value, value:$('#r-value').value })).rules; $('#ruleDlg').close(); render(); toast(editing ? 'A szabály mentve.' : 'Az új szabály felkerült a lista végére.'); }
    catch(err){ $('#ruleErr').textContent = err.message; $('#ruleErr').hidden = false; } finally { busy(b, false); }
  });
  $('#ruleTable').addEventListener('click', async e => {
    const ed = e.target.closest('[data-redit]'), del = e.target.closest('[data-rdel]'), mv = e.target.closest('[data-rmove]');
    if (ed) open(rules.find(r => r.id === +ed.dataset.redit));
    if (del){ const r = rules.find(x => x.id === +del.dataset.rdel); if (!await confirmBox({ title:`Törli a(z) „${r.name}” szabályt?`, body:'A következő posztgenerálás már nélküle készül.', confirm:'Szabály törlése' })) return;
      try { rules = (await api('szabalytorles', { id:r.id })).rules; render(); toast('A szabály törölve.'); } catch(err){ toast(err.message, { type:'error' }); } }
    if (mv){ const i = rules.findIndex(r => r.id === +mv.dataset.rmove), j = i + +mv.dataset.dir; if (j < 0 || j >= rules.length) return;
      const ids = rules.map(r => r.id); [ids[i], ids[j]] = [ids[j], ids[i]];
      try { rules = (await api('szabalysorrend', { ids })).rules; render(); $(`[data-rmove="${ids[j]}"][data-dir="${mv.dataset.dir}"]`)?.focus(); } catch(err){ toast(err.message, { type:'error' }); } }
  });
  $('#ruleQ').addEventListener('input', render);
  render();
}

/* ==================================================================
   12/K. POSZTÖTLETEK: lista és űrlap (új, szerkesztés, elvetés, törlés, generálás)
   ================================================================== */
const IDEA_ST = { uj:['b-info','plus'], elkeszult:['b-success','check'], jovahagyva:['b-success','check'], utemezve:['b-info','calendar'], elvetve:['b-neutral','x'] };
if (APP.page === 'otletek' && $('#ideaTable')){
  const rows = (APP.data.ideas || []).map(r => ({ ...r, id:r.code }));
  const table = DataTable({ id:'otletek', caption:'Posztötletek', rows, labelKey:'topic', selectable:false, table:$('#ideaTable'), pager:$('#ideaPager'), sort:{ key:'created_at', dir:'desc' }, pageSize:25,
    columns:[
      { key:'code', label:'ID', render:r => `<a href="index.php?p=otlet&amp;kod=${encodeURIComponent(r.code)}">${esc(r.code)}</a>` },
      { key:'topic', label:'Téma és üzenet', render:r => `<div class="cell-title"><a href="index.php?p=otlet&amp;kod=${encodeURIComponent(r.code)}">${esc(r.topic)}</a><span>${esc(r.message)}</span></div>` },
      { key:'type', label:'Típus' }, { key:'wanted_week', label:'Kívánt hét' }, { key:'submitter', label:'Beküldő' },
      { key:'status', label:'Állapot', render:r => { const x = IDEA_ST[r.status] || ['b-neutral','info']; return `<span class="badge ${x[0]}">${ic(x[1],'icon')}${esc(r.status_label)}</span>${r.pending ? ` <span class="badge b-warning">${ic('clock','icon')}generálásra vár</span>` : ''}`; } },
      { key:'post_code', label:'Poszt', sortable:false, render:r => r.post_code ? `<a href="index.php?p=poszt&amp;kod=${encodeURIComponent(r.post_code)}">${esc(r.post_code)}</a>` : '<span class="meta">–</span>' },
      { key:'created_at', label:'Beküldve', render:r => esc(dtHu(r.created_at)) }
    ], emptyHtml:() => rows.length ? 'Nincs a szűrésnek megfelelő ötlet.' : 'Még nincs beküldött ötlet. Az „Új ötlet” gombbal küldheti be az elsőt.' });
  const pred = () => { const q = norm($('#ideaQ').value.trim()), st = $('#ideaSt').value; table.setPred(r => (!q || norm(`${r.topic} ${r.message} ${r.submitter} ${r.code}`).includes(q)) && (!st || r.status === st)); };
  $('#ideaQ').addEventListener('input', pred); $('#ideaSt').addEventListener('change', pred); pred();
}
if (APP.page === 'otlet' && $('#ideaForm')){
  const code = () => $('#f-code').value;
  const values = () => ({ submitter:$('#f-from').value, type:$('#f-tipus').value, topic:$('#f-tema').value, message:$('#f-uzenet').value, link:$('#f-link').value, wanted_week:$('#f-het').value, note:$('#f-note').value, status:$('#f-allapot')?.value || '' });
  async function save(extra = {}){
    const req = [['#f-tema','A téma kötelező.'], ['#f-uzenet','A fő üzenet kötelező.'], ['#f-tipus','Válassza ki a poszttípust.']].find(([s]) => !$(s).value.trim());
    if (req){ $(req[0]).setAttribute('aria-invalid','true'); $(req[0]).focus(); toast(req[1], { type:'error' }); return null; }
    $$('[aria-invalid]', $('#ideaForm')).forEach(x => x.removeAttribute('aria-invalid'));
    return (await api('otlet', { code:code(), idea:{ ...values(), ...extra } })).idea;
  }
  $('#ideaForm').addEventListener('submit', async e => {
    e.preventDefault(); const b = $('#ideaSave'); busy(b, true);
    try { const d = await save(); if (!d) return; if (!code()){ location.href = 'index.php?p=otlet&kod=' + encodeURIComponent(d.code); return; } toast('Az ötlet mentve.'); }
    catch(err){ toast(err.message, { type:'error' }); } finally { busy(b, false); }
  });
  $('#ideaGen')?.addEventListener('click', async () => {
    const ins = await genDialog({ title:'Poszt készítése az ötletből', confirm:'Küldés posztgenerálásra', intro:'A generálás fél–egy percig tart (Claude Opus, kb. 15–25 cent). A kész poszt magától jóváhagyásra kerül.' });
    if (!ins) return;
    const b = $('#ideaGen'); busy(b, true); announce('A poszt készül, ez fél–egy percig tart…');
    try { if (!await save()) return; const d = await api('posztkeres', { kind:'otlet', ref:code(), ...ins }); location.href = 'index.php?p=poszt&kod=' + encodeURIComponent(d.code); }
    catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
  });
  $('#ideaReject')?.addEventListener('click', async () => {
    if (!await confirmBox({ title:'Elveti az ötletet?', body:'Nem készül belőle poszt. Később visszaállíthatja az Állapot mezőben.', confirm:'Elvetés', danger:false })) return;
    try { await save({ status:'elvetve' }); location.reload(); } catch(err){ toast(err.message, { type:'error' }); }
  });
  $('#ideaDelete')?.addEventListener('click', async () => {
    if (!await confirmBox({ title:'Törli az ötletet?', body:'Az ötlet véglegesen törlődik. Ha készült belőle poszt, az megmarad.', confirm:'Ötlet törlése' })) return;
    try { await api('otlettorles', { code:code() }); location.href = 'index.php?p=otletek'; } catch(err){ toast(err.message, { type:'error' }); }
  });
}

/* ==================================================================
   12/L. LINKEDIN-POSZTOK: lista, havi poszt készítése
   ================================================================== */
const POST_ST = { tervezet:['b-neutral','edit'], ugyfelnel:['b-warning','clock'], jovahagyva:['b-success','check'], megjelent:['b-info','linkedin'] };
const postBadge = p => { const x = POST_ST[p.status] || ['b-neutral','info']; return `<span class="badge ${x[0]}">${ic(x[1],'icon')}${esc(p.status_label)}</span>`; };
if (APP.page === 'posztok' && $('#postGrid')){
  const posts = APP.data.posts || []; let f = '', page = 1; const SIZE = 12;
  const render = () => {
    const all = posts.filter(p => !f || p.status === f), pages = Math.max(1, Math.ceil(all.length / SIZE)); page = Math.min(page, pages);
    const list = all.slice((page - 1) * SIZE, page * SIZE);
    const svgSrc = s => 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(s)));
    $('#postGrid').innerHTML = list.map(p => `<a class="card post-card" href="index.php?p=poszt&amp;kod=${encodeURIComponent(p.code)}"><div class="row"><span class="id">${esc(p.code)}</span>${postBadge(p)}</div><h2>${esc(p.topic || '(téma nélkül)')}</h2>
      <div class="mini-li" aria-hidden="true"><div class="mini-head"><span class="mini-logo">${esc((APP.data.company || 'X').slice(0, 1))}</span><b>${esc(APP.data.company || '')}</b></div><p>${esc(p.opening)}</p>${p.thumb ? `<img src="${svgSrc(p.thumb)}" alt="" loading="lazy">` : '<div class="mini-noimg">kép még nincs</div>'}</div>
      <span class="meta">${esc({ cikkek:'Cikkekből', cikk:'Egy cikkből', otlet:'Ötletből' + (p.idea_code ? ': ' + p.idea_code : '') }[p.source_kind] || 'Átvett poszt')} · ${esc(p.created_hu)}</span></a>`).join('');
    $('#postEmpty').hidden = list.length > 0;
    $('#postPager').hidden = pages < 2;
    $('#postPager').innerHTML = `<span class="pg-info">${(page - 1) * SIZE + 1}–${Math.min(page * SIZE, all.length)} / ${all.length} poszt</span><span class="spacer"></span><button class="btn btn-ghost sm" type="button" data-pp="${page - 1}"${page < 2 ? ' disabled title="Ez az első oldal"' : ''}>${ic('chev-left','icon icon-sm')}Előző</button><span class="pg-info">${page}. oldal / ${pages}</span><button class="btn btn-ghost sm" type="button" data-pp="${page + 1}"${page >= pages ? ' disabled title="Ez az utolsó oldal"' : ''}>Következő${ic('chev-right','icon icon-sm')}</button>`;
  };
  $('#postPager').addEventListener('click', e => { const b = e.target.closest('[data-pp]'); if (!b || b.disabled) return; page = +b.dataset.pp; render(); $('#postGrid').scrollIntoView({ block:'start', behavior:'smooth' }); });
  $$('#postFilter [data-pf]').forEach(b => b.addEventListener('click', () => { f = b.dataset.pf; page = 1; $$('#postFilter [data-pf]').forEach(x => x.setAttribute('aria-pressed', String(x === b))); render(); }));
  $('#genMonthly')?.addEventListener('click', async () => {
    const b = $('#genMonthly'); busy(b, true); announce('A poszt készül, ez fél–egy percig tart…');
    try { const d = await api('posztkeres', { kind:'cikkek', ref:$('#genMonth').value }); location.href = 'index.php?p=poszt&kod=' + encodeURIComponent(d.code); }
    catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
  });
  render();
}

/* ==================================================================
   12/M. EGY POSZT: előnézet, kép (Canvas), szerkesztés, jóváhagyási lépések
   ================================================================== */
if (APP.page === 'poszt' && $('#liText')){
  let P = APP.data.post, expanded = false;
  const C = APP.data.company || {};
  const site = String(C.website || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || 'xperion.hu';
  const full = p => ['opening','context','highlights','xperion_view','closing','hashtags'].map(k => String(p[k] || '').trim()).filter(Boolean).join('\n\n');
  function renderText(){
    const t = full(P);
    $('#liText').innerHTML = expanded || t === P.opening ? esc(t) : `${esc(P.opening)}<span class="more">… <button class="linklike" type="button" id="liMore">több</button></span>`;
    $('#liMore')?.addEventListener('click', () => { expanded = true; syncMode(); });
  }
  const syncMode = () => { $$('#liMode [data-m]').forEach(b => b.setAttribute('aria-pressed', String((b.dataset.m === 'open') === expanded))); renderText(); };
  $$('#liMode [data-m]').forEach(b => b.addEventListener('click', () => { expanded = b.dataset.m === 'open'; syncMode(); }));
  function wrap(x, text, maxW){ const out = []; let line = ''; String(text || '').split(/\s+/).filter(Boolean).forEach(w => { const t = line ? line + ' ' + w : w; if (x.measureText(t).width > maxW && line){ out.push(line); line = w; } else line = t; }); if (line) out.push(line); return out; }
  function draw(){
    const img = P.image || {}, c = $('#postCanvas'), x = c.getContext('2d'), W = 1200;
    x.fillStyle = '#0F1640'; x.fillRect(0, 0, W, W);
    const g = x.createLinearGradient(0, 0, W, W); g.addColorStop(0, 'rgba(45,56,138,.95)'); g.addColorStop(1, 'rgba(0,174,239,.55)');
    x.fillStyle = g; x.beginPath(); x.arc(W * .95, W * .05, 760, 0, Math.PI * 2); x.fill();
    x.fillStyle = 'rgba(255,255,255,.06)'; for (let i = 0; i < 12; i++) for (let j = 0; j < 12; j++){ x.beginPath(); x.arc(80 + i * 96, 80 + j * 96, 3, 0, Math.PI * 2); x.fill(); }
    const bar = x.createLinearGradient(96, 0, 400, 0); bar.addColorStop(0, '#2D388A'); bar.addColorStop(1, '#00AEEF'); x.fillStyle = bar; x.fillRect(96, 150, 140, 12);
    x.textBaseline = 'top'; x.fillStyle = '#FFFFFF'; x.font = '600 92px Inter, Arial, sans-serif';
    let y = 210; wrap(x, img.title || P.topic, W - 192).slice(0, 4).forEach(l => { x.fillText(l, 96, y); y += 108; });
    x.fillStyle = '#BFE9FB'; x.font = '400 44px Inter, Arial, sans-serif'; y += 16;
    wrap(x, img.subtitle, W - 192).slice(0, 3).forEach(l => { x.fillText(l, 96, y); y += 58; });
    if (img.measured && img.real){
      y = Math.max(y + 60, 640);
      x.fillStyle = 'rgba(255,255,255,.08)'; x.strokeStyle = 'rgba(255,255,255,.25)'; x.lineWidth = 2;
      x.beginPath(); x.roundRect ? x.roundRect(96, y, W - 192, 250, 24) : x.rect(96, y, W - 192, 250); x.fill(); x.stroke();
      x.fillStyle = '#00AEEF'; x.font = '600 26px Inter, Arial, sans-serif'; x.fillText('ILLUSZTRATÍV PÉLDA', 136, y + 36);
      x.fillStyle = '#FFFFFF'; x.font = '500 46px Inter, Arial, sans-serif'; x.fillText(wrap(x, img.measured, W - 272)[0] || '', 136, y + 92);
      x.fillStyle = '#7FD3F5'; x.fillText(wrap(x, '→ ' + img.real, W - 272)[0] || '', 136, y + 162);
    }
    x.fillStyle = '#FFFFFF'; x.font = '600 40px Inter, Arial, sans-serif'; x.fillText(site, 96, W - 140);
    const name = C.name || ''; x.fillStyle = 'rgba(255,255,255,.6)'; x.font = '400 28px Inter, Arial, sans-serif'; x.fillText(name, W - 96 - x.measureText(name).width, W - 132);
  }
  /* Generálás közben mindig látszik: sáv a lap tetején forgó jellel, a műveletgombok tiltva */
  function working(on, title = '', text = ''){
    $('#workBanner').hidden = !on; $('#workTitle').textContent = title; $('#workText').textContent = text;
    $$('#postActs button, #postMore, #artsActs button, #copyText, #dlImage').forEach(b => { if (on){ b.dataset.wasDis = b.disabled ? '1' : ''; b.disabled = true; } else if (b.dataset.wasDis !== undefined){ b.disabled = b.dataset.wasDis === '1'; delete b.dataset.wasDis; } });
    if (on){ announce(title + ' ' + text); window.scrollTo({ top:0, behavior:'smooth' }); }
  }
  const artsDirty = () => { const now = $$('#artsList input:checked').map(x => +x.value).sort().join(','), was = (P.candidates || []).filter(c => c.in).map(c => +c.id).sort().join(','); return now !== '' && now !== was; };
  const syncArts = () => { const d = artsDirty(); $('#artsRegen').disabled = !d; $('#artsSave').disabled = !d; $('#artsHint').hidden = d; };
  function showImage(){
    const has = !!P.image_svg;
    $('#postImg').hidden = !has; $('#postCanvas').hidden = has;
    if (has) $('#postImg').src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(P.image_svg))); else draw();
  }
  function renderAll(){
    renderText(); showImage();
    const opts = [['opening', 'Jelenlegi'], ['alt_opening_1', 'Alternatív 1'], ['alt_opening_2', 'Alternatív 2']].filter(([k]) => String(P[k] || '').trim());
    const canEdit = can('ideas') && (P.status === 'tervezet' || P.status === 'ugyfelnel' || (P.status === 'jovahagyva' && can('ops')));
    $('#openChoices').innerHTML = '<legend class="sr-only">Nyitó sor választása</legend>' + opts.map(([k, l], i) => `<label class="open-opt"><input type="radio" name="openopt" value="${k}"${i === 0 ? ' checked' : ''}${canEdit ? '' : ' disabled'}><span><b>${l}</b><br>${esc(P[k])}</span></label>`).join('') + (canEdit && opts.length > 1 ? '<span class="help">Másik nyitó sor választásakor a jelenlegi az alternatívák közé kerül.</span>' : '');
    $('#copyLink').hidden = !P.source_link;
    const cands = P.candidates || [], canArts = can('decide') && (P.status === 'tervezet' || P.status === 'ugyfelnel') && cands.length > 0;
    $('#artsCard').hidden = !(cands.length > 0);
    $('#artsList').innerHTML = '<legend class="sr-only">Forráscikkek kiválasztása</legend>' + cands.map(c => `<label class="art-opt"><input type="checkbox" value="${c.id}"${c.in ? ' checked' : ''}${canArts ? '' : ' disabled'}><span><b>${esc(c.source_name)}</b> · ${esc(c.month)}<br>${esc(c.title)}</span></label>`).join('');
    $('#artsActs').hidden = !canArts; $('#artsHint').hidden = !canArts; syncArts();
    const srcs = P.articles && P.articles.length ? P.articles.map(a => `<a href="${esc(a.url)}" target="_blank" rel="noopener">${esc(a.source_name)}: ${esc(a.title)}</a>`).join('<br>') : P.idea_code ? `<a href="index.php?p=otlet&amp;kod=${encodeURIComponent(P.idea_code)}">${esc(P.idea_code)}: ${esc(P.idea_topic || '')}</a>` : esc(P.source_articles || '–');
    $('#postKv').innerHTML = [['Témakör', esc(P.topic || '–')], ['Forrás', srcs], ['Forráslink', P.source_link ? `<a href="${esc(P.source_link)}" target="_blank" rel="noopener">${esc(P.source_link)}</a>` : '–'], ['Létrehozva', esc(P.created_hu)], ['Modell', esc(P.model || 'átvett / kézi')], ['Utasítás a szöveghez', esc(P.instr_text || '–')], ['Utasítás a képhez', esc(P.instr_image || '–')], ['Megjegyzés', esc(P.note || '–')]].map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('');
    const ev = P.events || [];
    $('#histCount').textContent = ev.length;
    $('#postHist').innerHTML = ev.length ? ev.map((e, i) => `<li class="tl-item${i === ev.length - 1 ? ' last' : ''}"><span class="tl-dot badge b-neutral">${ic('clock','icon')}</span><div class="tl-body"><div class="tl-title">${esc(e.action)}</div><div class="tl-desc">${esc(e.user)} · ${esc(e.time_hu)}${e.details ? ' · ' + esc(e.details) : ''}</div></div></li>`).join('') : '<li class="meta">Még nincs bejegyzés.</li>';
    $('#crBanner').hidden = !(P.change_request && P.status === 'tervezet'); $('#crText').textContent = P.change_request || '';
    const st = POST_ST[P.status] || ['b-neutral','info']; $('#postStatus').className = 'badge ' + st[0]; $('#postStatus').innerHTML = ic(st[1],'icon') + esc(P.status_label);
    const acts = [];
    if (P.status === 'tervezet' && can('ops')) acts.push(['kuldes', 'Küldés jóváhagyásra', 'send', 'btn-primary']);
    if (P.status === 'ugyfelnel' && can('approve')){ acts.push(['modositas', 'Módosítást kérek', 'msg', 'btn-secondary']); acts.push(['jovahagyas', 'Jóváhagyom', 'check', 'btn-primary']); }
    if (P.status === 'jovahagyva' && can('publish')) acts.push(['megjelent', 'Megjelent a LinkedInen', 'linkedin', 'btn-primary']);
    const canAi = (can('ideas') || can('approve')) && P.status !== 'megjelent';
    const btn = ([a, l, i, c]) => `<button class="btn ${c}" type="button" data-pa="${a}">${ic(i,'icon icon-sm')}${l}</button>`;
    $('#postActs').innerHTML = acts.filter(x => x[3] !== 'btn-primary').map(btn).join('')
      + (canAi ? `<button class="btn btn-secondary" type="button" id="aiBtn" aria-haspopup="menu" aria-expanded="false">${ic('sparkle','icon icon-sm')}AI-generálás${ic('chev-down','icon icon-sm')}</button>` : '')
      + acts.filter(x => x[3] === 'btn-primary').map(btn).join('');
    $('#tab-szerk').hidden = !canEdit;
    fillForm();
  }
  const F = ['topic','opening','alt_opening_1','alt_opening_2','context','highlights','xperion_view','closing','hashtags','source_link','note'];
  function fillForm(){ F.forEach(k => { const el = $('#pe-' + k); if (el) el.value = P[k] || ''; }); const im = P.image || {}; $('#pe-it').value = im.title || ''; $('#pe-is').value = im.subtitle || ''; $('#pe-e1').value = im.measured || ''; $('#pe-e2').value = im.real || ''; }
  async function savePost(body, msg){ try { P = (await api('poszt', { code:P.code, post:body })).post; renderAll(); toast(msg); return true; } catch(err){ toast(err.message, { type:'error' }); return false; } }
  $('#postForm').addEventListener('submit', async e => {
    e.preventDefault(); const b = $('#peSave'); busy(b, true);
    const body = Object.fromEntries(F.map(k => [k, $('#pe-' + k).value])); body.image = { title:$('#pe-it').value, subtitle:$('#pe-is').value, measured:$('#pe-e1').value, real:$('#pe-e2').value };
    if (await savePost(body, 'A poszt mentve.')) selectTab('elo'); busy(b, false);
  });
  $('#peReset').addEventListener('click', () => { fillForm(); toast('A módosítások elvetve.', { type:'info' }); });
  $('#openChoices').addEventListener('change', async e => {
    const k = e.target.value; if (k === 'opening') return;
    const body = { opening:P[k], [k]:P.opening }; await savePost(body, 'A nyitó sor lecserélve.');
  });
  const TABS = ['elo','szerk','adat','hist'];
  function selectTab(t){ TABS.forEach(x => { $('#tab-' + x).setAttribute('aria-selected', String(x === t)); $('#tab-' + x).tabIndex = x === t ? 0 : -1; $('#p-' + x).hidden = x !== t; }); }
  $$('.tabs [role=tab]').forEach(t => t.addEventListener('click', () => selectTab(t.id.replace('tab-', ''))));
  $('.tabs').addEventListener('keydown', e => { if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return; const vis = TABS.filter(x => !$('#tab-' + x).hidden), i = vis.findIndex(x => $('#tab-' + x).getAttribute('aria-selected') === 'true'), n = vis[(i + (e.key === 'ArrowRight' ? 1 : vis.length - 1)) % vis.length]; selectTab(n); $('#tab-' + n).focus(); });
  $('#copyText').addEventListener('click', () => copyText(full(P), $('#liText')));
  $('#artsList').addEventListener('change', syncArts);
  async function regenText(){
    const ins = await genDialog({ title:'Tartalom újragenerálása', confirm:'Újragenerálás', text:P.instr_text || '', image:P.instr_image || '', intro:'A Claude a Posztszabályok szerint újraírja a szöveget és a képet. A jelenlegi szöveg (a kézi módosításokkal együtt) felülíródik; a módosítási kérést figyelembe veszi. Fél–egy perc, kb. 15–25 cent.' }); if (!ins) return;
    working(true, 'A poszt újragenerálása folyamatban…', 'A Claude írja a szöveget és tervezi a képet; ez fél–egy percig tart. Ne zárja be az oldalt.');
    try { await api('posztkeres', { kind:'ujra', ref:P.code, ...ins }); location.reload(); } catch(err){ working(false); toast(err.message, { type:'error' }); }
  }
  $('#copyLink').addEventListener('click', () => copyText(P.source_link));
  $('#dlImage').addEventListener('click', async () => {
    const c = $('#postCanvas');
    try {
      if (P.image_svg){ const im = $('#postImg'); if (!im.complete) await new Promise(r => im.onload = r); c.getContext('2d').drawImage(im, 0, 0, 1200, 1200); }
      const a = document.createElement('a'); a.download = P.code + '.png'; a.href = c.toDataURL('image/png'); document.body.appendChild(a); a.click(); a.remove();
    } catch(err){ toast('A PNG nem készíthető el ebben a böngészőben; tartsa nyomva a képet, és mentse el.', { type:'error' }); }
  });
  $('#postActs').addEventListener('click', async e => {
    const ai = e.target.closest('#aiBtn');
    if (ai){ e.stopPropagation(); openRowMenu(ai, P, { ujra:regenText, kep:newImage }, [{ k:'ujra', l:'Tartalom újragenerálása…', i:'refresh' }, { k:'kep', l:'Új kép készítése…', i:'image' }]); return; }
    const b = e.target.closest('[data-pa]'); if (!b) return; const a = b.dataset.pa; let comment = '';
    if (a === 'modositas'){ comment = await confirmBox({ title:'Mit módosítsunk?', body:'Írja le röviden (a szövegre és a képre is vonatkozhat). A Claude a kérése szerint azonnal újraírja, és a poszt újra Önhöz kerül jóváhagyásra; ez 1–2 perc.', confirm:'Módosítás kérése', danger:false, input:{ label:'Módosítási kérés' } }); if (!comment) return; announce('A poszt újraírása folyamatban, 1–2 perc…'); }
    if (a === 'kuldes' && !await confirmBox({ title:'Elküldi jóváhagyásra?', body:'Az ügyfél-adminisztrátor értesítést kap, és a felületen jóváhagyhatja vagy módosítást kérhet.', confirm:'Küldés jóváhagyásra', danger:false })) return;
    if (a === 'megjelent' && !await confirmBox({ title:'Megjelent a LinkedInen?', body:'Jelölje meg, ha a posztot közzétette vagy időzítette a LinkedIn ütemezőjében.', confirm:'Megjelentnek jelölöm', danger:false })) return;
    busy(b, true); if (a === 'modositas') working(true, 'A poszt átírása folyamatban…', 'A Claude a kérése szerint újraírja a posztot; ez 1–2 percig tart. Ne zárja be az oldalt.');
    try { P = (await api('posztlepes', { code:P.code, action:a, comment })).post; working(false); renderAll(); toast({ kuldes:'Elküldve jóváhagyásra.', jovahagyas:'Jóváhagyva. A tartalomkezelő értesítést kapott.', modositas:P.status === 'ugyfelnel' ? 'A poszt a kérése szerint újraírva; nézze át, és hagyja jóvá.' : 'A kérés rögzítve; az újraírás nem sikerült, az óránkénti futás újrapróbálja.', megjelent:'Megjelentnek jelölve.' }[a] || 'Kész.'); busy(b, false); }
    catch(err){ working(false); toast(err.message, { type:'error' }); busy(b, false); }
  });
  async function newImage(){
    const req = await confirmBox({ title:'Új kép készüljön?', body:'A Claude új tartalmat és elrendezést választ a képhez (kb. fél perc, néhány cent). Ha van kérése (pl. „a három pontos elrendezéssel”, „kevesebb szöveg”, „más cím”), írja ide; üresen is mehet.', confirm:'Új kép', danger:false, input:{ label:'Kérés a képhez (nem kötelező)' } });
    if (req === false) return; working(true, 'Új kép készül…', 'A Claude megtervezi a képet; ez kb. fél percig tart.');
    try { P = (await api('posztkep', { code:P.code, request:typeof req === 'string' ? req : '' })).post; working(false); renderAll(); toast('Az új kép elkészült.'); } catch(err){ working(false); toast(err.message, { type:'error' }); }
  }
  async function saveArts(regen){
    const ids = $$('#artsList input:checked').map(x => +x.value); if (!ids.length){ toast('Legalább egy cikket jelöljön ki.', { type:'error' }); return; }
    if (regen && !await confirmBox({ title:'Újraírja a posztot a kijelölt cikkekből?', body:`${ids.length} cikk. A Claude újraírja a szöveget és a képet (1–2 perc), és a poszt újra jóváhagyásra kerül.`, confirm:'Mentés és újraírás', danger:false })) return;
    const b = $(regen ? '#artsRegen' : '#artsSave'); busy(b, true); if (regen) working(true, 'A poszt újraírása folyamatban…', `${ids.length} cikkből; a Claude írja a szöveget és tervezi a képet (1–2 perc). Ne zárja be az oldalt.`);
    try { P = (await api('posztcikkek', { code:P.code, ids, regen })).post; working(false); busy(b, false); renderAll(); toast(regen ? 'A poszt a kijelölt cikkekből újraírva.' : 'A forráscikkek mentve. Az újraíráshoz: Mentés és újraírás.'); }
    catch(err){ working(false); busy(b, false); toast(err.message, { type:'error' }); }
  }
  $('#artsRegen').addEventListener('click', () => saveArts(true)); $('#artsSave').addEventListener('click', () => saveArts(false));
  $('#postMore').addEventListener('click', e => {
    e.stopPropagation(); // különben a dokumentum kattintásfigyelője azonnal bezárja a most nyitott menüt
    const items = [];
    if (can('ops') && P.status !== 'tervezet') items.push({ k:'vissza', l:'Vissza tervezetre', i:'repeat' });
    if (can('deletePost')) items.push({ k:'torles', l:'Poszt törlése…', i:'trash', danger:true, sep:items.length > 0 });
    if (!items.length){ toast('Ehhez a poszthoz nincs további művelet.', { type:'info' }); return; }
    openRowMenu(e.currentTarget, P, {
      vissza: async () => { try { P = (await api('posztlepes', { code:P.code, action:'vissza' })).post; renderAll(); toast('Visszaállítva tervezetre.'); } catch(err){ toast(err.message, { type:'error' }); } },
      torles: async () => { if (!await confirmBox({ title:`Törli a(z) ${P.code} posztot?`, body:'A szöveg, a kép és az előzmények véglegesen törlődnek. A forráscikkek és az ötlet megmaradnak, és újra küldhetők generálásra.', confirm:'Poszt törlése' })) return;
        try { await api('poszttorles', { code:P.code }); location.href = 'index.php?p=posztok'; } catch(err){ toast(err.message, { type:'error' }); } }
    }, items);
  });
  renderAll();
}

/* ==================================================================
   12/E. NAPLÓ
   ================================================================== */
if (APP.page === 'naplo'){
  const audit = (APP.data.audit || []).map((r, i) => ({ ...r, id:'a' + i, i, day:String(r.time_hu || '').slice(0, 13).replace(/\. /g, '-').replace(/\.$/, '') }));
  const runs = (APP.data.runs || []).map((r, i) => ({ ...r, id:'r' + i, i, mode:r.dry ? 'dry' : 'live', res:r.ok ? 'ok' : 'err', trig:{ admin:'Felület', cron:'Cron', cli:'Parancssor' }[r.trigger] || r.trigger }));
  const isoDay = r => { const m = String(r.created_at || '').match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):/); if (!m) return ''; const d = new Date(m[1] + 'T' + m[2] + ':00:00Z'); return isNaN(d) ? m[1] : d.toLocaleDateString('sv-SE', { timeZone:'Europe/Budapest' }); };
  audit.forEach(r => r.iso = isoDay(r));
  const opts = (sel, vals) => { if (sel) sel.insertAdjacentHTML('beforeend', [...new Set(vals.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'hu')).map(v => `<option>${esc(v)}</option>`).join('')); };
  opts($('#aClient'), audit.map(r => r.client)); opts($('#aUser'), audit.map(r => r.who));
  const CAT = { 'Döntések':['b-info','check'], 'Cikkek':['b-info','list'], 'Posztok':['b-ai','linkedin'], 'Havi e-mail':['b-warning','send'], 'Feltöltés':['b-success','upload'], 'Források':['b-neutral','globe'], 'Futások':['b-neutral','play'], 'Felhasználók':['b-neutral','users'], 'Belépés':['b-neutral','login'], 'Beállítások':['b-neutral','settings'] };
  const at = DataTable({ id:'audit', caption:'Felhasználói műveletek', rows:audit, labelKey:'action', selectable:false, table:$('#auditTable'), pager:$('#auditPager'), sort:{ key:'created_at', dir:'desc' }, pageSize:25,
    columns:[ { key:'created_at', label:'Időpont', render:r => esc(r.time_hu || dtHu(r.created_at)), sortValue:r => -r.i, firstDir:'desc' }, { key:'who', label:'Ki' },
      { key:'category', label:'Kategória', render:r => { const k = CAT[r.category] || ['b-neutral','info']; return `<span class="badge ${k[0]}">${ic(k[1],'icon')}${esc(r.category || '')}</span>`; } },
      { key:'action', label:'Művelet', render:r => `<span class="log-act">${esc(r.action)}</span>` },
      ...(APP.data.all ? [{ key:'client', label:'Ügyfél', render:r => esc(r.client || '–') }] : []), { key:'details', label:'Részletek', sortable:false, render:r => `<span class="log-det">${esc(r.details || '')}</span>` } ],
    emptyHtml:() => audit.length ? 'Nincs a szűrésnek megfelelő művelet.' : 'Még nincs művelet.' });
  let aRows = audit;
  const aPred = () => {
    const q = norm($('#aQ').value.trim()), c = $('#aClient')?.value || '', u = $('#aUser').value, k = $('#aCat').value, f = $('#aFrom').value, t = $('#aTo').value;
    const p = r => (!q || norm(`${r.who} ${r.action} ${r.details} ${r.client || ''}`).includes(q)) && (!c || r.client === c) && (!u || r.who === u) && (!k || r.category === k) && (!f || r.iso >= f) && (!t || r.iso <= t);
    aRows = audit.filter(p); at.setPred(p);
  };
  ['#aQ','#aClient','#aUser','#aCat','#aFrom','#aTo'].forEach(id => $(id)?.addEventListener(id === '#aQ' ? 'input' : 'change', aPred));
  $('#aCsv').addEventListener('click', () => {
    const cell = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const head = ['Időpont', 'Ki', 'Művelettípus', 'Művelet', ...(APP.data.all ? ['Ügyfél'] : []), 'Részletek'];
    const lines = [head.map(cell).join(';'), ...aRows.map(r => [r.time_hu || r.created_at, r.who, r.category, r.action, ...(APP.data.all ? [r.client || ''] : []), r.details].map(cell).join(';'))];
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type:'text/csv;charset=utf-8' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `naplo-${new Date().toISOString().slice(0, 10)}.csv`; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    toast(`${aRows.length} sor letöltve.`);
  });
  at.render();
  if ($('#runTable')){
    const rt = DataTable({ id:'futasok', caption:'Futások', rows:runs, labelKey:'label', selectable:false, table:$('#runTable'), pager:$('#runPager'), sort:{ key:'time', dir:'desc' }, pageSize:25,
      columns:[ { key:'time', label:'Időpont', render:r => esc(r.time_hu), sortValue:r => -r.i, firstDir:'desc' }, { key:'label', label:'Feladat' },
        { key:'mode', label:'Mód', type:'status', map:{ dry:{ label:'Száraz', cls:'b-info', icon:'flask' }, live:{ label:'Éles', cls:'b-neutral', icon:'play' } } }, { key:'trig', label:'Indító' },
        { key:'res', label:'Eredmény', type:'status', map:{ ok:{ label:'OK', cls:'b-success', icon:'check' }, err:{ label:'Hiba', cls:'b-danger', icon:'x-circle' } } }, { key:'details', label:'Részletek', sortable:false, render:r => `<div class="${r.ok ? '' : 'run-err'}">${esc(r.details)}</div>` } ],
      emptyHtml:() => 'Nincs a szűrésnek megfelelő futás.' });
    const errs = runs.filter(r => !r.ok).length; if (errs){ $('#runErrCount').textContent = errs + ' hiba'; $('#runErrCount').hidden = false; }
    const rPred = () => { const q = norm($('#rQ').value.trim()), e = $('#rErr').checked; rt.setPred(r => (!q || norm(`${r.label} ${r.details} ${r.trig}`).includes(q)) && (!e || !r.ok)); };
    $('#rQ').addEventListener('input', rPred); $('#rErr').addEventListener('change', rPred); rt.render();
    const tabs = $$('[role=tab]');
    const sel = t => tabs.forEach(x => { const on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; $('#' + x.getAttribute('aria-controls')).hidden = !on; });
    tabs.forEach(t => { t.addEventListener('click', () => sel(t)); t.addEventListener('keydown', e => { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft'){ const o = tabs.find(x => x !== t); sel(o); o.focus(); } }); });
  }
}

/* ==================================================================
   12/N. FORRÁSOK ÉS KULCSSZAVAK
   ================================================================== */
if (APP.page === 'forrasok' && $('#srcTable')){
  let D = { sources:APP.data.sources || [], keywords:APP.data.keywords || [] }, editS = null, editK = null, SUG = APP.data.suggestions || [], sugN = APP.data.suggestCount || 3;
  const canSearch = APP.data.canSearch !== false, hasClaude = APP.data.claude !== false;
  const renderSug = () => {
    $('#sugCount').textContent = SUG.length;
    $('#sugSearch').hidden = !canSearch;
    $('#sugTable').innerHTML = SUG.length ? `<table><caption class="sr-only">Javasolt források</caption><thead><tr><th scope="col">Javasolt forrás</th><th scope="col">Próbagyűjtés</th><th scope="col"><span class="sr-only">Műveletek</span></th></tr></thead><tbody>${SUG.map(r => `<tr><td><div class="cell-title sug-src"><b>${esc(r.name)}</b><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.url)}</a><p class="sug-why">${esc(r.reason || '')}</p><span>${esc([r.type, r.topics].filter(Boolean).join(' · '))}</span></div></td><td><div class="sug-test"><span class="badge b-success">${ic('check','icon')}${r.count} cikk · ${esc(r.method)}</span>${(r.sample || []).map(i => `<span class="sug-item" title="${esc(i.title)}">${esc(i.date || '–')} · ${esc(i.title)}</span>`).join('')}</div></td><td class="sug-acts">${canSearch ? `<button class="btn btn-primary sm" type="button" data-sacc="${r.id}">${ic('plus')}Felvétel</button><button class="btn btn-ghost sm" type="button" data-sdis="${r.id}" aria-label="${esc(r.name)} elvetése">${ic('x')}Elvetés</button>` : ''}</td></tr>`).join('')}</tbody></table>`
      : `<div class="empty-state"><b>Nincs döntésre váró javaslat</b><span>${canSearch ? 'Az „Új források keresése” gombbal a Claude új, működő hírforrásokat keres a kulcsszavak és a témák alapján.' : 'Új forrást az ügyfél-adminisztrátor kereshet.'}</span></div>`;
  };
  const render = () => {
    $('#srcCount').textContent = D.sources.length; $('#kwCount').textContent = D.keywords.length;
    const q = norm($('#srcQ').value.trim()), list = D.sources.filter(r => !q || norm(`${r.name} ${r.url} ${r.topics} ${r.type}`).includes(q));
    $('#srcTable').innerHTML = list.length ? `<table><caption class="sr-only">Források</caption><thead><tr><th scope="col">Forrás</th><th scope="col">Típus és témák</th><th scope="col">Állapot</th><th scope="col" class="num">Cikkek</th><th scope="col"><span class="sr-only">Műveletek</span></th></tr></thead><tbody>${list.map(r => `<tr><td><div class="cell-title"><b>${esc(r.name)}</b><a href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.url)}</a></div></td><td><div class="cell-title"><span>${esc(r.type || '–')}</span><span>${esc(r.topics)}</span></div></td><td>${r.active ? `<span class="badge b-success">${ic('check','icon')}aktív</span>` : `<span class="badge b-neutral">${ic('minus','icon')}szünetel</span>`}${r.is_new ? ` <span class="badge b-ai">${ic('plus','icon')}új forrás</span>` : ''}</td><td class="num">${r.articles}${r.last_month ? `<div class="meta">utoljára: ${esc(r.last_month)}</div>` : ''}</td><td class="rule-acts"><button class="icon-btn" type="button" data-stog="${r.id}" aria-label="${esc(r.name)}: ${r.active ? 'szüneteltetés' : 'aktiválás'}">${ic(r.active ? 'eye-off' : 'eye')}</button><button class="icon-btn" type="button" data-sedit="${r.id}" aria-label="${esc(r.name)} szerkesztése">${ic('edit')}</button><button class="icon-btn" type="button" data-sdel="${r.id}" aria-label="${esc(r.name)} törlése">${ic('trash')}</button></td></tr>`).join('')}</tbody></table>`
      : `<div class="empty-state"><b>${D.sources.length ? 'Nincs találat' : 'Még nincs forrás'}</b><span>${D.sources.length ? 'Módosítsa a keresést.' : 'Az „Új forrás” gombbal vegye fel az elsőt (hírlista vagy RSS címe), vagy vegye át a Google Sheetből.'}</span></div>`;
    const kq = norm($('#kwQ').value.trim()), kl = D.keywords.filter(r => !kq || norm(`${r.keyword} ${r.topic}`).includes(kq));
    $('#kwTable').innerHTML = kl.length ? `<table><caption class="sr-only">Kulcsszavak</caption><thead><tr><th scope="col">Kulcsszó</th><th scope="col">Téma</th><th scope="col">Nyelv</th><th scope="col">Fajta</th><th scope="col"><span class="sr-only">Műveletek</span></th></tr></thead><tbody>${kl.map(r => `<tr><td><b>${esc(r.keyword)}</b></td><td>${esc(r.topic || '–')}</td><td>${esc({ hu:'magyar', en:'angol' }[r.language] || 'mindegy')}</td><td>${r.is_exclude ? `<span class="badge b-danger">${ic('x','icon')}kizáró</span>` : `<span class="badge b-info">${ic('check','icon')}figyelt</span>`}</td><td class="rule-acts"><button class="icon-btn" type="button" data-kedit="${r.id}" aria-label="${esc(r.keyword)} szerkesztése">${ic('edit')}</button><button class="icon-btn" type="button" data-kdel="${r.id}" aria-label="${esc(r.keyword)} törlése">${ic('trash')}</button></td></tr>`).join('')}</tbody></table>`
      : `<div class="empty-state"><b>${D.keywords.length ? 'Nincs találat' : 'Még nincs kulcsszó'}</b><span>A kulcsszavak a Claude-értékelést segítik; a kizáró kulcsszóval jelölt témájú cikk kimarad.</span></div>`;
  };
  const openS = r => { editS = r; $('#srcDlgTitle').textContent = r ? 'Forrás szerkesztése' : 'Új forrás'; $('#s-url').value = r?.url || ''; $('#s-name').value = r?.name || ''; $('#s-type').value = r?.type || ''; $('#s-topics').value = r?.topics || ''; $('#s-active').checked = r ? r.active : true; $('#s-new').checked = r ? r.is_new : !!D.sources.length; $('#s-note').value = r?.note || ''; $('#srcErr').hidden = true; $('#srcTestOut').textContent = ''; $('#srcDlg').showModal(); $('#s-url').focus(); };
  const openK = r => { editK = r; $('#kwDlgTitle').textContent = r ? 'Kulcsszó szerkesztése' : 'Új kulcsszó'; $('#k-word').value = r?.keyword || ''; $('#k-topic').value = r?.topic || ''; $('#k-lang').value = r?.language || ''; $('#k-exclude').checked = !!r?.is_exclude; $('#kwErr').hidden = true; $('#kwDlg').showModal(); $('#k-word').focus(); };
  const srcBody = r => ({ url:$('#s-url').value, name:$('#s-name').value, type:$('#s-type').value, topics:$('#s-topics').value, active:$('#s-active').checked, is_new:$('#s-new').checked, note:$('#s-note').value, ...r });
  $('#srcAdd').addEventListener('click', () => openS(null)); $('#kwAdd').addEventListener('click', () => openK(null));
  $('#sugSearch').addEventListener('click', async () => {
    if (!hasClaude){ toast('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key). Szóljon az üzemeltetőnek.', { type:'error' }); return; }
    const fd = await formBox({ title:'Új források keresése', confirm:'Keresés indítása', html:`<p class="dlg-intro">A Claude a weben keres a kulcsszavak és a témák alapján, majd minden találatot próbagyűjtéssel ellenőrzünk. 1–3 perc, kb. 5–15 cent. A javaslatok csak az Ön „Felvétel” döntésével lesznek figyelt források.</p>
      <div class="field"><label for="g-count">Hány új forrást keressen?</label><input class="input" id="g-count" name="count" type="number" min="1" max="10" step="1" required value="${sugN}" inputmode="numeric" style="max-width:8rem"><p class="help">1–10; a beállítás megmarad a következő keresésre.</p></div>` });
    if (!fd) return;
    const b = $('#sugSearch'); busy(b, true); announce('Új források keresése folyamatban, ez 1–3 percig tart…');
    try { const r = await api('forraskereses', { count:+fd.get('count') || 3 }); SUG = r.pending; sugN = r.count; renderSug();
      toast(r.found ? `${r.found} új forrás javaslat (${r.checked} ellenőrizve).` : `Nem találtam új, működő forrást${r.checked ? ` (${r.checked} ellenőrizve, egyik sem adott cikket)` : ''}.`, r.found ? {} : { type:'warning' }); }
    catch(err){ toast(err.message, { type:'error' }); } finally { busy(b, false); } });
  $('#srcCancel').addEventListener('click', () => $('#srcDlg').close()); $('#kwCancel').addEventListener('click', () => $('#kwDlg').close());
  $('#srcForm').addEventListener('submit', async e => { e.preventDefault(); const b = $('#srcSubmit'); busy(b, true);
    try { D = (await api('forras', { id:editS?.id ?? null, source:srcBody() })).data; $('#srcDlg').close(); render(); toast('A forrás mentve. A következő havi gyűjtés már figyeli.'); }
    catch(err){ $('#srcErr').textContent = err.message; $('#srcErr').hidden = false; } finally { busy(b, false); } });
  $('#srcTest').addEventListener('click', async () => { const b = $('#srcTest'); busy(b, true); $('#srcTestOut').textContent = 'Próbagyűjtés…';
    try { const t = (await api('forrasproba', { url:$('#s-url').value, type:$('#s-type').value })).test;
      $('#srcTestOut').innerHTML = t.error && !t.count ? `<span class="error">${esc(t.error)}</span>` : `<b>${esc(t.method)}:</b> ${t.count} cikk.${t.items.length ? '<br>' + t.items.map(i => `${esc(i.date || '–')} · ${esc(i.title)}`).join('<br>') : ''}`; }
    catch(err){ $('#srcTestOut').innerHTML = `<span class="error">${esc(err.message)}</span>`; } finally { busy(b, false); } });
  $('#kwForm').addEventListener('submit', async e => { e.preventDefault(); const b = $('#kwSubmit'); busy(b, true);
    try { D = (await api('kulcsszo', { id:editK?.id ?? null, keyword:{ keyword:$('#k-word').value, topic:$('#k-topic').value, language:$('#k-lang').value, is_exclude:$('#k-exclude').checked } })).data; $('#kwDlg').close(); render(); toast('A kulcsszó mentve.'); }
    catch(err){ $('#kwErr').textContent = err.message; $('#kwErr').hidden = false; } finally { busy(b, false); } });
  document.addEventListener('click', async e => {
    const sa = e.target.closest('[data-sacc]'), sx = e.target.closest('[data-sdis]');
    if (sa || sx){ const b = sa || sx; busy(b, true);
      try { if (sa){ const r = await api('javaslatfelvetel', { id:+sa.dataset.sacc }); D = r.data; SUG = r.pending; render(); renderSug(); toast('A forrás felvéve „új forrás” jelöléssel. A következő havi gyűjtés már figyeli.'); }
        else { SUG = (await api('javaslatelvetes', { id:+sx.dataset.sdis })).pending; renderSug(); toast('A javaslat elvetve; a figyelő többé nem javasolja.'); } }
      catch(err){ toast(err.message, { type:'error' }); busy(b, false); }
      return; }
    const se = e.target.closest('[data-sedit]'), sd = e.target.closest('[data-sdel]'), st = e.target.closest('[data-stog]'), ke = e.target.closest('[data-kedit]'), kd = e.target.closest('[data-kdel]');
    try {
      if (se) openS(D.sources.find(r => r.id === +se.dataset.sedit));
      if (ke) openK(D.keywords.find(r => r.id === +ke.dataset.kedit));
      if (st){ const r = D.sources.find(x => x.id === +st.dataset.stog); D = (await api('forras', { id:r.id, source:{ ...r, active:!r.active } })).data; render(); toast(r.active ? `${r.name}: szünetel.` : `${r.name}: újra aktív.`); }
      if (sd){ const r = D.sources.find(x => x.id === +sd.dataset.sdel); if (!await confirmBox({ title:`Törli a(z) ${r.name} forrást?`, body:'A havi gyűjtés nem figyeli többé. A korábbi cikkei megmaradnak. Ha csak átmenetileg nem kell, szüneteltesse.', confirm:'Forrás törlése' })) return; D = (await api('forrastorles', { id:r.id })).data; render(); toast('A forrás törölve.'); }
      if (kd){ const r = D.keywords.find(x => x.id === +kd.dataset.kdel); if (!await confirmBox({ title:`Törli a(z) „${r.keyword}” kulcsszót?`, confirm:'Kulcsszó törlése' })) return; D = (await api('kulcsszotorles', { id:r.id })).data; render(); toast('A kulcsszó törölve.'); }
    } catch(err){ toast(err.message, { type:'error' }); }
  });
  $('#srcQ').addEventListener('input', render); $('#kwQ').addEventListener('input', render);
  const tabs = $$('[role=tab]');
  const sel = t => tabs.forEach(x => { const on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; $('#' + x.getAttribute('aria-controls')).hidden = !on; });
  tabs.forEach(t => { t.addEventListener('click', () => sel(t)); t.addEventListener('keydown', e => { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft'){ const i = tabs.indexOf(t), o = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]; sel(o); o.focus(); } }); });
  if (location.hash === '#javaslatok') sel($('#tab-sug'));
  render(); renderSug();
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
/* Megjelenés (v2): két kapcsoló a felső sávban – téma (világos/sötét) és sűrűség (normál/kompakt). Az első
   betöltéskor a rendszer témáját követi; a fejléc inline szkriptje már beállította, villanás nélkül. */
function applyTheme(t){ root.setAttribute('data-theme', t); const dark = t === 'dark', b = $('#themeBtn'); if (!b) return;
  $('#themeIcon').setAttribute('href', dark ? '#i-moon' : '#i-sun'); $('#themeLabel').textContent = dark ? 'Sötét' : 'Világos';
  b.setAttribute('aria-pressed', String(dark)); b.title = 'Téma: ' + (dark ? 'sötét' : 'világos'); b.setAttribute('aria-label', 'Téma: ' + (dark ? 'sötét' : 'világos') + '. Váltás'); }
let theme = store.get('xp-theme'); if (theme !== 'light' && theme !== 'dark') theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; applyTheme(theme);
if (shell){
  $('#themeBtn').addEventListener('click', () => { theme = theme === 'dark' ? 'light' : 'dark'; applyTheme(theme); store.set('xp-theme', theme); });
  const applyDensity = d => { const c = d === 'compact'; root.setAttribute('data-density', d); $('#densityBtn').setAttribute('aria-pressed', String(c)); $('#densityLabel').textContent = c ? 'Kompakt' : 'Normál'; $('#densityBtn').title = 'Sűrűség: ' + (c ? 'kompakt' : 'normál'); $('#densityBtn').setAttribute('aria-label', 'Sűrűség: ' + (c ? 'kompakt' : 'normál') + '. Váltás'); };
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
