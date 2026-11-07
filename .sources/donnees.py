# Données du site Madame Cho v2 : textes et carte, tirés des réponses de Youngmi
# (réponses du 04/11/2026 : annulation 24 h avant le retrait, soja (lécithine) partout où il y a du chocolat, photos choux et anniversaire, lien d'Approach)
# (séance 1 du 03/11/2026 : carte et fiches ; séance 2 : textes, retrait, paiement ;
#  séance 3 : maquette « Galerie », acompte confirmé, texte Anniversaires réécrit par elle).
# Prix en centimes, TTC. Un gâteau dont une information manque n'est pas proposé (spécification, rubrique 5).

TRACES = "Tous nos gâteaux sont préparés dans un atelier qui utilise aussi des fruits à coque et des arachides."
PLAQUE = "Prénom et message sur une plaque en chocolat, compris dans le prix."

PRODUITS = [
 dict(id="entremets-fruits", nom="Entremets aux fruits", photo="entremets",
      alt="Entremets aux fruits de saison, couronne de crème et figues sur le tour",
      phrase="Une génoise moelleuse, une crème vanille et des fruits de saison, pour un gâteau qui change au fil de l'année.",
      court="Une génoise moelleuse, une crème vanille et des fruits de saison, pour un gâteau qui change au fil de l'année.",
      compo=[], mode="taille",
      tailles=[("16", "16 cm", "4 personnes", 2800), ("18", "18 cm", "6 personnes", 3900),
               ("20", "20 cm", "8 personnes", 4800), ("22", "22 cm", "10 personnes", 6000)],
      delai=2, delai_txt="2 jours avant le retrait", plaque=True,
      conservation="Au réfrigérateur, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, soja (lécithine de la plaque en chocolat).",
      carte_pour="4 à 10 personnes", carte_prix="dès 28 €"),
 dict(id="charlotte-chocolat-framboise", nom="Charlotte chocolat-framboise", photo="charlotte",
      alt="Charlotte au chocolat couronnée de framboises fraîches",
      phrase="Du chocolat sous toutes ses textures, et juste ce qu'il faut d'acidité avec la framboise.",
      court="Du chocolat sous toutes ses textures, et juste ce qu'il faut d'acidité avec la framboise.",
      compo=["Biscuit joconde au chocolat", "Croustillant au grué de cacao", "Confit de framboise",
             "Mousse au chocolat noir", "Chantilly au chocolat au lait", "Framboises fraîches"],
      mode="taille",
      tailles=[("16", "16 cm", "4 personnes", 2800), ("18", "18 cm", "6 personnes", 3900),
               ("20", "20 cm", "8 personnes", 4800), ("22", "22 cm", "10 personnes", 6000)],
      delai=2, delai_txt="2 jours avant le retrait", plaque=True,
      conservation="Au réfrigérateur, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, soja (lécithine du chocolat), fruits à coque (amande).",
      carte_pour="4 à 10 personnes", carte_prix="dès 28 €"),
 dict(id="tarte-pecan", nom="Tarte aux noix de pécan", photo="tarte",
      alt="Tarte aux noix de pécan sur un plat à pied",
      phrase="Le sirop de riz apporte une douceur délicate, juste assez pour révéler le goût des noix sans le masquer.",
      court="Le sirop de riz apporte une douceur délicate, juste assez pour révéler le goût des noix sans le masquer.",
      compo=[], mode="taille",
      tailles=[("16", "16 cm", "4 personnes", 2800), ("18", "18 cm", "4 à 6 personnes", 3400),
               ("20", "20 cm", "6 à 8 personnes", 3900), ("24", "24 cm", "8 à 10 personnes", 4500)],
      delai=1, delai_txt="la veille du retrait", plaque=True,
      conservation="Au réfrigérateur, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, fruits à coque (noix, noix de pécan), soja (lécithine de la plaque en chocolat).",
      carte_pour="4 à 10 personnes", carte_prix="dès 28 €"),
 dict(id="cookies", nom="Cookies au chocolat", photo="cookies",
      alt="Cookie au chocolat garni de noisettes et de caramel, d'autres cookies derrière",
      phrase="Noisette et caramel, ou cacahuète et praliné, au chocolat noir ou au chocolat au lait : à vous de choisir votre combinaison préférée.",
      court="Noisette et caramel, ou cacahuète et praliné, au chocolat noir ou au chocolat au lait : à vous de choisir.",
      compo=[], mode="parfums", taille="Environ 9 à 10 cm de diamètre", unite=350, minimum=6, pas=1,
      parfums=[("noisette-noir", "Noisette et caramel, chocolat noir", ""),
               ("noisette-lait", "Noisette et caramel, chocolat au lait", ""),
               ("cacahuete-noir", "Cacahuète et praliné, chocolat noir", ""),
               ("cacahuete-lait", "Cacahuète et praliné, chocolat au lait", "")],
      regle="À partir de 6 cookies, en mélangeant les combinaisons comme vous voulez.",
      delai=1, delai_txt="la veille du retrait", plaque=False,
      conservation="À température ambiante, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, soja (lécithine du chocolat), arachides, fruits à coque (noisette). Les cookies cacahuète et praliné ne contiennent pas de noisette.",
      carte_pour="à partir de 6", carte_prix="3,50 € la pièce"),
 dict(id="choux", nom="Choux", photo="choux-parfums",
      alt="Cinq choux craquelin alignés sur une pierre claire, un de chaque parfum",
      phrase="Choux craquelin garnis selon vos envies et déposés sur un sablé.",
      court="Choux craquelin garnis selon vos envies et posés sur un sablé : vanille, caramel, chocolat, praliné noisette ou citron.",
      compo=[], mode="parfums", taille="Environ 5 cm de diamètre", unite=250, minimum=10, pas=5,
      parfums=[("vanille", "Vanille", "Chou caramélisé garni d'une crème à la vanille"),
               ("caramel", "Caramel", "Crème légère au caramel et cœur caramel"),
               ("chocolat", "Chocolat", "Crémeux au chocolat"),
               ("praline", "Praliné noisette", "Mousseline à la noisette, praliné noisette et noisettes caramélisées"),
               ("citron", "Citron", "Crémeux au citron et confit de citron vert")],
      regle="À partir de 10 choux, par 5 du même parfum.",
      delai=2, delai_txt="2 jours avant le retrait", plaque=False,
      conservation="Au réfrigérateur, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, soja (lécithine du chocolat), fruits à coque (noisette). La noisette ne concerne que le chou praliné noisette.",
      carte_pour="à partir de 10, par 5 du même parfum", carte_prix="2,50 € la pièce"),
 dict(id="petits-gateaux-du-gouter", nom="Petits gâteaux du goûter", photo="gouter",
      alt="Quatre petits cakes moulés : chocolat, coco, caramel et chocolat noir",
      phrase="De petits cakes moulés pour le goûter, à partager ou à offrir.",
      court="De petits cakes moulés pour le goûter, à partager ou à offrir.",
      compo=[], mode="parfums", taille="Environ 7 cm de diamètre", unite=350, minimum=6, pas=1,
      parfums=[("banane-chocolat", "Cake banane-chocolat", ""),
               ("coco-exotique", "Cake à la noix de coco", "Gel exotique à la mangue et au fruit de la passion"),
               ("vegan-cacahuete", "Cake vegan au praliné cacahuète", ""),
               ("chocolat", "Cake au chocolat", "")],
      regle="À partir de 6 petits gâteaux, en mélangeant les parfums comme vous voulez.",
      delai=2, delai_txt="2 jours avant le retrait", plaque=False,
      conservation="Au réfrigérateur, à déguster dans les 3 jours.",
      allergenes="Gluten (blé), œufs, lait, soja (lécithine du chocolat), arachides. Les arachides ne concernent que le cake vegan au praliné cacahuète, qui est sans œufs ni lait.",
      carte_pour="à partir de 6", carte_prix="3,50 € la pièce"),
]

