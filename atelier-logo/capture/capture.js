// Capture des pages d'accueil du corpus du Tinder des logos, avec acceptation des cookies.
// Usage : node capture.js             (toutes les fiches actives de ../corpus.json, saute celles déjà faites ; fenêtre invisible, 3 sites à la fois)
//         node capture.js L008 L132   (seulement ces fiches, refaites)
//         node capture.js --manuel    (fenêtre VISIBLE, un site à la fois : reprend les fiches marquées « vérification » dans le rapport,
//                                      c'est-à-dire les sites qui affichent « je ne suis pas un robot » ; tu coches la case, le script attend puis capture)
//         node capture.js --manuel L008 L132 (pareil, pour ces fiches)
// Résultat : ../captures/<id>.jpg (1280 x 960, JPEG) et ../captures/_rapport.json
const fs = require("fs"), path = require("path");
const { chromium } = require("playwright");

const CORPUS = path.join(__dirname, "..", "corpus.json");
const OUT = path.join(__dirname, "..", "captures");
const TIMEOUT = 35000, RETRIES = 2, ATTENTE_HUMAIN = 120000; // 2 min pour cocher la case

// textes et sélecteurs des boutons « accepter » les plus courants (FR, EN, KO, JA, DE, IT, ES)
const TEXTS = /^\s*(tout accepter|accepter( tout| et fermer| et continuer)?|j'accepte|j’accepte|ok|d'accord|accept( all)?( cookies)?|allow all|agree( and close)?|i agree|got it|yes,? i agree|accept and close|모두 동의|동의|확인|모두 허용|すべて同意|同意する|同意|alle akzeptieren|akzeptieren|accetta( tutto)?|aceptar( todo)?|accetto)\s*$/i;
const SELECTORS = [
  "#onetrust-accept-btn-handler", "#didomi-notice-agree-button", "#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll", "#CybotCookiebotDialogBodyButtonAccept",
  "#axeptio_btn_acceptAll", ".axeptio_btn_acceptAll", "#tarteaucitronPersonalize2", ".tarteaucitronAllow", "#tarteaucitronAllAllowed",
  "[data-testid='uc-accept-all-button']", "#usercentrics-root button[data-testid='uc-accept-all-button']", ".cc-allow", ".cc-btn.cc-allow", ".cc-accept-all",
  "#cmpbntyestxt", ".cmpboxbtnyes", "button.sp_choice_type_11", "[data-cky-tag='accept-button']", ".cky-btn-accept", "#cookie-accept", "#accept-cookies", "#acceptCookies",
  "button[id*='accept' i]", "button[class*='accept' i]", "a[id*='accept' i]", "button[aria-label*='accept' i]", "button[aria-label*='accepter' i]", "button[title*='accepter' i]"
];
// pages de vérification « je ne suis pas un robot » (Cloudflare, hCaptcha, reCAPTCHA, Akamai…)
const VERIF = /je ne suis pas un robot|i'?m not a robot|not a robot|v[ée]rifiez que vous [êe]tes (un )?humain|verify (that )?you are (a )?human|confirm you are human|prouvez que vous|just a moment|un instant\.{0,3}$|attention required|access denied|acc[èe]s refus[ée]|checking your browser|v[ée]rification de votre navigateur|performing security|enable javascript and cookies to continue|사람인지 확인|로봇이 아닙니다/i;
const VERIF_FRAMES = /challenges\.cloudflare\.com|hcaptcha\.com\/captcha|google\.com\/recaptcha\/api2\/anchor|geo\.captcha-delivery\.com|arkoselabs/;

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

// vrai si la page affichée est un écran de vérification (titre, texte de la page ou de ses cadres, cadres de captcha)
async function verificationPage(page){
  try{
    if(VERIF.test((await page.title())||"")) return true;
    for(const f of page.frames()){
      if(VERIF_FRAMES.test(f.url())) { try{ if(await f.locator("body").first().isVisible({timeout:300})) return true; }catch(e){ return true; } }
      try{ const t = await f.evaluate(()=> (document.body && document.body.innerText || "").slice(0, 3000), {timeout:1500}); if(VERIF.test(t)) return true; }catch(e){}
    }
  }catch(e){}
  return false;
}
// mode manuel : on attend que la personne ait coché la case (la page de vérification disparaît)
async function attendreHumain(page, log){
  const t0 = Date.now(); let n = 0;
  while(Date.now()-t0 < ATTENTE_HUMAIN){
    if(!(await verificationPage(page))) return true;
    if(n++ % 10 === 0 && log) log("   → coche la case « je ne suis pas un robot » dans la fenêtre, j'attends…");
    await page.waitForTimeout(1500);
  }
  return !(await verificationPage(page));
}

