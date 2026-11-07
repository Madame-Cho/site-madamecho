# Génère le site Madame Cho v2 (maquette « Galerie » choisie par Youngmi le 03/11/2026).
# Usage, depuis la racine du dépôt : python3 -I .sources/gen.py . --en-ligne   (voir .sources/LISEZMOI.md)
# Autres modes : python3 -I gen.py SORTIE [--apercu | --essai]
#   --apercu : liens vers index.html (lecture hors serveur), bandeau « aperçu privé », envoi du formulaire simulé.
# Sans --apercu : liens propres (/carte/…/), prêt pour OVH ; la balise noindex reste sans --en-ligne (version de la racine, R-19).
import os, sys, json, html, shutil

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from donnees import *

OUT = sys.argv[1]
APERCU = "--apercu" in sys.argv
ESSAI = "--essai" in sys.argv   # copie d'essai sous atelier-site/site/ : vrai formulaire, bandeau « version d'essai », noindex
EN_LIGNE = "--en-ligne" in sys.argv   # version de la racine, publiée seulement après le go d'Olivier (R-19) : pages indexables, sitemap, robots.txt, .htaccess complet
AVEC_PROS = True          # section Professionnels (restaurants cités, avec leurs liens)
DEMANDE_PRO = True        # offre, devis et demande des professionnels (décision d'Olivier du 03/11/2026, audit C02 : partie Pros gardée)
DELAI_PRO = 14            # « Commande deux semaines à l'avance » : appliqué aussi par le formulaire et par le serveur (audit C02)
VERSION = "2026-11-07-agenda"
SITE = "https://madamecho.com/"
# Mesure d'audience (T-06) : Matomo installé chez OVH sur stats.madamecho.com, réglé pour l'exemption de la CNIL (sans cookie).
# Vide = aucune mesure, le site reste exactement comme avant. À remplir seulement quand Matomo répond à cette adresse.
MATOMO = "https://stats.madamecho.com/"   # Matomo installé le 06/11/2026 (avec la barre finale)
MATOMO_SITE = 1           # numéro du site dans Matomo
MESURE = bool(MATOMO) and EN_LIGNE
BASE = "/atelier-site/site/" if ESSAI else "/"   # chemin absolu du site sur le serveur (page 404, servie à n'importe quelle adresse)
MAJ = VERSION[:10]        # date de mise à jour annoncée au plan du site
ICI = os.path.dirname(os.path.abspath(__file__))
COMMUN = os.path.join(ICI, "commun") + "/"   # police Jost et logo, repris des maquettes (retirées du site le 03/11/2026)
E = html.escape
PHOTOS = json.load(open(os.path.join(ICI, "photos.json")))   # dimensions des photos de assets/photos (photos.py)

def R(prof):
    """Préfixe des liens : relatif (../) selon la profondeur, ou absolu (prof < 0) pour la page 404."""
    return BASE if prof < 0 else "../" * prof

def euros(c):
    e, ct = divmod(c, 100)
    return f"{e} €" if ct == 0 else f"{e},{ct:02d} €"

def lien(cible, prof):
    """cible : '' (accueil), 'carte/', 'carte/x/', 'ma-demande/'…  prof : profondeur de la page courante."""
    r = R(prof)
    if APERCU:
        if cible == "":   # l'aperçu publié sert l'accueil comme page principale ; les sous-pages pointent vers sa copie accueil/
            return "" if prof == 0 else r + "accueil/index.html"
        return r + cible + "index.html"
    return (r + cible) if (r + cible) else "./"

def img(nom, prof, alt, tailles="100vw", lazy=True, cls="", grand=None, petit=None):
    r = R(prof)
    if grand is None:
        grand, petit = f"{nom}-grand.webp", f"{nom}-600.webp"
    w, h = PHOTOS[grand]
    pw = PHOTOS[petit][0]
    c = f' class="{cls}"' if cls else ""
    l = ' loading="lazy" decoding="async"' if lazy else ' fetchpriority="high"'
    return (f'<img{c} src="{r}assets/photos/{grand}" srcset="{r}assets/photos/{petit} {pw}w, {r}assets/photos/{grand} {w}w" '
            f'sizes="{tailles}" width="{w}" height="{h}" alt="{E(alt)}"{l}>')

def entete(prof):
    a = lien("", prof)
    pros = f'<a href="{a}#pros">Professionnels</a>' if AVEC_PROS else ""
    return f'''<a class="aller" href="#contenu">Aller au contenu</a>
<header class="entete">
 <a class="logo-lien" href="{a}"><img class="logo" src="{R(prof)}assets/logo-chocolat.svg" alt="Madame Cho, retour à l'accueil" width="168" height="50"></a>
 <nav class="nav" aria-label="Menu principal">
  <a href="{lien("carte/", prof)}">La carte</a>
  <a href="{a}#anniversaires">Anniversaires</a>
  <a href="{a}#evenements">Événements</a>
  {pros}
  <a href="{a}#maison">La maison</a>
  <a class="nav-demande" href="{lien('ma-demande/', prof)}">Ma demande<span class="compteur" data-compteur hidden></span></a>
 </nav>
</header>'''

def bouton_insta(classe=""):
    """Bouton Instagram aux couleurs de Madame Cho : chocolat et ivoire, pictogramme dessiné au trait."""
    pseudo = "@" + INSTAGRAM.rstrip("/").rsplit("/", 1)[-1]
    cl = ("insta " + classe).strip()
    return (f'<a class="{cl}" href="{E(INSTAGRAM)}" rel="noopener" target="_blank">'
            '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="20" height="20">'
            '<rect x="3" y="3" width="18" height="18" rx="5.5" fill="none" stroke="currentColor" stroke-width="1.7"/>'
            '<circle cx="12" cy="12" r="4.1" fill="none" stroke="currentColor" stroke-width="1.7"/>'
            '<circle cx="17.2" cy="6.8" r="1.15" fill="currentColor"/></svg>'
            f'<span>Suivre sur Instagram</span><span class="insta-pseudo">{E(pseudo)}</span></a>')

def pied(prof):
    r = R(prof)
    return f'''<footer class="pied">
 <img class="logo-pied" src="{r}assets/logo-chocolat.svg" alt="Madame Cho" width="150" height="45" loading="lazy">
 <p class="activite">{E(ACTIVITE).replace("Hauts-de-Seine", '<span class="insecable">Hauts-de-Seine</span>')}</p>
 <address>{E(ADRESSE)}<br><a href="{lien('retrait/', prof)}">Retrait sur rendez-vous</a>, tous les jours de 9 h à 19 h<br>
 <a href="tel:{TEL_LIEN}">{TEL}</a> · <a href="mailto:{EMAIL}">{EMAIL}</a><br>
 </address>
 {bouton_insta("insta-pied")}
 <nav class="liens-pied" aria-label="Informations légales">
  <a href="{lien('mentions-legales/', prof)}">Mentions légales</a>
  <a href="{lien('conditions-de-commande/', prof)}">Conditions de commande</a>
  <a href="{lien('confidentialite/', prof)}">Confidentialité</a>
 </nav>
</footer>'''

def bandeau():
    if ESSAI:
        return ('<div class="bandeau-apercu" role="note">Version d\'essai du nouveau site, non référencée par les moteurs de recherche : '
                'une demande envoyée ici arrive vraiment à contact@madamecho.com.</div>')
    if not APERCU:
        return ""
    return ('<div class="bandeau-apercu" role="note">Aperçu privé du nouveau site, version du 3 novembre : '
            'rien n\'est en ligne et le formulaire n\'envoie rien.</div>')