MOMENTS = [("matin", "Le matin, de 9 h à 12 h"), ("midi", "À midi, de 12 h à 14 h"),
           ("apres-midi", "L'après-midi, de 14 h à 18 h"), ("soir", "Le soir, de 18 h à 19 h")]
JOURS_FERMES = ["12-24"]  # pas de retrait le 24 décembre (séance 2)

H1 = "Des gâteaux à la façon de Madame Cho, à Meudon."
SOUS = "Sur commande, à retirer sur rendez-vous, tous les jours de 9 h à 19 h."
INTRO = "Madame Cho, c'est la pâtisserie à votre écoute : entremets, tartes, choux et cookies, préparés à la commande, avec le soin du détail et une touche de Corée."
ANNIV = [
 "Pour un anniversaire, Madame Cho réalise votre gâteau sur commande, pour 4 à 12 personnes. Saveurs, couleurs, ingrédients, décoration : faites-lui part de vos envies pour imaginer ensemble un gâteau à votre image.",
 "Une plaque en chocolat avec le prénom et le message de votre choix est comprise dans le prix.",
 "Commandez au moins 2 jours à l'avance.",
]
PARCOURS = "Diplômée de l'Université nationale de Séoul, Youngmi arrive en France en 2015 et se forme à l'École Ducasse. Elle passe six ans dans de grandes maisons de la gastronomie française, dont le George V et La Réserve. Aujourd'hui, à Meudon, elle prépare chaque gâteau à la commande et partage aussi les saveurs de la Corée."
REPERES = ["Université nationale de Séoul", "École Ducasse", "George V et La Réserve", "Saveurs coréennes"]
PROS_REF = "Madame Cho prépare chaque semaine les desserts de restaurants et de cafés parisiens : tartes, cakes, cookies, brownies et carrés."
PROS = "Madame Cho prépare chaque semaine les desserts de restaurants et de cafés parisiens : tartes, cakes, cookies, brownies et carrés. Pour une réunion, un séminaire ou une fête d'entreprise, Madame Cho compose une sélection sucrée, sur devis."
# Restaurants qui servent les desserts de Madame Cho (noms : accord écrit du 03/11 ; liens donnés par Olivier le 03/11, site officiel sinon Instagram ; vérifiés par Youngmi le 04/11, Approach corrigé)
RESTOS = [
 ("Approach", "https://www.instagram.com/approachcoffee_/"),
 ("On", "https://www.instagram.com/on_resto/"),
 ("On The Bab", "https://www.onthebab.com/"),
 ("Misu", "https://www.instagram.com/misu.paris/"),
 ("Sétopa", "https://www.setopaparis.com/"),
]
OFFRE = ["Plateaux de petites pièces", "Boîtes individuelles", "Gâteaux à partager", "Coffrets de cookies à offrir"]
ETAPES = [
 ("Vous choisissez", "Ajoutez vos gâteaux à votre demande, sans engagement."),
 ("Madame Cho vous répond", "Sous 24 heures, avec une proposition écrite : le prix, le jour et l'heure du retrait."),
 ("Vous confirmez", "Vous acceptez la proposition par écrit, et Madame Cho confirme votre commande."),
 ("Vous retirez à Meudon", "Au 4 avenue du Château, à l'heure convenue."),
]
PRATIQUE = [
 ("Retrait", "Sur rendez-vous, au 4 avenue du Château, 92190 Meudon, tous les jours de 9 h à 19 h. Pas de livraison. Nos clients viennent aussi de Boulogne-Billancourt, d'Issy-les-Moulineaux, de Sèvres, de Chaville et de Clamart."),
 ("Délais", "La veille pour les cookies et la tarte, 2 jours avant pour les autres gâteaux. Annulation sans frais jusqu'à 24 heures avant le retrait. Pas de retrait le 24 décembre."),
 ("Paiement", "Au retrait, par carte bancaire, virement ou espèces. Pour une personnalisation au-delà de la plaque, un acompte de 30 % est demandé."),
 ("Conservation", "Indiquée sur la page de chaque gâteau."),
]

