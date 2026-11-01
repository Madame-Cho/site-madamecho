// Capture des pages d'accueil du corpus du Tinder des logos, avec acceptation des cookies.
// Usage : node capture.js            (toutes les fiches actives de ../corpus.json, saute celles déjà faites)
//         node capture.js L008 L132  (seulement ces fiches, refaites)
// Résultat : ../captures/<id>.jpg (1280 x 960, JPEG) et ../captures/_rapport.json
const fs = require("fs"), path = require("path");
const { chromium } = require("playwright");

const CORPUS = path.join(__dirname, "..", "corpus.json");
const OUT = path.join(__dirname, "..", "captures");
const PARALLEL = 3, TIMEOUT = 35000, RETRIES = 2;

// textes et sélecteurs des boutons « accepter » les plus courants (FR, EN, KO, JA, DE, IT, ES)
const TEXTS = /^\s*(tout accepter|accepter( tout| et fermer| et continuer)?|j'accepte|j’accepte|ok|d'accord|accept( all)?( cookies)?|allow all|agree( and close)?|i agree|got it|yes,? i agree|accept and close|모두 동의|동의|확인|모두 허용|すべて同意|同意する|同意|alle akzeptieren|akzeptieren|accetta( tutto)?|aceptar( todo)?|accetto)\s*$/i;
const SELECTORS = [
  "#onetrust-accept-btn-handler", "#didomi-notice-agree-button", "#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll", "#CybotCookiebotDialogBodyButtonAccept",
  "#axeptio_btn_acceptAll", ".axeptio_btn_acceptAll", "#tarteaucitronPersonalize2", ".tarteaucitronAllow", "#tarteaucitronAllAllowed",
  "[data-testid='uc-accept-all-button']", "#usercentrics-root button[data-testid='uc-accept-all-button']", ".cc-allow", ".cc-btn.cc-allow", ".cc-accept-all",
  "#cmpbntyestxt", ".cmpboxbtnyes", "button.sp_choice_type_11", "[data-cky-tag='accept-button']", ".cky-btn-accept", "#cookie-accept", "#accept-cookies", "#acceptCookies",
  "button[id*='accept' i]", "button[class*='accept' i]", "a[id*='accept' i]", "button[aria-label*='accept' i]", "button[aria-label*='accepter' i]", "button[title*='accepter' i]"
];

async function clickConsent(page){
  const frames = [page.mainFrame(), ...page.frames().filter(f=>f!==page.mainFrame())];
  for(const frame of frames){
    for(const sel of SELECTORS){
      try{ const el = frame.locator(sel).first(); if(await el.count() && await el.isVisible({timeout:300})){ await el.click({timeout:1500}); return sel; } }catch(e){}
    }
    try{
      const btns = frame.locator("button, a[role=button], [role=button], input[type=button], input[type=submit]");
      const n = Math.min(await btns.count(), 150);
      for(let i=0;i<n;i++){ const b=btns.nth(i); try{ const t=((await b.innerText({timeout:200}))||(await b.getAttribute("value"))||"").trim();
        if(t && t.length<40 && TEXTS.test(t) && await b.isVisible({timeout:200})){ await b.click({timeout:1500}); return "texte « "+t+" »"; } }catch(e){} }
    }catch(e){}
  }
  return null;
}

async function capture(browser, c, rapport){
  const file = path.join(OUT, c.id+".jpg");
  const ctx = await browser.newContext({ viewport:{width:1280,height:960}, locale:"fr-FR", deviceScaleFactor:1,
    userAgent:"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36" });
  const page = await ctx.newPage();
  let note = "";
  try{
    const url = "https://"+c.domaine;
    try{ await page.goto(url, {waitUntil:"networkidle", timeout:TIMEOUT}); }
    catch(e){ await page.goto(url, {waitUntil:"domcontentloaded", timeout:TIMEOUT}); note="chargement partiel ; "; }
    await page.waitForTimeout(1500);
    const clicked = await clickConsent(page);
    if(clicked){ note += "cookies : "+clicked; await page.waitForTimeout(1200); const again = await clickConsent(page); if(again) await page.waitForTimeout(800); }
    else note += "pas de bandeau trouvé";
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({ path:file, type:"jpeg", quality:82, clip:{x:0,y:0,width:1280,height:960} });
    rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"ok", note };
    console.log("✓", c.id, c.nom, "—", note);
  }catch(e){
    rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"échec", note: note + String(e.message||e).split("\n")[0].slice(0,160) };
    console.log("✗", c.id, c.nom, "—", rapport[c.id].note);
    throw e;
  }finally{ await ctx.close(); }
}

(async()=>{
  fs.mkdirSync(OUT, {recursive:true});
  const wanted = process.argv.slice(2);
  const corpus = JSON.parse(fs.readFileSync(CORPUS, "utf8")).filter(c=>!c.retire).filter(c=>!wanted.length || wanted.includes(c.id));
  const todo = corpus.filter(c=> wanted.length || !fs.existsSync(path.join(OUT, c.id+".jpg")));
  const rapportFile = path.join(OUT, "_rapport.json");
  const rapport = fs.existsSync(rapportFile) ? JSON.parse(fs.readFileSync(rapportFile,"utf8")) : {};
  console.log(todo.length+" fiches à capturer sur "+corpus.length+" (les captures déjà présentes sont gardées)");
  const browser = await chromium.launch();
  let i = 0;
  async function worker(){ while(i<todo.length){ const c = todo[i++]; for(let k=0;k<=RETRIES;k++){ try{ await capture(browser, c, rapport); break; }catch(e){ if(k===RETRIES) console.log("   abandon", c.id); } }
    fs.writeFileSync(rapportFile, JSON.stringify(rapport, null, 1)); } }
  await Promise.all(Array.from({length:PARALLEL}, worker));
  await browser.close();
  const ok = Object.values(rapport).filter(r=>r.statut==="ok").length, ko = Object.values(rapport).filter(r=>r.statut!=="ok").length;
  console.log("Terminé : "+ok+" captures, "+ko+" échecs. Détail dans captures/_rapport.json. Vérifie quelques images, puis envoie le dossier captures (zippé) à Claude, ou dépose-le dans atelier-logo/ sur la branche.");
})();
