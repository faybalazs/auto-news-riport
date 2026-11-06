// A komponenskatalógushoz: a helyi próbafelületről (kitalált tesztadatokkal) kigyűjti a renderelt oldalakat és állapotokat.
const { chromium } = require('playwright'); const fs = require('fs');
const B = 'http://127.0.0.1:8766/', OUT = process.argv[2];
const R = { pages: {}, states: {}, log: [] };
(async () => {
  const b = await chromium.launch(); const c = await b.newContext({ viewport: { width: 1366, height: 900 } }); const p = await c.newPage();
  await p.route('**/fonts.g*/**', r => r.abort());
  const go = async q => { await p.goto(B + 'index.php?p=' + q); await p.waitForTimeout(700); };
  const grab = async (key, fn) => { try { R.states[key] = await fn(); R.log.push(key + ' ok'); } catch (e) { R.log.push(key + ' HIBA ' + e.message.split('\n')[0]); } };
  const outer = sel => p.$eval(sel, el => el.outerHTML);
  const closeDlg = () => p.evaluate(() => document.querySelectorAll('dialog[open]').forEach(d => d.close()));

  await p.goto(B + 'index.php?p=belepes'); await p.waitForTimeout(300);
  await grab('auth', () => outer('.auth'));
  await p.fill('#l-email', 'balazs@pelda.hu'); await p.fill('#l-pass', 'balazs-jelszo-1'); await Promise.all([p.waitForLoadState('load'), p.click('button[type=submit]')]);
  await go('attekintes'); await p.click('#wsBtn'); await p.click('#wsMenu button:has-text("Xperion")'); await p.waitForLoadState('load'); await p.waitForTimeout(500);

  const pages = [['attekintes', 'attekintes&honap=2610'], ['cikkek', 'cikkek&honap=2610'], ['posztok', 'posztok'], ['poszt', 'poszt&kod=LI-2610-01'], ['poszt-jovahagyott', 'poszt&kod=LI-2610-02'],
    ['otletek', 'otletek'], ['otlet', 'otlet'], ['szabalyok', 'szabalyok'], ['forrasok', 'forrasok'], ['felhasznalok', 'felhasznalok'], ['ugyfelek', 'ugyfelek'], ['vezerlo', 'vezerlo'], ['naplo', 'naplo'], ['profil', 'profil']];
  for (const [k, q] of pages){
    await go(q);
    if (k === 'ugyfelek'){ const r = p.locator('#clientTable tbody tr').first(); if (await r.count()) { await r.click().catch(() => {}); await p.waitForTimeout(300); } }
    try { R.pages[k] = await p.$eval('#main', el => el.innerHTML); R.log.push('oldal ' + k + ' ok'); } catch (e) { R.log.push('oldal ' + k + ' HIBA ' + e.message); }
    if (k === 'attekintes'){
      await grab('shell', () => p.evaluate(() => { const s = document.querySelector('.shell').cloneNode(true); s.querySelector('#main').innerHTML = '<!--TARTALOM-->'; return s.outerHTML; }));
      await grab('wsMenu', async () => { await p.click('#wsBtn'); await p.waitForTimeout(150); const h = await outer('#wsMenu'); await p.keyboard.press('Escape'); return h; });
      await grab('profileMenu', async () => { await p.click('#profileBtn'); await p.waitForTimeout(150); const h = await outer('#profileMenu'); await p.keyboard.press('Escape'); return h; });
      await grab('sumSend', async () => { await p.click('#sumSend'); await p.waitForSelector('#fDlg[open]'); await p.fill('#fDlg input >> nth=0', 'vezeto@pelda.hu'); const h = await outer('#fDlg'); await closeDlg(); return h; });
    }
    if (k === 'cikkek'){
      await grab('rowMenu', async () => { await p.locator('#artTable .c-act button').first().click(); await p.waitForTimeout(200); const h = await outer('#rowMenu'); await p.keyboard.press('Escape'); return h; });
      await grab('bulk', async () => { await p.click('#artViews [data-v="linkedin"]'); await p.waitForTimeout(200); const bx = p.locator('#artTable .dt-row'); for (let i = 0; i < Math.min(2, await bx.count()); i++) await bx.nth(i).check(); await p.waitForTimeout(200); return outer('.table-card'); });
      await grab('genDialog', async () => { await p.click('#bulkPost'); await p.waitForSelector('#fDlg[open]'); await p.fill('#g-text', 'A bankfiókok vezetőinek szóljon.'); const h = await outer('#fDlg'); await closeDlg(); return h; });
      await grab('upload', async () => { await p.click('#bulkClear').catch(() => {}); return outer('#artUpload'); });
    }
    if (k === 'poszt'){
      await grab('workBanner', () => p.evaluate(() => { const w = document.querySelector('#workBanner').cloneNode(true); w.hidden = false; w.querySelector('#workTitle').textContent = 'A poszt újragenerálása folyamatban…'; w.querySelector('#workText').textContent = 'A Claude írja a szöveget és tervezi a képet; ez fél–egy percig tart. Ne zárja be az oldalt.'; return w.outerHTML; }));
      await grab('aiMenu', async () => { await p.click('#aiBtn'); await p.waitForTimeout(150); const h = await outer('#rowMenu'); await p.keyboard.press('Escape'); return h; });
      await grab('inputConfirm', async () => { await p.click('[data-pa="modositas"]'); await p.waitForSelector('#dlg[open]'); const h = await outer('#dlg'); await closeDlg(); return h; });
    }
    if (k === 'forrasok'){
      await grab('srcDlg', async () => { await p.click('#srcAdd'); await p.waitForSelector('#srcDlg[open]'); await p.fill('#s-url', 'https://www.retailinsiders.test/feed'); const h = await outer('#srcDlg'); await closeDlg(); return h; });
      await grab('confirm', async () => { await p.locator('[data-sdel]').first().click(); await p.waitForSelector('#dlg[open]'); const h = await outer('#dlg'); await closeDlg(); return h; });
      await grab('busyBtn', () => p.evaluate(() => { const bt = document.querySelector('#srcAdd').cloneNode(true); bt.setAttribute('aria-busy', 'true'); bt.disabled = true; return bt.outerHTML; }));
    }
    if (k === 'felhasznalok') await grab('userDlg', async () => { await p.click('#inviteBtn'); await p.waitForSelector('#userDlg[open]'); const h = await outer('#userDlg'); await closeDlg(); return h; });
    if (k === 'vezerlo') await grab('collectRun', async () => { await p.click('#colRun'); await p.waitForSelector('#colOut:not([hidden])', { timeout: 30000 }); await p.waitForTimeout(400); return outer('#colOut'); });
  }
  R.sprite = await p.$eval('svg[aria-hidden="true"][style*="position:absolute"]', el => el.outerHTML).catch(() => '');
  fs.writeFileSync(OUT, JSON.stringify(R)); console.log(R.log.join('\n')); await b.close();
})();