async function capture(browser, c, rapport, manuel){
  const file = path.join(OUT, c.id+".jpg");
  const ctx = await browser.newContext({ viewport:{width:1280,height:960}, locale:"fr-FR", deviceScaleFactor:1,
    userAgent: manuel ? undefined : "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36" });
  const page = await ctx.newPage();
  let note = "";
  try{
    const url = "https://"+c.domaine;
    try{ await page.goto(url, {waitUntil:"networkidle", timeout:TIMEOUT}); }
    catch(e){ await page.goto(url, {waitUntil:"domcontentloaded", timeout:TIMEOUT}); note="chargement partiel ; "; }
    await page.waitForTimeout(1500);
    if(await verificationPage(page)){
      if(!manuel){ rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"vérification", note: note+"page « je ne suis pas un robot » : à refaire avec node capture.js --manuel" };
        console.log("⚠", c.id, c.nom, "— page de vérification (à refaire en mode --manuel)"); return; }
      console.log("⚠", c.id, c.nom, "— page de vérification : coche la case dans la fenêtre (2 min max)");
      if(!(await attendreHumain(page, console.log))){ rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"vérification", note: note+"case non cochée à temps, ou site qui refuse encore" }; console.log("✗", c.id, c.nom, "— toujours bloqué"); return; }
      note += "vérification passée à la main ; "; await page.waitForTimeout(2500);
      try{ await page.waitForLoadState("networkidle", {timeout:15000}); }catch(e){}
    }
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

async function main(){
  fs.mkdirSync(OUT, {recursive:true});
  const args = process.argv.slice(2);
  const manuel = args.includes("--manuel") || args.includes("--manual");
  const wanted = args.filter(a=>!a.startsWith("--"));
  const rapportFile = path.join(OUT, "_rapport.json");
  const rapport = fs.existsSync(rapportFile) ? JSON.parse(fs.readFileSync(rapportFile,"utf8")) : {};
  const corpus = JSON.parse(fs.readFileSync(CORPUS, "utf8")).filter(c=>!c.retire).filter(c=>!wanted.length || wanted.includes(c.id));
  let todo;
  if(wanted.length) todo = corpus;
  else if(manuel) todo = corpus.filter(c=> rapport[c.id] && rapport[c.id].statut==="vérification");
  else todo = corpus.filter(c=> !fs.existsSync(path.join(OUT, c.id+".jpg")));
  const parallel = manuel ? 1 : 3;
  console.log(todo.length+" fiches à capturer sur "+corpus.length+(manuel ? " (mode manuel : fenêtre visible, un site à la fois)" : " (les captures déjà présentes sont gardées)"));
  if(!todo.length){ console.log(manuel ? "Aucune fiche marquée « vérification » dans le rapport : rien à faire." : "Rien à faire."); return; }
  let browser;
  if(manuel){ try{ browser = await chromium.launch({headless:false, channel:"chrome"}); console.log("Chrome installé utilisé (fenêtre visible)."); }
    catch(e){ browser = await chromium.launch({headless:false}); console.log("Chromium de test utilisé (fenêtre visible)."); } }
  else browser = await chromium.launch();
  let i = 0;
  async function worker(){ while(i<todo.length){ const c = todo[i++]; for(let k=0;k<=RETRIES;k++){ try{ await capture(browser, c, rapport, manuel); break; }catch(e){ if(k===RETRIES) console.log("   abandon", c.id); } }
    fs.writeFileSync(rapportFile, JSON.stringify(rapport, null, 1)); } }
  await Promise.all(Array.from({length:parallel}, worker));
  await browser.close();
  const ok = Object.values(rapport).filter(r=>r.statut==="ok").length, verif = Object.values(rapport).filter(r=>r.statut==="vérification").length, ko = Object.values(rapport).filter(r=>r.statut==="échec").length;
  console.log("Terminé : "+ok+" captures, "+verif+" pages « je ne suis pas un robot »"+(verif && !manuel ? " (lance « node capture.js --manuel » pour les faire à la main)" : "")+", "+ko+" échecs. Détail dans captures/_rapport.json. Vérifie quelques images, puis envoie le dossier captures (zippé) à Claude, ou dépose-le dans atelier-logo/ sur la branche.");
}
module.exports = { clickConsent, verificationPage, attendreHumain };
if(require.main === module) main();
