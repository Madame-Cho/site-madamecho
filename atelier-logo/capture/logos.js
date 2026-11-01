// Téléchargement des logos du corpus du Tinder des logos, tels que la page les montre (mêmes adresses, même ordre, chargés par un vrai navigateur).
// Usage : node logos.js            (toutes les fiches actives de ../corpus.json, saute celles déjà faites)
//         node logos.js --tout     (refait toutes les fiches, même celles déjà faites)
//         node logos.js L008 L132  (seulement ces fiches, refaites)
// Résultat : ../captures/logos/<id>.svg ou .png et ../captures/logos/_logos.json (source retenue pour chaque fiche)
// Sources, dans l'ordre : Brandfetch logo complet (SVG puis PNG), Brandfetch symbole, logo.dev (symbole carré). Version 2 (navigateur Chromium).
const fs = require("fs"), path = require("path");
const { chromium } = require("playwright");
const CORPUS = path.join(__dirname, "..", "corpus.json");
const OUT = path.join(__dirname, "..", "captures", "logos");
const BF_ID = "1idSzZOW6L33rldWhPT";          // client ID Brandfetch de Madame Cho (public)
const LOGO_KEY = "pk_R1VMahZ-R7meTooQii3bIg"; // clé publique logo.dev de Madame Cho (pas un secret)
const TIMEOUT = 20000;

function sources(c){
  const d = c.domaine.replace(/^https?:\/\//,"").split("/")[0]; const b = "https://cdn.brandfetch.io/domain/"+d+"/w/800/h/400/fallback/404/";
  return [
    { src: b+"logo.svg?c="+BF_ID, kind:"logo complet", svg:true },
    { src: b+"logo.png?c="+BF_ID, kind:"logo complet" },
    { src: b+"symbol.png?c="+BF_ID, kind:"symbole" },
    { src: "https://img.logo.dev/"+d+"?token="+LOGO_KEY+"&size=256&retina=true&format=png&fallback=404", kind:"symbole" },
  ];
}
function origin(req){ let r = req; while(r.redirectedFrom()) r = r.redirectedFrom(); return r.url(); }

// Charge l'image dans la page comme la page « Atelier logo » le fait (balise <img>), et garde les octets de la réponse.
async function fetchLogo(page, c, srcs){
  for(const s of (srcs || sources(c))){
    // une seule image est chargée à la fois : on garde la réponse de son adresse d'origine, sinon la dernière réponse image reçue (redirections)
    const bodies = new Map(); let last = null;
    const onResp = async (resp) => { try{ const type = resp.headers()["content-type"] || ""; if(!resp.ok() || !/image|svg|octet/i.test(type)) return;
      const rec = { buf: await resp.body(), type }; last = rec; if(origin(resp.request()) === s.src) bodies.set(s.src, rec); }catch(e){} };
    page.on("response", onResp);
    let dims = null;
    try{
      dims = await page.evaluate(([src, t]) => new Promise(res => { const im = new Image(); const to = setTimeout(()=>res(null), t);
        im.onload = () => { clearTimeout(to); res({ w: im.naturalWidth, h: im.naturalHeight }); }; im.onerror = () => { clearTimeout(to); res(null); }; im.src = src; }), [s.src, TIMEOUT]);
      if(dims) await page.waitForTimeout(150); // laisse le temps à la réponse d'être lue
    }catch(e){ dims = null; }
    page.off("response", onResp);
    const got = bodies.get(s.src) || last;
    if(!dims || !got) continue;
    if(!s.svg && dims.w < 64) continue;           // comme la page : une image minuscule est ignorée
    const isSvg = s.svg || /svg/i.test(got.type) || /<svg[\s>]/i.test(got.buf.subarray(0, 2000).toString("utf8"));
    return { ...s, buf: got.buf, ext: isSvg ? "svg" : (/webp/i.test(got.type) ? "webp" : "png"), w: dims.w, h: dims.h };
  }
  return null;
}

async function main(){
  fs.mkdirSync(OUT, { recursive:true });
  const args = process.argv.slice(2); const tout = args.includes("--tout");
  const wanted = args.filter(a=>!a.startsWith("--"));
  const rapportFile = path.join(OUT, "_logos.json");
  const rapport = (!tout && fs.existsSync(rapportFile)) ? JSON.parse(fs.readFileSync(rapportFile, "utf8")) : {};
  const corpus = JSON.parse(fs.readFileSync(CORPUS, "utf8")).filter(c=>!c.retire).filter(c=>!wanted.length || wanted.includes(c.id));
  const done = id => ["svg","png","webp"].some(e=>fs.existsSync(path.join(OUT, id+"."+e)));
  const todo = (wanted.length || tout) ? corpus : corpus.filter(c=>!done(c.id));
  console.log(todo.length+" logos à télécharger sur "+corpus.length+" fiches actives"+(tout ? " (tout est refait)" : " (les logos déjà présents sont gardés ; « node logos.js --tout » pour tout refaire)"));
  const browser = await chromium.launch(); const ctx = await browser.newContext({ locale:"fr-FR" }); const page = await ctx.newPage();
  // page de départ locale (file://), comme la page « Atelier logo » ouverte depuis le disque : une page data: ou about:blank peut être bloquée par Chromium
  const pageFile = path.join(OUT, "_page.html"); fs.writeFileSync(pageFile, "<!doctype html><html><body></body></html>");
  await page.goto(require("url").pathToFileURL(pageFile).href);
  let ok = 0, ko = 0, complets = 0;
  for(const c of todo){
    const got = await fetchLogo(page, c);
    for(const e of ["svg","png","webp"]){ const f=path.join(OUT, c.id+"."+e); if(fs.existsSync(f)) fs.unlinkSync(f); }
    if(got){ fs.writeFileSync(path.join(OUT, c.id+"."+got.ext), got.buf);
      rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"ok", kind:got.kind, fichier:c.id+"."+got.ext, largeur:got.w, hauteur:got.h, source:got.src.replace(/\?.*$/,"") }; ok++; if(got.kind==="logo complet") complets++;
      console.log("✓", c.id, c.nom, "—", got.kind, "("+got.ext+", "+got.w+"×"+got.h+")"); }
    else { rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"aucun logo" }; ko++; console.log("✗", c.id, c.nom, "— aucun logo trouvé"); }
    fs.writeFileSync(rapportFile, JSON.stringify(rapport, null, 1));
  }
  await browser.close();
  console.log("Terminé : "+ok+" logos ("+complets+" logos complets, "+(ok-complets)+" symboles), "+ko+" sans logo. Les fichiers sont dans captures/logos/. Zippe ensuite tout le dossier captures et envoie-le à Claude.");
}
module.exports = { sources, fetchLogo, OUT };
if(require.main === module) main();