def page(chemin, titre, desc, corps, prof, script_page="", jsonld=None, classe="", indexer=True, image=None, image_alt=None, titre_partage=None):
    """indexer=False : page utile mais sans intérêt dans les résultats (Ma demande, 404), « noindex, follow » même en ligne.
    image : image de partage (1200 x 630) propre à la page, sinon celle du site."""
    r = R(prof)
    url = SITE + (chemin[:-len("index.html")] if chemin.endswith("index.html") else chemin)
    if not EN_LIGNE:
        robots = '<meta name="robots" content="noindex, nofollow">\n'
    elif not indexer:
        robots = '<meta name="robots" content="noindex, follow">\n'
    else:
        robots = '<meta name="robots" content="index, follow, max-image-preview:large">\n'
    canon = f'<link rel="canonical" href="{url}">\n' if indexer else ""
    image = image or "partage.jpg"
    image_alt = image_alt or "Choux craquelin de Madame Cho posés sur une pierre claire"
    tp = titre_partage or titre
    ld = f'<script type="application/ld+json">{json.dumps(jsonld, ensure_ascii=False)}</script>\n' if jsonld else ""
    cfg = '<script>window.MC_APERCU=true;</script>\n' if APERCU else ""
    mesure = f'<script src="{r}assets/mesure.js?v={VERSION}" data-matomo="{E(MATOMO)}" data-site="{MATOMO_SITE}"></script>\n' if MESURE else ""
    return f'''<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{E(titre)}</title>
<meta name="description" content="{E(desc)}">
{robots}{canon}<meta property="og:type" content="website">
<meta property="og:site_name" content="Madame Cho">
<meta property="og:title" content="{E(tp)}">
<meta property="og:description" content="{E(desc)}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE}assets/photos/{image}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{E(image_alt)}">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#ffffff">
<link rel="icon" href="{r}favicon.ico" sizes="48x48">
<link rel="icon" href="{r}assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="{r}assets/apple-touch-icon.png">
<link rel="manifest" href="{r}assets/site.webmanifest?v={VERSION}">
<link rel="preload" href="{r}assets/polices/jost-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{r}assets/site.css?v={VERSION}">
{ld}</head>
<body class="{classe}">
{bandeau()}{entete(prof)}
<main id="contenu">
{corps}
</main>
{pied(prof)}
{cfg}<script src="{r}assets/catalogue.js?v={VERSION}"></script>
<script src="{r}assets/site.js?v={VERSION}"></script>
{mesure}{script_page}<!-- test -->
</body>
</html>
'''

def carte_items(prof, h="h3"):
    out = []
    for p in PRODUITS:
        out.append(f'''<a class="gateau" href="{lien('carte/' + p['id'] + '/', prof)}">
 <div class="cadre">{img(p['photo'], prof, p['alt'], "(min-width: 860px) 31vw, (min-width: 700px) 46vw, 100vw")}</div>
 <div class="legende"><{h}>{E(p['nom'])}</{h}><p>{E(p['court'])}</p>
 <div class="meta"><span>{E(p['carte_pour'])}</span><b>{E(p['carte_prix'])}</b></div></div>
</a>''')
    return "\n".join(out)

# ---------- Accueil ----------
def accueil(prof=0):
    a = lien("ma-demande/", prof)
    anniv = "".join(f"<p>{E(x)}</p>" for x in ANNIV)
    rep = "".join(f"<li>{E(x)}</li>" for x in REPERES)
    etapes = "".join(f'<li><span class="num">{i}</span><b>{E(t)}</b><span>{E(d)}</span></li>' for i, (t, d) in enumerate(ETAPES, 1))
    prat = "".join(f"<div><dt>{E(t)}</dt><dd>{E(d)}</dd></div>" for t, d in PRATIQUE)
    pros = ""
    if AVEC_PROS:
        rs = "".join(f'<li><a href="{E(u)}" rel="noopener" target="_blank">{E(x)}</a></li>' for x, u in RESTOS)
        of = "".join(f"<li>{E(x)}</li>" for x in OFFRE)
        offre = (f'''<ul class="offre">{of}</ul><p class="petit">Commande deux semaines à l'avance, sur devis.</p>
 <a class="bouton" href="{a}?pro=1#coordonnees">Demander un devis</a>''') if DEMANDE_PRO else ""
        titre = "Pour les restaurants et les entreprises" if DEMANDE_PRO else "Pour les restaurants et les cafés"
        pros = f'''<section class="bloc" id="pros" aria-labelledby="t-pros"><div class="deux">
 <div><h2 class="titre" id="t-pros"><span class="etiquette">Professionnels</span> {titre}</h2><p>{E(PROS if DEMANDE_PRO else PROS_REF)}</p>
 <div class="sous-titre">Ils servent nos desserts</div><ul class="restos">{rs}</ul>
 {offre}</div>
 <div>{img("professionnels-desserts", prof, "Assortiment de desserts : tarte aux fruits à coque, pâtisseries individuelles et gâteau aux carottes", "(min-width: 860px) 45vw, 100vw", grand="professionnels-final-grand.webp", petit="professionnels-final-600.webp")}</div>
</div></section>'''
    corps = f'''<section class="ouverture" aria-labelledby="t-accueil">
 {img("ouverture", prof, "Quatre choux craquelin posés sur une pierre claire, dans la lumière du matin", "(min-width: 860px) calc(100vw - 96px), 100vw", lazy=False, grand="ouverture-1600.webp", petit="ouverture-800.webp")}
 <div class="texte"><h1 id="t-accueil">{E(H1)}</h1><p>{E(SOUS)}</p><a class="lien" href="#carte">Voir la carte</a></div>
</section>
<p class="intro">{E(INTRO)}</p>
<section class="bloc" id="carte" aria-labelledby="t-carte"><h2 class="etiquette centre" id="t-carte">La carte</h2>
 <div class="grille">{carte_items(prof)}</div>
 <p class="petit centre">{E(TRACES)}</p>
</section>
<section class="bloc" id="anniversaires" aria-labelledby="t-anniv"><div class="deux">
 <div>{img("anniversaire", prof, "Tiramisu cerclé de chocolat, saupoudré de cacao et de feuilles d'or", "(min-width: 860px) 45vw, 100vw")}</div>
 <div><h2 class="titre" id="t-anniv"><span class="etiquette">Anniversaires</span> Un gâteau à votre nom</h2>{anniv}
 <a class="bouton" href="{a}#perso">Faire ma demande</a></div>
</div></section>
<section class="bloc" id="evenements" aria-labelledby="t-evenements"><div class="large">
 <h2 class="titre" id="t-evenements"><span class="etiquette">Événements &amp; créations sur commande</span> Un thème, une occasion, une touche gourmande.</h2>
 <p>Anniversaire d’enfant, baptême, mariage ou toute autre occasion : Madame Cho imagine des créations gourmandes sur commande, adaptées au thème et à l’ambiance que vous souhaitez. Halloween n’est ici qu’un exemple parmi tant d’autres !</p>
 <p>Partagez vos envies, votre thème, la date et le nombre de personnes : imaginons ensemble les douceurs qui accompagneront votre événement.</p>
 <figure class="evenements-collage">{img("halloween-triptyque", prof, "Créations d’Halloween : truffes araignées et biscuits doigts de sorcière, mini-gâteaux au chocolat et desserts fantômes", "(min-width: 1200px) 1100px, 100vw")}<figcaption>Truffes au chocolat en forme d’araignées et biscuits « doigts de sorcière », mini-gâteaux au chocolat et desserts fantômes : des créations imaginées pour Halloween.</figcaption></figure>
 <a class="bouton" href="{a}#perso">Parlons de votre événement</a>
</div></section>
{pros}
<section class="bloc" id="maison" aria-labelledby="t-maison"><div class="maison">
 <figure class="portrait">{img("youngmi", prof, "Madame Cho en cuisine, en toque, dressant des assiettes", "(min-width: 860px) 40vw, 100vw", grand="youngmi-900.webp", petit="youngmi-450.webp")}</figure>
 <div><h2 class="titre" id="t-maison"><span class="etiquette">La maison</span> Madame Cho</h2><p>{E(PARCOURS)}</p><ul class="reperes">{rep}</ul>
 {bouton_insta()}</div>
</div><div class="deux atelier">
 <figure>{img("atelier", prof, "Étagères et matériel de pâtisserie dans l’atelier de Madame Cho", "(min-width: 860px) 40vw, 100vw")}<figcaption>Dans l’atelier de Madame Cho.</figcaption></figure>
 <div><h3 class="titre">Dans l’atelier</h3><p>C’est dans cet atelier que Madame Cho prépare ses créations. Ustensiles, moules et petits équipements composent son espace de travail au quotidien.</p><a class="bouton" href="{lien('carte/', prof)}">Découvrir les gâteaux</a></div>
</div></section>
<section class="bloc" id="commander" aria-labelledby="t-cmd"><div class="large">
 <h2 class="titre" id="t-cmd"><span class="etiquette">Commander</span> Comment ça marche</h2>
 <ol class="etapes">{etapes}</ol>
 <dl class="pratique">{prat}</dl>
 <div class="centre"><a class="bouton plein" href="#carte">Choisir les gâteaux</a></div>
</div></section>'''
    ld = {"@context": "https://schema.org", "@graph": [
        {"@type": "WebSite", "@id": SITE + "#site", "url": SITE, "name": "Madame Cho", "inLanguage": "fr-FR",
         "publisher": {"@id": SITE + "#maison"}},
        MAISON_LD]}
    return page("index.html", SEO_ACCUEIL[0], SEO_ACCUEIL[1], corps, prof, jsonld=ld, classe="accueil",
                image_alt="Quatre choux craquelin de Madame Cho posés sur une pierre claire")