ADRESSE = "4 avenue du Château, 92190 Meudon"
TEL = "06 61 31 17 79"
TEL_LIEN = "+33661311779"
EMAIL = "contact@madamecho.com"
INSTAGRAM = "https://www.instagram.com/madamecho.fr/"

# ---------- Référencement (passe SEO du 03/11/2026) ----------
# Titres et descriptions pour les moteurs de recherche et le partage : tirés des textes validés, sans nouvelle promesse.
# Une description reste sous 160 signes, pour ne pas être coupée dans les résultats.
SEO_ACCUEIL = ("Madame Cho, pâtisserie et gâteaux d'anniversaire à Meudon",
               "Gâteaux d'anniversaire, entremets, tartes, choux et cookies faits main par Madame Cho. Sur commande, retrait à Meudon, près de Boulogne, Issy et Sèvres.")
SEO_CARTE = ("La carte et les prix · Madame Cho, pâtisserie à Meudon",
             "La carte de Madame Cho et ses prix : entremets aux fruits, charlotte chocolat-framboise, tarte aux noix de pécan, cookies, choux et petits cakes.")
SEO_PRODUITS = {
 "entremets-fruits": ("Entremets aux fruits sur commande à Meudon",
                      "Entremets aux fruits de saison fait main par Madame Cho : génoise moelleuse et crème vanille, de 4 à 10 personnes, dès 28 €. Retrait à Meudon."),
 "charlotte-chocolat-framboise": ("Charlotte chocolat-framboise sur commande à Meudon",
                      "Charlotte chocolat-framboise faite main par Madame Cho : mousse au chocolat noir, confit et framboises fraîches. De 4 à 10 personnes, dès 28 €."),
 "tarte-pecan": ("Tarte aux noix de pécan sur commande à Meudon",
                      "Tarte aux noix de pécan au sirop de riz, faite main par Madame Cho. De 4 à 10 personnes, dès 28 €. Sur commande, à retirer à Meudon."),
 "cookies": ("Cookies au chocolat sur commande à Meudon",
                      "Cookies faits main, noisette et caramel ou cacahuète et praliné, au chocolat noir ou au lait. 3,50 € la pièce, à partir de 6. Retrait à Meudon."),
 "choux": ("Choux craquelin sur commande à Meudon",
                      "Choux craquelin faits main : vanille, caramel, chocolat, praliné noisette ou citron. 2,50 € la pièce, à partir de 10. Retrait à Meudon."),
 "petits-gateaux-du-gouter": ("Petits gâteaux du goûter sur commande à Meudon",
                      "Petits cakes faits main pour le goûter : banane-chocolat, noix de coco, chocolat ou vegan au praliné cacahuète. 3,50 € la pièce, à partir de 6."),
}
ACTIVITE = "Pâtisserie sur commande à Meudon, dans les Hauts-de-Seine."
# Villes citées pour le référencement local (accord d'Olivier du 07/11/2026 : pas de livraison, retrait à Meudon seulement)
VILLES = ["Meudon", "Boulogne-Billancourt", "Issy-les-Moulineaux", "Sèvres", "Chaville", "Clamart"]
SEO_RETRAIT = ("Retrait de vos gâteaux à Meudon, près de Boulogne, Issy et Sèvres · Madame Cho",
               "Pas de livraison : vos gâteaux se retirent sur rendez-vous au 4 avenue du Château à Meudon, à quelques minutes de Boulogne, Issy, Sèvres, Chaville et Clamart.")
