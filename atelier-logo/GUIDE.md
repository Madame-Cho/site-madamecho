# Atelier logo – guide de mise en route (phase 1, Tinder des logos)

Sous-page privée de madamecho.com (`/atelier-logo/`), non référencée (`noindex`), non reliée au site.
Rien ici n'est un secret : la liste des votes n'est pas dans ce dépôt, elle part vers la feuille Google « Tinder des logos » (Drive 91/01).

## 1. Activer la collecte des votes (5 min, Olivier)
1. Ouvrir la feuille « Tinder des logos » > Extensions > Apps Script.
2. Remplacer le contenu par `apps-script.gs`, enregistrer.
3. Déployer > Nouveau déploiement > type « Application web » ; exécuter en tant que « moi » ; accès « Tout le monde ». Autoriser.
4. Copier l'URL `https://script.google.com/macros/s/…/exec` et la coller dans `index.html`, ligne `const VOTES_URL = "";`.
   Tant que l'URL est vide, les votes restent dans le navigateur et se téléchargent en CSV à la fin.

## 2. Tester (Olivier d'abord)
- Cas prévus par la page : logo introuvable (Brandfetch puis logo.dev) → fiche passée automatiquement (vote « sans-image ») ; réseau lent (12 s) → fiche passée (« lent ») ; logo blanc sur fond transparent → fond sombre automatique, et bouton « fond sombre / clair » ; image floue ou qui n'est pas le logo → bouton « pas un logo / illisible » ; double clic → un seul vote ; « annuler le dernier » (ou touche Retour arrière) ; nom de la maison sous le logo, et mention « symbole seul » quand seul le symbole est disponible ; lien « voir le site ↗ » dans un nouvel onglet ; réponses gardées par prénom sur l'appareil, reprise automatique ; option « reprendre à une fiche précise » pour changer d'appareil ; « effacer mes réponses sur cet appareil » ; envoi des votes par paquets de 3, et de ce qui reste quand on quitte la page.
- Écran « mes votes » (bouton sous les boutons de vote, et depuis la pause ou la fin) : grille des logos déjà jugés avec le vote, un clic rouvre la fiche pour changer (l'ancien vote est annulé dans la feuille par une ligne « annule », le nouveau porte « (modifié) ») ; liste déroulante « n° · marque » de toutes les fiches pour aller directement à l'une d'elles (déjà votée → modification ; pas encore → on y saute, et les fiches sautées reviennent à la fin). Touche Échap pour revenir à la liste.
- Grand écran (largeur ≥ 1000 px) : le site de la marque s'affiche à droite de la fiche, avec la consigne « tu évalues le logo, pas le site » et un lien « voir en grand ». Beaucoup de sites interdisent leur affichage dans une autre page (X-Frame-Options, CSP) : la fenêtre reste alors vide ou affiche un refus, ce que la page ne peut pas détecter ; le lien reste. Sur téléphone, pas de volet (rien n'est chargé).
- Limites : un même prénom sur deux appareils sans l'option « reprendre à la fiche n° » revote depuis le début (doublons à écarter à l'analyse par Prénom + Session + Position) ; la qualité des logos dépend des sources (Brandfetch fournit souvent le SVG, net ; logo.dev agrandit parfois de petites images). Brandfetch exige un en-tête Referer : si les images ne s'affichent pas depuis un fichier ouvert en local, tester depuis un petit serveur local ou sur le site.
- En local : double-clic sur `index.html` suffit (le corpus est intégré à la page ; `corpus.json` et `corpus.csv` restent la source pour la feuille).
- Ou sur la branche GitHub via un aperçu (Netlify, Pages de la branche) si le site le permet.
- Vérifier : prénom, défilement, 👎👍❤️, « passer », annuler, pause toutes les 50 fiches jugées, arrêt et reprise, « mes votes » et liste déroulante, volet du site sur grand écran, lignes qui arrivent dans l'onglet « Votes », colonne « Image » (oui · logo complet / oui · symbole / non / lent, puis « (site consulté) », « (modifié) »).

## 3. Mise en ligne (uniquement sur feu vert d'Olivier, R-19)
Fusionner la branche dans production : la page sera à https://madamecho.com/atelier-logo/. Retirer le dossier à la fin de la phase 1.

## 4. Lecture des résultats
Onglet « Votes » : une ligne par geste (haut, bas, coeur, passer, illisible, sans-image, annule). Une ligne « annule » annule la ligne de même Session + Position + Horodatage navigateur ; un vote modifié depuis « mes votes » arrive en ligne « annule » + nouvelle ligne « (modifié) » à la même position. La colonne « Image » dit ce qui a été montré (« oui · logo complet » = logo avec le nom ; « oui · symbole » = symbole seul, sans le nom ; « non » / « lent » = rien) et porte « (site consulté) » si le lien du site a été ouvert. Croiser avec l'onglet « Corpus » par l'Id.
Claude produit ensuite le « Profil des goûts de Youngmi » (91/01) avec une section sur les biais (images manquantes, marques reconnues, ordre).

## Images
Les logos sont chargés côté navigateur, jamais copiés dans le dépôt, dans cet ordre : Brandfetch logo complet (SVG puis PNG, avec le nom), Brandfetch symbole, logo.dev (symbole carré). Brandfetch : Logo API gratuite (1 000 000 requêtes par mois, sans attribution), compte Madame Cho, client ID public dans BF_ID de index.html, chemin `fallback/404`. logo.dev : compte Community gratuit Madame Cho, clé publique pk_… dans LOGO_KEY, `fallback=404`, mention « Logos provided by Logo.dev » sur la page. Jamais d'icône passe-partout : sans logo, la fiche est passée automatiquement (vote « sans-image »). Les deux comptes sont à fermer à la fin de la phase 1 (P-18).