# ---------- La carte (page seule, pour le QR code et Instagram) ----------
def carte():
    prof = 1
    corps = f'''<section class="bloc premier" aria-labelledby="t-carte"><div class="tete-page"><h1 class="etiquette centre" id="t-carte">La carte</h1>
 <p class="chapo centre">Six gâteaux préparés à la commande par Madame Cho, à retirer sur rendez-vous à Meudon.</p></div>
 <div class="grille">{carte_items(prof, "h2")}</div>
 <p class="petit centre">{E(TRACES)}</p>
 <p class="centre"><a class="bouton" href="{lien('', prof)}#anniversaires">Un gâteau d'anniversaire ?</a></p>
</section>'''
    ld = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Accueil", "item": SITE},
        {"@type": "ListItem", "position": 2, "name": "La carte", "item": SITE + "carte/"}]}
    return page("carte/index.html", SEO_CARTE[0], SEO_CARTE[1], corps, prof, jsonld=ld)

# ---------- Page d'un gâteau ----------
def fiche(p):
    prof = 2
    compo = ""
    if p["compo"]:
        compo = '<ul class="compo">' + "".join(f"<li>{E(x)}</li>" for x in p["compo"]) + "</ul>"
    if p["mode"] == "taille":
        opts = "".join(
            f'<label class="taille"><input type="radio" name="taille" value="{c}"{" checked" if i == 1 else ""}>'
            f'<span class="t-nom">{E(l)} · {E(pour)}</span><b>{euros(prix)}</b></label>'
            for i, (c, l, pour, prix) in enumerate(p["tailles"]))
        choix = f'''<fieldset class="choix"><legend>Taille</legend>{opts}</fieldset>
<div class="quantite-ligne"><span id="q-libelle">Quantité</span>
 <div class="pas" role="group" aria-labelledby="q-libelle"><button type="button" data-moins aria-label="Un de moins">−</button><output data-q aria-live="polite">1</output><button type="button" data-plus aria-label="Un de plus">+</button></div></div>'''
    else:
        lignes = "".join(
            f'''<div class="parfum" data-parfum="{c}"><div class="p-texte"><span class="p-nom" id="pn-{c}">{E(l)}</span>{f'<span class="p-desc">{E(d)}</span>' if d else ''}</div>
 <div class="pas" role="group" aria-labelledby="pn-{c}"><button type="button" data-moins aria-label="{p['pas']} de moins : {E(l)}">−</button><output data-q aria-live="polite">0</output><button type="button" data-plus aria-label="{p['pas']} de plus : {E(l)}">+</button></div></div>'''
            for c, l, d in p["parfums"])
        choix = f'''<fieldset class="choix parfums"><legend>{"Parfums" if p["id"] != "cookies" else "Combinaisons"}</legend>{lignes}</fieldset>
<p class="regle">{E(p["regle"])} <span class="prix-u">{euros(p["unite"])} la pièce · {E(p["taille"].lower())}.</span></p>
<p class="total" data-total aria-live="polite"></p>'''
    perso = PLAQUE if p["plaque"] else "Pas de personnalisation pour ce gâteau."
    details = [("Commander", p["delai_txt"].capitalize() + ", sur rendez-vous à Meudon."),
               ("Personnalisation", perso + (" Sur demande : goût, couleurs, ingrédients, recette (acompte de 30 %)." if p["plaque"] else "")),
               ("Allergènes", p["allergenes"] + " " + TRACES),
               ("Conservation", p["conservation"])]
    dl = "".join(f"<div><dt>{E(a)}</dt><dd>{E(b)}</dd></div>" for a, b in details)
    autres = [q for q in PRODUITS if q["id"] != p["id"]]
    autres_html = "".join(
        f'<a class="autre" href="{lien("carte/" + q["id"] + "/", prof)}">{img(q["photo"], prof, q["alt"], "(min-width: 860px) 18vw, 45vw")}<span>{E(q["nom"])}</span></a>'
        for q in autres)
    corps = f'''<nav class="fil" aria-label="Fil d'Ariane"><a href="{lien('carte/', prof)}">← La carte</a></nav>
<article class="fiche" data-produit="{p['id']}">
 <div class="fiche-photo">{img(p['photo'], prof, p['alt'], "(min-width: 860px) 50vw, 100vw", lazy=False)}</div>
 <div class="fiche-texte">
  <div class="etiquette">La carte</div>
  <h1 class="titre">{E(p['nom'])}</h1>
  <p class="phrase">{E(p['phrase'])}</p>
  {compo}
  <form class="ajout" data-ajout novalidate>
   {choix}
   <button class="bouton plein" type="submit" data-ajouter>Ajouter à ma demande</button>
   <p class="confirmation" data-confirmation role="status" aria-live="polite"></p>
  </form>
  <dl class="details">{dl}</dl>
  <div class="partage"><button type="button" class="lien-bouton" data-partager>Partager ce gâteau</button></div>
 </div>
</article>
<section class="bloc autres-bloc" aria-labelledby="t-autres"><h2 class="etiquette centre" id="t-autres">Les autres gâteaux</h2><div class="autres">{autres_html}</div></section>'''
    url = SITE + "carte/" + p["id"] + "/"
    vendeur = {"@type": "Bakery", "@id": SITE + "#maison", "name": "Madame Cho"}
    if p["mode"] == "taille":
        prix = [t[3] for t in p["tailles"]]
        offre = {"@type": "AggregateOffer", "priceCurrency": "EUR", "lowPrice": f"{min(prix)/100:.2f}", "highPrice": f"{max(prix)/100:.2f}",
                 "offerCount": len(prix), "url": url, "seller": vendeur}
    else:
        offre = {"@type": "Offer", "priceCurrency": "EUR", "price": f"{p['unite']/100:.2f}", "url": url, "seller": vendeur,
                 "eligibleQuantity": {"@type": "QuantitativeValue", "minValue": p["minimum"], "unitText": "pièces"}}
    ld = {"@context": "https://schema.org", "@graph": [
        {"@type": "Product", "@id": url + "#gateau", "name": p["nom"], "description": p["phrase"],
         "image": [SITE + "assets/photos/" + p["photo"] + "-grand.webp", SITE + "assets/photos/partage-" + p["id"] + ".jpg"],
         "brand": {"@type": "Brand", "name": "Madame Cho"}, "category": "Pâtisserie", "offers": offre},
        {"@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Accueil", "item": SITE},
            {"@type": "ListItem", "position": 2, "name": "La carte", "item": SITE + "carte/"},
            {"@type": "ListItem", "position": 3, "name": p["nom"], "item": url}]}]}
    titre_seo, desc = SEO_PRODUITS[p["id"]]
    return page(f"carte/{p['id']}/index.html", f"{titre_seo} · Madame Cho", desc, corps, prof, jsonld=ld, classe="page-fiche",
                image="partage-" + p["id"] + ".jpg", image_alt=p["alt"], titre_partage=f"{p['nom']} · Madame Cho")

