// Téléchargement des logos du corpus du Tinder des logos, tels que la page les montre (même ordre de sources).
// Usage : node logos.js            (toutes les fiches actives de ../corpus.json, saute celles déjà faites)
//         node logos.js L008 L132  (seulement ces fiches, refaites)
// Résultat : ../captures/logos/<id>.svg ou .png et ../captures/logos/_logos.json (source retenue pour chaque fiche)
// Sources, dans l'ordre : Brandfetch logo complet (SVG puis PNG), Brandfetch symbole, logo.dev (symbole carré).
const fs = require("fs"), path = require("path");
const CORPUS = path.join(__dirname, "..", "corpus.json");
const OUT = path.join(__dirname, "..", "captures", "logos");
const BF_ID = "1idSzZOW6L33rldWhPT";          // client ID Brandfetch de Madame Cho (public)
const LOGO_KEY = "pk_R1VMahZ-R7meTooQii3bIg"; // clé publique logo.dev de Madame Cho (pas un secret)
const TIMEOUT = 20000, PARALLEL = 4;

function sources(c){
  const d = c.domaine.replace(/^https?:\/\//,"").split("/")[0]; const b = "https://cdn.brandfetch.io/domain/"+d+"/w/800/h/400/fallback/404/";
  return [
    { src: b+"logo.svg?c="+BF_ID, kind:"logo complet", ext:"svg" },
    { src: b+"logo.png?c="+BF_ID, kind:"logo complet", ext:"png" },
    { src: b+"symbol.png?c="+BF_ID, kind:"symbole", ext:"png" },
    { src: "https://img.logo.dev/"+d+"?token="+LOGO_KEY+"&size=256&retina=true&format=png&fallback=404", kind:"symbole", ext:"png" },
  ];
}
function pngWidth(buf){ return (buf.length > 24 && buf[0]===0x89 && buf[1]===0x50) ? buf.readUInt32BE(16) : 0; }

async function fetchLogo(c){
  for(const s of sources(c)){
    try{
      const r = await fetch(s.src, { signal: AbortSignal.timeout(TIMEOUT), headers: { "User-Agent": "Mozilla/5.0" } });
      if(!r.ok) continue;
      const buf = Buffer.from(await r.arrayBuffer());
      if(s.ext==="svg"){ if(!/<svg[\s>]/i.test(buf.subarray(0, 2000).toString("utf8"))) continue; }
      else if(pngWidth(buf) < 64) continue;   // comme la page : un PNG minuscule est ignoré
      return { ...s, buf };
    }catch(e){}
  }
  return null;
}

async function main(){
  fs.mkdirSync(OUT, { recursive:true });
  const wanted = process.argv.slice(2).filter(a=>!a.startsWith("--"));
  const rapportFile = path.join(OUT, "_logos.json");
  const rapport = fs.existsSync(rapportFile) ? JSON.parse(fs.readFileSync(rapportFile, "utf8")) : {};
  const corpus = JSON.parse(fs.readFileSync(CORPUS, "utf8")).filter(c=>!c.retire).filter(c=>!wanted.length || wanted.includes(c.id));
  const done = id => ["svg","png"].some(e=>fs.existsSync(path.join(OUT, id+"."+e)));
  const todo = wanted.length ? corpus : corpus.filter(c=>!done(c.id));
  console.log(todo.length+" logos à télécharger sur "+corpus.length+" fiches actives (les logos déjà présents sont gardés)");
  let i = 0, ok = 0, ko = 0;
  async function worker(){ while(i<todo.length){ const c = todo[i++];
    const got = await fetchLogo(c);
    if(got){ for(const e of ["svg","png"]){ const f=path.join(OUT, c.id+"."+e); if(fs.existsSync(f)) fs.unlinkSync(f); }
      fs.writeFileSync(path.join(OUT, c.id+"."+got.ext), got.buf);
      rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"ok", kind:got.kind, fichier:c.id+"."+got.ext, source:got.src.replace(/\?.*$/,"") }; ok++;
      console.log("✓", c.id, c.nom, "—", got.kind, "("+got.ext+")"); }
    else { rapport[c.id] = { nom:c.nom, domaine:c.domaine, statut:"aucun logo" }; ko++; console.log("✗", c.id, c.nom, "— aucun logo trouvé"); }
    fs.writeFileSync(rapportFile, JSON.stringify(rapport, null, 1)); } }
  await Promise.all(Array.from({ length: PARALLEL }, worker));
  console.log("Terminé : "+ok+" logos, "+ko+" sans logo. Les fichiers sont dans captures/logos/. Zippe ensuite tout le dossier captures et envoie-le à Claude.");
}
module.exports = { sources, fetchLogo, pngWidth };
if(require.main === module) main();