# ---------- Ma demande ----------
def demande():
    prof = 1
    moments = "".join(f'<option value="{c}">{E(l)}</option>' for c, l in MOMENTS)
    corps = f'''<section class="bloc premier demande" aria-labelledby="t-dem">
<div class="etroit">
 <h1 class="titre" id="t-dem">Ma demande</h1>
 <p class="chapo">Sans engagement : Madame Cho vérifie ses disponibilités et vous répond sous 24 heures par une proposition écrite. Votre commande n'est réservée qu'après votre accord et sa confirmation.</p>

 <form id="formulaire" class="formulaire" method="post" action="envoyer.php" novalidate>
  <h2 class="sous-titre">Vos gâteaux</h2>
  <div class="panier" data-panier aria-live="polite"><p class="vide">Votre demande est vide pour l'instant. <a href="{lien('', prof)}#carte">Voir la carte</a></p></div>

  <h2 class="sous-titre" id="perso">Un gâteau personnalisé ?</h2>
  <p class="aide">Pour un anniversaire ou une envie particulière : nombre de personnes (4 à 12), saveurs, couleurs, ingrédients, décoration. Une personnalisation au-delà de la plaque demande un acompte de 30 %.</p>
  <label class="champ"><span>Votre envie <i>(facultatif)</i></span>
   <textarea name="perso" rows="4" maxlength="1200" data-perso aria-describedby="aide-sante"></textarea></label>
  <p class="aide" id="aide-sante">N'écrivez pas d'information médicale ici. Les allergènes sont indiqués sur la page de chaque gâteau ; pour une allergie ou un besoin particulier, appelez Madame Cho au <a href="tel:{TEL_LIEN}">{TEL}</a>.</p>

  <h2 class="sous-titre">Le retrait</h2>
  <p class="aide">Au {E(ADRESSE)}, sur rendez-vous. Madame Cho confirme l'heure dans sa réponse.</p>
  <div class="deux-champs">
   <label class="champ"><span>Date souhaitée</span><input type="date" name="date" required data-date aria-describedby="aide-date indispo-date"></label>
   <label class="champ"><span>Moment</span><select name="moment" required>{moments}</select></label>
  </div>
  <p class="aide" id="aide-date" data-date-aide></p>
  <p class="indispo" id="indispo-date" data-date-indispo role="status"></p>

  <h2 class="sous-titre" id="coordonnees">Vos coordonnées</h2>
  <div class="deux-champs">
   <label class="champ"><span>Prénom</span><input type="text" name="prenom" autocomplete="given-name" required maxlength="60"></label>
   <label class="champ"><span>Nom</span><input type="text" name="nom" autocomplete="family-name" required maxlength="80"></label>
  </div>
  <label class="champ"><span>Email</span><input type="email" name="email" autocomplete="email" required maxlength="120"></label>
  <label class="champ"><span>Téléphone <i>(facultatif)</i></span><input type="tel" name="telephone" autocomplete="tel" maxlength="30"></label>
  <label class="case" data-pro-case {'hidden' if not DEMANDE_PRO else ''}><input type="checkbox" name="pro" value="1" data-pro> Je fais cette demande pour une entreprise ou un restaurant</label>
  <label class="champ" data-societe hidden><span>Nom de l'entreprise</span><input type="text" name="societe" autocomplete="organization" maxlength="100"></label>
  <label class="champ" data-message-pro hidden><span>Votre besoin <i>(nombre de personnes, date, budget)</i></span><textarea name="besoin" rows="3" maxlength="1200"></textarea></label>
  <div class="piege" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>
  <input type="hidden" name="via" value="">

  <p class="notice">Vos coordonnées servent seulement à répondre à votre demande ; elles ne sont ni vendues ni utilisées pour de la publicité. <a href="{lien('confidentialite/', prof)}">Confidentialité</a> · <a href="{lien('conditions-de-commande/', prof)}">Conditions de commande</a></p>
  <p class="erreurs" id="erreurs" data-erreurs role="alert" tabindex="-1" hidden></p>
  <p class="aide" data-sans-stockage hidden>Votre navigateur ne garde pas la demande d'une page à l'autre. Si vous voulez d'autres gâteaux, ajoutez-les depuis leur page juste avant d'envoyer, ou écrivez-les dans « Votre envie ».</p>
  <button class="bouton plein envoyer" type="submit" data-envoyer>Envoyer ma demande, sans engagement</button>
  <p class="aide centre">Un souci ? Appelez Madame Cho au <a href="tel:{TEL_LIEN}">{TEL}</a> ou écrivez à <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
 </form>

 <div class="recu" data-recu hidden tabindex="-1">
  <div class="etiquette">Demande envoyée</div>
  <h2 class="titre">Merci, Madame Cho vous répond sous 24 heures</h2>
  <p>Votre demande n'est pas encore une commande : Madame Cho vous envoie une proposition écrite (prix, jour et heure du retrait), que vous acceptez par écrit.</p>
  <p>Sa réponse viendra de <b>{EMAIL}</b> : ajoutez cette adresse à vos contacts, et regardez dans vos courriers indésirables si vous ne la voyez pas sous 24 heures.</p>
  <p class="ref">Référence : <b data-ref></b></p>
  <pre class="recap" data-recap></pre>
  <p class="aide">Gardez ce récapitulatif. <button type="button" class="lien-bouton" data-copier>Copier le récapitulatif</button></p>
  <a class="bouton" href="{lien('', prof)}">Retour à l'accueil</a>
 </div>
</div>
</section>'''
    return page("ma-demande/index.html", "Ma demande · Madame Cho", "Envoyer une demande sans engagement à Madame Cho : gâteaux, date de retrait à Meudon, coordonnées.",
                corps, prof, classe="page-demande", indexer=False)

# ---------- Pages légales ----------
def legale(slug, titre, desc, sections, maj="3 novembre 2026"):
    prof = 1
    s = ""
    for h, ps in sections:
        s += f'<h2 class="sous-titre">{E(h)}</h2>' + "".join(p if p.startswith("<") else f"<p>{p}</p>" for p in ps)
    corps = f'''<section class="bloc premier" aria-labelledby="t-leg"><div class="etroit texte-long">
 <h1 class="titre" id="t-leg">{E(titre)}</h1>{s}
 <p class="petit">Mise à jour : {maj}.</p></div></section>'''
    return page(f"{slug}/index.html", f"{titre} · Madame Cho", desc, corps, prof)

# ---------- Retrait à Meudon (référencement local, 07/11/2026) ----------
# Une seule page pour les villes voisines, jamais une page par ville au texte recopié (Google y voit du spam)
def retrait():
    prof = 1
    voisines = VILLES[1:]
    liste = ", ".join(voisines[:-1]) + " et " + voisines[-1]
    itineraire = "https://www.google.com/maps/dir/?api=1&destination=" + "4+avenue+du+Ch%C3%A2teau+92190+Meudon"
    corps = f'''<section class="bloc premier" aria-labelledby="t-ret"><div class="etroit texte-long">
 <h1 class="titre" id="t-ret">Retrait de vos gâteaux à Meudon</h1>
 <p>Madame Cho ne livre pas : chaque commande se retire sur rendez-vous, à l'heure convenue, au <b>{E(ADRESSE)}</b>, tous les jours de 9 h à 19 h (pas de retrait le 24 décembre).</p>
 <h2 class="sous-titre">Pour les villes voisines</h2>
 <p>Meudon est à quelques minutes de {E(liste)}. Pour un gâteau d'anniversaire, un entremets ou des choux, vous choisissez le jour et l'heure du retrait dans votre demande, Madame Cho vous confirme le rendez-vous.</p>
 <p><a href="{E(itineraire)}" rel="noopener" target="_blank">Préparer l'itinéraire jusqu'au 4 avenue du Château</a></p>
 <h2 class="sous-titre">Comment commander</h2>
 <p>Choisissez vos gâteaux sur <a href="{lien('carte/', prof)}">la carte</a> et envoyez votre demande, sans engagement. Madame Cho vous répond sous 24 heures avec une proposition écrite : le prix, le jour et l'heure du retrait. Pour une question : <a href="tel:{TEL_LIEN}">{TEL}</a> ou <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
 <p class="centre"><a class="bouton plein" href="{lien('ma-demande/', prof)}">Faire ma demande</a></p></div></section>'''
    ld = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Accueil", "item": SITE},
        {"@type": "ListItem", "position": 2, "name": "Retrait à Meudon", "item": SITE + "retrait/"}]}
    return page("retrait/index.html", SEO_RETRAIT[0], SEO_RETRAIT[1], corps, prof, jsonld=ld)

# Médiateur : CM2C choisi par Olivier ; texte à remplacer par ses coordonnées et le numéro d'adhérent dès la confirmation de l'adhésion
MEDIATION = ("En cas de litige non résolu avec Madame Cho, le client consommateur peut recourir gratuitement à un médiateur de la consommation. "
             "L'adhésion de Madame Cho au CM2C est en cours : ses coordonnées seront indiquées ici dès qu'elle sera confirmée.")
# Encadré imposé par l'article D211-2 du code de la consommation (version en vigueur depuis le 01/10/2022), audit C01.
# Texte comparé phrase par phrase le 03/11/2026 ; à revérifier sur Légifrance avant la mise en ligne à la racine.
GARANTIES = '<div class="encadre" role="note" aria-label="Garanties légales"><p>Le consommateur dispose d&#x27;un délai de deux ans à compter de la délivrance du bien pour obtenir la mise en œuvre de la garantie légale de conformité en cas d&#x27;apparition d&#x27;un défaut de conformité. Durant ce délai, le consommateur n&#x27;est tenu d&#x27;établir que l&#x27;existence du défaut de conformité et non la date d&#x27;apparition de celui-ci.</p><p>Lorsque le contrat de vente du bien prévoit la fourniture d&#x27;un contenu numérique ou d&#x27;un service numérique de manière continue pendant une durée supérieure à deux ans, la garantie légale est applicable à ce contenu numérique ou ce service numérique tout au long de la période de fourniture prévue. Durant ce délai, le consommateur n&#x27;est tenu d&#x27;établir que l&#x27;existence du défaut de conformité affectant le contenu numérique ou le service numérique et non la date d&#x27;apparition de celui-ci.</p><p>La garantie légale de conformité emporte obligation pour le professionnel, le cas échéant, de fournir toutes les mises à jour nécessaires au maintien de la conformité du bien.</p><p>La garantie légale de conformité donne au consommateur droit à la réparation ou au remplacement du bien dans un délai de trente jours suivant sa demande, sans frais et sans inconvénient majeur pour lui.</p><p>Si le bien est réparé dans le cadre de la garantie légale de conformité, le consommateur bénéficie d&#x27;une extension de six mois de la garantie initiale.</p><p>Si le consommateur demande la réparation du bien, mais que le vendeur impose le remplacement, la garantie légale de conformité est renouvelée pour une période de deux ans à compter de la date de remplacement du bien.</p><p>Le consommateur peut obtenir une réduction du prix d&#x27;achat en conservant le bien ou mettre fin au contrat en se faisant rembourser intégralement contre restitution du bien, si :<br>1° Le professionnel refuse de réparer ou de remplacer le bien ;<br>2° La réparation ou le remplacement du bien intervient après un délai de trente jours ;<br>3° La réparation ou le remplacement du bien occasionne un inconvénient majeur pour le consommateur, notamment lorsque le consommateur supporte définitivement les frais de reprise ou d&#x27;enlèvement du bien non conforme, ou s&#x27;il supporte les frais d&#x27;installation du bien réparé ou de remplacement ;<br>4° La non-conformité du bien persiste en dépit de la tentative de mise en conformité du vendeur restée infructueuse.</p><p>Le consommateur a également droit à une réduction du prix du bien ou à la résolution du contrat lorsque le défaut de conformité est si grave qu&#x27;il justifie que la réduction du prix ou la résolution du contrat soit immédiate. Le consommateur n&#x27;est alors pas tenu de demander la réparation ou le remplacement du bien au préalable.</p><p>Le consommateur n&#x27;a pas droit à la résolution de la vente si le défaut de conformité est mineur.</p><p>Toute période d&#x27;immobilisation du bien en vue de sa réparation ou de son remplacement suspend la garantie qui restait à courir jusqu&#x27;à la délivrance du bien remis en état.</p><p>Les droits mentionnés ci-dessus résultent de l&#x27;application des articles L. 217-1 à L. 217-32 du code de la consommation.</p><p>Le vendeur qui fait obstacle de mauvaise foi à la mise en œuvre de la garantie légale de conformité encourt une amende civile d&#x27;un montant maximal de 300 000 euros, qui peut être porté jusqu&#x27;à 10 % du chiffre d&#x27;affaires moyen annuel (article L. 241-5 du code de la consommation).</p><p>Le consommateur bénéficie également de la garantie légale des vices cachés en application des articles 1641 à 1649 du code civil, pendant une durée de deux ans à compter de la découverte du défaut. Cette garantie donne droit à une réduction de prix si le bien est conservé ou à un remboursement intégral contre restitution du bien.</p></div>'

MENTIONS = [
 ("Éditeur du site", ["MADAME CHO, société par actions simplifiée unipersonnelle au capital de 1 000 €, immatriculée au RCS de Nanterre sous le numéro 941 761 918. TVA intracommunautaire : FR37941761918.",
   f"Siège : {E(ADRESSE)}. Téléphone : <a href=\"tel:{TEL_LIEN}\">{TEL}</a>. Email : <a href=\"mailto:{EMAIL}\">{EMAIL}</a>.",
   "Directrice de la publication : Youngmi Cho, présidente."]),
 ("Hébergeur", ["OVH SAS, 2 rue Kellermann, 59100 Roubaix, France. Téléphone : 1007."]),
 ("Médiation de la consommation", [MEDIATION]),
 ("Photos et textes", ["Les photos et les textes du site appartiennent à Madame Cho ; ils ne peuvent pas être repris sans son accord.",
   "Le logo Madame Cho utilise la police Jost, sous licence SIL Open Font License."]),
 ("Cookies", [("Ce site ne dépose aucun cookie. Il compte ses visites de façon anonyme, sans cookie, avec Matomo installé chez son hébergeur ; vous pouvez refuser cette mesure sur la page <a href=\"../confidentialite/#mesure\">Confidentialité</a>." if MESURE else "Ce site ne dépose aucun cookie et ne mesure pas l'audience.") + " Votre demande en cours (gâteaux, tailles et quantités) est gardée dans votre navigateur, pour ne pas la perdre : après 7 jours, le site ne la reprend plus et l'efface à votre visite suivante. Vous pouvez la vider à tout moment en retirant les gâteaux."]),
]
CONFID = [
 ("Qui traite vos données", [f"MADAME CHO, {E(ADRESSE)}, <a href=\"mailto:{EMAIL}\">{EMAIL}</a>."]),
 ("Pourquoi", ["Seulement pour répondre à votre demande, vous faire une proposition, préparer et retirer votre commande. Base légale : les mesures prises à votre demande avant un contrat, puis le contrat lui-même. Aucune publicité, aucune revente."]),
 ("Quelles données", ["Votre nom, votre email, votre téléphone si vous le donnez, la date de retrait souhaitée et le contenu de votre demande, puis le suivi de votre commande (étapes, montant, paiement, notes de préparation). N'écrivez pas d'information médicale dans le formulaire : les allergènes sont indiqués sur la page de chaque gâteau, et pour une allergie ou un besoin particulier, Madame Cho en parle avec vous au téléphone."]),
 ("Qui les reçoit", ["Madame Cho. Prestataires techniques : OVH (hébergement du site, envoi du formulaire et outil de suivi des commandes, en France) et Google (messagerie).",
   "Votre demande est enregistrée dans l'outil de suivi des commandes de Madame Cho, hébergé avec le site chez OVH en France et réservé à Madame Cho.",
   "Pour classer les emails, suivre les commandes et préparer les réponses, Madame Cho se fait aider par des assistants d'intelligence artificielle : Claude (Anthropic) et ChatGPT (OpenAI). L'outil de suivi des commandes a son propre assistant (Claude), qui lit les demandes et les commandes pour répondre aux questions de Madame Cho et lui préparer des mises à jour ; quand elle lui parle à voix haute, sa voix est transcrite par le service vocal de son navigateur (Google ou Apple). Ces assistants ne décident de rien et n'envoient aucun email : Madame Cho relit, confirme et répond elle-même. Aucun autre destinataire.",
   "Google, Apple, Anthropic et OpenAI peuvent traiter des données hors de l'Union européenne, notamment aux États-Unis, avec les garanties prévues par leurs conditions (cadre de protection des données UE–États-Unis ou clauses contractuelles types de la Commission européenne)."]),
 ("Sécurité du formulaire", ["Pour bloquer les envois abusifs, le site garde une empreinte pseudonymisée de votre adresse IP (calculée avec une clé secrète ; l'adresse elle-même n'est jamais enregistrée) et l'heure de vos envois. Elles sont effacées automatiquement 48 heures au plus après votre dernier envoi. Base légale : l'intérêt légitime de Madame Cho à protéger son formulaire. L'hébergeur OVH tient aussi des journaux techniques de connexion, comme la loi l'y oblige."]),
 ("Combien de temps", ["Une demande sans suite est supprimée au plus tard 12 mois après le dernier échange. Les informations d'un client sont effacées 3 ans après sa dernière commande ou son dernier échange avec Madame Cho. Les factures sont conservées 10 ans, comme la loi l'exige. Les échanges avec l'assistant de l'outil de suivi des commandes sont effacés après 6 mois."]),
 ("Vos droits", [f"Vous pouvez demander l'accès à vos données, leur correction, leur effacement, leur portabilité, la limitation de leur usage ou vous y opposer, en écrivant à <a href=\"mailto:{EMAIL}\">{EMAIL}</a>. Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la CNIL (cnil.fr)."]),
 ("Navigateur", [("Le site ne dépose aucun cookie." if MESURE else "Le site ne dépose aucun cookie et ne mesure pas l'audience.") + " Votre demande en cours (gâteaux, tailles et quantités, sans nom ni coordonnées) est gardée dans votre navigateur : après 7 jours, le site ne la reprend plus et l'efface à votre visite suivante. Quand vous choisissez la date et le moment du retrait, la page demande au site si Madame Cho est disponible : seuls la date et le moment sont envoyés, et rien n'est enregistré. Si vous arrivez par le QR code d'un emballage, les pages que vous ouvrez ensuite gardent cette indication dans leur adresse, et votre demande la signale à Madame Cho ; rien d'autre n'est noté."]),
] + ([("Mesure d'audience", [
   "Pour savoir quelles pages sont lues et améliorer le site, Madame Cho compte les visites avec Matomo, un outil installé sur son propre hébergement chez OVH, en France. "
   "Aucun cookie n'est déposé, l'adresse IP est tronquée avant d'être enregistrée, les données ne sont ni croisées avec d'autres ni transmises à quiconque, et elles servent seulement à produire des statistiques anonymes. "
   "Elles sont conservées 13 mois au plus. Cette mesure est exemptée de consentement, selon les recommandations de la CNIL ; si votre navigateur demande de ne pas être suivi, le site n'est pas mesuré.",
   '<p class="choix-mesure" id="mesure"><label><input type="checkbox" id="mesure-choix" checked> Compter mes visites de façon anonyme</label> <span id="mesure-etat" class="petit" aria-live="polite"></span></p>'])] if MESURE else [])
CONDITIONS = [
 ("Madame Cho", [f"MADAME CHO, SASU au capital de 1 000 €, RCS Nanterre 941 761 918, {E(ADRESSE)}. Téléphone : <a href=\"tel:{TEL_LIEN}\">{TEL}</a>. Email : <a href=\"mailto:{EMAIL}\">{EMAIL}</a>."]),
 ("1. La demande", ["Le formulaire du site envoie une demande sans engagement. Elle ne vaut ni commande ni réservation d'un créneau."]),
 ("2. La proposition et l'acceptation", ["Madame Cho répond sous 24 heures par une proposition écrite : gâteaux, tailles, personnalisation, prix TTC total, jour et heure du retrait, acompte éventuel. Le client l'accepte par écrit (réponse à l'email ou message) ; la commande est conclue quand Madame Cho la confirme par écrit."]),
 ("3. Les prix", ["Les prix sont indiqués en euros TTC. Le prix de la carte est celui de la proposition ; une personnalisation au-delà de la plaque en chocolat (comprise) est chiffrée dans la proposition."]),
 ("4. L'acompte", ["Pour un gâteau personnalisé au-delà de la plaque, un acompte de 30 % du prix est demandé à l'acceptation de la proposition ; il est déduit du prix au retrait."]),
 ("5. Le retrait", [f"Pas de livraison : la commande est retirée au {E(ADRESSE)}, sur rendez-vous, à l'heure convenue, tous les jours de 9 h à 19 h (pas de retrait le 24 décembre). Le client vérifie sa commande au retrait."]),
 ("6. Le paiement", ["Au retrait, par carte bancaire, virement ou espèces, déduction faite de l'acompte éventuel."]),
 # Délai d'annulation fixé par Youngmi le 04/11/2026 (réponse au mail du 03/11) : 24 heures avant le retrait
 ("7. Annulation", ["Le client peut annuler sans frais, par écrit, une commande confirmée jusqu'à 24 heures avant le retrait. Passé ce délai, l'acompte versé, s'il y en a un, reste acquis à Madame Cho."]),
 ("8. Droit de rétractation", ["Les gâteaux de la carte sont des denrées fraîches, à consommer dans les 3 jours : le droit de rétractation ne s'applique pas à eux, car ils sont susceptibles de se détériorer rapidement (code de la consommation, article L221-28, 4°). Il ne s'applique pas non plus à un gâteau confectionné selon les choix du client, comme un gâteau personnalisé (même article, 3°)."]),
 ("9. Allergènes", ["Les allergènes de chaque gâteau sont indiqués sur sa page et rappelés au retrait. Tous les gâteaux sont préparés dans un atelier qui utilise aussi des fruits à coque et des arachides."]),
 ("10. Réclamations", [f"Une réclamation s'adresse à <a href=\"mailto:{EMAIL}\">{EMAIL}</a> ou au {TEL}, de préférence le jour du retrait."]),
 ("11. Garanties légales", ["Le client consommateur bénéficie des garanties légales rappelées ci-dessous.", GARANTIES]),
 ("12. Médiation", [MEDIATION]),
]

# ---------- Page introuvable (404) ----------
def introuvable():
    prof = -1
    corps = f'''<section class="bloc premier" aria-labelledby="t-404"><div class="etroit">
 <h1 class="titre" id="t-404"><span class="etiquette">Page introuvable</span> Cette page n'existe pas, ou plus.</h1>
 <p>Les gâteaux de Madame Cho sont sur la carte. Pour une question, appelez Madame Cho au <a href="tel:{TEL_LIEN}">{TEL}</a> ou écrivez à <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
 <p class="liens-404"><a class="bouton plein" href="{lien('carte/', prof)}">Voir la carte</a> <a class="bouton" href="{lien('', prof)}">Retour à l'accueil</a></p>
</div></section>'''
    return page("404.html", "Page introuvable · Madame Cho", "Cette page n'existe pas sur le site de Madame Cho.", corps, prof, indexer=False)

# Madame Cho dans les données structurées (accueil ; les fiches y renvoient par son @id)
MAISON_LD = {"@type": "Bakery", "@id": SITE + "#maison", "name": "Madame Cho", "url": SITE,
             "description": INTRO,
             "image": [SITE + "assets/photos/partage.jpg", SITE + "assets/photos/ouverture-1600.webp"],
             "logo": SITE + "assets/icone-512.png",
             "telephone": "+33 6 61 31 17 79", "email": EMAIL,
             "address": {"@type": "PostalAddress", "streetAddress": "4 avenue du Château", "postalCode": "92190",
                         "addressLocality": "Meudon", "addressRegion": "Hauts-de-Seine", "addressCountry": "FR"},
             "areaServed": [{"@type": "City", "name": v} for v in VILLES],
             "openingHoursSpecification": [{"@type": "OpeningHoursSpecification",
                                            "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
                                            "opens": "09:00", "closes": "19:00"}],
             "hasMenu": SITE + "carte/", "servesCuisine": "Pâtisserie", "priceRange": "€€",
             "paymentAccepted": "Carte bancaire, virement, espèces", "currenciesAccepted": "EUR",
             "founder": {"@type": "Person", "name": "Youngmi Cho", "jobTitle": "Pâtissière",
                         "alumniOf": [{"@type": "CollegeOrUniversity", "name": "Université nationale de Séoul"},
                                      {"@type": "EducationalOrganization", "name": "École Ducasse"}]},
             "sameAs": [INSTAGRAM]}

# Réglages du serveur (Apache chez OVH) : page 404, compression, cache, en-têtes de sécurité.
# Chaque bloc est protégé par IfModule : un module absent ne casse pas le site.
def htaccess():
    l = ["# Réglages du serveur pour le site Madame Cho, générés par gen.py (passe SEO du 03/11/2026) : ne pas modifier à la main."]
    if EN_LIGNE:
        l += ["", "# Toujours en HTTPS, sur l'adresse principale madamecho.com (sans www)", "RewriteEngine On",
              "RewriteCond %{SERVER_PORT} 80 [OR]", "RewriteCond %{HTTP_HOST} ^www\\. [NC]",
              "RewriteRule ^ https://madamecho.com%{REQUEST_URI} [L,R=301]", "",
              "# Ne servir ni les fichiers internes du dépôt ni l'historique Git", "RedirectMatch 404 /\\.git",
              '<FilesMatch "^(README\\.md|\\.gitignore)$">', "  Require all denied", "</FilesMatch>", "Options -Indexes", "",
              "# Fichiers et dossiers cachés (limites du formulaire, clés…) jamais servis, où qu'ils soient, sauf .well-known",
              "RewriteRule (^|/)\\.(?!well-known(/|$)) - [R=404,L]", "",
              "# Spécifications du projet (Spec Kit, dossier specs/ du dépôt) : documents de travail, jamais servis par le site",
              "RewriteRule ^specs(/|$) - [R=404,L]", "",
              "# Anciennes pages de travail, retirées à la mise en ligne du 03/11/2026 :",
              "# la version d'essai renvoie vers la même page du site, l'atelier du logo et l'atelier du site n'existent plus (410)",
              "RewriteRule ^atelier-site/site/(.*)$ /$1 [R=301,L]",
              "RewriteRule ^atelier-(site|logo)(/|$) - [G,L]", "",
              "# QR code imprimé sur les emballages (décision d'Olivier du 07/11/2026) : adresse fixe madamecho.com/qr, redirection temporaire (302)",
              "# pour changer un jour la destination sans réimprimer ; Matomo compte chaque scan dans la campagne « qr-code », mot-clé « packaging »",
              "RewriteRule ^qr/?$ /carte/?mtm_campaign=qr-code&mtm_kwd=packaging [R=302,L,NC,NE]", "",
              "# Retrait dans l'agenda du client (email de confirmation, demande d'Olivier du 07/11/2026) : adresses courtes madamecho.com/rdv/…,",
              "# servies par la gestion (?p=rdv, jeton secret) sans changer l'adresse affichée",
              "RewriteRule ^rdv/([0-9]+-[0-9a-f]{24})/google/?$ /gestion/index.php?p=rdv&c=$1&vers=google [L]",
              "RewriteRule ^rdv/([0-9]+-[0-9a-f]{24})/?$ /gestion/index.php?p=rdv&c=$1 [L]"]
    if ESSAI:
        l += ["", "# Version d'essai : le dossier atelier-site/ refuse les .js (scripts de feuille) ; ici, les scripts du site",
              "# (panier et formulaire) doivent être servis. Le reste des règles d'atelier-site/ s'applique.",
              '<FilesMatch "\\.js$">', "  Require all granted", "</FilesMatch>"]
    l += ["", "# Page introuvable (et anciennes pages retirées)", f"ErrorDocument 404 {BASE}404.html"] + ([f"ErrorDocument 410 {BASE}404.html"] if EN_LIGNE else []) + [
          "", "# Types des fichiers", "AddType image/webp .webp", "AddType font/woff2 .woff2", "AddType application/manifest+json .webmanifest",
          "", "# Compression des textes",
          "<IfModule mod_deflate.c>", "  <IfModule mod_filter.c>",
          "    AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/xml application/javascript text/javascript application/json image/svg+xml application/manifest+json",
          "  </IfModule>", "</IfModule>",
          "", "# Cache du navigateur : les feuilles de style et scripts portent leur version (?v=…), les pages sont toujours relues",
          "<IfModule mod_expires.c>", "  ExpiresActive On",
          '  ExpiresByType text/html "access plus 0 seconds"',
          '  ExpiresByType text/css "access plus 1 year"',
          '  ExpiresByType application/javascript "access plus 1 year"',
          '  ExpiresByType text/javascript "access plus 1 year"',
          '  ExpiresByType font/woff2 "access plus 1 year"',
          '  ExpiresByType image/webp "access plus 1 month"',
          '  ExpiresByType image/jpeg "access plus 1 month"',
          '  ExpiresByType image/png "access plus 1 month"',
          '  ExpiresByType image/svg+xml "access plus 1 month"',
          '  ExpiresByType image/x-icon "access plus 1 month"',
          '  ExpiresByType application/manifest+json "access plus 1 week"',
          "</IfModule>",
          "", "# En-têtes de sécurité (audit du site, C09) ; la politique de contenu n'autorise que les fichiers du site",
          "<IfModule mod_headers.c>",
          '  Header always set X-Content-Type-Options "nosniff"',
          '  Header always set Referrer-Policy "strict-origin-when-cross-origin"',
          '  Header always set X-Frame-Options "SAMEORIGIN"',
          '  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"',
          f'  Header always set Content-Security-Policy "{CSP}"',
          "</IfModule>", ""]
    return "\n".join(l)

_M = (" " + MATOMO.rstrip("/")) if MESURE else ""   # Matomo autorisé seulement quand la mesure est active
CSP = (f"default-src 'self'; img-src 'self' data:{_M}; style-src 'self'; script-src 'self'{_M}; font-src 'self'; connect-src 'self'{_M}; "
       "manifest-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'")

# Images de partage (1200 x 630) de chaque gâteau, recadrées au centre de sa photo carrée
def images_partage():
    try:
        from PIL import Image
    except ImportError:
        print("PIL absent : images de partage non refaites"); return
    for p in PRODUITS:
        cible = os.path.join(OUT, "assets/photos", f"partage-{p['id']}.jpg")
        source = os.path.join(OUT, "assets/photos", f"{p['photo']}-grand.webp")
        if os.path.exists(cible) and os.path.getmtime(cible) >= os.path.getmtime(source):
            continue
        im = Image.open(source).convert("RGB")
        w, h = im.size
        haut = round(w * 630 / 1200)
        y = (h - haut) // 2
        im.crop((0, y, w, y + haut)).resize((1200, 630), Image.LANCZOS).save(cible, "JPEG", quality=82, optimize=True, progressive=True)

# ---------- Catalogue (navigateur et serveur) ----------
def catalogue():
    prods = {}
    for p in PRODUITS:
        d = {"nom": p["nom"], "url": "carte/" + p["id"] + "/", "photo": p["photo"], "mode": p["mode"], "delai": p["delai"], "plaque": p["plaque"]}
        if p["mode"] == "taille":
            d["tailles"] = {c: {"libelle": f"{l}, {pour}", "prix": prix} for c, l, pour, prix in p["tailles"]}
        else:
            d["parfums"] = {c: {"libelle": l} for c, l, _ in p["parfums"]}
            d.update(unite=p["unite"], minimum=p["minimum"], pas=p["pas"])
        prods[p["id"]] = d
    return {"version": VERSION, "produits": prods, "moments": dict(MOMENTS), "fermes": JOURS_FERMES, "delaiPerso": 2, "delaiPro": DELAI_PRO}

def catalogue_gestion():
    """Carte pour l'espace de gestion : la même, plus les textes publics des fiches (comparaison des allergènes, R-39)."""
    cat = catalogue()
    for p in PRODUITS:
        cat["produits"][p["id"]].update(phrase=p["phrase"], compo=list(p["compo"]), allergenes=p["allergenes"], conservation=p["conservation"],
                                        delai_txt=p["delai_txt"])
    cat["traces"] = TRACES
    return cat

def php_val(v):
    if isinstance(v, bool): return "true" if v else "false"
    if isinstance(v, int): return str(v)
    if isinstance(v, str): return "'" + v.replace("\\", "\\\\").replace("'", "\\'") + "'"
    if isinstance(v, list): return "[" + ", ".join(php_val(x) for x in v) + "]"
    if isinstance(v, dict): return "[" + ", ".join(f"{php_val(k)} => {php_val(x)}" for k, x in v.items()) + "]"
    raise TypeError(v)

# ---------- Écriture ----------
import re as _re
_BLOCS = _re.compile(r"(<script\b.*?</script>|<style\b.*?</style>|<[^>]*>)", _re.S | _re.I)
def typo(html_txt):
    """Espace insécable avant : ; ? ! » et après « dans le texte visible, pour qu'un « : » ne commence jamais une ligne."""
    morceaux = _BLOCS.split(html_txt)
    for i in range(0, len(morceaux), 2):
        t = morceaux[i]
        t = _re.sub(r" ([:;?!»])", "\u00a0\\1", t)
        t = t.replace("« ", "«\u00a0")
        morceaux[i] = t
    return "".join(morceaux)

def ecrire(chemin, texte):
    if chemin.endswith(".html"):
        texte = typo(texte)
    f = os.path.join(OUT, chemin)
    os.makedirs(os.path.dirname(f), exist_ok=True)
    with open(f, "w", encoding="utf-8") as h:
        h.write(texte)

ecrire("index.html", accueil())
if APERCU:
    ecrire("accueil/index.html", accueil(1))
ecrire("carte/index.html", carte())
for p in PRODUITS:
    ecrire(f"carte/{p['id']}/index.html", fiche(p))
ecrire("ma-demande/index.html", demande())
ecrire("retrait/index.html", retrait())
if not APERCU:
    ecrire("404.html", introuvable())
    ecrire(".htaccess", htaccess())
images_partage()
ecrire("mentions-legales/index.html", legale("mentions-legales", "Mentions légales", "Mentions légales du site de Madame Cho, pâtisserie à Meudon.", MENTIONS))
ecrire("confidentialite/index.html", legale("confidentialite", "Confidentialité", "Comment Madame Cho utilise les données envoyées avec une demande.", CONFID, maj="7 novembre 2026"))
ecrire("conditions-de-commande/index.html", legale("conditions-de-commande", "Conditions de commande", "Conditions de commande de Madame Cho : demande, proposition, acompte, retrait, paiement.", CONDITIONS, maj="4 novembre 2026"))
cat = catalogue()
ecrire("assets/catalogue.js", "/* Carte de Madame Cho, générée par gen.py : ne pas modifier à la main. */\nwindow.MC_CATALOGUE=" + json.dumps(cat, ensure_ascii=False) + ";\n")
ecrire("ma-demande/catalogue.php", "<?php\n// Carte de Madame Cho, générée par gen.py : ne pas modifier à la main.\nreturn " + php_val(cat) + ";\n")
for f in ("site.css", "site.js") + (("mesure.js",) if MESURE else ()):
    shutil.copy(os.path.join(ICI, f), os.path.join(OUT, "assets", f))
for f in ("envoyer.php", "dkim.php", "menage.php", "cle-dkim.php"):
    shutil.copy(os.path.join(ICI, f), os.path.join(OUT, "ma-demande", f))
ecrire("ma-demande/.htaccess", "# Le dossier des limites d'envoi, la carte, la signature, le ménage et la page de préparation DKIM ne sont pas servis\n"
       "# (cle-dkim.php a servi une fois, le 03/11/2026 ; pour la rouvrir, retirer « cle-dkim » de la liste)\nRedirectMatch 404 /ma-demande/\\.limites\n"
       "<FilesMatch \"^(catalogue|dkim|menage|cle-dkim)\\.php$|\\.pem$\">\n  Require all denied\n</FilesMatch>\n")
if not APERCU:
    # Espace de gestion (clients, commandes, produits), spécification du 06/11/2026 : PHP et SQLite chez OVH, réservé à Madame Cho.
    # Les données (base, comptes) vivent sur le serveur hors du dossier du site, jamais dans ce dépôt (R-08).
    src = os.path.join(ICI, "gestion")
    for racine, _, fichiers in os.walk(src):
        rel = os.path.relpath(racine, src)
        for f in fichiers:
            if f.endswith((".php", ".css", ".js", ".webmanifest", ".svg", ".png", ".ico")) and f != "catalogue.php":   # .svg, .png, .ico : icônes de la gestion (07/11/2026)
                os.makedirs(os.path.join(OUT, "gestion", rel), exist_ok=True)
                shutil.copy(os.path.join(racine, f), os.path.join(OUT, "gestion", rel, f))
    ecrire("gestion/inc/catalogue.php", "<?php\n// Carte de Madame Cho pour l'espace de gestion, générée par gen.py : ne pas modifier à la main.\nreturn " + php_val(catalogue_gestion()) + ";\n")
    ecrire("gestion/.htaccess", "# Espace de gestion de Madame Cho : réservé (connexion), jamais indexé ; seule la page index.php est servie, avec son habillage\n"
           "DirectoryIndex index.php\n<IfModule mod_headers.c>\n  Header always set X-Robots-Tag \"noindex, nofollow, noarchive\"\n"
           "  # Le micro, ici seulement : le mode vocal de l'assistant (demande d'Olivier, 07/11/2026) ; le reste du site le refuse toujours\n"
           "  Header always set Permissions-Policy \"camera=(), microphone=(self), geolocation=(), payment=()\"\n</IfModule>\n"
           "<FilesMatch \"\\.php$\">\n  Require all denied\n</FilesMatch>\n<Files \"index.php\">\n  Require all granted\n</Files>\n")
    ecrire("gestion/inc/.htaccess", "# Code et carte de l'espace de gestion : jamais servis\nRequire all denied\n")
os.makedirs(os.path.join(OUT, "assets/polices"), exist_ok=True)
for f in ("jost-latin-400-normal.woff2", "jost-latin-500-normal.woff2", "OFL-Jost.txt"):
    shutil.copy(os.path.join(COMMUN, "polices", f), os.path.join(OUT, "assets/polices", f))
shutil.copy(os.path.join(COMMUN, "logo-chocolat.svg"), os.path.join(OUT, "assets/logo-chocolat.svg"))
# Logo de la signature email de contact@madamecho.com (PNG sur fond blanc : Gmail n'affiche pas le SVG), 06/11/2026
shutil.copy(os.path.join(COMMUN, "logo-email.png"), os.path.join(OUT, "assets/logo-email.png"))
# Favicon B choisi par Olivier le 03/11/2026 : le 조 du logo, ivoire sur chocolat (gen_fav.py)
for f in ("favicon.ico", "favicon.svg", "apple-touch-icon.png", "icone-192.png", "icone-512.png", "site.webmanifest"):
    shutil.copy(os.path.join(ICI, "favicon", f), os.path.join(OUT, "assets", f))
# Favicon pour Google (07/11/2026) : aussi à la racine, à une adresse qui ne change jamais (sans ?v=),
# sinon chaque publication donne à Google une nouvelle adresse d'icône à revisiter
shutil.copy(os.path.join(ICI, "favicon", "favicon.ico"), os.path.join(OUT, "favicon.ico"))
if EN_LIGNE:
    # Plan du site : les pages à trouver dans les résultats (Ma demande et la 404 n'y sont pas)
    urls = ["", "carte/"] + ["carte/" + p["id"] + "/" for p in PRODUITS] + ["retrait/", "conditions-de-commande/", "mentions-legales/", "confidentialite/"]
    ecrire("sitemap.xml", '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
           "".join(f"<url><loc>{SITE}{u}</loc><lastmod>{MAJ}</lastmod></url>\n" for u in urls) + "</urlset>\n")
    # Tout est permis aux robots : les pages à ne pas indexer (Ma demande, 404) portent leur « noindex » (il faut pouvoir le lire)
    ecrire("robots.txt", f"User-agent: *\nAllow: /\n\nSitemap: {SITE}sitemap.xml\n")
print("ok", OUT, "aperçu" if APERCU else "production")
